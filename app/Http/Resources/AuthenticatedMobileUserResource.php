<?php

namespace App\Http\Resources;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthenticatedMobileUserData
 */
class AuthenticatedMobileUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new MobileUserResource($this->user),
            /** Send as `Authorization: Bearer {token}`. It does not expire; signing out revokes it. */
            'token' => $this->token,
        ];
    }
}
