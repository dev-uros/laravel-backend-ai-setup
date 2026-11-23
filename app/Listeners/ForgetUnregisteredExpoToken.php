<?php

namespace App\Listeners;

use App\Repositories\Contracts\InternalUserDeviceRepositoryInterface;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Expo\Events\ExpoNotificationFailed;

/**
 * Expo answers `DeviceNotRegistered` for a phone that uninstalled the app or
 * revoked notifications. The token is dead from then on, and keeping it would
 * cost a slot in every later push.
 *
 * Only that error. The others -- a message too large, a rate limit, bad
 * credentials -- are about the sender, and dropping a working token over them
 * would quietly unsubscribe somebody who did nothing wrong.
 */
class ForgetUnregisteredExpoToken
{
    public function __construct(private readonly InternalUserDeviceRepositoryInterface $devices) {}

    public function handle(ExpoNotificationFailed $event): void
    {
        if (! $event->error->type->isDeviceNotRegistered()) {
            return;
        }

        if ($this->devices->forgetPushTokens([$event->error->token->asString()]) > 0) {
            Log::info('expo token forgotten', ['reason' => 'device_not_registered']);
        }
    }
}
