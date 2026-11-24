<?php

namespace App\Services\MobileUser;

use App\Data\MobileUser\MobileUserProfileData;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

class UpdateMobileUserProfileService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * Change only what the request carried. The language changes here too:
     * mail and pushes follow it from the next one.
     */
    public function handle(MobileUser $user, MobileUserProfileData $data): MobileUser
    {
        return $this->mobileUsers->updateProfile($user, $data);
    }
}
