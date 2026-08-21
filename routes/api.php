<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\User\DeactivateUserController;
use App\Http\Controllers\User\IndexLoginAttemptsController;
use App\Http\Controllers\User\IndexUserLogsController;
use App\Http\Controllers\User\ReactivateUserController;
use App\Http\Controllers\User\ResendActivationController;
use App\Http\Controllers\User\UpdateUserRoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAccountIsActive;
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
 * Everything else. The middleware is stated once for the whole group so a new
 * endpoint cannot be added without it -- the failure mode of per-route
 * middleware is silent, and it is an open API endpoint.
 */
Route::middleware(['auth:sanctum', EnsureAccountIsActive::class])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

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
});
