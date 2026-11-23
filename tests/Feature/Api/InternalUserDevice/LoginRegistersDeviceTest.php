<?php

namespace Tests\Feature\Api\InternalUserDevice;

use App\Enums\DevicePlatform;
use App\Models\InternalUserDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Signing in from the mobile app records the phone, bound to the session the
 * login returns. Signing in from the web records nothing.
 */
class LoginRegistersDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => 'tajna-lozinka']);
    }

    /**
     * @return array<string, string|null>
     */
    private function device(array $overrides = []): array
    {
        return [
            'device_id' => 'a1b2c3d4e5f60718',
            'platform' => 'android',
            'name' => 'Pixel 8',
            'operating_system' => 'Android',
            'manufacturer' => 'Google',
            'os_version' => '16',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $device
     */
    private function login(User $user, ?array $device): string
    {
        return $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
            ...($device === null ? [] : ['device' => $device]),
        ])->assertOk()->json('data.token');
    }

    public function test_a_web_login_records_no_device(): void
    {
        $token = $this->login($this->user(), null);

        $this->assertSame(0, InternalUserDevice::query()->count());
        $this->assertSame('spa', PersonalAccessToken::findToken($token)->name);
    }

    public function test_a_mobile_login_records_the_phone_bound_to_the_new_session(): void
    {
        $user = $this->user();

        $token = PersonalAccessToken::findToken($this->login($user, $this->device()));

        $device = InternalUserDevice::query()->sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($token->id, $device->personal_access_token_id);
        $this->assertSame('a1b2c3d4e5f60718', $device->device_id);
        $this->assertSame(DevicePlatform::Android, $device->platform);
        $this->assertSame('Pixel 8', $device->name);
        $this->assertSame('16', $device->os_version);
        $this->assertNull($device->expo_push_notification_token);
        $this->assertNotNull($device->last_seen_at);
        $this->assertSame('mobile', $token->name);
    }

    public function test_signing_in_again_on_the_same_phone_updates_its_row_and_keeps_the_push_token(): void
    {
        $user = $this->user();
        $this->login($user, $this->device());
        InternalUserDevice::query()->update(['expo_push_notification_token' => 'ExponentPushToken[keepkeepkeepkeepkeep]']);

        $this->app['auth']->forgetGuards();
        $second = PersonalAccessToken::findToken($this->login($user, $this->device(['os_version' => '17'])));

        $device = InternalUserDevice::query()->sole();
        $this->assertSame('17', $device->os_version);
        $this->assertSame($second->id, $device->personal_access_token_id);
        $this->assertSame('ExponentPushToken[keepkeepkeepkeepkeep]', $device->expo_push_notification_token);
    }

    public function test_two_people_on_one_phone_get_a_row_each(): void
    {
        $this->login($this->user(), $this->device());
        $this->login($this->user(), $this->device());

        $this->assertSame(2, InternalUserDevice::query()->where('device_id', 'a1b2c3d4e5f60718')->count());
    }

    public function test_a_failed_login_records_no_device(): void
    {
        $user = $this->user();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'pogresna-lozinka',
            'device' => $this->device(),
        ])->assertUnauthorized();

        $this->assertSame(0, InternalUserDevice::query()->count());
    }

    public function test_the_descriptive_fields_may_be_missing(): void
    {
        $this->login($this->user(), ['device_id' => 'ios-vendor-id', 'platform' => 'ios']);

        $device = InternalUserDevice::query()->sole();
        $this->assertSame(DevicePlatform::Ios, $device->platform);
        $this->assertNull($device->name);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    #[DataProvider('invalidDevices')]
    public function test_a_malformed_device_is_refused(mixed $device, string $field): void
    {
        $user = $this->user();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
            'device' => $device,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame(0, InternalUserDevice::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidDevices(): array
    {
        return [
            'bez identifikatora' => [['platform' => 'android'], 'device'],
            'bez platforme' => [['device_id' => 'x'], 'device'],
            'nepoznata platforma' => [['device_id' => 'x', 'platform' => 'windows'], 'device.platform'],
            'nepoznato polje' => [['device_id' => 'x', 'platform' => 'ios', 'imei' => '123'], 'device'],
            'prazan objekat' => [[], 'device'],
            'nije objekat' => ['telefon', 'device'],
        ];
    }
}
