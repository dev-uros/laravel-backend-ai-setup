<?php

namespace App\Services\Auth;

use App\Data\Auth\ForgotPasswordData;
use App\Notifications\PasswordResetNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Password;

class ForgotPasswordService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Email a reset link, if the address belongs to an activated account.
     *
     * Returns nothing and throws nothing. The caller answers the same way either
     * way: telling an anonymous caller whether an address is registered turns
     * this endpoint into a way to harvest accounts.
     *
     * An account that has never been activated is skipped -- it already has an
     * activation link, and its owner has not proved the address is theirs. So is
     * a deactivated one.
     */
    public function handle(ForgotPasswordData $data): void
    {
        $user = $this->users->findByEmail($data->email);

        /**
         * A deactivated account is skipped along with the unknown and the
         * never-activated. It cannot be logged into anyway, and answering
         * differently here would leak the state of somebody else's account to
         * an anonymous caller.
         */
        if ($user === null || ! $user->hasVerifiedEmail() || ! $user->isActive()) {
            return;
        }

        $user->notify(new PasswordResetNotification(Password::broker()->createToken($user)));
    }
}
