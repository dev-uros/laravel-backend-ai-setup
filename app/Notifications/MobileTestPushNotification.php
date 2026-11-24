<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoChannel;
use NotificationChannels\Expo\ExpoMessage;

/**
 * Sent to one phone from the external app's settings, so a person can check
 * that pushes reach it. In the account's language (`->locale()` at the call
 * site).
 */
class MobileTestPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [ExpoChannel::class];
    }

    public function toExpo(object $notifiable): ExpoMessage
    {
        return ExpoMessage::create(__('mobile.push.test.title'))
            ->body(__('mobile.push.test.body'))
            ->playSound()
            ->data(['type' => 'test']);
    }
}
