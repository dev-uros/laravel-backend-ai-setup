<?php

namespace App\Http\Resources;

use App\Data\Auth\AuthenticatedUserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthenticatedUserData
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'token' => $this->token,
        ];
    }
}
