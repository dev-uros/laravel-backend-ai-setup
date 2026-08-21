<?php

namespace App\Providers;

use App\Logging\JobLogger;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

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
    }
}
