<?php

namespace App\Http\Requests\MobileUserDevice;

use App\Http\Requests\MobileAuth\MobileUserRules;
use Illuminate\Foundation\Http\FormRequest;
use NotificationChannels\Expo\ExpoPushToken;

class UpdateMobileUserDeviceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...MobileUserRules::device(),

            /**
             * `ExponentPushToken[...]` from expo-notifications. Leave the key
             * out to keep whatever is stored; send null to switch
             * notifications off on this phone.
             */
            'expo_push_notification_token' => ['sometimes', 'nullable', 'string', 'max:255', ExpoPushToken::rule()],
        ];
    }
}
