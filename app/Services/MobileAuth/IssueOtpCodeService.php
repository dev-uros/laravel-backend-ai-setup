<?php

namespace App\Services\MobileAuth;

use App\Enums\AppLocale;
use App\Exceptions\OtpResendTooSoonException;
use App\Models\MobileOtpCode;
use App\Models\MobileUser;
use App\Notifications\MobileOtpCodeNotification;
use App\Repositories\Contracts\MobileOtpCodeRepositoryInterface;
use App\Support\Auth\OtpGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Issue a code for an address and mail it -- shared by signing in and
 * registering, so the cooldown, the hash and the demo exception cannot drift
 * between them.
 *
 * - **Cooldown:** a code younger than `MobileOtpCode::RESEND_AFTER_SECONDS`
 *   (30 s) is not replaced; the caller gets a 429 with how long to wait
 *   (`Retry-After`). The route throttles sit
 *   above this per address and per IP.
 * - **One live code per address:** a new one replaces the old, attempts at 0.
 * - **Only the hash is stored**; the plain code exists in the mail alone, which
 *   is queued encrypted, and never in a log line.
 * - **A store reviewer's account** (`is_demo`, created by
 *   `mobile:demo-accounts`) is issued `MOBILE_DEMO_CODE` and mailed nothing --
 *   the reviewer cannot read that inbox. So is an address from
 *   `MOBILE_DEMO_EMAILS` that has no account yet, so registration can be
 *   reviewed (and run by Maestro) too; the account it makes is a demo one.
 *   The branch is chosen by the account or the configured list, never by the
 *   code: a real account can never be issued it. Without the setting, demo
 *   addresses are mailed a code like everybody else.
 */
class IssueOtpCodeService
{
    public function __construct(
        private readonly MobileOtpCodeRepositoryInterface $codes,
        private readonly OtpGenerator $generator,
    ) {}

    /**
     * @return int seconds until another code may be asked for
     *
     * @throws OtpResendTooSoonException
     */
    public function handle(string $email, AppLocale $locale, ?MobileUser $account = null): int
    {
        $existing = $this->codes->findFor($email);

        if ($existing !== null) {
            $wait = MobileOtpCode::RESEND_AFTER_SECONDS - (int) $existing->sent_at->diffInSeconds(now(), absolute: true);

            if ($wait > 0) {
                throw new OtpResendTooSoonException($wait);
            }
        }

        $fixed = $this->demoCodeFor($email, $account);
        $code = $fixed ?? $this->generator->generate();

        $this->codes->issue($email, Hash::make($code));

        if ($fixed === null) {
            Notification::route('mail', $email)
                ->notify((new MobileOtpCodeNotification($code))->locale($locale->value));
        } else {
            Log::info('mobile demo code issued', ['mobile_user_id' => $account?->id]);
        }

        return MobileOtpCode::RESEND_AFTER_SECONDS;
    }

    private function demoCodeFor(string $email, ?MobileUser $account): ?string
    {
        $code = config('mobile.demo.code');

        if (! is_string($code) || $code === '') {
            return null;
        }

        $isDemo = $account === null
            ? in_array($email, (array) config('mobile.demo.emails'), true)
            : $account->is_demo;

        return $isDemo ? $code : null;
    }
}
