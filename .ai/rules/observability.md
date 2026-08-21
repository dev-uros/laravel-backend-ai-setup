---
paths:
  - '{compose.observability.yaml,docker/observability/**,Makefile}'
---

# Observability

## Observability is its own compose project, and Alloy replaces Promtail
Loki + Alloy + Grafana live in `compose.observability.yaml` as a separate compose project (`make observe` / `make observe-down`), never in `compose.yaml`. Keeps `make up` fast, lets `make down` run without killing Grafana, and lets the whole folder move to an infra repo later — the only coupling is the Docker socket.

Alloy, not Promtail: Grafana took Promtail to EOL in March 2026. Alloy reads container stdout off `/var/run/docker.sock` (mounted read-only) and turns `com.docker.compose.service` / `.project` labels into Loki labels, so no application container needs a logging driver or a sidecar.

Loki labels are `service`, `project`, `container`, `env`, `level` only. `request_id`, `user_id` and `route` stay inside the JSON line and come out with `| json`, which flattens nesting with underscores (`context_status`, `context_duration_ms`, `extra_request_id`). A high-cardinality label gives Loki one stream per value and will bring it down.

The dashboard is a provisioned read-only file (`docker/observability/grafana/dashboards/laravel.json`) — edit the file, not the browser. Grafana reloads it within ~10s.

Stat panels append `or vector(0)` so an empty result reads 0 instead of "No data", and the latency panel filters `context_route != ""` because a request that matched no route has none.

This is dev only: anonymous admin Grafana, no retention, no TLS. `docker/observability/README.md` is the usage guide. A production setup is a separate piece of work.
