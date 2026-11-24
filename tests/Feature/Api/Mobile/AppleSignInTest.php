<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\MobileUser;
use App\Support\Apple\AppleRefreshTokenData;
use App\Support\Apple\AppleTokenClientInterface;
use App\Support\Apple\AppleTokenRequestFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Concerns\IssuesIdTokens;
use Tests\TestCase;

/**
 * Signing in and registering with Apple, nonce included. Apple's token
 * endpoint (refresh token, revocation) is a mock; the identity token is
 * checked for real against a fake key server.
 */
class AppleSignInTest extends TestCase
{
    use IssuesIdTokens, RefreshDatabase;

    private const NONCE = 'a-random-nonce-from-the-app-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        $this->inSerbian();
        Cache::flush();
        $this->fakeIdTokenKeys();
        config(['services.apple_oauth.client_ids' => ['com.example.customers.dev', 'com.example.customers']]);
    }

    public function test_the_apple_id_signs_in_and_keeps_the_refresh_token_encrypted(): void
    {
        $user = MobileUser::factory()->withApple('000123.abcdef0123456789.0123')->create();
        $this->mock(AppleTokenClientInterface::class, fn (MockInterface $mock) => $mock
            ->expects('exchangeCode')->with('auth-code', 'com.example.customers')
            ->andReturn(new AppleRefreshTokenData('refresh-token', '000123.abcdef0123456789.0123')));

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE),
            'nonce' => self::NONCE,
            'authorization_code' => 'auth-code',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $user->refresh();
        $this->assertSame('refresh-token', $user->apple_refresh_token);
        $this->assertSame('com.example.customers', $user->apple_client_id);
        $this->assertNotSame('refresh-token', DB::table('mobile_users')->value('apple_refresh_token'));
    }

    public function test_a_wrong_nonce_is_refused(): void
    {
        MobileUser::factory()->withApple('000123.abcdef0123456789.0123')->create();

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE),
            'nonce' => 'a-different-nonce-0123456789',
        ])->assertUnprocessable()->assertJsonPath('message', 'Prijava preko naloga Apple nije uspela. Pokušajte ponovo.');
    }

    public function test_a_token_for_another_app_is_refused(): void
    {
        MobileUser::factory()->withApple('000123.abcdef0123456789.0123')->create();

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE, ['aud' => 'com.somebody.else']),
            'nonce' => self::NONCE,
        ])->assertUnprocessable();
    }

    public function test_an_icloud_account_is_linked_and_any_other_is_not(): void
    {
        $icloud = MobileUser::factory()->create(['email' => 'ana@icloud.com']);
        MobileUser::factory()->create(['email' => 'pera@gmail.com']);

        $this->postJson(route('mobile.auth.apple.sign-in'), ['id_token' => $this->appleIdToken(self::NONCE), 'nonce' => self::NONCE])
            ->assertOk();
        $this->assertSame('000123.abcdef0123456789.0123', $icloud->fresh()->apple_id);

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE, ['sub' => 'other', 'email' => 'pera@gmail.com']),
            'nonce' => self::NONCE,
        ])->assertConflict();
    }

    public function test_no_account_leads_to_registration_with_the_same_code(): void
    {
        $this->mock(AppleTokenClientInterface::class, fn (MockInterface $mock) => $mock
            ->expects('exchangeCode')->once()
            ->andReturn(new AppleRefreshTokenData('refresh-token', 'relay-sub')));

        $token = $this->appleIdToken(self::NONCE, ['sub' => 'relay-sub', 'email' => 'x7f@privaterelay.appleid.com', 'is_private_email' => 'true']);

        /** Refused sign-ins never spend the code -- the mock above allows one trade only. */
        $this->postJson(route('mobile.auth.apple.sign-in'), ['id_token' => $token, 'nonce' => self::NONCE, 'authorization_code' => 'c'])
            ->assertNotFound();

        $this->postJson(route('mobile.auth.apple.register'), [
            'id_token' => $token,
            'nonce' => self::NONCE,
            'authorization_code' => 'c',
            'username' => 'ana',
            'first_name' => 'Ana',
            'last_name' => 'Anić',
        ])->assertCreated()->assertJsonPath('data.user.email', 'x7f@privaterelay.appleid.com');

        $this->assertSame('refresh-token', MobileUser::query()->sole()->apple_refresh_token);
    }

    public function test_a_failed_trade_does_not_fail_the_sign_in(): void
    {
        MobileUser::factory()->withApple('000123.abcdef0123456789.0123')->create();
        $this->mock(AppleTokenClientInterface::class, fn (MockInterface $mock) => $mock
            ->expects('exchangeCode')->andThrow(new AppleTokenRequestFailedException('apple oauth team_id is not configured')));

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE),
            'nonce' => self::NONCE,
            'authorization_code' => 'auth-code',
        ])->assertOk();

        $this->assertNull(MobileUser::query()->sole()->apple_refresh_token);
    }

    public function test_a_code_for_another_apple_id_is_not_kept(): void
    {
        MobileUser::factory()->withApple('000123.abcdef0123456789.0123')->create();
        $this->mock(AppleTokenClientInterface::class, fn (MockInterface $mock) => $mock
            ->expects('exchangeCode')->andReturn(new AppleRefreshTokenData('someone-elses', 'another-sub')));

        $this->postJson(route('mobile.auth.apple.sign-in'), [
            'id_token' => $this->appleIdToken(self::NONCE),
            'nonce' => self::NONCE,
            'authorization_code' => 'stolen',
        ])->assertOk();

        $this->assertNull(MobileUser::query()->sole()->apple_refresh_token);
    }

    public function test_without_a_bundle_id_it_is_unavailable(): void
    {
        config(['services.apple_oauth.client_ids' => []]);

        $this->postJson(route('mobile.auth.apple.sign-in'), ['id_token' => $this->appleIdToken(self::NONCE), 'nonce' => self::NONCE])
            ->assertServiceUnavailable();
    }
}
