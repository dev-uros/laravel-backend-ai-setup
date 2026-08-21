---
paths:
  - '{app/Services/**,app/Data/**}'
---

# Data

## One service class per use case, receiving a DTO
All business logic lives in services under `app/Services/{Model}/`. One class per use case named `{Action}{Model}Service`, with a single public `handle()` method.

A service:
- receives a DTO from `app/Data/{Model}/{Action}{Model}Data.php`, never the Request object, never a loose array;
- returns models, collections, DTOs or primitives — never an HTTP response, never an API Resource;
- owns the database transaction (`DB::transaction()`) when it writes more than one row;
- talks to repository interfaces, never to Eloquent or the query builder directly;
- does not call auth(), request() or session() — whatever it needs is passed in, so it works from a command or job too;
- throws domain exceptions rather than returning error flags.

DTOs are `final readonly` classes with typed public promoted properties and a `fromRequest(FormRequest $request): self` named constructor. The plain constructor stays usable from non-HTTP callers.

A service that needs another use case calls that service, not its own copy of the logic.
