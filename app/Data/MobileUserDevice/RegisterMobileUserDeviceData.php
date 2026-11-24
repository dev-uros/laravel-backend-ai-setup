<?php

namespace App\Data\MobileUserDevice;

use App\Enums\DevicePlatform;
use App\Http\Requests\MobileUserDevice\UpdateMobileUserDeviceRequest;

/**
 * The phone a session is on, as the external app describes it -- at sign-in,
 * and again on every launch. The twin of `RegisterInternalUserDeviceData`.
 *
 * The push token is three-state: absent leaves whatever is stored alone, null
 * switches notifications off, a value switches them on.
 */
final readonly class RegisterMobileUserDeviceData
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
     * From the validated `device` object of a sign-in or registration, or the
     * body of `PUT /mobile/v1/device`.
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
     * The `device` object of a request, or null when it was left out.
     *
     * @param  array<string, mixed>|null  $device
     */
    public static function fromOptionalArray(?array $device): ?self
    {
        return $device === null ? null : self::fromArray($device);
    }

    public static function fromRequest(UpdateMobileUserDeviceRequest $request): self
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
