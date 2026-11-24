<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\MobileUserDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone a mobile user signed in on with the external app. The twin of
 * `InternalUserDevice`, kept in its own table so the two audiences never share
 * a push.
 */
#[Fillable([])]
class MobileUserDevice extends Model
{
    /** @use HasFactory<MobileUserDeviceFactory> */
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
     * @return BelongsTo<MobileUser, $this>
     */
    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    /**
     * Devices a push can reach: notifications allowed, and somebody still
     * signed in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeReachable(Builder $query): void
    {
        $query->whereNotNull('expo_push_notification_token')
            ->whereNotNull('personal_access_token_id');
    }
}
