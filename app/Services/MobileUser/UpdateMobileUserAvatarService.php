<?php

namespace App\Services\MobileUser;

use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Media\MobileAvatarStore;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateMobileUserAvatarService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly MobileAvatarStore $avatars,
    ) {}

    /**
     * Set the caller's picture, replacing whatever they had. The same order as
     * the internal avatars: write the new file first, delete it again if the
     * row cannot be saved, delete the old one only after.
     */
    public function handle(MobileUser $user, UploadedFile $avatar): MobileUser
    {
        $previous = $user->avatar_path;
        $path = $this->avatars->put($avatar);

        try {
            $updated = $this->mobileUsers->updateAvatarPath($user, $path);
        } catch (Throwable $exception) {
            $this->avatars->delete($path);

            throw $exception;
        }

        $this->avatars->delete($previous);

        return $updated;
    }
}
