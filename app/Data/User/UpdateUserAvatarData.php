<?php

namespace App\Data\User;

use App\Http\Requests\User\UpdateUserAvatarRequest;
use Illuminate\Http\UploadedFile;

/**
 * The uploaded file, still in whatever format the browser sent it.
 *
 * Nothing is read off it here: the format, the size and the dimensions have
 * already been validated by the FormRequest, and the conversion to a square
 * WebP is the service's job.
 */
final readonly class UpdateUserAvatarData
{
    public function __construct(
        public UploadedFile $avatar,
    ) {}

    /**
     * Build the DTO from a validated request.
     *
     * The plain constructor stays usable from a command or a job.
     */
    public static function fromRequest(UpdateUserAvatarRequest $request): self
    {
        /** @var UploadedFile $avatar */
        $avatar = $request->file('avatar');

        return new self(avatar: $avatar);
    }
}
