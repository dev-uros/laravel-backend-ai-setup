<?php

namespace App\Http\Requests\MobileAuth;

use App\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

/**
 * The fields every way into the external app shares, written once so signing
 * in, registering and editing the profile cannot come to disagree about what
 * an address, a handle or a picture may be.
 */
class MobileUserRules
{
    /** Pictures off a phone are several megabytes; they are re-encoded to a small WebP. */
    public const AVATAR_MAX_KILOBYTES = 5120;

    /**
     * Lower-case letters, digits, dots and underscores; it is stored
     * lower-cased, so the plain unique index is case-insensitive.
     */
    public const USERNAME_PATTERN = '/^[a-z0-9._]+$/';

    /**
     * @return array<int, mixed>
     */
    public static function email(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    /**
     * @return array<int, mixed>
     */
    public static function freeEmail(): array
    {
        return [...self::email(), Rule::unique('mobile_users', 'email')];
    }

    /**
     * @return array<int, mixed>
     */
    public static function username(?int $ignoreId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            'regex:'.self::USERNAME_PATTERN,
            Rule::unique('mobile_users', 'username')->ignore($ignoreId),
        ];
    }

    /**
     * The optional parts of a profile.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function names(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            /** Show "first last" instead of the username, where a name was given. */
            'display_personal_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A JPEG, PNG, WebP or GIF of at least 64x64, up to 5 MB -- the same
     * formats the internal avatars accept, for the same reasons (see
     * `UpdateUserAvatarRequest`).
     *
     * @return array<int, mixed>
     */
    public static function avatar(string $presence = 'required'): array
    {
        return [$presence, 'image', 'mimes:jpeg,jpg,png,webp,gif', 'dimensions:min_width=64,min_height=64', 'max:'.self::AVATAR_MAX_KILOBYTES];
    }

    /**
     * The registration profile: handle, optional names and picture.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function registrationProfile(): array
    {
        return [
            /** 3-30 lower-case letters, digits, dots and underscores; unique. Sent upper-case, it is lower-cased. */
            'username' => self::username(),
            ...self::names(),
            /** Optional. Sent as multipart/form-data; re-encoded to a square 512x512 WebP. */
            'avatar' => self::avatar('nullable'),
        ];
    }

    /**
     * The handset fields, at the top level (`PUT /device`) or under `device.`
     * in a sign-in, where the object itself is optional.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function device(string $prefix = '', string $presence = 'required'): array
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
     * The optional `device` object of a sign-in or registration: the phone the
     * new session is on, bound to the token it returns.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function optionalDevice(): array
    {
        return [
            'device' => ['sometimes', 'array:'.implode(',', array_keys(self::device())), 'required_array_keys:device_id,platform'],
            ...self::device('device.', 'required_with:device'),
        ];
    }
}
