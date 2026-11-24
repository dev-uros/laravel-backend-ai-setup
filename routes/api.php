<?php

use App\Enums\DevicePlatform;
use App\Enums\MobileApp;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InternalUserDevice\SendTestPushController;
use App\Http\Controllers\InternalUserDeviceController;
use App\Http\Controllers\Mobile\DeleteMobileAvatarController;
use App\Http\Controllers\Mobile\MobileAccountController;
use App\Http\Controllers\Mobile\MobileAuthController;
use App\Http\Controllers\Mobile\MobileUserDeviceController;
use App\Http\Controllers\Mobile\SendMobileTestPushController;
use App\Http\Controllers\Mobile\UpdateMobileAvatarController;
use App\Http\Controllers\MobileAppVersionController;
use App\Http\Controllers\MobileUser\BlockMobileUserController;
use App\Http\Controllers\MobileUser\UnblockMobileUserController;
use App\Http\Controllers\MobileUserController;
use App\Http\Controllers\User\DeactivateUserController;
use App\Http\Controllers\User\DeleteUserAvatarController;
use App\Http\Controllers\User\IndexLoginAttemptsController;
use App\Http\Controllers\User\IndexUserLogsController;
use App\Http\Controllers\User\ReactivateUserController;
use App\Http\Controllers\User\ResendActivationController;
use App\Http\Controllers\User\UpdateUserAvatarController;
use App\Http\Controllers\User\UpdateUserRoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureInternalUser;
use App\Http\Middleware\EnsureMobileUser;
use App\Http\Middleware\SetLocale;
use App\Models\InternalUserDevice;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Public: the endpoints a caller reaches before they hold a token, or when they
 * have lost access to the account that would issue one.
 *
 * All of them are throttled -- each one either accepts a guess (login) or
 * sends mail on request, and nothing above rewards being hammered.
 */
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::post('auth/activate-account', [AuthController::class, 'activateAccount'])
    ->middleware('throttle:10,1')
    ->name('auth.activate-account');

Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1')
    ->name('auth.forgot-password');

Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:10,1')
    ->name('auth.reset-password');

/**
 * Asked by both mobile apps on every launch, before they know whether anyone
 * is signed in: an app too old to talk to this API must be stopped at the
 * door, login screen included. `{app}` is `internal` or `external` -- each
 * ships on its own schedule, so each has its own minimum. Public, and
 * throttled generously, because a phone asks again on every return to the
 * foreground.
 */
Route::get('mobile/app-version/{app}/{platform}', [MobileAppVersionController::class, 'show'])
    ->whereIn('app', array_column(MobileApp::cases(), 'value'))
    ->whereIn('platform', array_column(DevicePlatform::cases(), 'value'))
    ->middleware('throttle:60,1')
    ->name('mobile.app-version.show');

/**
 * The external mobile app's API: its own accounts (`mobile_users`), in Serbian
 * or English after `Accept-Language`. Kept under its own versioned prefix so a
 * phone that cannot be updated at once keeps talking to the shape it was built
 * against.
 */
