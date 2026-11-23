<?php

namespace App\Services\InternalUserDevice;

use App\Exceptions\InternalUserDeviceNotFoundException;
use App\Exceptions\PushNotificationsDisabledException;
use App\Notifications\TestPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Expo\ExpoPushToken;

class SendInternalUserDeviceTestPushService
{
    public function __construct(private readonly ShowInternalUserDeviceService $showDevice) {}

    /**
     * Send a test notification to the phone the calling session is on, so a
     * person can check that pushes arrive without waiting for a real one.
     *
     * Addressed to this one token, not to the user: their other phones should
     * not buzz because they pressed a button on this one.
     *
     * @throws InternalUserDeviceNotFoundException when the session registered no phone
     * @throws PushNotificationsDisabledException when the phone holds no push token
     */
    public function handle(int $tokenId): void
    {
        $device = $this->showDevice->handle($tokenId);

        if ($device->expo_push_notification_token === null) {
            throw new PushNotificationsDisabledException;
        }

        Notification::route('expo', ExpoPushToken::make($device->expo_push_notification_token))
            ->notify(new TestPushNotification);

        Log::info('test push queued', ['device_id' => $device->id, 'user_id' => $device->user_id]);
    }
}
