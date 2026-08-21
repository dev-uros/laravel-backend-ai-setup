---
paths:
  - 'app/Repositories/**'
---

# Repositories

## Every model has a repository behind an interface
Each model gets `app/Repositories/{Model}Repository.php` implementing `app/Repositories/Contracts/{Model}RepositoryInterface.php`. Adding a model means adding both, plus the binding.

The repository is the only place Eloquent is touched for its model. Pure data access: no business rules, no validation, no events dispatched, no transactions (the service owns those). Methods return models, collections or null — never arrays of raw rows, never HTTP concerns.

Every public method is declared on the interface with explicit parameter and return types. Consumers type-hint `UserRepositoryInterface`, never `UserRepository` — that is the whole point of the split.

Bind each pair in `app/Providers/RepositoryServiceProvider.php`:

    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

Keep queries that only one use case needs as named repository methods (`findActiveByEmail`), not as a generic `query()` escape hatch that leaks the builder back to callers.

## UserLogRepository must keep eager loading the actor
`UserLogResource` reads `$this->actor` directly, not through `whenLoaded()`, so that the OpenAPI document can declare the key required — it always arrives, and `null` there means the system made the change.

That makes `->with('actor')` in `UserLogRepository::paginateForUser()` load-bearing. Drop it and the endpoint still answers correctly while running one extra select per row. Nothing catches that: this application does not enable `Model::preventLazyLoading()`.

A new query feeding `UserLogResource` has to eager load the relation too.
