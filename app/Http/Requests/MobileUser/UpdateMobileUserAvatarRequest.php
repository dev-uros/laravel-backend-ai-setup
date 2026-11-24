<?php

namespace App\Http\Requests\MobileUser;

use App\Http\Requests\MobileAuth\MobileUserRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMobileUserAvatarRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** A JPEG, PNG, WebP or GIF of at least 64x64, up to 5 MB, as multipart/form-data. Re-encoded to a square 512x512 WebP. */
            'avatar' => MobileUserRules::avatar(),
        ];
    }
}
