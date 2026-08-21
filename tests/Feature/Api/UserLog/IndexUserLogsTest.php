<?php

namespace Tests\Feature\Api\UserLog;

use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexUserLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_returns_the_trail_with_who_did_what(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.role.update', $user), ['role' => 'admin'])
            ->assertOk();

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk()
            ->assertJsonPath('message', 'Istorija izmena je uspešno učitana.')
            ->assertJsonPath('data.0.event', 'user.role_changed')
            ->assertJsonPath('data.0.old_values.role', 'user')
            ->assertJsonPath('data.0.new_values.role', 'admin')
            ->assertJsonPath('data.0.actor.id', $this->admin->id)
            ->assertJsonPath('data.0.actor.email', $this->admin->email)
            ->assertJsonStructure([
                'message',
                'data' => [['id', 'event', 'old_values', 'new_values', 'actor', 'ip_address', 'created_at']],
                'links',
                'meta',
            ]);
    }

    public function test_it_returns_the_newest_entry_first(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)->postJson(route('users.deactivate', $user))->assertOk();
        $this->actingAsAdmin($this->admin)->postJson(route('users.reactivate', $user))->assertOk();

        $events = collect($this->actingAsAdmin($this->admin)->getJson(route('users.logs.index', $user))->json('data'))
            ->pluck('event')
            ->all();

        $this->assertSame(['user.reactivated', 'user.deactivated'], $events);
    }

    public function test_it_only_returns_that_user_s_entries(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        $this->actingAsAdmin($this->admin)->postJson(route('users.deactivate', $someoneElse))->assertOk();

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_paginates(): void
    {
        $user = User::factory()->create();

        UserLog::factory()->count(30)->create([
            'user_id' => $user->id,
            'actor_id' => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk();

        $this->assertCount(25, $response->json('data'));
        $this->assertSame(30, $response->json('meta.total'));
    }

    /**
     * A system entry is one nobody made from a browser -- a seeder, a command,
     * the scheduler. It has to be readable, not an error.
     */
    public function test_an_entry_without_an_actor_reads_as_null(): void
    {
        $user = User::factory()->create();

        UserLog::factory()->create([
            'user_id' => $user->id,
            'actor_id' => null,
            'event' => UserLogEvent::Updated,
        ]);

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk()
            ->assertJsonPath('data.0.actor', null);
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', ['id' => 99999]))
            ->assertNotFound();
    }

    public function test_a_plain_user_may_not_read_the_trail(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser()
            ->getJson(route('users.logs.index', $user))
            ->assertForbidden();
    }

    public function test_it_requires_a_token(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('users.logs.index', $user))->assertUnauthorized();
    }
}
