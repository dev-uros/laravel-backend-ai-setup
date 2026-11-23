<?php

namespace App\Http\Controllers\InternalUserDevice;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\InternalUserDevice\SendInternalUserDeviceTestPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller: not a CRUD verb, and it has a throttle of its own.
 */
class SendTestPushController extends Controller
{
    /**
     * Send a test push to this device
     *
     * Queues one notification to the phone this session is on -- only that
     * one, not the account's other phones. 409 when notifications are not
     * switched on there.
     */
    public function __invoke(Request $request, SendInternalUserDeviceTestPushService $sendTestPush): JsonResponse
    {
        $sendTestPush->handle($request->user()->currentAccessToken()->id);

        return ApiResponse::success('Probno obaveštenje je poslato.');
    }
}
