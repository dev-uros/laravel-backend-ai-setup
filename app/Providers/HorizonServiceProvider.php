<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Horizon renders the payload of every queued job, which for this
     * application means the constructor arguments of every queued notification
     * -- email addresses included, and a failed job keeps them for a week. That
     * is the same audience as the audit trail, so it is the same rule: an
     * administrator, nobody else.
     *
     * The default this replaces was a hardcoded list of email addresses, which
     * is empty, so Horizon answers 403 to everyone outside `local` until
     * somebody edits a class and redeploys. Reading the role means a promotion
     * takes effect on the next request, exactly like every other ability here.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->role === UserRole::Admin);
    }
}
