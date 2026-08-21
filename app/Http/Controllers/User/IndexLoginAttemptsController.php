<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginAttemptResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\IndexLoginAttemptsService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller: outside the users resource, on a sub-path.
 */
class IndexLoginAttemptsController extends Controller
{
    /**
     * A user's login attempts
     *
     * Every attempt to sign in to the account, successful or not, newest first,
     * with the address that was typed and where it came from. Paginated.
     *
     * A failed attempt says only `invalid_credentials` -- whether the password
     * was wrong or the address unknown is not recorded, for the same reason the
     * login endpoint does not say.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(IndexLoginAttemptsService $indexLoginAttempts, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Istorija prijava je uspešno učitana.',
            LoginAttemptResource::collection($indexLoginAttempts->handle($id)),
        );
    }
}
