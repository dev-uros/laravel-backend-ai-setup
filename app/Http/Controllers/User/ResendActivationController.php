<?php

namespace App\Http\Controllers\User;

use App\Data\ActorData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\User\ResendActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller because this falls outside the users resource: it is
 * not one of the CRUD verbs and it acts on a sub-path.
 */
class ResendActivationController extends Controller
{
    /**
     * Resend the activation email
     *
     * Issues a fresh activation link for a user who never received theirs and
     * emails it again. Any previously issued link stops working.
     *
     * @param  int  $id  The user's id.
     */
    public function __invoke(Request $request, ResendActivationService $resendActivation, int $id): JsonResponse
    {
        $resendActivation->handle($id, ActorData::fromRequest($request));

        return ApiResponse::success('Aktivacioni email je ponovo poslat.');
    }
}
