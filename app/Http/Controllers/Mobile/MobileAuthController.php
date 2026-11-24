<?php

namespace App\Http\Controllers\Mobile;

use App\Data\MobileAuth\EmailCodeData;
use App\Data\MobileAuth\RegisterWithAppleData;
use App\Data\MobileAuth\RegisterWithCodeData;
use App\Data\MobileAuth\RegisterWithGoogleData;
use App\Data\MobileAuth\SignInWithAppleData;
use App\Data\MobileAuth\SignInWithCodeData;
use App\Data\MobileAuth\SignInWithGoogleData;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\MobileAccountAlreadyExistsException;
use App\Exceptions\MobileAccountNotFoundException;
use App\Exceptions\OtpResendTooSoonException;
use App\Exceptions\ProviderAccountNotLinkableException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Exceptions\WrongAudienceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobileAuth\RegisterWithAppleRequest;
use App\Http\Requests\MobileAuth\RegisterWithCodeRequest;
use App\Http\Requests\MobileAuth\RegisterWithGoogleRequest;
use App\Http\Requests\MobileAuth\RequestRegistrationCodeRequest;
use App\Http\Requests\MobileAuth\RequestSignInCodeRequest;
use App\Http\Requests\MobileAuth\SignInWithAppleRequest;
use App\Http\Requests\MobileAuth\SignInWithCodeRequest;
use App\Http\Requests\MobileAuth\SignInWithGoogleRequest;
use App\Http\Resources\AuthenticatedMobileUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\MobileAuth\RegisterWithAppleService;
use App\Services\MobileAuth\RegisterWithCodeService;
use App\Services\MobileAuth\RegisterWithGoogleService;
use App\Services\MobileAuth\RequestRegistrationCodeService;
use App\Services\MobileAuth\RequestSignInCodeService;
use App\Services\MobileAuth\SignInWithAppleService;
use App\Services\MobileAuth\SignInWithCodeService;
use App\Services\MobileAuth\SignInWithGoogleService;
use App\Services\MobileAuth\SignOutMobileUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The external mobile app's front door. No passwords: an address is proved by
 * a mailed six-digit code, or Google or Apple vouch for it. Signing in and
 * registering are separate routes for each of the three.
 *
 * Every answer is in the language of `Accept-Language` (`sr` or `en`), which
 * is also stored on the account for mail and pushes.
 */
class MobileAuthController extends Controller
{
    /**
     * Ask for a sign-in code
     *
     * Mails a six-digit code (valid 10 minutes) if the address has an account,
     * and says whether it has one -- `account_exists: false` sends the person to
     * registration; nothing is mailed then.
     *
     * `retry_after` is how many seconds until another code may be asked for. Asked
     * sooner, the answer is 429 with the wait in the `Retry-After` header.
     *
     * @throws WrongAudienceException when the account has been blocked
     * @throws OtpResendTooSoonException
     */
    public function requestSignInCode(RequestSignInCodeRequest $request, RequestSignInCodeService $send): JsonResponse
    {
        $result = $send->handle(EmailCodeData::fromRequest($request));

        return ApiResponse::success(
            __($result->accountExists ? 'mobile.auth.code_sent' : 'mobile.auth.no_account'),
            ['account_exists' => $result->accountExists, 'retry_after' => $result->retryAfter],
        );
    }

