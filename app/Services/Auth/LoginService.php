<?php

namespace App\Services\Auth;

use App\Data\ActorData;
use App\Data\Auth\AuthenticatedUserData;
use App\Data\Auth\LoginData;
use App\Enums\LoginOutcome;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\InvalidCredentialsException;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    /**
     * A valid bcrypt hash of a value nobody can guess, used only so that an
     * unknown email spends the same time hashing as a known one. Without it a
     * missing user answers measurably faster than a wrong password, which turns
     * this endpoint into a way to discover which addresses have accounts.
     */
    private const TIMING_EQUALISER = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordLoginAttemptService $recordAttempt,
    ) {}

    /**
     * Exchange an email and password for a bearer token.
     *
     * @throws InvalidCredentialsException when the pair does not identify a user
     */
    public function handle(LoginData $data, ActorData $actor): AuthenticatedUserData
    {
        $user = $this->users->findByEmail($data->email);

        if ($user === null) {
            Hash::check($data->password, self::TIMING_EQUALISER);

            /**
             * Recorded with no user attached. An address nobody uses is exactly
             * what a sweep looks like, and the row is the only place that shows.
             */
            $this->recordAttempt->handle(null, $data->email, LoginOutcome::InvalidCredentials, $actor);

            throw new InvalidCredentialsException;
        }

        if (! Hash::check($data->password, $user->password)) {
            /**
             * The same single insert as the branch above, so the two failures
             * still cost the same. Recorded as the same outcome, too: keeping
             * "no such user" and "wrong password" apart in a table would write
             * down the very distinction this endpoint refuses to answer.
             */
            $this->recordAttempt->handle($user, $data->email, LoginOutcome::InvalidCredentials, $actor);

            throw new InvalidCredentialsException;
        }

        /**
         * Checked only once the password is known to be right. Answering
         * "deactivated" before that would tell anyone which addresses have
         * accounts and which of those are switched off, which is exactly what
         * TIMING_EQUALISER above exists to prevent.
         */
        if (! $user->isActive()) {
            $this->recordAttempt->handle($user, $data->email, LoginOutcome::AccountDeactivated, $actor);

            throw new AccountDeactivatedException;
        }

        $this->recordAttempt->handle($user, $data->email, LoginOutcome::Succeeded, $actor);

        return new AuthenticatedUserData($user, $user->createToken('spa')->plainTextToken);
    }
}
