<?php

namespace App\Services\MobileUser;

use App\Exceptions\MobileAccountNotFoundException;
use App\Jobs\RevokeAppleTokenJob;
use App\Repositories\Contracts\MobileOtpCodeRepositoryInterface;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Services\MobileAuth\ExchangeAppleAuthorizationCodeService;
use App\Support\Media\MobileAvatarStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Somebody deletes their own account from the app -- App Store and Google Play
 * both require that this is possible in the app.
 *
 * **Final.** The personal data goes, the row stays: it is emptied
 * (`anonymise`) and soft deleted, so whatever a project later hangs off it
 * keeps an owner that describes nobody. With it go:
 *
 * - every token and every phone, so the app is signed out everywhere;
 * - the code still waiting for that address;
 * - the Sign in with Apple authorisation, revoked at Apple by a job after the
 *   commit. The app runs Sign in with Apple on the deletion screen and sends a
 *   fresh authorization code; its refresh token is revoked, or else the one
 *   kept at sign-in. A refused or missing code never stops the deletion.
 *
 * The address and the username are free for a new account the moment this
 * commits: both columns are NULL, which a unique index allows any number of.
 *
 * Domain data a project adds goes here too, in the same transaction -- this
 * is the one place that knows what "the account is gone" means.
 */
class DeleteMobileUserService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly MobileUserDeviceRepositoryInterface $devices,
        private readonly MobileOtpCodeRepositoryInterface $codes,
        private readonly ExchangeAppleAuthorizationCodeService $exchangeAppleCode,
        private readonly MobileAvatarStore $avatars,
    ) {}

    /**
     * @throws MobileAccountNotFoundException when the account is already gone
     */
    public function handle(int $mobileUserId, ?string $appleAuthorizationCode = null): void
    {
        $user = $this->mobileUsers->findById($mobileUserId) ?? throw MobileAccountNotFoundException::account();

        /** Before the transaction, so a slow Apple never holds the row lock. */
        $freshAppleToken = $user->apple_id === null || $user->apple_client_id === null
            ? null
            : $this->exchangeAppleCode->handle($user->id, $user->apple_id, $user->apple_client_id, $appleAuthorizationCode);

        $avatar = DB::transaction(function () use ($mobileUserId, $freshAppleToken): ?string {
            /** Locked, so a second tap waits and then finds nothing. */
            $user = $this->mobileUsers->lockForDeletion($mobileUserId) ?? throw MobileAccountNotFoundException::account();

            $avatar = $user->avatar_path;
            $appleRefreshToken = $freshAppleToken ?? $user->apple_refresh_token;
            $appleClientId = $user->apple_client_id;

            $this->devices->deleteAllFor($user->id);
            $this->mobileUsers->revokeTokens($user);

            if ($user->email !== null) {
                $this->codes->deleteFor($user->email);
            }

            $this->mobileUsers->anonymise($user);

            if ($appleRefreshToken !== null && $appleClientId !== null) {
                RevokeAppleTokenJob::dispatch($user->id, $appleRefreshToken, $appleClientId)->afterCommit();
            }

            return $avatar;
        });

        /** After the commit: a file left by a failed transaction is invisible, a row pointing at a deleted one is not. */
        $this->avatars->delete($avatar);

        Log::info('mobile user deleted', ['mobile_user_id' => $mobileUserId]);
    }
}
