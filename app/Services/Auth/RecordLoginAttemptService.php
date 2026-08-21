<?php

namespace App\Services\Auth;

use App\Data\ActorData;
use App\Enums\LoginOutcome;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Repositories\Contracts\LoginAttemptRepositoryInterface;

/**
 * The one way an attempt gets into `login_attempts`.
 *
 * Deliberately never handed a password, not even to ignore it: a service that
 * cannot see one cannot leak one.
 */
class RecordLoginAttemptService
{
    public function __construct(private readonly LoginAttemptRepositoryInterface $attempts) {}

    /**
     * Record one attempt.
     *
     * Called on every path out of LoginService, successful or not, and always
     * with the same amount of work -- one insert. A failure that skipped this,
     * or did more of it for a known address than an unknown one, would put back
     * the timing difference TIMING_EQUALISER exists to remove.
     */
    public function handle(?User $user, string $email, LoginOutcome $outcome, ActorData $actor): LoginAttempt
    {
        return $this->attempts->record($user, $email, $outcome, $actor);
    }
}