Route::prefix('mobile/v1')->name('mobile.')->middleware(SetLocale::class)->group(function (): void {
    /**
     * The front door. Throttled with named limiters (AppServiceProvider), keyed
     * per address and per IP -- either alone leaves the other sweep open.
     */
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('email/sign-in-code', [MobileAuthController::class, 'requestSignInCode'])
            ->middleware('throttle:mobile-code-request')
            ->name('email.sign-in-code');

        Route::post('email/sign-in', [MobileAuthController::class, 'signIn'])
            ->middleware('throttle:mobile-code-check')
            ->name('email.sign-in');

        Route::post('email/registration-code', [MobileAuthController::class, 'requestRegistrationCode'])
            ->middleware('throttle:mobile-code-request')
            ->name('email.registration-code');

        Route::post('email/register', [MobileAuthController::class, 'register'])
            ->middleware('throttle:mobile-code-check')
            ->name('email.register');

        Route::post('google/sign-in', [MobileAuthController::class, 'signInWithGoogle'])
            ->middleware('throttle:mobile-provider')
            ->name('google.sign-in');

        Route::post('google/register', [MobileAuthController::class, 'registerWithGoogle'])
            ->middleware('throttle:mobile-provider')
            ->name('google.register');

        Route::post('apple/sign-in', [MobileAuthController::class, 'signInWithApple'])
            ->middleware('throttle:mobile-provider')
            ->name('apple.sign-in');

        Route::post('apple/register', [MobileAuthController::class, 'registerWithApple'])
            ->middleware('throttle:mobile-provider')
            ->name('apple.register');
    });

    /**
     * Everything the app does once it holds a token. `EnsureMobileUser` is
     * stated once for the whole group: a route added without it would be
     * reachable by an internal user's token, and the failure would be silent.
     */
    Route::middleware(['auth:sanctum', EnsureMobileUser::class])->group(function (): void {
        Route::post('auth/logout', [MobileAuthController::class, 'signOut'])->name('auth.logout');

        Route::get('me', [MobileAccountController::class, 'show'])->name('me.show');
        Route::patch('me', [MobileAccountController::class, 'update'])->name('me.update');
        Route::delete('me', [MobileAccountController::class, 'destroy'])->name('me.destroy');

        /** POST rather than PUT: the body is multipart/form-data, which PHP only parses on a POST. */
        Route::post('me/avatar', UpdateMobileAvatarController::class)->name('me.avatar.update');
        Route::delete('me/avatar', DeleteMobileAvatarController::class)->name('me.avatar.destroy');

        /** The phone this session is on; no id in the path. */
        Route::get('device', [MobileUserDeviceController::class, 'show'])->name('device.show');
        Route::put('device', [MobileUserDeviceController::class, 'update'])->name('device.update');

        /** Throttled because each call is a real request to Expo's push service. */
        Route::post('device/test-push', SendMobileTestPushController::class)
            ->middleware('throttle:5,1')
            ->name('device.test-push');
    });
});

/**
 * Everything else: the internal API, for `users` only. The middleware is
 * stated once for the whole group so a new endpoint cannot be added without it
 * -- the failure mode of per-route middleware is silent, and it is an open API
 * endpoint. `EnsureInternalUser` comes before `EnsureAccountIsActive`: a token
 * minted by the external app would otherwise pass `auth:sanctum` here.
 */
