<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\DeactivateUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller: not a CRUD verb, and it acts on a sub-path.
 */
class DeactivateUserController extends Controller
{
    /**
     * Switch an account off
     *
     * The user keeps their row and their history but cannot use the API: every
     * token they hold is revoked, and logging in again is refused while the
     * account is off. This is not a delete -- the account still appears in the
     * users list, with `deactivated_at` set.
     *
     * An administrator cannot switch off their own account. That is what stops
     * the last one from locking everybody out of the panel.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(Request $request, DeactivateUserService $deactivateUser, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Nalog je isključen.',
            new UserResource($deactivateUser->handle($id, ActorData::fromRequest($request))),
        );
    }
}
