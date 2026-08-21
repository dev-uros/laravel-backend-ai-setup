---
paths:
  - 'app/Models/**'
---

# Models

## Adding a model means adding its repository pair
A new model is not finished until it has `app/Repositories/{Model}Repository.php`, `app/Repositories/Contracts/{Model}RepositoryInterface.php`, and the binding in `RepositoryServiceProvider`. Also create the factory and seeder.

Models stay thin: casts, relationships, scopes and accessors only. No business logic, no static query helpers that encode a use case — those belong in the repository or the service.

Nothing in the application layer queries a model directly. If you find yourself writing `User::where(...)` in a controller, service, job or command, the query belongs on the repository interface instead.

Exempt from that rule: seeders, factories, migrations and tests. Those are database tooling, not application code — they legitimately use Eloquent and factories directly, and routing them through repositories would only obscure what they do.
