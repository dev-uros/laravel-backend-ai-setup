<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Exceptions\CannotChangeAnotherUsersAvatarException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUserAvatarService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
        private readonly AuthorizeAvatarChangeService $authorize,
    ) {}

    /**
     * Take a user's avatar away and delete the file behind it.
     *
     * Removing an avatar from an account that has none is not an error: the
     * request asked for a state, the account is already in it. Nothing is
     * written and nothing is recorded -- an entry saying nothing changed would
     * bury the ones that did, the same way UpdateUserService keeps quiet when a
     * form is saved untouched.
     *
     * The caller is passed in rather than read from auth(), so this works from a
     * command or a job the same way it does from a request.
     *
     * @throws UserNotFoundException when no user has that id
     * @throws CannotChangeAnotherUsersAvatarException when a plain user aims at somebody else
     */
    public function handle(int $id, User $caller, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        $this->authorize->handle($caller, $user);

        $previous = $user->avatar_path;

        if ($previous === null) {
            return $user;
        }

        $updated = DB::transaction(function () use ($user, $previous, $actor): User {
            $updated = $this->users->updateAvatarPath($user, null);

            $this->recordLog->handle(
                $updated,
                UserLogEvent::AvatarRemoved,
                $actor,
                ['avatar_path' => $previous],
                ['avatar_path' => null],
            );

            return $updated;
        });

        /**
         * After the commit, never before: a rolled back transaction would
         * otherwise leave the row pointing at a file that no longer exists.
         */
        Storage::disk(User::AVATAR_DISK)->delete($previous);

        return $updated;
    }
}