    /**
     * Sign in with a code
     *
     * Exchanges the code for the account and a bearer token. Five wrong tries
     * use the code up.
     *
     * @throws InvalidOtpCodeException when the code is wrong, expired or used up
     * @throws WrongAudienceException when the account has been blocked
     */
    public function signIn(SignInWithCodeRequest $request, SignInWithCodeService $signIn): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.auth.signed_in'),
            new AuthenticatedMobileUserResource($signIn->handle(SignInWithCodeData::fromRequest($request))),
        );
    }

    /**
     * Ask for a registration code
     *
     * Checks that the address and the username are free (422 otherwise) and
     * mails a code to the address. Send it to `register` with the rest of the
     * profile.
     *
     * @throws MobileAccountAlreadyExistsException
     * @throws OtpResendTooSoonException
     */
    public function requestRegistrationCode(RequestRegistrationCodeRequest $request, RequestRegistrationCodeService $send): JsonResponse
    {
        $result = $send->handle(EmailCodeData::fromRequest($request));

        return ApiResponse::success(__('mobile.auth.code_sent'), ['retry_after' => $result->retryAfter]);
    }

    /**
     * Register with a code
     *
     * Makes the account and answers with it and a bearer token. Multipart
     * when it carries `avatar`.
     *
     * @throws InvalidOtpCodeException when the code is wrong, expired or used up
     * @throws MobileAccountAlreadyExistsException
     */
    public function register(RegisterWithCodeRequest $request, RegisterWithCodeService $register): JsonResponse
    {
        return ApiResponse::created(
            __('mobile.auth.registered'),
            new AuthenticatedMobileUserResource($register->handle(RegisterWithCodeData::fromRequest($request))),
        );
    }

    /**
     * Sign in with Google
     *
     * Exchanges a Google ID token for the account and a bearer token. An
     * account registered with a code is linked on the way in when the address
     * is Gmail or Google Workspace; any other address with an account is a 409
     * (sign in with a code). No account at all is a 404: register instead.
     *
     * @throws InvalidProviderTokenException
     * @throws MobileAccountNotFoundException
     * @throws ProviderAccountNotLinkableException
     * @throws WrongAudienceException when the account has been blocked
     * @throws ProviderSignInUnavailableException when no client ID is configured or Google's keys cannot be fetched
     */
    public function signInWithGoogle(SignInWithGoogleRequest $request, SignInWithGoogleService $signIn): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.auth.signed_in'),
            new AuthenticatedMobileUserResource($signIn->handle(SignInWithGoogleData::fromRequest($request))),
        );
    }

    /**
     * Register with Google
     *
     * Makes the account from a Google ID token instead of a code. The address
     * is read out of the token.
     *
     * @throws InvalidProviderTokenException
     * @throws MobileAccountAlreadyExistsException
     * @throws ProviderSignInUnavailableException
     */
    public function registerWithGoogle(RegisterWithGoogleRequest $request, RegisterWithGoogleService $register): JsonResponse
    {
        return ApiResponse::created(
            __('mobile.auth.registered'),
            new AuthenticatedMobileUserResource($register->handle(RegisterWithGoogleData::fromRequest($request))),
        );
    }

    /**
     * Sign in with Apple
     *
     * Exchanges a Sign in with Apple identity token (with the raw `nonce`) for
     * the account and a bearer token. Linking by address only for iCloud
     * addresses; 409 for any other address with an account, 404 for none.
     *
     * @throws InvalidProviderTokenException
     * @throws MobileAccountNotFoundException
     * @throws ProviderAccountNotLinkableException
     * @throws WrongAudienceException when the account has been blocked
     * @throws ProviderSignInUnavailableException when no bundle ID is configured or Apple's keys cannot be fetched
     */
    public function signInWithApple(SignInWithAppleRequest $request, SignInWithAppleService $signIn): JsonResponse
    {
        return ApiResponse::success(
            __('mobile.auth.signed_in'),
            new AuthenticatedMobileUserResource($signIn->handle(SignInWithAppleData::fromRequest($request))),
        );
    }

    /**
     * Register with Apple
     *
     * Makes the account from an Apple identity token. The address -- possibly
     * a hidden relay one -- is read out of the token.
     *
     * @throws InvalidProviderTokenException
     * @throws MobileAccountAlreadyExistsException
     * @throws ProviderSignInUnavailableException
     */
    public function registerWithApple(RegisterWithAppleRequest $request, RegisterWithAppleService $register): JsonResponse
    {
        return ApiResponse::created(
            __('mobile.auth.registered'),
            new AuthenticatedMobileUserResource($register->handle(RegisterWithAppleData::fromRequest($request))),
        );
    }

    /**
     * Sign out
     *
     * Revokes the token this request arrived with and clears this phone's push
     * token. The account's other phones stay signed in.
     */
    public function signOut(Request $request, SignOutMobileUserService $signOut): JsonResponse
    {
        $signOut->handle($request->user()->currentAccessToken());

        return ApiResponse::success(__('mobile.auth.signed_out'));
    }
}
