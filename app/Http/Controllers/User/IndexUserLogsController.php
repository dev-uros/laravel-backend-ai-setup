<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLogResource;
use App\Http\Responses\ApiResponse;
use App\Services\UserLog\IndexUserLogsService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller: outside the users resource, on a sub-path.
 */
class IndexUserLogsController extends Controller
{
    /**
     * A user's audit trail
     *
     * Every recorded change to the account, newest first, with who made it and
     * which fields moved. Paginated, so `links` and `meta` sit beside `message`.
     *
     * An entry with no actor was made by the system -- a seeder, a console
     * command, the scheduler.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(IndexUserLogsService $indexUserLogs, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Istorija izmena je uspešno učitana.',
            UserLogResource::collection($indexUserLogs->handle($id)),
        );
    }
}
