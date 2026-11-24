<?php

namespace App\Data\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * What an account is made of, whichever way the address was proved: the
 * handle, optionally a name and a picture.
 */
final readonly class RegistrationProfileData
{
    public function __construct(
        public string $username,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public bool $displayPersonalName = false,
        public ?UploadedFile $avatar = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            username: (string) $request->validated('username'),
            firstName: self::nameOf($request->validated('first_name')),
            lastName: self::nameOf($request->validated('last_name')),
            displayPersonalName: $request->boolean('display_personal_name'),
            avatar: $request->file('avatar'),
        );
    }

    private static function nameOf(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
