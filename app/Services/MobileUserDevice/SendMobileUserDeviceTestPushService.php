<?php

namespace App\Services\MobileUserDevice;

use App\Exceptions\MobilePushNotificationsDisabledException;
use App\Exceptions\MobileUserDeviceNotFoundException;
use App\Notifications\MobileTestPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Expo\ExpoPushToken;

class SendMobileUserDeviceTestPushService
{
    public function __construct(private readonly ShowMobileUserDeviceService $showDevice) {}

    /**
     * Send a test notification to the phone the calling session is on, in the
     * given language. Addressed to this one token, not to the account.
     *
     * @throws MobileUserDeviceNotFoundException
     * @throws MobilePushNotificationsDisabledException
     */
    public function handle(int $tokenId, string $locale): void
    {
        $device = $this->showDevice->handle($tokenId);

        if ($device->expo_push_notification_token === null) {
            throw new MobilePushNotificationsDisabledException;
        }

        Notification::route('expo', ExpoPushToken::make($device->expo_push_notification_token))
            ->notify((new MobileTestPushNotification)->locale($locale));

        Log::info('mobile test push queued', ['device_id' => $device->id, 'mobile_user_id' => $device->mobile_user_id]);
    }
}
