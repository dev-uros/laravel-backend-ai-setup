<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoChannel;
use NotificationChannels\Expo\ExpoMessage;

/**
 * "Obaveštenja rade." -- sent to one phone from the profile screen of the
 * mobile app, so a person can check that pushes reach it.
 *
 * Queued like every notification here: the request should not wait on Expo.
 */
class TestPushNotification extends Notification implements ShouldQueue
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
        return ExpoMessage::create('Probno obaveštenje')
            ->body('Obaveštenja rade na ovom uređaju.')
            ->playSound()
            ->data(['type' => 'test']);
    }
}
