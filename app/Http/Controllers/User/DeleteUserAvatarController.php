<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\User\DeleteUserAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller, paired with UpdateUserAvatarController.
 */
class DeleteUserAvatarController extends Controller
{
    /**
     * Remove a user's avatar
     *
     * Deletes the file and clears `avatar_url`. Removing an avatar from an
     * account that has none answers 200 as well -- the account is already in the
     * state that was asked for.
     *
     * An administrator may remove anybody's; anyone else only their own, and
     * gets 403 for another account.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(Request $request, DeleteUserAvatarService $deleteUserAvatar, int $id): JsonResponse
    {
        /** @var User $caller */
        $caller = $request->user();

        return ApiResponse::success(
            'Profilna slika je uspešno uklonjena.',
            new UserResource($deleteUserAvatar->handle($id, $caller, ActorData::fromRequest($request))),
        );
    }
}
