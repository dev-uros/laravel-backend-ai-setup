<?php

namespace App\Models;

use App\Enums\LoginOutcome;
use Database\Factories\LoginAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to sign in, successful or not.
 *
 * Append-only, like the audit trail, so UPDATED_AT is switched off. Unlike the
 * audit trail this is pruned: it answers "what has been happening lately", and
 * an attempt from two years ago answers nothing.
 */
#[Fillable(['user_id', 'email', 'outcome', 'ip_address', 'user_agent'])]
class LoginAttempt extends Model
{
    /** @use HasFactory<LoginAttemptFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * How long an attempt is kept.
     */
    public const RETENTION_DAYS = 180;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => LoginOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Which rows `model:prune` deletes.
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * The account that was signed in to, or null when the address matched
     * nobody.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
