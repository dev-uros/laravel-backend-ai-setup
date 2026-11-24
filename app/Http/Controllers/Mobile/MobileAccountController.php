<?php

namespace App\Http\Controllers\Mobile;

use App\Data\MobileUser\MobileUserProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobileUser\DeleteMobileUserRequest;
use App\Http\Requests\MobileUser\UpdateMobileUserProfileRequest;
use App\Http\Resources\MobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\DeleteMobileUserService;
use App\Services\MobileUser\ShowMobileUserService;
use App\Services\MobileUser\UpdateMobileUserProfileService;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's own account. No id in the path: the token is the
 * account.
 */
class MobileAccountController extends Controller
{
    /**
     * The signed-in account
     *
     * Read fresh, so the app can restore its session from a stored token.
     */
    #[IgnoreResponse(404)]
    public function show(Request $request, ShowMobileUserService $showUser): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.account.profile'),
            new MobileUserResource($showUser->handle($request->user()->id)),
        );
    }

    /**
     * Update the profile
     *
     * A partial update: username, first and last name, whether to show the
     * name, and the language. Null clears a name.
     */
    public function update(UpdateMobileUserProfileRequest $request, UpdateMobileUserProfileService $update): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.account.updated'),
            new MobileUserResource($update->handle($request->user(), MobileUserProfileData::fromRequest($request))),
        );
    }

    /**
     * Delete the account
     *
     * Final: personal data is erased, every phone is signed out. For an account
     * linked to Apple, send `apple_authorization_code` from a fresh Sign in
     * with Apple so the authorisation can be revoked; without it the deletion
     * still goes ahead.
     */
    #[IgnoreResponse(404)]
    public function destroy(DeleteMobileUserRequest $request, DeleteMobileUserService $delete): JsonResponse
    {
        $delete->handle($request->user()->id, $request->validated('apple_authorization_code'));

        return ApiResponse::success(__('mobile.account.deleted'));
    }
}
