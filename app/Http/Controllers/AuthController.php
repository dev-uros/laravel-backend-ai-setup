<?php

namespace App\Http\Controllers;

use App\Data\ActorData;
use App\Data\Auth\ActivateAccountData;
use App\Data\Auth\ForgotPasswordData;
use App\Data\Auth\LoginData;
use App\Data\Auth\ResetPasswordData;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\ActivateAccountService;
use App\Services\Auth\ForgotPasswordService;
use App\Services\Auth\LoginService;
use App\Services\Auth\LogoutService;
use App\Services\Auth\ResetPasswordService;
use App\Services\User\ShowUserService;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Log in
     *
     * Exchanges an email and password for the user's details and a bearer token.
     * Send the token as `Authorization: Bearer {token}` on later requests.
     *
     * @throws InvalidCredentialsException when the pair does not identify a user
     * @throws AccountDeactivatedException when the account has been switched off
     */
    public function login(LoginRequest $request, LoginService $login): JsonResponse
    {
        return ApiResponse::success(
            'Uspešno ste prijavljeni.',
            new AuthenticatedUserResource($login->handle(LoginData::fromRequest($request), ActorData::guest($request))),
        );
    }

    /**
     * Log out
     *
     * Revokes the bearer token this request was made with. Tokens issued to the
     * same account elsewhere keep working, so signing out on one device does not
     * sign the user out on the others.
     */
    public function logout(Request $request, LogoutService $logout): JsonResponse
    {
        $logout->handle($request->user()->currentAccessToken());

        return ApiResponse::success('Uspešno ste odjavljeni.');
    }

    /**
     * The signed-in user
     *
     * Returns the account the bearer token belongs to, read fresh from the
     * database, so the SPA can restore its session from a stored token without
     * asking for the password again.
     *
     * ShowUserService can answer 404, which is why it is hidden here: the token
     * only resolves while its owner exists, so this endpoint cannot reach that
     * branch, and documenting it would send frontend developers hunting for a
     * case that never happens.
     */
    #[IgnoreResponse(404)]
    public function me(Request $request, ShowUserService $showUser): JsonResponse
    {
        return ApiResponse::success(
            'Podaci o nalogu.',
            new UserResource($showUser->handle($request->user()->id)),
        );
    }

    /**
     * Activate an account
     *
     * Sets the password behind an activation link, marks the email as verified
     * and returns a bearer token, so the user is signed in straight away. The
     * link works exactly once.
     */
    public function activateAccount(ActivateAccountRequest $request, ActivateAccountService $activateAccount): JsonResponse
    {
        return ApiResponse::success(
            'Nalog je uspešno aktiviran.',
            new AuthenticatedUserResource($activateAccount->handle(ActivateAccountData::fromRequest($request), ActorData::guest($request))),
        );
    }

    /**
     * Request a password reset link
     *
     * Emails a reset link when the address belongs to an activated account.
     *
     * The answer is the same whether or not it does: revealing which addresses
     * are registered would let anyone harvest accounts from this endpoint.
     */
    public function forgotPassword(ForgotPasswordRequest $request, ForgotPasswordService $forgotPassword): JsonResponse
    {
        $forgotPassword->handle(ForgotPasswordData::fromRequest($request));

        return ApiResponse::success('Ako nalog sa ovom email adresom postoji, poslali smo link za promenu lozinke.');
    }

    /**
     * Reset a password
     *
     * Sets a new password behind a reset link and returns a bearer token. The
     * link works exactly once, and every token issued before the reset stops
     * working.
     */
    public function resetPassword(ResetPasswordRequest $request, ResetPasswordService $resetPassword): JsonResponse
    {
        return ApiResponse::success(
            'Lozinka je uspešno promenjena.',
            new AuthenticatedUserResource($resetPassword->handle(ResetPasswordData::fromRequest($request), ActorData::guest($request))),
        );
    }
}
