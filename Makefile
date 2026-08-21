# Local development helpers. Run `make` (or `make help`) for the list.

DC := docker compose
# The observability stack is its own compose project: `make up` stays fast, and
# `make down` cannot take Grafana with it by accident.
DC_OBS := docker compose -f compose.observability.yaml
# Run as www-data (remapped to your host uid at build time) so files created by
# artisan/composer are owned by you, not by root.
PHP := $(DC) exec --user www-data php

# The php/horizon containers are built and run as your host user so that
# anything they write into the bind mount stays yours. Overrides .env.
export WWWUSER := $(shell id -u)
export WWWGROUP := $(shell id -g)

# Read a handful of values out of .env so the helper targets report the ports
# and credentials you actually configured, not hardcoded defaults.
dotenv = $(shell sed -n 's/^$(1)=//p' .env 2>/dev/null | tail -n1)

APP_PORT     := $(or $(call dotenv,APP_PORT),8000)
GRAFANA_PORT := $(or $(call dotenv,GRAFANA_PORT),3000)
ALLOY_PORT   := $(or $(call dotenv,ALLOY_PORT),12345)
DB_USER    := $(or $(call dotenv,DB_USERNAME),laravel)
DB_NAME    := $(or $(call dotenv,DB_DATABASE),laravel)

# Pass-through arguments, e.g. `make artisan c="migrate --seed"`
c ?=

.DEFAULT_GOAL := help

# -----------------------------------------------------------------------------
# Lifecycle
# -----------------------------------------------------------------------------

.PHONY: up
up: ## Start every container (builds the php image if missing)
	$(DC) up -d --build --remove-orphans
	@$(MAKE) --no-print-directory wait-db
	@echo ""
	@echo "  app      -> http://localhost:$(APP_PORT)"
	@echo "  horizon  -> http://localhost:$(APP_PORT)/horizon"
	@echo "  pulse    -> http://localhost:$(APP_PORT)/pulse"
	@echo ""

.PHONY: down
down: ## Stop and remove containers + network (data volumes are kept)
	$(DC) down --remove-orphans

.PHONY: destroy
destroy: ## Stop everything AND delete the postgres/redis volumes (data loss)
	$(DC) down --remove-orphans --volumes

.PHONY: restart
restart: ## Restart every container
	$(DC) restart

.PHONY: build
build: ## Rebuild the php image
	$(DC) build

.PHONY: rebuild
rebuild: ## Rebuild the php image from scratch, ignoring cache
	$(DC) build --no-cache

.PHONY: ps
ps: ## Show container status
	$(DC) ps

.PHONY: logs
logs: ## Tail logs from all containers (or one: make logs s=horizon)
	$(DC) logs -f --tail=100 $(s)

# -----------------------------------------------------------------------------
# Observability (dev only) -- Loki + Alloy + Grafana, see docker/observability
# -----------------------------------------------------------------------------

.PHONY: observe
observe: ## Start Grafana + Loki + Alloy and print the URL
	$(DC_OBS) up -d --remove-orphans
	@echo ""
	@echo "  grafana  -> http://localhost:$(GRAFANA_PORT)  (dashboard: Laravel)"
	@echo "  alloy    -> http://localhost:$(ALLOY_PORT)"
	@echo ""

.PHONY: observe-down
observe-down: ## Stop the observability stack (log data is kept)
	$(DC_OBS) down --remove-orphans

.PHONY: observe-destroy
observe-destroy: ## Stop it AND delete the stored logs and dashboards state
	$(DC_OBS) down --remove-orphans --volumes

.PHONY: observe-logs
observe-logs: ## Tail the observability stack's own logs (or one: s=alloy)
	$(DC_OBS) logs -f --tail=100 $(s)

# -----------------------------------------------------------------------------
# Shells
# -----------------------------------------------------------------------------

.PHONY: php
php: ## Open a shell inside the php container (composer / php / artisan)
	$(PHP) bash

.PHONY: php-root
php-root: ## Open a root shell inside the php container (apt-get, etc.)
	$(DC) exec php bash

.PHONY: psql
psql: ## Open a psql shell on the application database
	$(DC) exec postgres psql -U $(DB_USER) -d $(DB_NAME)

.PHONY: psql-test
psql-test: ## Open a psql shell on the test database
	$(DC) exec postgres-test psql -U $(DB_USER) -d $(DB_NAME)_testing

.PHONY: redis-cli
redis-cli: ## Open a redis-cli shell
	$(DC) exec redis redis-cli

# -----------------------------------------------------------------------------
# Application
# -----------------------------------------------------------------------------

.PHONY: artisan
artisan: ## Run an artisan command, e.g. make artisan c="route:list"
	$(PHP) php artisan $(c)

.PHONY: composer
composer: ## Run a composer command, e.g. make composer c="require vendor/pkg"
	$(PHP) composer $(c)

.PHONY: tinker
tinker: ## Open a tinker REPL
	$(PHP) php artisan tinker

.PHONY: migrate
migrate: ## Run pending migrations
	$(PHP) php artisan migrate

.PHONY: fresh
fresh: ## Drop everything and re-migrate with seeders (data loss)
	$(PHP) php artisan migrate:fresh --seed

.PHONY: storage-link
storage-link: ## Link public/storage -> storage/app/public (avatars are served from there)
	@$(PHP) sh -c 'test -L public/storage || ln -s ../storage/app/public public/storage'
	@echo "public/storage -> storage/app/public"

.PHONY: demo
demo: ## Fill the database with demo users, edit history and login history
	$(PHP) php artisan db:seed --class=DemoDataSeeder

.PHONY: test
test: wait-test-db ## Run the test suite (against the postgres-test container)
	$(PHP) php artisan test --compact

.PHONY: pint
pint: ## Format PHP files with Pint (only the changed ones inside a git repo)
	@if [ -d .git ]; then \
		$(PHP) vendor/bin/pint --dirty; \
	else \
		echo "not a git repo -- formatting everything"; \
		$(PHP) vendor/bin/pint; \
	fi

.PHONY: horizon-restart
horizon-restart: ## Gracefully restart the Horizon workers (after a code change)
	$(PHP) php artisan horizon:terminate

# -----------------------------------------------------------------------------
# First run
# -----------------------------------------------------------------------------

.PHONY: setup
setup: ## First-time setup: build, boot, install deps, migrate
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d --remove-orphans
	@$(MAKE) --no-print-directory wait-db
	$(PHP) composer install
	@grep -q '^APP_KEY=base64:' .env || $(PHP) php artisan key:generate
	$(PHP) php artisan migrate --force
	@$(MAKE) --no-print-directory storage-link
	@$(MAKE) --no-print-directory up

.PHONY: wait-db
wait-db: ; @$(MAKE) --no-print-directory wait-for s=postgres

.PHONY: wait-test-db
wait-test-db: ; @$(MAKE) --no-print-directory wait-for s=postgres-test

# Block until the postgres service named by `s` accepts connections.
.PHONY: wait-for
wait-for:
	@printf "waiting for $(s)"
	@for i in $$(seq 1 60); do \
		if $(DC) exec -T $(s) pg_isready -q -U $(DB_USER) 2>/dev/null; then \
			echo " ready"; exit 0; \
		fi; \
		printf "."; sleep 1; \
	done; \
	echo " timed out after 60s"; $(DC) logs --tail=30 $(s); exit 1

.PHONY: help
help: ## Show this help
	@echo ""
	@echo "  usage: make <target>"
	@echo ""
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
	@echo ""
