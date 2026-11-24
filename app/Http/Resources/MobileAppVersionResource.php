<?php

namespace App\Http\Resources;

use App\Data\MobileApp\MobileAppVersionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MobileAppVersionData
 */
class MobileAppVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The oldest app version still allowed to run, like `1.4.0`; an
             * older app must be updated before it can be used. Null: no minimum.
             */
            'minimum_version' => $this->minimumVersion,
            /** The app's page in this platform's store. Null: not set. */
            'store_url' => $this->storeUrl,
        ];
    }
}
