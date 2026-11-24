<?php

namespace Tests\Feature\Api\Mobile;

use App\Enums\AppLocale;
use App\Models\MobileOtpCode;
use App\Models\MobileUser;
use App\Models\MobileUserDevice;
use App\Notifications\MobileOtpCodeNotification;
use App\Repositories\Contracts\MobileOtpCodeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Signing in to the external app with a code mailed to the address.
 */
class EmailSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'sr');
        Notification::fake();
    }

    public function test_an_address_with_an_account_is_mailed_a_code(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => ' Ana@Example.com '])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Poslali smo kod na vašu email adresu.',
                'data' => ['account_exists' => true, 'retry_after' => 30],
            ]);

        $code = $this->mailedCode('ana@example.com');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(Hash::check($code, MobileOtpCode::query()->sole()->code_hash), 'only the hash is stored');
        $this->assertNotSame($code, MobileOtpCode::query()->sole()->code_hash);
    }

    public function test_an_address_without_an_account_is_told_so_and_mailed_nothing(): void
    {
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('data.account_exists', false)
            ->assertJsonPath('message', 'Ne postoji nalog sa ovom email adresom.');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('mobile_otp_codes', 0);
    }

    public function test_a_blocked_account_is_refused_and_mailed_nothing(): void
    {
        MobileUser::factory()->blocked()->create(['email' => 'ana@example.com']);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_another_code_within_half_a_minute_is_refused_with_the_wait(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])->assertOk();

        $this->travel(20)->seconds();

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 10)
            ->assertJsonPath('message', 'Novi kod možete zatražiti za 10 s.');

        $this->travel(11)->seconds();

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])->assertOk();

        Notification::assertSentOnDemandTimes(MobileOtpCodeNotification::class, 2);
    }

    public function test_the_mail_is_encrypted_on_the_queue_and_in_the_apps_language(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);

        /** The job that carries a queued notification takes its encryption from the notification. */
        $job = new SendQueuedNotifications(new AnonymousNotifiable, new MobileOtpCodeNotification('123456'), ['mail']);
        $this->assertTrue($job->shouldBeEncrypted);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'], ['Accept-Language' => 'en'])
            ->assertJsonPath('message', 'We sent a code to your email address.');

        Notification::assertSentOnDemand(
            MobileOtpCodeNotification::class,
            fn (MobileOtpCodeNotification $notification): bool => $notification->locale === 'en',
        );
    }

    public function test_the_code_never_reaches_a_log_line(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines): void {
            $lines[] = $event->message.json_encode($event->context);
        });

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])->assertOk();
        $code = $this->mailedCode('ana@example.com');
        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $code])->assertOk();

        $this->assertNotEmpty($lines, 'the request log ran');
        foreach ($lines as $line) {
            $this->assertStringNotContainsString($code, $line);
        }
    }

    public function test_the_right_code_signs_in_with_the_phone_and_the_language(): void
    {
        $user = MobileUser::factory()->create(['email' => 'ana@example.com', 'locale' => AppLocale::Sr]);
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])->assertOk();

        $response = $this->postJson(route('mobile.auth.email.sign-in'), [
            'email' => 'ANA@example.com',
            'code' => $this->mailedCode('ana@example.com'),
            'device' => ['device_id' => 'phone-1', 'platform' => 'ios', 'name' => 'iPhone'],
        ], ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('message', 'You are signed in.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.locale', 'en');

        $this->assertSame('en', $user->fresh()->locale->value, 'mail and pushes follow the app from now on');
        $this->assertDatabaseCount('mobile_otp_codes', 0);

        $device = MobileUserDevice::query()->sole();
        $this->assertSame($user->id, $device->mobile_user_id);
        $this->assertNotNull($device->personal_access_token_id);

        $this->getJson(route('mobile.me.show'), ['Authorization' => 'Bearer '.$response->json('data.token')])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_a_code_works_once(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com']);
        $code = $this->mailedCode('ana@example.com');

        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $code])->assertOk();
        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kod nije ispravan ili je istekao. Zatražite novi.');
    }

    public function test_an_expired_code_is_refused(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com']);
        $code = $this->mailedCode('ana@example.com');

        $this->travel(MobileOtpCode::VALID_FOR_MINUTES)->minutes();
        $this->travel(1)->seconds();

        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $code])
            ->assertUnprocessable();
    }

    public function test_the_fifth_wrong_guess_uses_the_code_up(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com']);
        $code = $this->mailedCode('ana@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < MobileOtpCode::MAX_ATTEMPTS; $i++) {
            $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $wrong])
                ->assertUnprocessable();
        }

        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $code])
            ->assertUnprocessable();
    }

    /**
     * The counter is claimed by one conditional UPDATE, so the database -- not
     * a read-then-write in PHP -- decides who gets the last attempt.
     */
    public function test_the_last_attempt_can_be_claimed_only_once(): void
    {
        $codes = app(MobileOtpCodeRepositoryInterface::class);
        $codes->issue('ana@example.com', Hash::make('123456'));
        MobileOtpCode::query()->update(['attempts' => MobileOtpCode::MAX_ATTEMPTS - 1]);

        $this->assertNotNull($codes->claimAttempt('ana@example.com'));
        $this->assertNull($codes->claimAttempt('ana@example.com'));
        $this->assertSame(MobileOtpCode::MAX_ATTEMPTS, MobileOtpCode::query()->sole()->attempts);
    }

    public function test_a_registration_code_does_not_sign_in_to_a_missing_account(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'new@example.com', 'username' => 'novi']);

        $this->postJson(route('mobile.auth.email.sign-in'), [
            'email' => 'new@example.com',
            'code' => $this->mailedCode('new@example.com'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('mobile_users', 0);
    }

    public function test_a_code_issued_before_a_block_cannot_be_used(): void
    {
        $user = MobileUser::factory()->create(['email' => 'ana@example.com']);
        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com']);
        $user->forceFill(['blocked_at' => now()])->save();

        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => $this->mailedCode('ana@example.com')])
            ->assertForbidden();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_asking_for_codes_is_throttled_per_address(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com']);
            $this->travel(61)->seconds();
        }

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ANA@example.com'])
            ->assertTooManyRequests();
    }

    private function mailedCode(string $email): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            MobileOtpCodeNotification::class,
            function (MobileOtpCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code): bool {
                if (($notifiable->routes['mail'] ?? null) !== $email) {
                    return false;
                }

                preg_match('/\*\*(\d{6})\*\*/', implode("\n", $notification->toMail($notifiable)->introLines), $matches);
                $code = $matches[1] ?? null;

                return true;
            },
        );

        $this->assertNotNull($code);

        return $code;
    }
}
