<?php

namespace App\Providers;

use App\Enums\Roles;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('login', function (Request $request) {
            $dni = $request->input('dni');
            $key = $dni ? $dni.'|'.$request->ip() : $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('password', function (Request $request) {
            $email = Str::lower($request->input('email'));
            $key = $email ? $email.'|'.$request->ip() : $request->ip();

            return Limit::perMinute(3)->by($key);
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = config('app.frontend_url', 'http://localhost:3000');

            return $frontend.'/ingresar/restablecer-contrasena'
                 .'#token='.$token
                .'&email='.urlencode($user->email);
        });

        Gate::define('viewPulse', function (?User $user) {
            if (app()->isLocal()) {
                return true;
            }

            return $user
                && $user->hasRole(Roles::ADMINISTRADOR->value)
                && strcasecmp($user->email, (string) config('filomena.initial_admin.email')) === 0;
        });

        Gate::define('viewHealth', function (?User $user) {
            if (app()->isLocal()) {
                return true;
            }

            return $user
                && $user->hasRole(Roles::ADMINISTRADOR->value)
                && strcasecmp($user->email, (string) config('filomena.initial_admin.email')) === 0;
        });
    }
}
