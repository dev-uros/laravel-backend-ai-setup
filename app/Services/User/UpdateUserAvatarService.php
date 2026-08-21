<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Data\User\UpdateUserAvatarData;
use App\Enums\UserLogEvent;
use App\Exceptions\CannotChangeAnotherUsersAvatarException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class UpdateUserAvatarService
{
    /**
     * Avatars are stored square, at the largest size any screen shows one.
     *
     * The uploaded file is never kept: a photo off a phone is several megabytes
     * of pixels nothing renders, and every list row would pay for it. What lands
     * on the disk is a few dozen kilobytes.
     */
    private const SIZE = 512;

    /**
     * WebP quality. 80 is the point where the next percent costs more bytes
     * than it returns in a picture this size.
     */
    private const QUALITY = 80;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
        private readonly AuthorizeAvatarChangeService $authorize,
        private readonly ImageManager $images,
    ) {}

    /**
     * Set a user's avatar, replacing whatever they had.
     *
     * The caller is passed in rather than read from auth(), so this works from a
     * command or a job the same way it does from a request.
     *
     * @throws UserNotFoundException when no user has that id
     * @throws CannotChangeAnotherUsersAvatarException when a plain user aims at somebody else
     */
    public function handle(int $id, UpdateUserAvatarData $data, User $caller, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        $this->authorize->handle($caller, $user);

        $previous = $user->avatar_path;
        $path = $this->store($data->avatar);

        try {
            $updated = DB::transaction(function () use ($user, $path, $previous, $actor): User {
                $updated = $this->users->updateAvatarPath($user, $path);

                $this->recordLog->handle(
                    $updated,
                    UserLogEvent::AvatarUpdated,
                    $actor,
                    ['avatar_path' => $previous],
                    ['avatar_path' => $path],
                );

                return $updated;
            });
        } catch (Throwable $exception) {
            /**
             * The file is written before the row, so a failed transaction would
             * otherwise leave an image on the disk that nothing points at and
             * nothing will ever collect.
             */
            Storage::disk(User::AVATAR_DISK)->delete($path);

            throw $exception;
        }

        /**
         * Only once the new path is committed. Deleting first would leave the
         * user with a broken image if the write then failed.
         */
        if ($previous !== null) {
            Storage::disk(User::AVATAR_DISK)->delete($previous);
        }

        return $updated;
    }

    /**
     * Re-encode the upload as a square WebP and put it on the disk, returning
     * the path that was written.
     *
     * `cover()` scales and centre-crops in one step, so a portrait photo comes
     * out as the middle of itself rather than squashed. The filename is a ULID:
     * a name derived from the user would make one avatar's URL guessable from
     * another's, and reusing a name would be served stale from every cache in
     * between.
     */
    private function store(UploadedFile $file): string
    {
        $encoded = $this->images
            ->read($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->toWebp(self::QUALITY);

        $path = User::AVATAR_DIRECTORY.'/'.Str::ulid().'.webp';

        Storage::disk(User::AVATAR_DISK)->put($path, (string) $encoded);

        return $path;
    }
}
