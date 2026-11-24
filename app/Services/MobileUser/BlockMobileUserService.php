<?php

namespace App\Services\MobileUser;

use App\Exceptions\MobileAccountNotFoundException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockMobileUserService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * Shut an account out of the app. Every token goes in the same
     * transaction -- tokens do not expire, so without that the person stays
     * signed in -- and `EnsureMobileUser` refuses a blocked account on every
     * request as a second lock. Signing in and asking for a code answer 403.
     *
     * Blocking a blocked account changes nothing and is not an error.
     *
     * @throws MobileAccountNotFoundException
     */
    public function handle(int $mobileUserId, int $actorId): MobileUser
    {
        $blocked = DB::transaction(function () use ($mobileUserId): MobileUser {
            $user = $this->mobileUsers->findById($mobileUserId) ?? throw MobileAccountNotFoundException::account();

            if ($user->isBlocked()) {
                return $user;
            }

            $this->mobileUsers->revokeTokens($user);

            return $this->mobileUsers->setBlockedAt($user, now());
        });

        Log::info('mobile user blocked', ['mobile_user_id' => $mobileUserId, 'user_id' => $actorId]);

        return $blocked;
    }
}
