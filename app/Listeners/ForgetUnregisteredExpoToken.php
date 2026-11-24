<?php

namespace App\Listeners;

use App\Repositories\Contracts\InternalUserDeviceRepositoryInterface;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
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
    public function __construct(
        private readonly InternalUserDeviceRepositoryInterface $devices,
        private readonly MobileUserDeviceRepositoryInterface $mobileDevices,
    ) {}

    public function handle(ExpoNotificationFailed $event): void
    {
        if (! $event->error->type->isDeviceNotRegistered()) {
            return;
        }

        $token = [$event->error->token->asString()];

        /** Either app's phone: one token is only ever in one of the two tables. */
        if ($this->devices->forgetPushTokens($token) + $this->mobileDevices->forgetPushTokens($token) > 0) {
            Log::info('expo token forgotten', ['reason' => 'device_not_registered']);
        }
    }
}
