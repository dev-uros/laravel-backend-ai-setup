<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\MobileUser;
use App\Notifications\MobileOtpCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registering in the external app: the form (email, username, optional names
 * and picture) asks for a code, and the account is made when it comes back.
 */
class EmailRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'sr');
        Notification::fake();
        Storage::fake(MobileUser::AVATAR_DISK);
    }

    public function test_a_free_address_and_username_are_mailed_a_code(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'Nova@Example.com', 'username' => 'Nova.Korisnica'])
            ->assertOk()
            ->assertJsonPath('data.retry_after', 30);

        Notification::assertSentOnDemand(
            MobileOtpCodeNotification::class,
            fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'nova@example.com',
        );
    }

    public function test_a_taken_address_or_username_is_refused_before_anything_is_mailed(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);

        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'ANA@example.com', 'username' => 'nova'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Vrednost polja email adresa je već zauzeta.']);

        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'nova@example.com', 'username' => 'ANA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        Notification::assertNothingSent();
    }

    public function test_the_username_format_is_explained_in_the_apps_language(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'nova@example.com', 'username' => 'ne valja!'])
            ->assertJsonValidationErrors(['username' => 'Korisničko ime sme da sadrži samo mala slova, brojeve, tačku i donju crtu.']);

        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'nova@example.com', 'username' => 'ne valja!'], ['Accept-Language' => 'en'])
            ->assertJsonValidationErrors(['username' => 'A username may contain only lower-case letters, digits, dots and underscores.']);
    }

    public function test_the_code_makes_the_account_with_its_profile_and_picture(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'nova@example.com', 'username' => 'nova']);

        $response = $this->post(route('mobile.auth.email.register'), [
            'email' => 'nova@example.com',
            'code' => $this->mailedCode(),
            'username' => 'Nova',
            'first_name' => ' Nova ',
            'last_name' => 'Korisnica',
            'display_personal_name' => '1',
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 200),
            'device' => ['device_id' => 'phone-1', 'platform' => 'android'],
        ], ['Accept' => 'application/json', 'Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonPath('message', 'Your account has been created.')
            ->assertJsonPath('data.user.username', 'nova')
            ->assertJsonPath('data.user.first_name', 'Nova')
            ->assertJsonPath('data.user.display_name', 'Nova Korisnica')
            ->assertJsonPath('data.user.locale', 'en')
            ->assertJsonPath('data.user.has_google', false);

        $user = MobileUser::query()->sole();
        $this->assertSame('nova@example.com', $user->email);
        $this->assertStringEndsWith('.webp', (string) $user->avatar_path);
        Storage::disk(MobileUser::AVATAR_DISK)->assertExists((string) $user->avatar_path);
        $this->assertSame(1, $user->devices()->count());
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_a_wrong_code_makes_nothing_and_leaves_no_picture(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'nova@example.com', 'username' => 'nova']);

        $this->post(route('mobile.auth.email.register'), [
            'email' => 'nova@example.com',
            'code' => $this->mailedCode() === '999999' ? '888888' : '999999',
            'username' => 'nova',
            'avatar' => UploadedFile::fake()->image('me.jpg', 100, 100),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('mobile_users', 0);
        $this->assertSame([], Storage::disk(MobileUser::AVATAR_DISK)->allFiles());
    }

    public function test_a_deleted_accounts_address_and_username_can_register_again(): void
    {
        $old = MobileUser::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);
        $this->actingAsMobileUser($old);
        $this->deleteJson(route('mobile.me.destroy'))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'ana@example.com', 'username' => 'ana'])
            ->assertOk();
    }

    private function mailedCode(): string
    {
        $code = null;

        Notification::assertSentOnDemand(MobileOtpCodeNotification::class, function ($notification, $channels, $notifiable) use (&$code): bool {
            preg_match('/\*\*(\d{6})\*\*/', implode("\n", $notification->toMail($notifiable)->introLines), $matches);
            $code = $matches[1];

            return true;
        });

        return (string) $code;
    }
}
