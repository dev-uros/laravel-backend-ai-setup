<?php

namespace App\Repositories;

use App\Models\MobileOtpCode;
use App\Repositories\Contracts\MobileOtpCodeRepositoryInterface;

class MobileOtpCodeRepository implements MobileOtpCodeRepositoryInterface
{
    public function findFor(string $email): ?MobileOtpCode
    {
        return MobileOtpCode::query()->where('email', $email)->first();
    }

    public function issue(string $email, string $hash): MobileOtpCode
    {
        $now = now();

        MobileOtpCode::query()->upsert([[
            'email' => $email,
            'code_hash' => $hash,
            'attempts' => 0,
            'sent_at' => $now,
            'expires_at' => $now->copy()->addMinutes(MobileOtpCode::VALID_FOR_MINUTES),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['email'], ['code_hash', 'attempts', 'sent_at', 'expires_at', 'updated_at']);

        return MobileOtpCode::query()->where('email', $email)->firstOrFail();
    }

    public function claimAttempt(string $email): ?MobileOtpCode
    {
        $claimed = MobileOtpCode::query()
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->where('attempts', '<', MobileOtpCode::MAX_ATTEMPTS)
            ->increment('attempts');

        return $claimed === 1 ? $this->findFor($email) : null;
    }

    public function deleteFor(string $email): void
    {
        MobileOtpCode::query()->where('email', $email)->delete();
    }
}
