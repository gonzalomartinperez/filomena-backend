<?php

namespace App\Providers;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        // Vista para /login
        Fortify::loginView(fn () => view('auth.login'));

        // Fortify: el "username" es DNI
        Fortify::username('dni');

        // Throttle
        RateLimiter::for('login', function (Request $request) {
            $dni = (string) $request->input('dni');

            return Limit::perMinute(5)->by($dni.$request->ip());
        });

        // El acceso requiere las credenciales y el rol del administrador configurado.
        Fortify::authenticateUsing(function (Request $request) {
            $dni = $request->string('dni');
            $user = User::where('dni', $dni)->first();

            return ($user
                && Hash::check($request->string('password'), $user->password)
                && $user->hasRole(Roles::ADMINISTRADOR->value)
                && strcasecmp($user->email, (string) config('filomena.initial_admin.email')) === 0
            ) ? $user : null;
        });
    }
}
