<?php

namespace App\Console\Commands;

use App\Models\MobileUser;
use App\Services\MobileUser\PrepareDemoMobileUsersService;
use Illuminate\Console\Command;

/**
 * Create or reset the external app's demo accounts -- for App Store and Google
 * Play review, and for Maestro locally.
 *
 *     make artisan c="mobile:demo-accounts"
 *
 * Run after deploying, and again before every store submission: a reviewer may
 * delete the account. The work is `PrepareDemoMobileUsersService`'s; this
 * prints what to type into the stores.
 */
class PrepareDemoMobileUsersCommand extends Command
{
    protected $signature = 'mobile:demo-accounts';

    protected $description = 'Create or reset the external mobile app demo accounts (MOBILE_DEMO_EMAILS) that sign in with MOBILE_DEMO_CODE.';

    public function handle(PrepareDemoMobileUsersService $prepare): int
    {
        /** @var list<string> $emails */
        $emails = config('mobile.demo.emails');

        if ($emails === []) {
            $this->components->error('MOBILE_DEMO_EMAILS is empty.');

            return self::FAILURE;
        }

        $prepare->handle($emails)->each(
            fn (MobileUser $user) => $this->components->twoColumnDetail((string) $user->email, (string) $user->username),
        );

        config('mobile.demo.code') === null
            ? $this->components->warn('MOBILE_DEMO_CODE is not set: demo accounts are mailed a code like everybody else.')
            : $this->components->info('They sign in with MOBILE_DEMO_CODE; nothing is mailed to them.');

        return self::SUCCESS;
    }
}
