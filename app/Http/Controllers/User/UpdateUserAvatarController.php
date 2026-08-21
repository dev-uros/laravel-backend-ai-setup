<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Data\User\UpdateUserAvatarData;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserAvatarRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\User\UpdateUserAvatarService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller: it takes multipart rather than JSON, acts on a
 * sub-path, and is the one write a user without the administrator role may
 * make -- on their own account only.
 */
class UpdateUserAvatarController extends Controller
{
    /**
     * Set a user's avatar
     *
     * Send the file as `multipart/form-data` under the key `avatar`. It is
     * re-encoded server side to a square 512x512 WebP, so what comes back in
     * `avatar_url` is neither the format nor the size that went up, and any
     * avatar the account had before is deleted.
     *
     * An administrator may set anybody's; anyone else only their own, and gets
     * 403 for another account.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(UpdateUserAvatarRequest $request, UpdateUserAvatarService $updateUserAvatar, int $id): JsonResponse
    {
        /** @var User $caller */
        $caller = $request->user();

        return ApiResponse::success(
            'Profilna slika je uspešno postavljena.',
            new UserResource($updateUserAvatar->handle(
                $id,
                UpdateUserAvatarData::fromRequest($request),
                $caller,
                ActorData::fromRequest($request),
            )),
        );
    }
}
