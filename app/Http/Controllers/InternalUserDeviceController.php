<?php

namespace App\Http\Controllers;

use App\Data\InternalUserDevice\RegisterInternalUserDeviceData;
use App\Exceptions\InternalUserDeviceNotFoundException;
use App\Http\Requests\InternalUserDevice\UpdateInternalUserDeviceRequest;
use App\Http\Resources\InternalUserDeviceResource;
use App\Http\Responses\ApiResponse;
use App\Services\InternalUserDevice\RegisterInternalUserDeviceService;
use App\Services\InternalUserDevice\ShowInternalUserDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone the calling session is on. There is no id in these routes: the
 * bearer token identifies the session, and the session identifies the phone.
 */
class InternalUserDeviceController extends Controller
{
    /**
     * This session's device
     *
     * The phone this token was issued to, as the mobile app last described it,
     * and whether it can receive push notifications.
     *
     * @throws InternalUserDeviceNotFoundException when the session registered no phone (the web app)
     */
    public function show(Request $request, ShowInternalUserDeviceService $showDevice): JsonResponse
    {
        return ApiResponse::success(
            'Podaci o uređaju.',
            new InternalUserDeviceResource($showDevice->handle($request->user()->currentAccessToken()->id)),
        );
    }

    /**
     * Register this session's device
     *
     * Called by the mobile app on every launch with a stored token, and again
     * once notifications are allowed, with the Expo push token. Creates the
     * row if the session has none, which also covers a token from before the
     * app sent its device at sign-in.
     *
     * Leave `expo_push_notification_token` out to keep the stored one; send
     * null to switch notifications off on this phone.
     */
    public function update(UpdateInternalUserDeviceRequest $request, RegisterInternalUserDeviceService $registerDevice): JsonResponse
    {
        return ApiResponse::success(
            'Uređaj je sačuvan.',
            new InternalUserDeviceResource($registerDevice->handle(
                $request->user()->id,
                $request->user()->currentAccessToken()->id,
                RegisterInternalUserDeviceData::fromRequest($request),
            )),
        );
    }
}
