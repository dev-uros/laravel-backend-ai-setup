<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\ReactivateUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller: not a CRUD verb, and it acts on a sub-path.
 */
class ReactivateUserController extends Controller
{
    /**
     * Switch an account back on
     *
     * Clears `deactivated_at`. The user logs in again to get a token; the ones
     * they held were revoked when the account was switched off.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(Request $request, ReactivateUserService $reactivateUser, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Nalog je ponovo uključen.',
            new UserResource($reactivateUser->handle($id, ActorData::fromRequest($request))),
        );
    }
}
