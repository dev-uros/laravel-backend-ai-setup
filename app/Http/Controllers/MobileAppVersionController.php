<?php

namespace App\Http\Controllers;

use App\Enums\DevicePlatform;
use App\Http\Resources\MobileAppVersionResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileApp\ShowMobileAppVersionService;
use Illuminate\Http\JsonResponse;

class MobileAppVersionController extends Controller
{
    /**
     * Minimum mobile app version
     *
     * The oldest version of the mobile app still allowed to run on this
     * platform, and the store page to update from. The app asks before
     * anything else -- signed in or not -- and an older app shows only an
     * "update required" screen.
     */
    public function show(string $platform, ShowMobileAppVersionService $showVersion): JsonResponse
    {
        return ApiResponse::success(
            'Podaci o verziji aplikacije.',
            new MobileAppVersionResource($showVersion->handle(DevicePlatform::from($platform))),
        );
    }
}
