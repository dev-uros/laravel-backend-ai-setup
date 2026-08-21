<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Data\User\UpdateUserRoleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\UpdateUserRoleService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller: a role change is not one of the CRUD verbs, it acts
 * on a sub-path, and it is guarded by rules the profile update knows nothing
 * about.
 */
class UpdateUserRoleController extends Controller
{
    /**
     * Change a user's role
     *
     * Takes effect on the user's next request -- their tokens keep working, they
     * simply may do less, or more, with them.
     *
     * An administrator cannot change their own role. That is what stops the last
     * one from demoting themselves and leaving nobody able to promote anyone.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(UpdateUserRoleRequest $request, UpdateUserRoleService $updateUserRole, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Uloga korisnika je uspešno promenjena.',
            new UserResource($updateUserRole->handle(
                $id,
                UpdateUserRoleData::fromRequest($request),
                ActorData::fromRequest($request),
            )),
        );
    }
}
