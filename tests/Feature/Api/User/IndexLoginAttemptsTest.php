<?php

namespace Tests\Feature\Api\User;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexLoginAttemptsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_returns_the_accounts_attempts_newest_first(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'pogresno'])->assertUnauthorized();
        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'tajna-lozinka'])->assertOk();

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logins.index', $user))
            ->assertOk()
            ->assertJsonPath('message', 'Istorija prijava je uspešno učitana.')
            ->assertJsonPath('data.0.outcome', 'succeeded')
            ->assertJsonPath('data.1.outcome', 'invalid_credentials')
            ->assertJsonPath('data.0.email', $user->email)
            ->assertJsonStructure([
                'message',
                'data' => [['id', 'outcome', 'email', 'ip_address', 'user_agent', 'created_at']],
                'links',
                'meta',
            ]);
    }

    public function test_it_only_returns_that_accounts_attempts(): void
    {
        $user = User::factory()->create();
        LoginAttempt::factory()->count(3)->create(['user_id' => User::factory()->create()->id]);

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logins.index', $user))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_paginates(): void
    {
        $user = User::factory()->create();
        LoginAttempt::factory()->count(30)->create(['user_id' => $user->id]);

        $response = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logins.index', $user))
            ->assertOk();

        $this->assertCount(25, $response->json('data'));
        $this->assertSame(30, $response->json('meta.total'));
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logins.index', ['id' => 99999]))
            ->assertNotFound();
    }

    public function test_a_plain_user_may_not_read_it(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser()
            ->getJson(route('users.logins.index', $user))
            ->assertForbidden();
    }

    public function test_it_requires_a_token(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('users.logins.index', $user))->assertUnauthorized();
    }
}
