<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWER = 'Ako nalog sa ovom email adresom postoji, poslali smo link za promenu lozinke.';

    public function test_it_emails_a_reset_link_to_an_activated_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'uros.minic@fifthguild.com']);

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => self::ANSWER, 'data' => null]);

        Notification::assertSentTo($user, PasswordResetNotification::class);
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_the_link_points_at_the_reset_page_not_the_activation_page(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.fifthguild.com']);
        $user = User::factory()->create(['email' => 'uros.minic@fifthguild.com']);

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            $parts = parse_url($url);
            parse_str($parts['query'], $query);

            $this->assertSame('app.fifthguild.com', $parts['host']);
            $this->assertSame('/auth/reset-password', $parts['path']);
            $this->assertSame($user->email, $query['email']);
            $this->assertNotEmpty($query['token']);

            return true;
        });
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_email(): void
    {
        Notification::fake();

        $this->postJson(route('auth.forgot-password'), ['email' => 'niko@nigde.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::ANSWER, 'data' => null]);

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_an_account_that_was_never_activated_gets_the_same_answer_and_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'neaktiviran@fifthguild.com']);

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => self::ANSWER, 'data' => null]);

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_all_three_outcomes_are_indistinguishable(): void
    {
        config(['app.debug' => false]);
        Notification::fake();

        $activated = User::factory()->create(['email' => 'aktiviran@fifthguild.com']);
        $unactivated = User::factory()->unverified()->create(['email' => 'neaktiviran@fifthguild.com']);

        $responses = collect([$activated->email, $unactivated->email, 'niko@nigde.com'])
            ->map(fn (string $email) => $this->postJson(route('auth.forgot-password'), ['email' => $email]));

        $this->assertCount(1, $responses->map(fn ($r) => $r->status())->unique());
        $this->assertCount(
            1,
            $responses->map(fn ($r) => json_encode($r->json()))->unique(),
            'the three outcomes must be identical, or the endpoint reveals which addresses are registered',
        );
    }

    public function test_the_email_is_required_and_must_be_valid(): void
    {
        $this->postJson(route('auth.forgot-password'), ['email' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson(route('auth.forgot-password'), ['email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_asking_twice_replaces_the_token_rather_than_stacking(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'uros.minic@fifthguild.com']);

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])->assertOk();
        $first = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])->assertOk();

        $rows = DB::table('password_reset_tokens')->where('email', $user->email)->get();
        $this->assertCount(1, $rows);
        $this->assertNotSame($first, $rows->first()->token);
    }
}
