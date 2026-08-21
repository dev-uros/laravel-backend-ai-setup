<?php

namespace Tests\Feature\Api\User;

use App\Data\ActorData;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Services\User\SendActivationLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResendActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_it_returns_the_envelope_with_no_payload(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson(route('users.resend-activation', $user));

        $response->assertOk()->assertExactJson([
            'message' => 'Aktivacioni email je ponovo poslat.',
            'data' => null,
        ]);
    }

    public function test_it_emails_the_user_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson(route('users.resend-activation', $user))->assertOk();

        Notification::assertSentTo($user, AccountCreatedNotification::class);
        Notification::assertCount(1);
    }

    public function test_it_issues_a_fresh_token_and_keeps_only_one(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        app(SendActivationLinkService::class)->handle($user, ActorData::system());
        $first = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->postJson(route('users.resend-activation', $user))->assertOk();

        $rows = DB::table('password_reset_tokens')->where('email', $user->email)->get();

        $this->assertCount(1, $rows, 'resending must replace the token, not pile up rows');
        $this->assertNotSame($first, $rows->first()->token);
    }

    public function test_the_previous_link_stops_working_and_the_new_one_works(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $old = $this->issueAndCaptureToken($user);
        $new = $this->issueAndCaptureToken($user);

        $this->assertNotSame($old, $new);
        $this->assertFalse(Password::broker()->tokenExists($user, $old), 'the superseded link must be dead');
        $this->assertTrue(Password::broker()->tokenExists($user, $new));
    }

    public function test_it_404s_for_an_unknown_user(): void
    {
        Notification::fake();

        $this->postJson(route('users.resend-activation', ['id' => 999]))
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik nije pronađen.');

        Notification::assertNothingSent();
    }

    public function test_a_non_numeric_id_does_not_reach_the_application(): void
    {
        $this->postJson('/api/users/abc/resend-activation')->assertNotFound();
    }

    /**
     * Send an activation link and read the plain token back out of the email.
     */
    private function issueAndCaptureToken(User $user): string
    {
        Notification::fake();

        $this->postJson(route('users.resend-activation', $user))->assertOk();

        $token = null;

        Notification::assertSentTo($user, AccountCreatedNotification::class, function ($notification) use ($user, &$token): bool {
            parse_str((string) parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return true;
        });

        return (string) $token;
    }
}
