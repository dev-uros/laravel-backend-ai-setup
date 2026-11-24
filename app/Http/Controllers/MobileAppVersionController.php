<?php

namespace App\Http\Controllers;

use App\Enums\DevicePlatform;
use App\Enums\MobileApp;
use App\Http\Resources\MobileAppVersionResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileApp\ShowMobileAppVersionService;
use Illuminate\Http\JsonResponse;

class MobileAppVersionController extends Controller
{
    /**
     * Minimum mobile app version
     *
     * The oldest version of one of the project's mobile apps -- `internal` or
     * `external` -- still allowed to run on this platform, and the store page
     * to update from. The app asks before anything else -- signed in or not --
     * and an older app shows only an "update required" screen.
     */
    public function show(string $app, string $platform, ShowMobileAppVersionService $showVersion): JsonResponse
    {
        return ApiResponse::success(
            'Podaci o verziji aplikacije.',
            new MobileAppVersionResource($showVersion->handle(MobileApp::from($app), DevicePlatform::from($platform))),
        );
    }
}
