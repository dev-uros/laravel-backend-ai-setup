<?php

namespace App\Http\Requests\InternalUserDevice;

use Illuminate\Foundation\Http\FormRequest;
use NotificationChannels\Expo\ExpoPushToken;

class UpdateInternalUserDeviceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...InternalUserDeviceRules::rules(),

            /**
             * `ExponentPushToken[...]` from expo-notifications. Leave the key
             * out to keep whatever is stored; send null to switch
             * notifications off on this phone.
             */
            'expo_push_notification_token' => ['sometimes', 'nullable', 'string', 'max:255', ExpoPushToken::rule()],
        ];
    }
}
