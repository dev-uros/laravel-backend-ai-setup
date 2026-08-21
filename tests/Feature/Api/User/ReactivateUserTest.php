<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactivateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin(User::factory()->admin()->create());
    }

    public function test_it_switches_the_account_back_on(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->postJson(route('users.reactivate', $user))
            ->assertOk()
            ->assertJsonPath('message', 'Nalog je ponovo uključen.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.deactivated_at', null);

        $this->assertTrue($user->refresh()->isActive());
    }

    public function test_reactivating_an_active_account_is_harmless(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('users.reactivate', $user))->assertOk();

        $this->assertTrue($user->refresh()->isActive());
    }

    /**
     * Reactivation does not hand back the tokens that deactivation revoked --
     * that would undo the point of revoking them. The user logs in again.
     */
    public function test_it_does_not_restore_revoked_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('spa');

        $this->postJson(route('users.deactivate', $user))->assertOk();
        $this->postJson(route('users.reactivate', $user))->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_user_can_log_in_again_afterwards(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $this->postJson(route('users.deactivate', $user))->assertOk();
        $this->postJson(route('users.reactivate', $user))->assertOk();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ])->assertOk();
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->postJson(route('users.reactivate', ['id' => 99999]))->assertNotFound();
    }
}
