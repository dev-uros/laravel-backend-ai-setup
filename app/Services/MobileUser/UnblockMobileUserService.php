<?php

namespace App\Services\MobileUser;

use App\Exceptions\MobileAccountNotFoundException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class UnblockMobileUserService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * Let an account back in. Revoked tokens are not restored: the person signs
     * in again.
     *
     * @throws MobileAccountNotFoundException
     */
    public function handle(int $mobileUserId, int $actorId): MobileUser
    {
        $user = $this->mobileUsers->findById($mobileUserId) ?? throw MobileAccountNotFoundException::account();

        if (! $user->isBlocked()) {
            return $user;
        }

        $user = $this->mobileUsers->setBlockedAt($user, null);

        Log::info('mobile user unblocked', ['mobile_user_id' => $mobileUserId, 'user_id' => $actorId]);

        return $user;
    }
}
