<?php

namespace App\Http\Resources;

use App\Models\InternalUserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InternalUserDevice
 */
class InternalUserDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The push token itself is not echoed: the phone already has it, and
     * whether one is stored is all the app needs to draw its switch.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'name' => $this->name,
            'platform' => $this->platform,
            'operating_system' => $this->operating_system,
            'manufacturer' => $this->manufacturer,
            'os_version' => $this->os_version,
            /** Whether this phone can receive push notifications. */
            'push_notifications_enabled' => $this->expo_push_notification_token !== null,
            'last_seen_at' => $this->last_seen_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
