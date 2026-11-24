<?php

namespace App\Services\MobileAuth;

use App\Exceptions\InvalidOtpCodeException;
use App\Repositories\Contracts\MobileOtpCodeRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Check a code, and use it up. The one place a code is ever compared, so
 * signing in and registering cannot disagree about what "valid" means.
 *
 * **The attempt is claimed before the digits are compared**, in one
 * conditional `UPDATE` (`claimAttempt`). Two guesses racing each other cannot
 * both read "4 attempts" and both get a fifth: the database counts. The fifth
 * attempt is the last whatever it brings; a right code deletes the row.
 *
 * **It must not run inside the caller's transaction.** The counter is written
 * on the way to throwing, and a throw inside a transaction would roll it back
 * -- the limit would not exist. Callers verify first and open their
 * transaction afterwards; a sign-in that fails after the code was accepted has
 * still spent it, which is the cheap side of that trade.
 */
class VerifyOtpCodeService
{
    public function __construct(private readonly MobileOtpCodeRepositoryInterface $codes) {}

    /**
     * @throws InvalidOtpCodeException for every failure, in one shape
     */
    public function handle(string $email, string $code): void
    {
        $issued = $this->codes->claimAttempt($email) ?? throw new InvalidOtpCodeException;

        if (! Hash::check($code, $issued->code_hash)) {
            throw new InvalidOtpCodeException;
        }

        $this->codes->deleteFor($email);
    }
}
