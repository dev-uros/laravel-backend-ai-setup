<?php

namespace App\Services\Auth;

use App\Data\ActorData;
use App\Data\Auth\AuthenticatedUserData;
use App\Data\Auth\ResetPasswordData;
use App\Enums\UserLogEvent;
use App\Exceptions\InvalidPasswordResetTokenException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class ResetPasswordService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ConsumePasswordResetTokenService $consumeToken,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Set a new password behind a reset link and sign the user in.
     *
     * Unlike activation this leaves `email_verified_at` alone.
     *
     * @throws InvalidPasswordResetTokenException when the link is expired,
     *                                            already used, or not usable by
     *                                            this account
     */
    public function handle(ResetPasswordData $data, ActorData $actor): AuthenticatedUserData
    {
        $existing = $this->users->findByEmail($data->email);

        /**
         * Both flows draw on the same token table, so an activation link would
         * otherwise work here -- letting someone set a password while skipping
         * email verification. Checked before the broker runs, because the broker
         * spends the token whether or not the callback does anything, which
         * would burn an unverified account's activation link on a failed attempt.
         *
         * A deactivated account is refused the same way rather than with
         * AccountDeactivatedException: this runs before the token is checked, so
         * a distinct answer would tell anyone holding nothing but an email
         * address which accounts are switched off.
         */
        if ($existing === null || ! $existing->hasVerifiedEmail() || ! $existing->isActive()) {
            throw new InvalidPasswordResetTokenException;
        }

        $user = DB::transaction(function () use ($data, $actor): ?User {
            $user = $this->consumeToken->handle(
                $data->email,
                $data->token,
                $data->password,
                fn (User $user, string $password): User => $this->users->updatePassword($user, $password),
            );

            /**
             * No diff: the only field that moved is the password, and neither
             * the old nor the new value may ever reach the trail.
             */
            if ($user !== null) {
                $this->recordLog->handle($user, UserLogEvent::PasswordReset, $actor->actingAs($user->id));
            }

            return $user;
        }) ?? throw new InvalidPasswordResetTokenException;

        /**
         * A reset is how someone recovers an account they may have lost control
         * of, so every token issued before now stops working.
         */
        $user->tokens()->delete();

        return new AuthenticatedUserData($user, $user->createToken('spa')->plainTextToken);
    }
}
