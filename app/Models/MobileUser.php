<?php

namespace App\Models;

use App\Enums\AppLocale;
use Database\Factories\MobileUserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\Expo\ExpoPushToken;

/**
 * Somebody who uses the external mobile app -- a customer, not one of the
 * people who run the system (`User`).
 *
 * **A second population with its own tokens.** Sanctum resolves a token to
 * whichever model minted it, so the same `auth:sanctum` guard answers for both.
 * That is why every route group states its audience with `EnsureInternalUser`
 * or `EnsureMobileUser`; `AudienceSeparationTest` fails if one does not.
 *
 * Nothing is fillable: every column is written by a named repository method,
 * so no request body can reach `is_demo` or `blocked_at`.
 *
 * **A deleted account is kept and emptied**: `deleted_at` is set and every
 * personal column cleared (`MobileUserRepository::anonymise`), so whatever a
 * project later hangs off the row keeps its owner while describing nobody.
 */
#[Fillable([])]
#[Hidden(['apple_refresh_token'])]
class MobileUser extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<MobileUserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** What the token a sign-in mints is called in Sanctum's table. */
    public const TOKEN_NAME = 'mobile';

    /**
     * The `public` disk, for the reason internal avatars use it: the app puts
     * the URL in an image tag, which carries no bearer token. ULID filenames.
     */
    public const AVATAR_DISK = 'public';

    public const AVATAR_DIRECTORY = 'mobile-users/avatars';

    /**
     * The database defaults, mirrored, so a model that was just created and
     * never read back does not carry nulls.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_demo' => false,
        'display_personal_name' => false,
        'locale' => 'sr',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => AppLocale::class,
            'display_personal_name' => 'boolean',
            'is_demo' => 'boolean',
            'blocked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'apple_refresh_token' => 'encrypted',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * The name to show: "first last" when the person asked for it and gave
     * one, otherwise the handle.
     */
    public function displayName(): string
    {
        $personal = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $this->display_personal_name && $personal !== '' ? $personal : (string) $this->username;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk(self::AVATAR_DISK)->url($this->avatar_path);
    }

    /**
     * Mail and pushes go out in the language the person last used the app in.
     * Through `HasLocalePreference`, so Laravel applies it to every
     * notification sent to the account without a `locale()` at the call site.
     */
    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    /**
     * @return HasMany<MobileUserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(MobileUserDevice::class);
    }

    /**
     * Every phone that allows notifications and is still signed in.
     *
     * @return array<int, ExpoPushToken>
     */
    public function routeNotificationForExpo(): array
    {
        return $this->devices()
            ->reachable()
            ->pluck('expo_push_notification_token')
            ->map(fn (string $token): ExpoPushToken => ExpoPushToken::make($token))
            ->all();
    }
}
