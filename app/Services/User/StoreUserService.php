<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Data\User\StoreUserData;
use App\Enums\UserLogEvent;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SendActivationLinkService $activationLink,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Create a user and invite them to set their own password.
     *
     * The account is stored with a random password nobody ever learns: the user
     * chooses their own through the activation link in the email.
     */
    public function handle(StoreUserData $data, ActorData $actor): User
    {
        $user = DB::transaction(function () use ($data, $actor): User {
            $user = $this->users->create($data, Str::password(32));

            $this->recordLog->handle($user, UserLogEvent::Created, $actor, new: [
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'role' => $user->role,
            ]);

            return $user;
        });

        /**
         * Issued after the user row is committed, so the queue worker cannot
         * pick the email up before the user exists. If this fails the account
         * is left without a link, which the resend endpoint exists to fix.
         */
        $this->activationLink->handle($user, $actor);

        return $user;
    }
}
