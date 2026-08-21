<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\Password;

/**
 * Issues an activation link and emails it.
 *
 * The single place that mints activation tokens, shared by account creation and
 * by a support-triggered resend, so the two can never drift apart.
 */
class SendActivationLinkService
{
    public function __construct(private readonly RecordUserLogService $recordLog) {}

    /**
     * Laravel's broker deletes any existing token for the user before issuing a
     * new one, so an earlier link stops working the moment this runs.
     */
    public function handle(User $user, ActorData $actor): void
    {
        $user->notify(
            new AccountCreatedNotification(Password::broker()->createToken($user))
        );

        /**
         * Recorded with no diff: nothing on the user row changed, but "was a
         * link ever sent, and when" is the first question support asks.
         */
        $this->recordLog->handle($user, UserLogEvent::ActivationLinkSent, $actor);
    }
}
