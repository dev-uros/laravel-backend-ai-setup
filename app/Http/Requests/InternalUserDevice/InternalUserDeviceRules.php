<?php

namespace App\Http\Requests\InternalUserDevice;

use App\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

/**
 * The handset fields, written once for the two requests that carry them: the
 * `device` object of a login and the body of `PUT /auth/device`.
 *
 * Only the id and the platform are required -- they are what a row is keyed
 * on and what a push is shaped for. The rest is descriptive, and a platform
 * may simply not report some of it.
 */
class InternalUserDeviceRules
{
    /**
     * `$presence` is `required` where the fields are the whole body, and
     * `required_with:device` inside a login, where the object itself is
     * optional.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = '', string $presence = 'required'): array
    {
        return [
            $prefix.'device_id' => [$presence, 'string', 'max:255'],
            $prefix.'platform' => [$presence, Rule::enum(DevicePlatform::class)],
            $prefix.'name' => ['nullable', 'string', 'max:255'],
            $prefix.'operating_system' => ['nullable', 'string', 'max:255'],
            $prefix.'manufacturer' => ['nullable', 'string', 'max:255'],
            $prefix.'os_version' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The keys an object of handset fields may hold, for an `array:` rule.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::rules());
    }
}
