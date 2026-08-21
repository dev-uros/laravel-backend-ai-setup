<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'surname', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Where avatars are kept.
     *
     * The `public` disk, so nginx serves the file straight off the symlink in
     * `public/storage` and the SPA can put the URL in an `<img>` tag -- an image
     * request carries no bearer token, so anything behind auth:sanctum could not
     * be rendered without proxying every avatar through the client. The
     * filename is a ULID, so a URL is not guessable from a user's id or email.
     */
    public const AVATAR_DISK = 'public';

    public const AVATAR_DIRECTORY = 'avatars';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Whether the account may be used at all.
     *
     * A deactivated user keeps their row, their role and their history; they
     * simply cannot get past the front door. See EnsureAccountIsActive.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * The avatar as the frontend needs it, or null when there is none.
     *
     * A real return type rather than an Eloquent accessor: this is what
     * `UserResource` renders, and it is what Scramble reads to document the
     * field as a nullable string.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk(self::AVATAR_DISK)->url($this->avatar_path);
    }
}
