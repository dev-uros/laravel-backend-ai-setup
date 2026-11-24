<?php

namespace App\Http\Controllers;

use App\Data\MobileUser\IndexMobileUsersData;
use App\Http\Requests\MobileUser\IndexMobileUsersRequest;
use App\Http\Resources\AdminMobileUserResource;
use App\Http\Resources\MobileUserDeviceResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\IndexMobileUsersService;
use App\Services\MobileUser\ShowMobileUserService;
use App\Services\MobileUserDevice\IndexMobileUserDevicesService;
use Illuminate\Http\JsonResponse;

/**
 * The external mobile app's accounts, as the admin panel sees them. Reading
 * only; blocking has its own controllers.
 */
class MobileUserController extends Controller
{
    /**
     * List app users
     *
     * Newest first, 25 a page. `search` matches part of the username, first or
     * last name, or address; `blocked` narrows to blocked (`1`) or active (`0`)
     * accounts. Deleted accounts are not listed.
     */
    public function index(IndexMobileUsersRequest $request, IndexMobileUsersService $indexUsers): JsonResponse
    {
        return ApiResponse::success(
            'Korisnici aplikacije.',
            AdminMobileUserResource::collection($indexUsers->handle(IndexMobileUsersData::fromRequest($request))),
        );
    }

    /**
     * Show an app user
     *
     * @param  int  $id  The account's id.
     */
    public function show(ShowMobileUserService $showUser, int $id): JsonResponse
    {
        return ApiResponse::success('Korisnik aplikacije.', new AdminMobileUserResource($showUser->handle($id)));
    }

    /**
     * An app user's devices
     *
     * Every phone the account has signed in on, most recently seen first. Not
     * paginated.
     *
     * @param  int  $id  The account's id.
     */
    public function devices(IndexMobileUserDevicesService $indexDevices, int $id): JsonResponse
    {
        return ApiResponse::success('Uređaji korisnika.', MobileUserDeviceResource::collection($indexDevices->handle($id)));
    }
}
