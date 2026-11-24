<?php

namespace App\Http\Resources;

use App\Models\MobileUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The account as its owner sees it in the external app.
 *
 * @mixin MobileUser
 */
class MobileUserResource extends JsonResource
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
            /** Show "first last" instead of the username, where a name was given. */
            'display_personal_name' => $this->display_personal_name,
            /** What to greet the person with: their name when they asked for it, else the username. */
            'display_name' => $this->displayName(),
            'email' => (string) $this->email,
            'avatar_url' => $this->avatarUrl(),
            /** The language mail and pushes are written in: `sr` or `en`. */
            'locale' => $this->locale,
            /** Whether the account can sign in with Google. */
            'has_google' => $this->google_id !== null,
            /** Whether the account can sign in with Apple -- deleting it then asks Apple for a fresh code. */
            'has_apple' => $this->apple_id !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
