<?php

namespace App\Console\Commands;

use App\Data\ActorData;
use App\Data\User\StoreUserData;
use App\Enums\UserRole;
use App\Notifications\AccountCreatedNotification;
use App\Services\User\StoreUserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first administrator on a freshly deployed server.
 *
 * `DatabaseSeeder` cannot do this job in production: it goes through
 * `User::factory()`, factories need Faker, and Faker is a require-dev package
 * that `composer install --no-dev` leaves out of the image. Even if it were
 * there, it would create an account whose password is the word "password".
 *
 * So this is not a second way to create a user -- it is the only way to create
 * the one account that has to exist before anybody can call `POST /api/users`.
 * It goes through StoreUserService like every other creation, which is what
 * gives it the random password, the emailed activation link and the two entries
 * in the audit trail. The actor is `system()`: nobody was signed in.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {email : Address the activation link is sent to}
                            {--name= : Given name}
                            {--surname= : Family name}
                            {--show-link : Print the activation link instead of relying on email}';

    protected $description = 'Create an administrator account and send them an activation link';

    public function handle(StoreUserService $storeUser): int
    {
        $attributes = [
            'email' => (string) $this->argument('email'),
            'name' => (string) ($this->option('name') ?: $this->ask('Given name')),
            'surname' => (string) ($this->option('surname') ?: $this->ask('Family name')),
        ];

        /**
         * The one place outside a FormRequest where validation is written, and
         * only because there is no request to attach it to. Duplicating
         * StoreUserRequest's rules is deliberate rather than reusing it: a
         * FormRequest resolves the HTTP request and the authenticated user,
         * neither of which exists here.
         */
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = $storeUser->handle(
            new StoreUserData(
                name: $attributes['name'],
                surname: $attributes['surname'],
                email: $attributes['email'],
                role: UserRole::Admin,
            ),
            ActorData::system(),
        );

        $this->components->info("Administrator #{$user->id} created: {$user->email}");

        if (! $this->option('show-link')) {
            $this->components->info('An activation link has been queued to that address.');

            return self::SUCCESS;
        }

        /**
         * The escape hatch for a server whose SMTP is not working yet, which is
         * the usual state of things ten minutes after the first deploy.
         *
         * Minting a token invalidates the previous one, so the link that was
         * just emailed stops working -- said out loud rather than left to be
         * discovered, because the two links look identical.
         */
        $link = AccountCreatedNotification::activationUrlFor($user, Password::broker()->createToken($user));

        $this->newLine();
        $this->components->warn('This link replaces the one that was emailed -- that one no longer works.');
        $this->line($link);
        $this->newLine();

        return self::SUCCESS;
    }
}
