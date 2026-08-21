<?php

namespace Tests\Feature\Api\Auth;

use App\Data\ActorData;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Notifications\PasswordResetNotification;
use App\Services\User\SendActivationLinkService;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Zmaj-Putuje-92!';

    protected function setUp(): void
    {
        parent::setUp();

        /** Never let the suite reach haveibeenpwned. */
        $this->app->bind(UncompromisedVerifier::class, fn (): UncompromisedVerifier => new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });
    }

    /**
     * An activated user plus the plain token from their reset email.
     *
     * @return array{0: User, 1: string}
     */
    private function userWithResetToken(): array
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'uros.minic@fifthguild.com', 'password' => 'stara-lozinka']);

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification) use ($user, &$token): bool {
            parse_str((string) parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return true;
        });

        return [$user, (string) $token];
    }

    /** @return array<string, mixed> */
    private function payload(string $token, array $overrides = []): array
    {
        return array_merge([
            'email' => 'uros.minic@fifthguild.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    public function test_it_changes_the_password_and_returns_user_and_token(): void
    {
        [$user, $token] = $this->userWithResetToken();

        $response = $this->postJson(route('auth.reset-password'), $this->payload($token));

        $response->assertOk()
            ->assertJsonPath('message', 'Lozinka je uspešno promenjena.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['message', 'data' => ['user' => ['id', 'email'], 'token']]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertTrue(PersonalAccessToken::findToken($response->json('data.token'))->tokenable->is($user));
    }

    public function test_it_leaves_email_verification_untouched(): void
    {
        [$user, $token] = $this->userWithResetToken();
        $verifiedAt = $user->email_verified_at;

        $this->postJson(route('auth.reset-password'), $this->payload($token))->assertOk();

        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
    }

    public function test_the_old_password_stops_working(): void
    {
        [$user, $token] = $this->userWithResetToken();

        $this->postJson(route('auth.reset-password'), $this->payload($token))->assertOk();

        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'stara-lozinka'])
            ->assertUnauthorized();

        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->assertOk();
    }

    public function test_it_revokes_tokens_issued_before_the_reset(): void
    {
        [$user, $token] = $this->userWithResetToken();

        $old = $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'stara-lozinka'])
            ->assertOk()->json('data.token');
        $this->assertNotNull(PersonalAccessToken::findToken($old));

        $fresh = $this->postJson(route('auth.reset-password'), $this->payload($token))
            ->assertOk()->json('data.token');

        $this->assertNull(
            PersonalAccessToken::findToken($old),
            'a reset is how a hijacked account is recovered, so older tokens must die',
        );
        $this->assertNotNull(PersonalAccessToken::findToken($fresh));
    }

    public function test_the_link_works_exactly_once(): void
    {
        [, $token] = $this->userWithResetToken();

        $this->postJson(route('auth.reset-password'), $this->payload($token))->assertOk();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $this->postJson(route('auth.reset-password'), $this->payload($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Link za promenu lozinke nije važeći ili je istekao. Zatražite novi.');
    }

    public function test_an_activation_link_cannot_be_spent_here(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'neaktiviran@fifthguild.com']);
        app(SendActivationLinkService::class)->handle($user, ActorData::system());

        $token = null;
        Notification::assertSentTo($user, AccountCreatedNotification::class, function ($notification) use ($user, &$token): bool {
            parse_str((string) parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return true;
        });

        $this->postJson(route('auth.reset-password'), $this->payload((string) $token, [
            'email' => $user->email,
        ]))->assertUnprocessable();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(
            1,
            DB::table('password_reset_tokens')->where('email', $user->email)->count(),
            'a refused attempt must not burn the activation link',
        );
    }

    public function test_a_wrong_token_changes_nothing(): void
    {
        [$user] = $this->userWithResetToken();
        $original = $user->password;

        $this->postJson(route('auth.reset-password'), $this->payload(str_repeat('a', 64)))
            ->assertUnprocessable();

        $this->assertSame($original, $user->fresh()->password);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        [, $token] = $this->userWithResetToken();

        $this->postJson(route('auth.reset-password'), $this->payload($token, ['email' => 'niko@nigde.com']))
            ->assertUnprocessable();
    }

    #[DataProvider('weakPasswords')]
    public function test_it_enforces_the_password_policy(string $password): void
    {
        [, $token] = $this->userWithResetToken();

        $this->postJson(route('auth.reset-password'), $this->payload($token, [
            'password' => $password,
            'password_confirmation' => $password,
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    /** @return array<string, array<int, string>> */
    public static function weakPasswords(): array
    {
        return [
            'fewer than 8 characters' => ['Aa1!bc'],
            'no uppercase letter' => ['bezvelikog-92!'],
            'no lowercase letter' => ['BEZMALOG-92!'],
            'no digit' => ['Bez-Broja-Ovde!'],
            'no symbol' => ['BezSimbola92'],
        ];
    }

    public function test_the_password_must_be_confirmed(): void
    {
        [, $token] = $this->userWithResetToken();

        $this->postJson(route('auth.reset-password'), $this->payload($token, ['password_confirmation' => 'nesto-drugo']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
