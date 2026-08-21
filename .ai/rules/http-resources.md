---
paths:
  - '{app/Support/Scramble/**,config/scramble.php,app/Http/Resources/**}'
---

# Http Resources

## A paginated collection inside the envelope needs links/meta declared by hand
Scramble only builds `links` and `meta` while transforming a *response*. `ApiResponse` renders the resource itself and merges the result, so the collection reaches Scramble as a value inside the envelope and both keys are silently dropped — the endpoint documents as `{message, data[]}` while returning four keys.

`ApiResponseTypeInfer::isPaginated()` detects a `ResourceCollection` over a paginator and appends `linksType()`/`metaType()`, hand-written to match `PaginatedResourceResponse::paginationLinks()` and `LengthAwarePaginator::toArray()`. Change either of those Laravel methods' output and those two builders have to follow.

`tests/Feature/Docs/OpenApiSpecTest.php` holds the generated document against real responses; it is the only thing that fails when the docs drift. Keep it in step.

Two traps it also covers: `?->` in a resource documents the field as nullable, so drop it for `created_at`/`updated_at` which Eloquent always stamps; and an `array` model cast documents a jsonb column as a JSON list — annotate the array item with `@var array<string, mixed>|null` to get an object.
