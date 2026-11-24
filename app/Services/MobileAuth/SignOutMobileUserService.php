<?php

namespace App\Services\MobileAuth;

use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Give up the token this request arrived with -- only that one; the same
 * account stays signed in on its other phones.
 *
 * **The phone's push token goes too.** Deleting the token already unbinds the
 * device (the foreign key nulls), which stops pushes; clearing the push token
 * as well means that signing in again on this phone does not quietly resume
 * notifications until the app registers it afresh.
 */
class SignOutMobileUserService
{
    public function __construct(private readonly MobileUserDeviceRepositoryInterface $devices) {}

    public function handle(PersonalAccessToken $token): void
    {
        DB::transaction(function () use ($token): void {
            $this->devices->forgetPushTokenOfSession($token->id);

            $token->delete();
        });
    }
}
