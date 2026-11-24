<?php

namespace App\Http\Controllers\MobileUser;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminMobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileUser\BlockMobileUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action: not a CRUD verb, and it acts on a sub-path.
 */
class BlockMobileUserController extends Controller
{
    /**
     * Block an app user
     *
     * Signs the account out of every phone and refuses it from then on --
     * signing in, asking for a code and every request with an old token answer
     * 403. Blocking a blocked account changes nothing.
     *
     * @param  int  $id  The account's id.
     */
    public function __invoke(Request $request, BlockMobileUserService $block, int $id): JsonResponse
    {
        return ApiResponse::success('Nalog je blokiran.', new AdminMobileUserResource($block->handle($id, $request->user()->id)));
    }
}
