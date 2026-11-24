<?php

namespace App\Jobs;

use App\Support\Apple\AppleTokenClientInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Revokes a deleted account's Sign in with Apple authorisation -- App Review
 * requires it.
 *
 * A job rather than a call inside the deletion: the account is gone the
 * moment the transaction commits, and Apple being slow or down must neither
 * keep the person waiting nor undo that. Dispatched `afterCommit`, retried on
 * its own schedule.
 *
 * **Carries the refresh token itself**, because the row no longer holds it,
 * so the payload is encrypted (`ShouldBeEncrypted`): Redis and the Horizon
 * dashboard see ciphertext.
 */
class RevokeAppleTokenJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly int $mobileUserId,
        public readonly string $refreshToken,
        public readonly string $clientId,
    ) {}

    public function handle(AppleTokenClientInterface $apple): void
    {
        $apple->revoke($this->refreshToken, $this->clientId);

        Log::info('apple token revoked', ['mobile_user_id' => $this->mobileUserId]);
    }
}
