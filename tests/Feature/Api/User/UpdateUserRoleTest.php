<?php

namespace Tests\Feature\Api\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateUserRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * This endpoint is the first that reads something off the caller -- it
     * compares their id with the target's -- so the administrator making these
     * requests has to be a real row. The bare actingAsAdmin() helper signs in an
     * unsaved user, whose id is null.
     */
    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_can_promote_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $user), ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('message', 'Uloga korisnika je uspešno promenjena.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame(UserRole::Admin, $user->refresh()->role);
    }

    public function test_an_admin_can_demote_another_admin(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $other), ['role' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.role', 'user');

        $this->assertSame(UserRole::User, $other->refresh()->role);
    }

    public function test_setting_the_role_a_user_already_has_is_harmless(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $user), ['role' => 'user'])
            ->assertOk();

        $this->assertSame(UserRole::User, $user->refresh()->role);
    }

    /**
     * The rule that keeps the last administrator from disappearing: demoting one
     * takes a second administrator, who is still an administrator afterwards.
     */
    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAsAdmin($admin)
            ->putJson(route('users.role.update', $admin), ['role' => 'user'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Ne možete promeniti sopstvenu ulogu. Zamolite drugog administratora.');

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_a_plain_user_cannot_change_anybody_s_role(): void
    {
        $target = User::factory()->create();

        $this->actingAsUser()
            ->putJson(route('users.role.update', $target), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::User, $target->refresh()->role);
    }

    public function test_it_requires_a_token(): void
    {
        $target = User::factory()->create();

        $this->putJson(route('users.role.update', $target), ['role' => 'admin'])
            ->assertUnauthorized();

        $this->assertSame(UserRole::User, $target->refresh()->role);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidRoles(): array
    {
        return [
            'missing' => [null],
            'unknown' => ['superadmin'],
            'empty' => [''],
            'wrong case' => ['Admin'],
            'not a string' => [['admin']],
        ];
    }

    #[DataProvider('invalidRoles')]
    public function test_it_rejects_a_role_it_does_not_know(mixed $role): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $user), $role === null ? [] : ['role' => $role])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(UserRole::User, $user->refresh()->role);
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', ['id' => 99999]), ['role' => 'admin'])
            ->assertNotFound();
    }

    public function test_it_rejects_a_non_numeric_id(): void
    {
        $this->actingAsAdmin($this->admin())
            ->putJson('/api/users/abc/role', ['role' => 'admin'])
            ->assertNotFound();
    }

    /**
     * A demotion is not a sign-out. The token stays valid, and the policy reads
     * the role out of the database on the next request, so the user keeps the
     * access their new role allows and loses the rest.
     */
    public function test_a_demotion_takes_effect_on_the_next_request_without_revoking_tokens(): void
    {
        $target = User::factory()->admin()->create();
        $token = $target->createToken('spa')->plainTextToken;

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $target), ['role' => 'user'])
            ->assertOk();

        $this->assertSame(1, $target->tokens()->count(), 'a role change must not revoke tokens');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('users.store'), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('users.index'))
            ->assertOk();
    }

    /**
     * The other direction, end to end: someone who could only read a moment ago
     * can write with the same token.
     */
    public function test_a_promotion_takes_effect_on_the_next_request(): void
    {
        $target = User::factory()->create();
        $token = $target->createToken('spa')->plainTextToken;

        $this->actingAsAdmin($this->admin())
            ->putJson(route('users.role.update', $target), ['role' => 'admin'])
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson(route('users.role.update', User::factory()->create()), ['role' => 'admin'])
            ->assertOk();
    }
}
