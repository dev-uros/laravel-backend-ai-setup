---
paths:
  - 'tests/**'
---

# Tests

## PHPUnit 12 needs attributes, not docblock annotations
This project runs PHPUnit 12, which removed docblock metadata. `/** @dataProvider foo */` is silently not picked up and the test fails with "Too few arguments to function ...". Use the attribute:

    use PHPUnit\Framework\Attributes\DataProvider;

    #[DataProvider('requiredFields')]
    public function test_every_field_is_required(string $field): void

The same applies to `@depends`, `@group`, `@testWith` and friends — all live under `PHPUnit\Framework\Attributes\` now. Data provider methods must be `public static`.

Older Laravel tutorials still show the annotation form, so treat any copied snippet using it as needing conversion.

## Authenticate tests with actingAsUser, and keep the 401 coverage
Every endpoint except login, activate-account, forgot-password and reset-password is behind `auth:sanctum`, so a test that calls one authenticates with `$this->actingAsUser()` (Tests\TestCase), normally once in `setUp`.

That helper signs in a user built with `User::factory()->make()` — deliberately not persisted. No endpoint reads anything off the caller, and a saved row would appear in the lists, counts and orderings the users tests assert on. Pass a user in when the test needs the caller in the database.

`Sanctum::actingAs` attaches a TransientToken with no database row, so it cannot exercise logout: tests for token revocation must mint a real token with `createToken('spa')->plainTextToken` and send it as a Bearer header.

Two requests in one test share an application instance and the guard caches the user it resolved, so a test asserting that a revoked token stops working must call `$this->app['auth']->forgetGuards()` in between — otherwise it passes against broken code.

`tests/Feature/Api/User/UsersRequireAuthenticationTest.php` is the only test that would notice a users route leaving the auth group. Keep it in step with the routes.
