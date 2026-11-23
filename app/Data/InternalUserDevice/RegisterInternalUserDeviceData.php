<?php

namespace App\Data\InternalUserDevice;

use App\Enums\DevicePlatform;
use App\Http\Requests\InternalUserDevice\UpdateInternalUserDeviceRequest;

/**
 * The phone a session is on, as the mobile app describes it -- at sign-in,
 * and again on every launch.
 *
 * The push token is three-state, which is why it travels with a flag: absent
 * leaves whatever is stored alone (a sign-in, or a launch before the app has
 * asked for permission), null switches notifications off, a value switches
 * them on. Collapsing absent into null would wipe the token on every login.
 */
final readonly class RegisterInternalUserDeviceData
{
    public function __construct(
        public string $deviceId,
        public DevicePlatform $platform,
        public ?string $name = null,
        public ?string $operatingSystem = null,
        public ?string $manufacturer = null,
        public ?string $osVersion = null,
        public bool $updatesPushToken = false,
        public ?string $pushToken = null,
    ) {}

    /**
     * From the validated `device` object of a login, or the body of
     * `PUT /auth/device`. Never carries a push token: that is the caller's to
     * add, because only one of the two endpoints accepts it.
     *
     * @param  array<string, mixed>  $device
     */
    public static function fromArray(array $device): self
    {
        return new self(
            deviceId: (string) $device['device_id'],
            platform: DevicePlatform::from((string) $device['platform']),
            name: $device['name'] ?? null,
            operatingSystem: $device['operating_system'] ?? null,
            manufacturer: $device['manufacturer'] ?? null,
            osVersion: $device['os_version'] ?? null,
        );
    }

    /**
     * Build the DTO from a validated request.
     */
    public static function fromRequest(UpdateInternalUserDeviceRequest $request): self
    {
        $device = self::fromArray($request->validated());

        return new self(
            deviceId: $device->deviceId,
            platform: $device->platform,
            name: $device->name,
            operatingSystem: $device->operatingSystem,
            manufacturer: $device->manufacturer,
            osVersion: $device->osVersion,
            updatesPushToken: $request->has('expo_push_notification_token'),
            pushToken: $request->validated('expo_push_notification_token'),
        );
    }

    /**
     * The columns that describe the handset itself.
     *
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'device_id' => $this->deviceId,
            'platform' => $this->platform->value,
            'name' => $this->name,
            'operating_system' => $this->operatingSystem,
            'manufacturer' => $this->manufacturer,
            'os_version' => $this->osVersion,
        ];
    }
}
