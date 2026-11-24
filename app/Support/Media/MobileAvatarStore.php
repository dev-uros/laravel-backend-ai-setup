<?php

namespace App\Support\Media;

use App\Models\MobileUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Writes and deletes the external app's profile pictures, re-encoded the same
 * way as the internal avatars (`UpdateUserAvatarService`): a square 512x512
 * WebP under a ULID name on the public disk.
 *
 * File plumbing, not a use case -- shared by registration, the avatar
 * endpoints and account deletion, which each decide when to call it.
 */
class MobileAvatarStore
{
    private const SIZE = 512;

    private const QUALITY = 80;

    public function __construct(private readonly ImageManager $images) {}

    /**
     * Re-encode the upload and write it; the path that was written.
     */
    public function put(UploadedFile $file): string
    {
        $encoded = $this->images
            ->read($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->toWebp(self::QUALITY);

        $path = MobileUser::AVATAR_DIRECTORY.'/'.Str::ulid().'.webp';

        Storage::disk(MobileUser::AVATAR_DISK)->put($path, (string) $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(MobileUser::AVATAR_DISK)->delete($path);
        }
    }
}
