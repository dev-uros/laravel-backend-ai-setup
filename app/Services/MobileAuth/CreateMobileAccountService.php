<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\RegistrationProfileData;
use App\Data\MobileAuth\SessionContextData;
use App\Data\MobileUser\NewMobileUserData;
use App\Exceptions\MobileAccountAlreadyExistsException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Media\MobileAvatarStore;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Make the account, once the address has been proved -- shared by every way
 * in (code, Google, Apple), so there is one place that says what a new
 * account is.
 *
 * One transaction: a half-made account is worse than none, because the
 * address is unique and the person could never register again.
 *
 * The picture is re-encoded **before** the transaction (it is slow, and holds
 * no lock) and deleted again if the account is not made. A registration that
 * loses a race on the address answers 409, and on the handle 422 -- the same
 * answers the request's own unique rules give.
 */
class CreateMobileAccountService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly StartMobileSessionService $startSession,
        private readonly MobileAvatarStore $avatars,
    ) {}

    /**
     * @throws MobileAccountAlreadyExistsException
     * @throws ValidationException when the username was taken a moment ago
     */
    public function handle(
        string $email,
        RegistrationProfileData $profile,
        SessionContextData $context,
        ?string $googleId = null,
        ?string $appleId = null,
    ): AuthenticatedMobileUserData {
        $avatarPath = $profile->avatar === null ? null : $this->avatars->put($profile->avatar);

        try {
            return DB::transaction(fn (): AuthenticatedMobileUserData => $this->startSession->handle(
                $this->mobileUsers->create(new NewMobileUserData(
                    email: $email,
                    username: $profile->username,
                    locale: $context->locale,
                    firstName: $profile->firstName,
                    lastName: $profile->lastName,
                    displayPersonalName: $profile->displayPersonalName,
                    googleId: $googleId,
                    appleId: $appleId,
                    avatarPath: $avatarPath,
                    /** Registered on a review address (`MOBILE_DEMO_EMAILS`): it signs in with the fixed code from now on. */
                    isDemo: in_array($email, (array) config('mobile.demo.emails'), true),
                )),
                $context,
            ));
        } catch (Throwable $exception) {
            $this->avatars->delete($avatarPath);

            throw $this->explain($exception, $profile->username);
        }
    }

    private function explain(Throwable $exception, string $username): Throwable
    {
        if (! $exception instanceof UniqueConstraintViolationException) {
            return $exception;
        }

        if (str_contains($exception->getMessage(), 'username')) {
            return ValidationException::withMessages([
                'username' => __('validation.unique', ['attribute' => __('validation.attributes.username')]),
            ]);
        }

        return new MobileAccountAlreadyExistsException;
    }
}
