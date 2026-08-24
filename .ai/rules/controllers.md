---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers only map HTTP to a service call
A controller action builds a DTO, calls one service, and wraps the result. Nothing else.

Forbidden in a controller: Eloquent or `DB::`/query builder, business logic or branching beyond mapping a result to a response, inline validation, try/catch around domain errors (let the exception handler render them).

Validation belongs in a FormRequest (`app/Http/Requests/{Model}/{Action}{Model}Request.php`) — that is the only place validation rules are written. Responses go through an API Resource.

One resource, one controller: `{Model}Controller` with `index`, `store`, `show`, `update`, `destroy`. Inject services **per action**, not through the constructor, so a request only resolves the service it uses.

    public function store(StoreUserRequest $request, StoreUserService $storeUser): JsonResponse
    {
        return ApiResponse::created(
            'Korisnik uspešno kreiran.',
            new UserResource($storeUser->handle(StoreUserData::fromRequest($request))),
        );
    }

Reach for a single-action `__invoke` controller only when an action genuinely falls outside the resource — a different verb, its own middleware, its own authorisation. Do not create one per CRUD action.

Routes: one `apiResource` per resource, `->parameters(['users' => 'id'])` because this project does not use route model binding — the repository is the only thing allowed to load a model — and `->whereNumber('id')`, which keeps non-numeric ids from ever reaching the application.

Register an action outside the resource when the verbs it should accept differ from the default. `update` is registered separately as `PUT` only: `apiResource` would also register `PATCH`, and every field on that endpoint is required, so advertising `PATCH` would promise a partial update that answers 422.

If an action needs anything the service should know (the authenticated user, the IP), the controller reads it and passes it in — services never call auth()/request().
