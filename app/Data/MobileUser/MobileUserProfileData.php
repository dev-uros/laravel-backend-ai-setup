<?php

namespace App\Data\MobileUser;

use App\Enums\AppLocale;
use App\Http\Requests\MobileUser\UpdateMobileUserProfileRequest;

/**
 * A partial profile update (`PATCH /mobile/v1/me`). `present` lists the
 * columns the request actually carried, because null is a value here -- it
 * clears a first or last name -- and must not be confused with "left out".
 */
final readonly class MobileUserProfileData
{
    /**
     * @param  list<string>  $present
     */
    public function __construct(
        public array $present,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?bool $displayPersonalName = null,
        public ?AppLocale $locale = null,
    ) {}

    public static function fromRequest(UpdateMobileUserProfileRequest $request): self
    {
        $columns = [
            'username' => 'username',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'display_personal_name' => 'display_personal_name',
            'locale' => 'locale',
        ];

        return new self(
            present: array_values(array_filter($columns, fn (string $field): bool => $request->has($field))),
            username: $request->validated('username'),
            firstName: $request->validated('first_name'),
            lastName: $request->validated('last_name'),
            displayPersonalName: $request->has('display_personal_name') ? $request->boolean('display_personal_name') : null,
            locale: $request->has('locale') ? AppLocale::from((string) $request->validated('locale')) : null,
        );
    }
}
