<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Logging\JobLogger;
use App\Models\User;
use App\Support\Apple\AppleIdTokenVerifier;
use App\Support\Apple\AppleIdTokenVerifierInterface;
use App\Support\Apple\AppleTokenClient;
use App\Support\Apple\AppleTokenClientInterface;
use App\Support\Google\GoogleIdTokenVerifier;
use App\Support\Google\GoogleIdTokenVerifierInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * A singleton because it holds the start time of the job in flight
         * between two events.
         */
        $this->app->singleton(JobLogger::class);

        /**
         * Intervention's manager cannot be autowired -- it needs a driver. GD
         * rather than Imagick: it is the extension the php image installs (see
         * docker/Dockerfile), and re-encoding an avatar asks nothing of a
         * driver that GD cannot do.
         *
         * Bound here so the services that resize images type-hint the manager
         * and never construct it, which is also what lets a test swap it.
         */
        $this->app->singleton(ImageManager::class, fn (): ImageManager => new ImageManager(new Driver));

        /**
         * The three classes that talk to Google and Apple, behind interfaces so
         * a test swaps them for fakes and never reaches the network.
         */
        $this->app->bind(GoogleIdTokenVerifierInterface::class, GoogleIdTokenVerifier::class);
        $this->app->bind(AppleIdTokenVerifierInterface::class, AppleIdTokenVerifier::class);
        $this->app->bind(AppleTokenClientInterface::class, AppleTokenClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $jobs = $this->app->make(JobLogger::class);

        /**
         * The queue's counterpart to the LogRequests middleware. Registered here
         * rather than in the job base class so a job cannot be written without
         * it, the same way a route cannot be added outside the middleware group.
         */
        Queue::before($jobs->starting(...));
        Queue::after($jobs->finished(...));
        Queue::failing($jobs->failed(...));
        Queue::exceptionOccurred($jobs->attemptFailed(...));

        /**
         * Pulse's own default allows the dashboard only in the `local`
         * environment, which means it is simply shut in production rather than
         * protected there. It shows slow queries with their bindings and the
         * busiest users by id, so it gets the same audience as Horizon and the
         * audit trail.
         *
         * Defined here rather than in a dedicated provider because Pulse ships
         * no published provider in this project -- the gate has nowhere else to
         * live. See HorizonServiceProvider for the other half of the same rule.
         */
        Gate::define('viewPulse', fn (?User $user = null): bool => $user?->role === UserRole::Admin);

        $this->defineMobileRateLimiters();
    }

    /**
     * The external app's front door, which takes no password and so must not
     * reward guessing or be usable to mail-bomb an address. Two keys each --
     * by address and by caller -- because either alone leaves the other sweep
     * open. The address is lower-cased the way the request stores it.
     */
    private function defineMobileRateLimiters(): void
    {
        $email = fn (Request $request): string => mb_strtolower(trim((string) $request->input('email')));

        RateLimiter::for('mobile-code-request', fn (Request $request): array => [
            Limit::perHour(5)->by('mobile-code-email:'.$email($request)),
            Limit::perHour(20)->by('mobile-code-ip:'.$request->ip()),
        ]);

        RateLimiter::for('mobile-code-check', fn (Request $request): array => [
            Limit::perMinutes(10, 10)->by('mobile-check-email:'.$email($request)),
            Limit::perMinutes(10, 30)->by('mobile-check-ip:'.$request->ip()),
        ]);

        /** No address in the body to key on -- it is inside the token -- so only the caller. */
        RateLimiter::for('mobile-provider', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('mobile-provider-ip:'.$request->ip()));
    }
}
