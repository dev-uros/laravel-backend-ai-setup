<?php

namespace App\Repositories\Contracts;

use App\Data\MobileUser\IndexMobileUsersData;
use App\Data\MobileUser\MobileUserProfileData;
use App\Data\MobileUser\NewMobileUserData;
use App\Enums\AppLocale;
use App\Models\MobileUser;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MobileUserRepositoryInterface
{
    /** A live (not deleted) account. */
    public function findById(int $id): ?MobileUser;

    /** By address, which every caller passes lower-cased. */
    public function findByEmail(string $email): ?MobileUser;

    public function findByGoogleId(string $googleId): ?MobileUser;

    public function findByAppleId(string $appleId): ?MobileUser;

    /**
     * The row locked for update, so two deletions cannot both empty it.
     */
    public function lockForDeletion(int $id): ?MobileUser;

    public function create(NewMobileUserData $data): MobileUser;

    public function updateProfile(MobileUser $user, MobileUserProfileData $data): MobileUser;

    public function updateLocale(MobileUser $user, AppLocale $locale): MobileUser;

    public function updateAvatarPath(MobileUser $user, ?string $path): MobileUser;

    public function touchLastSeen(MobileUser $user): void;

    /**
     * Link a Google account -- only if the row has none yet. Conditional, so
     * two sign-ins racing to link cannot overwrite each other; false when the
     * other one won.
     */
    public function linkGoogleAccount(MobileUser $user, string $googleId): bool;

    /** The same, for Apple. */
    public function linkAppleAccount(MobileUser $user, string $appleId): bool;

    /**
     * Remember the bundle ID the account signs in with Apple under, and the
     * refresh token when there is a new one (null keeps the stored one).
     */
    public function updateAppleCredentials(MobileUser $user, string $clientId, ?string $refreshToken): void;

    public function setBlockedAt(MobileUser $user, ?DateTimeInterface $blockedAt): MobileUser;

    /** Delete every Sanctum token the account holds. */
    public function revokeTokens(MobileUser $user): void;

    /**
     * Clear every personal column and soft delete the row.
     */
    public function anonymise(MobileUser $user): void;

    /**
     * For the admin list: newest first, optionally narrowed by a search over
     * handle, names and address, and by state.
     *
     * @return LengthAwarePaginator<int, MobileUser>
     */
    public function paginate(IndexMobileUsersData $data): LengthAwarePaginator;

    /**
     * Create the demo account for an address, or bring an existing one back
     * to a usable state (not blocked, marked as demo).
     */
    public function upsertDemo(string $email, string $username): MobileUser;
}
