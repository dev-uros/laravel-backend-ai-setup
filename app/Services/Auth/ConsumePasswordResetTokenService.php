<?php

namespace App\Services\Auth;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Password;

/**
 * The token half of the two flows that end in "set a password": activating a new
 * account, and resetting a forgotten one.
 *
 * Laravel's broker verifies the token against its stored hash and deletes it
 * once used, so a link works exactly once. Kept in one place so the two callers
 * cannot drift apart on something this easy to get subtly wrong.
 */
class ConsumePasswordResetTokenService
{
    /**
     * Run $onValid with the user behind a valid token, and return whatever it
     * produced. Returns null when the token is expired, spent, or not that
     * user's -- the caller decides which exception that deserves.
     *
     * @param  Closure(User, string): User  $onValid
     */
    public function handle(string $email, string $token, string $password, Closure $onValid): ?User
    {
        $result = null;

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => $password,
                'token' => $token,
            ],
            function (User $user, string $newPassword) use ($onValid, &$result): void {
                $result = $onValid($user, $newPassword);
            },
        );

        return $status === Password::PASSWORD_RESET && $result instanceof User
            ? $result
            : null;
    }
}
