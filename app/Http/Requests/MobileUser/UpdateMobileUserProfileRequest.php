<?php

namespace App\Http\Requests\MobileUser;

use App\Enums\AppLocale;
use App\Http\Requests\MobileAuth\MobileUserRules;
use App\Http\Requests\MobileAuth\NormalizesMobileIdentity;
use App\Models\MobileUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partial update: send only what changes. Null clears a first or last name.
 */
class UpdateMobileUserProfileRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var MobileUser $user */
        $user = $this->user();

        return [
            'username' => ['sometimes', ...MobileUserRules::username($user->id)],
            ...MobileUserRules::names(),
            /** What mail and pushes are written in from now on. */
            'locale' => ['sometimes', Rule::enum(AppLocale::class)],
        ];
    }
}
