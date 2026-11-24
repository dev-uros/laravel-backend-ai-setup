<?php

namespace App\Http\Controllers\Mobile;

use App\Data\MobileUserDevice\RegisterMobileUserDeviceData;
use App\Exceptions\MobileUserDeviceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobileUserDevice\UpdateMobileUserDeviceRequest;
use App\Http\Resources\MobileUserDeviceResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUserDevice\RegisterMobileUserDeviceService;
use App\Services\MobileUserDevice\ShowMobileUserDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone the calling session is on. No id in these routes: the bearer
 * token identifies the session, and the session identifies the phone.
 */
class MobileUserDeviceController extends Controller
{
    /**
     * This session's device
     *
     * @throws MobileUserDeviceNotFoundException when the session registered no phone
     */
    public function show(Request $request, ShowMobileUserDeviceService $showDevice): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.device.show'),
            new MobileUserDeviceResource($showDevice->handle($request->user()->currentAccessToken()->id)),
        );
    }

    /**
     * Register this session's device
     *
     * Called by the app on every launch with a stored token, and again once
     * notifications are allowed, with the Expo push token. Leave
     * `expo_push_notification_token` out to keep the stored one; send null to
     * switch notifications off on this phone.
     */
    public function update(UpdateMobileUserDeviceRequest $request, RegisterMobileUserDeviceService $registerDevice): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.device.saved'),
            new MobileUserDeviceResource($registerDevice->handle(
                $request->user()->id,
                $request->user()->currentAccessToken()->id,
                RegisterMobileUserDeviceData::fromRequest($request),
            )),
        );
    }
}