Route::middleware(['auth:sanctum', EnsureInternalUser::class, EnsureAccountIsActive::class])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    /**
     * The phone this session is on -- the mobile app's, for internal users.
     * No id in the path: the token is the session and the session is the
     * phone, so a caller cannot even name somebody else's device.
     */
    Route::get('auth/device', [InternalUserDeviceController::class, 'show'])
        ->middleware('can:manageOwn,'.InternalUserDevice::class)
        ->name('auth.device.show');

    Route::put('auth/device', [InternalUserDeviceController::class, 'update'])
        ->middleware('can:manageOwn,'.InternalUserDevice::class)
        ->name('auth.device.update');

    /**
     * Throttled because each call is a real request to Expo's push service.
     */
    Route::post('auth/device/test-push', SendTestPushController::class)
        ->middleware(['can:manageOwn,'.InternalUserDevice::class, 'throttle:5,1'])
        ->name('auth.device.test-push');

    /**
     * Authorised by UserPolicy through the `can:` middleware, so who may reach
     * an endpoint is answered here rather than somewhere inside the action.
     * The policy takes the class name, not a model: these routes carry a plain
     * id, and the checks are about the caller's role, not the target row.
     */
    Route::apiResource('users', UserController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['users' => 'id'])
        ->whereNumber('id')
        ->middlewareFor('index', 'can:viewAny,'.User::class)
        ->middlewareFor('show', 'can:view,'.User::class)
        ->middlewareFor('store', 'can:create,'.User::class);

    /**
     * Kept out of the resource above on purpose: apiResource would also register
     * PATCH, and every field on this endpoint is required. Advertising PATCH would
     * promise a partial update that answers 422 instead.
     */
    Route::put('users/{id}', [UserController::class, 'update'])
        ->middleware('can:update,'.User::class)
        ->whereNumber('id')
        ->name('users.update');

    /**
     * Outside the resource as well: not a CRUD verb, and it acts on a sub-path.
     * POST because it has side effects -- a new token is minted and mail is queued.
     */
    Route::post('users/{id}/resend-activation', ResendActivationController::class)
        ->middleware('can:resendActivation,'.User::class)
        ->whereNumber('id')
        ->name('users.resend-activation');

    Route::get('users/{id}/logins', IndexLoginAttemptsController::class)
        ->middleware('can:viewLogs,'.User::class)
        ->whereNumber('id')
        ->name('users.logins.index');

    Route::get('users/{id}/logs', IndexUserLogsController::class)
        ->middleware('can:viewLogs,'.User::class)
        ->whereNumber('id')
        ->name('users.logs.index');

    /**
     * Kept off the profile update: a role change carries rules of its own, and
     * putting it on PUT /users/{id} would make every profile edit a request to
     * hand out privileges.
     */
    Route::put('users/{id}/role', UpdateUserRoleController::class)
        ->middleware('can:changeRole,'.User::class)
        ->whereNumber('id')
        ->name('users.role.update');

    /**
     * The avatar is the one thing a user may change without being an
     * administrator -- on their own account. The policy only opens the door;
     * whose avatar the caller may touch depends on the target row, so it is
     * settled in AuthorizeAvatarChangeService.
     *
     * POST rather than PUT because the body is multipart/form-data, which PHP
     * only parses on a POST.
     */
    Route::post('users/{id}/avatar', UpdateUserAvatarController::class)
        ->middleware('can:changeAvatar,'.User::class)
        ->whereNumber('id')
        ->name('users.avatar.update');

    Route::delete('users/{id}/avatar', DeleteUserAvatarController::class)
        ->middleware('can:changeAvatar,'.User::class)
        ->whereNumber('id')
        ->name('users.avatar.destroy');

    /**
     * Switching an account off is not a delete, so it is not `destroy`: the row
     * stays, and the pair of routes reads as the pair of states it is.
     */
    Route::post('users/{id}/deactivate', DeactivateUserController::class)
        ->middleware('can:deactivate,'.User::class)
        ->whereNumber('id')
        ->name('users.deactivate');

    Route::post('users/{id}/reactivate', ReactivateUserController::class)
        ->middleware('can:reactivate,'.User::class)
        ->whereNumber('id')
        ->name('users.reactivate');

    /**
     * The external app's accounts, for the admin panel. Read by anyone signed
     * in, blocked and unblocked by administrators (MobileUserPolicy).
     */
    Route::get('mobile-users', [MobileUserController::class, 'index'])
        ->middleware('can:viewAny,'.MobileUser::class)
        ->name('mobile-users.index');

    Route::get('mobile-users/{id}', [MobileUserController::class, 'show'])
        ->middleware('can:view,'.MobileUser::class)
        ->whereNumber('id')
        ->name('mobile-users.show');

    Route::get('mobile-users/{id}/devices', [MobileUserController::class, 'devices'])
        ->middleware('can:view,'.MobileUser::class)
        ->whereNumber('id')
        ->name('mobile-users.devices.index');

    Route::post('mobile-users/{id}/block', BlockMobileUserController::class)
        ->middleware('can:block,'.MobileUser::class)
        ->whereNumber('id')
        ->name('mobile-users.block');

    Route::post('mobile-users/{id}/unblock', UnblockMobileUserController::class)
        ->middleware('can:block,'.MobileUser::class)
        ->whereNumber('id')
        ->name('mobile-users.unblock');
});
