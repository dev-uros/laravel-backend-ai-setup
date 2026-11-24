<?php

namespace App\Repositories\Contracts;

use App\Models\MobileOtpCode;

interface MobileOtpCodeRepositoryInterface
{
    /** The code currently issued for an address, live or not. */
    public function findFor(string $email): ?MobileOtpCode;

    /**
     * Issue a code for an address, replacing whatever was there: one live
     * code per address, with the attempt counter at nought.
     */
    public function issue(string $email, string $hash): MobileOtpCode;

    /**
     * Spend one attempt on the live code for an address, atomically: a single
     * `UPDATE ... SET attempts = attempts + 1 WHERE attempts < MAX` that two
     * concurrent guesses cannot both slip past. The code as it now stands, or
     * null when there is no live code or no attempt left.
     */
    public function claimAttempt(string $email): ?MobileOtpCode;

    public function deleteFor(string $email): void;
}
