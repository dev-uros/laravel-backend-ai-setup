<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\InternalUserDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone an internal user signed in on with the mobile app.
 *
 * Nothing here is fillable from a request: the session it is bound to and the
 * push token are set only by the repository, from values the service vouches
 * for.
 */
#[Fillable([])]
class InternalUserDevice extends Model
{
    /** @use HasFactory<InternalUserDeviceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Devices a push can reach: notifications allowed, and somebody still
     * signed in. A phone that signed out keeps its row and its token, but must
     * not light up with the previous user's notifications.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeReachable(Builder $query): void
    {
        $query->whereNotNull('expo_push_notification_token')
            ->whereNotNull('personal_access_token_id');
    }
}
