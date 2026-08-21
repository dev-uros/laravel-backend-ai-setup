<?php

namespace App\Services\Auth;

use App\Data\ActorData;
use App\Data\Auth\ActivateAccountData;
use App\Data\Auth\AuthenticatedUserData;
use App\Enums\UserLogEvent;
use App\Exceptions\InvalidActivationTokenException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class ActivateAccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ConsumePasswordResetTokenService $consumeToken,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Set the password behind an activation link and sign the user in.
     *
     * @throws InvalidActivationTokenException when the link is expired, already
     *                                         used, or not this user's
     */
    public function handle(ActivateAccountData $data, ActorData $actor): AuthenticatedUserData
    {
        /**
         * Checked before the broker runs, because the broker spends the token
         * whether or not the callback does anything. Refused with the token
         * exception rather than AccountDeactivatedException: nothing has been
         * verified yet at this point, so a distinct answer would tell anyone
         * holding an email address which accounts are switched off.
         */
        $existing = $this->users->findByEmail($data->email);

        if ($existing !== null && ! $existing->isActive()) {
            throw new InvalidActivationTokenException;
        }

        $user = DB::transaction(function () use ($data, $actor): ?User {
            $user = $this->consumeToken->handle(
                $data->email,
                $data->token,
                $data->password,
                fn (User $user, string $password): User => $this->users->activate($user, $password),
            );

            /**
             * The actor is the account itself: nobody was signed in when the
             * request arrived, and its id is only known once the token has been
             * verified. The address the link was used from is kept either way.
             */
            if ($user !== null) {
                $this->recordLog->handle(
                    $user,
                    UserLogEvent::Activated,
                    $actor->actingAs($user->id),
                    ['email_verified_at' => null],
                    ['email_verified_at' => $user->email_verified_at],
                );
            }

            return $user;
        }) ?? throw new InvalidActivationTokenException;

        return new AuthenticatedUserData($user, $user->createToken('spa')->plainTextToken);
    }
}
