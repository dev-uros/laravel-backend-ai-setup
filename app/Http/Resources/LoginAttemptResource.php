<?php

namespace App\Http\Resources;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoginAttempt
 */
class LoginAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outcome' => $this->outcome,
            /**
             * What was typed, which for a failed attempt is not necessarily this
             * user's current address -- it is what the person at the keyboard
             * believed it to be.
             */
            'email' => $this->email,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
