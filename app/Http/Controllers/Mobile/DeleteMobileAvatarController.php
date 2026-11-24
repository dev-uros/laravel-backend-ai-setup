<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\DeleteMobileUserAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteMobileAvatarController extends Controller
{
    /**
     * Remove the profile picture
     *
     * 200 even when there was none.
     */
    public function __invoke(Request $request, DeleteMobileUserAvatarService $delete): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.account.avatar_removed'),
            new MobileUserResource($delete->handle($request->user())),
        );
    }
}
