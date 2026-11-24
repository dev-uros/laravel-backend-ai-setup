<?php

namespace App\Http\Resources;

use App\Models\MobileUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An external app account as the admin panel sees it: identity and state,
 * nothing the owner alone should see (no tokens, no Apple credentials).
 *
 * @mixin MobileUser
 */
class AdminMobileUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => (string) $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->displayName(),
            'email' => (string) $this->email,
            'avatar_url' => $this->avatarUrl(),
            /** `sr` or `en`: what mail and pushes go out in. */
            'locale' => $this->locale,
            /** Can sign in with Google. */
            'has_google' => $this->google_id !== null,
            /** Can sign in with Apple. */
            'has_apple' => $this->apple_id !== null,
            /** A store reviewer's account (`mobile:demo-accounts`). */
            'is_demo' => $this->is_demo,
            /** Set while the account is blocked. */
            'blocked_at' => $this->blocked_at?->toIso8601String(),
            /** The last sign-in or app launch. */
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            /** How many phones the account has signed in on; only in the list. */
            'devices_count' => $this->whenCounted('devices'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
