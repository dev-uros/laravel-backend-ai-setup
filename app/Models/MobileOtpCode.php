<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A six-digit code somebody has been mailed, to sign in or to register with
 * the external app.
 *
 * Nothing is fillable and the hash is hidden: this is the whole of the app's
 * authentication, and no request body should reach either column.
 */
#[Fillable([])]
#[Hidden(['code_hash'])]
class MobileOtpCode extends Model
{
    use MassPrunable;

    /** How long a code is good for. */
    public const VALID_FOR_MINUTES = 10;

    /** How many guesses a code survives; the last one destroys it. */
    public const MAX_ATTEMPTS = 5;

    /** How long after a code is mailed another may be asked for. */
    public const RESEND_AFTER_SECONDS = 30;

    /**
     * Codes nobody came back for. A used or replaced code is deleted at once;
     * an abandoned one would otherwise keep somebody's address for ever.
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
