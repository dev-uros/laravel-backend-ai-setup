<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUserDevice\SendMobileUserDeviceTestPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action: not a CRUD verb, and it has a throttle of its own.
 */
class SendMobileTestPushController extends Controller
{
    /**
     * Send a test push to this device
     *
     * Queues one notification to the phone this session is on, in the app's
     * language. 409 when notifications are not switched on there.
     */
    public function __invoke(Request $request, SendMobileUserDeviceTestPushService $sendTestPush): JsonResponse
    {
        $sendTestPush->handle($request->user()->currentAccessToken()->id, app()->getLocale());

        return ApiResponse::success(__('mobile.device.test_push_sent'));
    }
}
