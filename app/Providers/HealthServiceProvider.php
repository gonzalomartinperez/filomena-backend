<?php

namespace App\Providers;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $checks = [
            DatabaseCheck::new(),                  // DB OK
            CacheCheck::new(),                     // cache driver operativo
            RedisCheck::new(),                     // redis OK
            HorizonCheck::new(),                   // Horizon corriendo (warning si pausado)
            QueueCheck::new(),                     // que los jobs se estén ejecutando
            ScheduleCheck::new(),                  // que el scheduler corra cada minuto
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(85)
                ->failWhenUsedSpaceIsAbovePercentage(95),
        ];

        // Solo en producción: alerta si APP_DEBUG está habilitado
        if (app()->isProduction()) {
            $checks[] = DebugModeCheck::new();
        }

        Health::checks($checks);
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user) {
            if (app()->isLocal()) {
                return true;
            }

            return $user->hasRole(Roles::ADMINISTRADOR->value)
                && strcasecmp($user->email, (string) config('filomena.initial_admin.email')) === 0;
        });
    }
}
