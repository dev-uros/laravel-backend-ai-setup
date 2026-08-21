<?php

namespace Tests\Feature\Api\Auth;

use App\Data\ActorData;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Services\User\SendActivationLinkService;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivateAccountTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'Zmaj-Putuje-92!';

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * The `uncompromised` rule calls haveibeenpwned over the network. Tests
         * must never depend on that, so the verifier is stubbed to "not leaked"
         * by default; the one test that cares stubs it the other way.
         */
        $this->stubBreachCheck(compromised: false);
    }

    private function stubBreachCheck(bool $compromised): void
    {
        $this->app->bind(UncompromisedVerifier::class, fn (): UncompromisedVerifier => new class($compromised) implements UncompromisedVerifier
        {
            public function __construct(private readonly bool $compromised) {}

            public function verify($data): bool
            {
                return ! $this->compromised;
            }
        });
    }

    /**
     * Create a user and return them with the plain activation token from the email.
     *
     * @return array{0: User, 1: string}
     */
    private function userWithActivationToken(): array
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'petar@gmail.com']);

        app(SendActivationLinkService::class)->handle($user, ActorData::system());

        $token = null;
        Notification::assertSentTo($user, AccountCreatedNotification::class, function ($notification) use ($user, &$token): bool {
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
            'email' => 'petar@gmail.com',
            'token' => $token,
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ], $overrides);
    }

    public function test_it_activates_the_account_and_returns_user_and_token(): void
    {
        [$user, $token] = $this->userWithActivationToken();

        $response = $this->postJson(route('auth.activate-account'), $this->payload($token));

        $response->assertOk()
            ->assertJsonPath('message', 'Nalog je uspešno aktiviran.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['message', 'data' => ['user' => ['id', 'email', 'email_verified_at'], 'token']]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertTrue(PersonalAccessToken::findToken($response->json('data.token'))->tokenable->is($user));
    }

    public function test_it_marks_the_email_as_verified(): void
    {
        [$user, $token] = $this->userWithActivationToken();
        $this->assertNull($user->email_verified_at);

        $response = $this->postJson(route('auth.activate-account'), $this->payload($token))->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull($response->json('data.user.email_verified_at'));
    }

    public function test_the_chosen_password_works_for_logging_in(): void
    {
        [$user, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token))->assertOk();

        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->fresh()->password));

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => self::STRONG_PASSWORD,
        ])->assertOk();
    }

    public function test_the_link_works_exactly_once(): void
    {
        [, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token))->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->postJson(route('auth.activate-account'), $this->payload($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Link za aktivaciju nije važeći ili je istekao. Zatražite novi.');
    }

    public function test_a_wrong_token_is_rejected_and_changes_nothing(): void
    {
        [$user] = $this->userWithActivationToken();
        $originalPassword = $user->password;

        $this->postJson(route('auth.activate-account'), $this->payload(str_repeat('a', 64)))
            ->assertUnprocessable();

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_token_belonging_to_another_email_is_rejected(): void
    {
        [, $token] = $this->userWithActivationToken();
        User::factory()->unverified()->create(['email' => 'neko.drugi@gmail.com']);

        $this->postJson(route('auth.activate-account'), $this->payload($token, ['email' => 'neko.drugi@gmail.com']))
            ->assertUnprocessable();
    }

    #[DataProvider('requiredFields')]
    public function test_every_field_is_required(string $field): void
    {
        [, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token, [$field => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /** @return array<string, array<int, string>> */
    public static function requiredFields(): array
    {
        return ['email' => ['email'], 'token' => ['token'], 'password' => ['password']];
    }

    public function test_the_password_must_be_confirmed(): void
    {
        [, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token, ['password_confirmation' => 'nesto-drugo']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    #[DataProvider('weakPasswords')]
    public function test_it_enforces_the_password_policy(string $password): void
    {
        [, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token, [
            'password' => $password,
            'password_confirmation' => $password,
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    /**
     * The data set name says which rule each one is meant to trip.
     *
     * @return array<string, array<int, string>>
     */
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

    public function test_a_breached_password_is_rejected(): void
    {
        $this->stubBreachCheck(compromised: true);
        [, $token] = $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame(
            1,
            DB::table('password_reset_tokens')->count(),
            'a rejected password must not spend the link',
        );
    }

    public function test_nothing_is_issued_when_the_token_is_invalid(): void
    {
        $this->userWithActivationToken();

        $this->postJson(route('auth.activate-account'), $this->payload('ocigledno-pogresan'))
            ->assertUnprocessable();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }
}
