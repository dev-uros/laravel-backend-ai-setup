<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\InternalUserDevice\InternalUserDeviceRules;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],

            /**
             * Sent by the mobile app only: the phone this session is on. Its
             * row in `internal_user_devices` is created or updated and bound
             * to the token this login returns. The web app leaves it out.
             */
            'device' => ['sometimes', 'array:'.implode(',', InternalUserDeviceRules::keys()), 'required_array_keys:device_id,platform'],
            ...InternalUserDeviceRules::rules('device.', 'required_with:device'),
        ];
    }
}
