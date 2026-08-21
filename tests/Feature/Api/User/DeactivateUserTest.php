<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DeactivateUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * A persisted administrator, because this endpoint compares the caller's id
     * with the target's. The bare helper signs in an unsaved user, whose id is
     * null.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->actingAsAdmin($this->admin);
    }

    public function test_it_switches_the_account_off_and_returns_it(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('users.deactivate', $user))
            ->assertOk()
            ->assertJsonPath('message', 'Nalog je isključen.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.deactivated_at', fn (?string $at): bool => $at !== null);

        $this->assertNotNull($user->refresh()->deactivated_at);
        $this->assertFalse($user->isActive());
    }

    /**
     * The row is the whole point of not using a soft delete: an administrator
     * has to be able to see who is switched off in order to switch them back on.
     */
    public function test_the_account_is_not_deleted_and_still_appears_in_the_list(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('users.deactivate', $user))->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);

        $listed = collect($this->getJson(route('users.index'))->assertOk()->json('data'))
            ->firstWhere('id', $user->id);

        $this->assertNotNull($listed, 'a switched-off account must stay in the list');
        $this->assertNotNull($listed['deactivated_at']);
    }

    public function test_it_revokes_every_token_the_account_holds(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('spa')->plainTextToken;
        $laptop = $user->createToken('spa')->plainTextToken;

        $this->postJson(route('users.deactivate', $user))->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($phone));
        $this->assertNull(PersonalAccessToken::findToken($laptop));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_leaves_other_accounts_tokens_alone(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        $theirToken = $someoneElse->createToken('spa')->plainTextToken;

        $this->postJson(route('users.deactivate', $user))->assertOk();

        $this->assertNotNull(PersonalAccessToken::findToken($theirToken));
    }

    public function test_deactivating_twice_keeps_the_original_timestamp(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('users.deactivate', $user))->assertOk();
        $first = $user->refresh()->deactivated_at;

        $this->travel(5)->minutes();

        $this->postJson(route('users.deactivate', $user))->assertOk();

        $this->assertTrue($first->equalTo($user->refresh()->deactivated_at));
    }

    /**
     * The rule that keeps an administrator able to get back in: switching
     * yourself off is the one thing you cannot undo yourself, and the last
     * administrator doing it would lock everybody out of the panel.
     */
    public function test_an_admin_cannot_switch_off_their_own_account(): void
    {
        $this->postJson(route('users.deactivate', $this->admin))
            ->assertForbidden()
            ->assertJsonPath('message', 'Ne možete isključiti sopstveni nalog. Zamolite drugog administratora.');

        $this->assertTrue($this->admin->refresh()->isActive());
    }

    /**
     * An administrator switching off another administrator is fine: the one
     * doing it is still an active administrator afterwards, so there is always
     * somebody left who can switch the account back on.
     */
    public function test_an_admin_can_switch_off_another_admin(): void
    {
        $other = User::factory()->admin()->create();

        $this->postJson(route('users.deactivate', $other))->assertOk();

        $this->assertFalse($other->refresh()->isActive());
        $this->assertTrue($this->admin->refresh()->isActive());
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->postJson(route('users.deactivate', ['id' => 99999]))->assertNotFound();
    }

    public function test_it_rejects_a_non_numeric_id(): void
    {
        $this->postJson('/api/users/abc/deactivate')->assertNotFound();
    }

    public function test_a_switched_off_account_cannot_be_sent_a_new_activation_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson(route('users.deactivate', $user))->assertOk();

        $this->postJson(route('users.resend-activation', $user))
            ->assertForbidden()
            ->assertJsonPath('message', 'Nalog je isključen. Obratite se administratoru.');

        Notification::assertNotSentTo($user, AccountCreatedNotification::class);
    }
}
