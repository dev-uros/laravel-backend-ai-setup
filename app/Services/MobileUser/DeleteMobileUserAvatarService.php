<?php

namespace App\Services\MobileUser;

use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Media\MobileAvatarStore;

class DeleteMobileUserAvatarService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly MobileAvatarStore $avatars,
    ) {}

    /**
     * Take the caller's picture away. Removing one they do not have is not an
     * error -- the account is already in the state asked for.
     */
    public function handle(MobileUser $user): MobileUser
    {
        $previous = $user->avatar_path;

        if ($previous === null) {
            return $user;
        }

        $updated = $this->mobileUsers->updateAvatarPath($user, null);

        /** After the row, never before: a failed save would point at a missing file. */
        $this->avatars->delete($previous);

        return $updated;
    }
}
