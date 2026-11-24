<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileUser\UpdateMobileUserAvatarRequest;
use App\Http\Resources\MobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\UpdateMobileUserAvatarService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action: POST because the body is multipart/form-data, which PHP only
 * parses on a POST.
 */
class UpdateMobileAvatarController extends Controller
{
    /**
     * Set the profile picture
     */
    public function __invoke(UpdateMobileUserAvatarRequest $request, UpdateMobileUserAvatarService $update): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.account.avatar_updated'),
            new MobileUserResource($update->handle($request->user(), $request->file('avatar'))),
        );
    }
}
