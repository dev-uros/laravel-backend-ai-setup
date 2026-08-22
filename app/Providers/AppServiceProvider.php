<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Logging\JobLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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
    }
}
