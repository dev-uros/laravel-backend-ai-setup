<?php

namespace App\Http\Requests\MobileUser;

use Illuminate\Foundation\Http\FormRequest;

class IndexMobileUsersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Part of the username, first or last name, or address. */
            'search' => ['nullable', 'string', 'max:255'],
            /** `1` for blocked accounts only, `0` for the rest; leave out for both. */
            'blocked' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
