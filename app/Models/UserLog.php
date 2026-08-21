<?php

namespace App\Models;

use App\Enums\UserLogEvent;
use Database\Factories\UserLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the audit trail of a user account.
 *
 * Append-only: a row is written inside the transaction that made the change and
 * is never updated or deleted, which is why UPDATED_AT is switched off.
 */
#[Fillable(['user_id', 'actor_id', 'event', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class UserLog extends Model
{
    /** @use HasFactory<UserLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * How long an entry is kept: three years, against six months for a login
     * attempt.
     *
     * The two answer different questions. "Who has been signing in lately" goes
     * stale in weeks; "who changed this address, and when" is asked long after
     * the fact -- during a dispute, an audit, or somebody leaving. Keeping the
     * trail for years is the whole reason it exists, so this number is a policy
     * decision rather than a technical one: raise it if the business says so.
     */
    public const RETENTION_DAYS = 1095;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => UserLogEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
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
     * The account the entry is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who made the change, or null when the system did.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
