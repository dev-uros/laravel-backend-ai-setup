<?php

namespace Tests\Feature\Api\Mobile;

use App\Jobs\RevokeAppleTokenJob;
use App\Models\MobileOtpCode;
use App\Models\MobileUser;
use App\Models\MobileUserDevice;
use App\Support\Apple\AppleRefreshTokenData;
use App\Support\Apple\AppleTokenClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The signed-in person's own account: profile, picture, deletion, blocking.
 */
class MobileAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inSerbian();
        Storage::fake(MobileUser::AVATAR_DISK);
    }

    public function test_me_answers_with_the_account(): void
    {
        $user = $this->actingAsMobileUser(MobileUser::factory()->create(['username' => 'ana', 'first_name' => 'Ana', 'last_name' => null]));

        $this->getJson(route('mobile.me.show'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.display_name', 'ana')
            ->assertJsonPath('data.has_apple', false);
    }

    public function test_the_profile_is_updated_partially(): void
    {
        $user = $this->actingAsMobileUser(MobileUser::factory()->create(['username' => 'ana', 'first_name' => 'Ana', 'last_name' => 'Anić']));

        $this->patchJson(route('mobile.me.update'), ['username' => 'Ana.Nova', 'last_name' => null, 'display_personal_name' => true, 'locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.username', 'ana.nova')
            ->assertJsonPath('data.first_name', 'Ana')
            ->assertJsonPath('data.last_name', null)
            ->assertJsonPath('data.display_name', 'Ana')
            ->assertJsonPath('data.locale', 'en');

        $this->assertSame('en', $user->fresh()->locale->value);
    }

    public function test_a_username_taken_in_another_case_is_refused_but_ones_own_is_fine(): void
    {
        MobileUser::factory()->create(['username' => 'pera']);
        $this->actingAsMobileUser(MobileUser::factory()->create(['username' => 'ana']));

        $this->patchJson(route('mobile.me.update'), ['username' => 'PERA'])->assertJsonValidationErrors('username');
        $this->patchJson(route('mobile.me.update'), ['username' => 'ANA'])->assertOk();
    }

    public function test_the_picture_is_replaced_and_removed(): void
    {
        $user = $this->actingAsMobileUser();

        $this->post(route('mobile.me.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.png', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $first = (string) $user->fresh()->avatar_path;

        $this->post(route('mobile.me.avatar.update'), ['avatar' => UploadedFile::fake()->image('b.png', 100, 100)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', fn (string $url): bool => str_ends_with($url, '.webp'));
        Storage::disk(MobileUser::AVATAR_DISK)->assertMissing($first);

        $this->deleteJson(route('mobile.me.avatar.destroy'))->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->deleteJson(route('mobile.me.avatar.destroy'))->assertOk();
        $this->assertSame([], Storage::disk(MobileUser::AVATAR_DISK)->allFiles());
    }

    public function test_deleting_the_account_erases_it_and_signs_out_everywhere(): void
    {
        $user = MobileUser::factory()->withGoogle()->create(['email' => 'ana@example.com', 'avatar_path' => 'mobile-users/avatars/x.webp']);
        Storage::disk(MobileUser::AVATAR_DISK)->put('mobile-users/avatars/x.webp', 'x');
        [, $token] = $this->mobileUserWithToken($user);
        $user->createToken(MobileUser::TOKEN_NAME);
        MobileUserDevice::factory()->withPushToken()->create(['mobile_user_id' => $user->id]);
        MobileOtpCode::query()->forceCreate(['email' => 'ana@example.com', 'code_hash' => 'x', 'sent_at' => now(), 'expires_at' => now()->addMinutes(10)]);

        $this->deleteJson(route('mobile.me.destroy'), [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Nalog je obrisan.');

        $row = MobileUser::withTrashed()->findOrFail($user->id);
        $this->assertTrue($row->trashed());
        foreach (['username', 'first_name', 'last_name', 'email', 'google_id', 'apple_id', 'avatar_path'] as $column) {
            $this->assertNull($row->{$column}, $column);
        }
        $this->assertSame(0, $row->tokens()->count());
        $this->assertDatabaseCount('mobile_user_devices', 0);
        $this->assertDatabaseCount('mobile_otp_codes', 0);
        Storage::disk(MobileUser::AVATAR_DISK)->assertMissing('mobile-users/avatars/x.webp');

        $this->app['auth']->forgetGuards();
        $this->getJson(route('mobile.me.show'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_deleting_an_apple_account_revokes_it_with_a_fresh_code(): void
    {
        Queue::fake();
        $user = $this->actingAsMobileUser(MobileUser::factory()->withApple('apple-sub')->create([
            'apple_client_id' => 'com.example.customers',
            'apple_refresh_token' => 'old-token',
        ]));
        $this->mock(AppleTokenClientInterface::class, fn (MockInterface $mock) => $mock
            ->expects('exchangeCode')->with('fresh-code', 'com.example.customers')
            ->andReturn(new AppleRefreshTokenData('fresh-token', 'apple-sub')));

        $this->deleteJson(route('mobile.me.destroy'), ['apple_authorization_code' => 'fresh-code'])->assertOk();

        Queue::assertPushed(RevokeAppleTokenJob::class, fn (RevokeAppleTokenJob $job): bool => $job->mobileUserId === $user->id
            && $job->refreshToken === 'fresh-token'
            && $job->clientId === 'com.example.customers');
    }

    public function test_without_a_fresh_code_the_kept_token_is_revoked(): void
    {
        Queue::fake();
        $this->actingAsMobileUser(MobileUser::factory()->withApple('apple-sub')->create([
            'apple_client_id' => 'com.example.customers',
            'apple_refresh_token' => 'old-token',
        ]));

        $this->deleteJson(route('mobile.me.destroy'))->assertOk();

        Queue::assertPushed(RevokeAppleTokenJob::class, fn (RevokeAppleTokenJob $job): bool => $job->refreshToken === 'old-token');
    }

    public function test_a_blocked_account_is_refused_on_its_next_request(): void
    {
        $user = $this->actingAsMobileUser();
        $user->forceFill(['blocked_at' => now()])->save();

        $this->getJson(route('mobile.me.show'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Vaš nalog je blokiran.');
    }
}
