<?php

namespace App\Repositories;

use App\Data\MobileUser\IndexMobileUsersData;
use App\Data\MobileUser\MobileUserProfileData;
use App\Data\MobileUser\NewMobileUserData;
use App\Enums\AppLocale;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MobileUserRepository implements MobileUserRepositoryInterface
{
    public function findById(int $id): ?MobileUser
    {
        return MobileUser::query()->find($id);
    }

    public function findByEmail(string $email): ?MobileUser
    {
        return MobileUser::query()->where('email', $email)->first();
    }

    public function findByGoogleId(string $googleId): ?MobileUser
    {
        return MobileUser::query()->where('google_id', $googleId)->first();
    }

    public function findByAppleId(string $appleId): ?MobileUser
    {
        return MobileUser::query()->where('apple_id', $appleId)->first();
    }

    public function lockForDeletion(int $id): ?MobileUser
    {
        return MobileUser::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function create(NewMobileUserData $data): MobileUser
    {
        $user = new MobileUser;

        $user->forceFill([
            'username' => $data->username,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'display_personal_name' => $data->displayPersonalName,
            'email' => $data->email,
            'google_id' => $data->googleId,
            'apple_id' => $data->appleId,
            'locale' => $data->locale,
            'avatar_path' => $data->avatarPath,
            'is_demo' => $data->isDemo,
            'last_seen_at' => now(),
        ])->save();

        return $user;
    }

    public function updateProfile(MobileUser $user, MobileUserProfileData $data): MobileUser
    {
        $user->forceFill(array_filter([
            'username' => $data->username,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'display_personal_name' => $data->displayPersonalName,
            'locale' => $data->locale,
        ], fn (string $key): bool => in_array($key, $data->present, true), ARRAY_FILTER_USE_KEY))->save();

        return $user;
    }

    public function updateLocale(MobileUser $user, AppLocale $locale): MobileUser
    {
        if ($user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return $user;
    }

    public function updateAvatarPath(MobileUser $user, ?string $path): MobileUser
    {
        $user->forceFill(['avatar_path' => $path])->save();

        return $user;
    }

    public function touchLastSeen(MobileUser $user): void
    {
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public function linkGoogleAccount(MobileUser $user, string $googleId): bool
    {
        $linked = MobileUser::query()
            ->whereKey($user->id)
            ->whereNull('google_id')
            ->update(['google_id' => $googleId, 'updated_at' => now()]) === 1;

        if ($linked) {
            $user->google_id = $googleId;
        }

        return $linked;
    }

    public function linkAppleAccount(MobileUser $user, string $appleId): bool
    {
        $linked = MobileUser::query()
            ->whereKey($user->id)
            ->whereNull('apple_id')
            ->update(['apple_id' => $appleId, 'updated_at' => now()]) === 1;

        if ($linked) {
            $user->apple_id = $appleId;
        }

        return $linked;
    }

    public function updateAppleCredentials(MobileUser $user, string $clientId, ?string $refreshToken): void
    {
        $user->forceFill(array_filter([
            'apple_client_id' => $clientId,
            'apple_refresh_token' => $refreshToken,
        ], fn (?string $value): bool => $value !== null))->save();
    }

    public function setBlockedAt(MobileUser $user, ?DateTimeInterface $blockedAt): MobileUser
    {
        $user->forceFill(['blocked_at' => $blockedAt])->save();

        return $user;
    }

    public function revokeTokens(MobileUser $user): void
    {
        $user->tokens()->delete();
    }

    public function anonymise(MobileUser $user): void
    {
        $user->forceFill([
            'username' => null,
            'first_name' => null,
            'last_name' => null,
            'display_personal_name' => false,
            'email' => null,
            'google_id' => null,
            'apple_id' => null,
            'apple_refresh_token' => null,
            'apple_client_id' => null,
            'avatar_path' => null,
        ])->save();

        $user->delete();
    }

    public function paginate(IndexMobileUsersData $data): LengthAwarePaginator
    {
        return MobileUser::query()
            ->withCount('devices')
            ->when($data->search !== null, function (Builder $query) use ($data): void {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($data->search)).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhereRaw('lower(first_name) like ?', [$term])
                    ->orWhereRaw('lower(last_name) like ?', [$term])
                    ->orWhereRaw("lower(concat_ws(' ', first_name, last_name)) like ?", [$term]));
            })
            ->when($data->blocked === true, fn (Builder $query) => $query->whereNotNull('blocked_at'))
            ->when($data->blocked === false, fn (Builder $query) => $query->whereNull('blocked_at'))
            ->latest('id')
            ->paginate($data->perPage);
    }

    public function upsertDemo(string $email, string $username): MobileUser
    {
        $user = MobileUser::query()->where('email', $email)->first() ?? new MobileUser;

        $user->forceFill([
            'email' => $email,
            'username' => $user->username ?? $username,
            'is_demo' => true,
            'blocked_at' => null,
        ])->save();

        return $user;
    }
}
