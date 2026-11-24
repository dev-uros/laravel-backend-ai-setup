<?php

namespace App\Services\MobileUser;

use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Create, or bring back, the accounts App Store and Google Play reviewers sign
 * in with -- and Maestro, locally. Each signs in with `MOBILE_DEMO_CODE` and is
 * never mailed (`IssueOtpCodeService`).
 *
 * Run before every submission: a reviewer may delete the account or an
 * administrator may block it, and this makes it usable again. A deleted demo
 * account is not restored -- its row was emptied -- so the address gets a new
 * one. Safe to run any number of times.
 */
class PrepareDemoMobileUsersService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * @param  list<string>  $emails
     * @return Collection<int, MobileUser>
     */
    public function handle(array $emails): Collection
    {
        return collect($emails)
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $email): MobileUser => $this->mobileUsers->upsertDemo($email, $this->usernameFor($email)));
    }

    /**
     * `review.ios@example.com` -> `review.ios_demo`: readable in the admin
     * list, and the suffix keeps it off the handles real people pick.
     */
    private function usernameFor(string $email): string
    {
        $local = preg_replace('/[^a-z0-9._]/', '', strstr($email, '@', true) ?: $email) ?: 'reviewer';

        return mb_substr($local, 0, 25).'_demo';
    }
}
