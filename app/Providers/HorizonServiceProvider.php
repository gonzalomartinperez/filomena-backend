<?php

namespace App\Providers;

use App\Enums\Roles;
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
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user) {
            if (app()->isLocal()) {
                return true;
            }

            return $user
                && $user->hasRole(Roles::ADMINISTRADOR->value)
                && strcasecmp($user->email, (string) config('filomena.initial_admin.email')) === 0;
        });
    }
}
