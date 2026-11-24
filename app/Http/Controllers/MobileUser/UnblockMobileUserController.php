<?php

namespace App\Http\Controllers\MobileUser;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminMobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\UnblockMobileUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnblockMobileUserController extends Controller
{
    /**
     * Unblock an app user
     *
     * The account may sign in again; tokens revoked by the block stay revoked.
     *
     * @param  int  $id  The account's id.
     */
    public function __invoke(Request $request, UnblockMobileUserService $unblock, int $id): JsonResponse
    {
        return ApiResponse::success('Nalog je odblokiran.', new AdminMobileUserResource($unblock->handle($id, $request->user()->id)));
    }
}
