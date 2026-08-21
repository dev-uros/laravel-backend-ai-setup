<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `created_at` and `updated_at` are read without the null-safe operator on
     * purpose. `users` carries Laravel's nullable `timestamps()` columns, but
     * Eloquent stamps both on insert and this resource only ever renders a
     * persisted user, so neither can be null -- and `?->` would document them
     * as nullable to every client generated from the spec.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'role' => $this->role,
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
