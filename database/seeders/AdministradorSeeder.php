<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, string|null> $admin */
        $admin = config('filomena.initial_admin', []);
        $required = ['dni', 'record_id', 'username', 'email', 'first_name', 'last_name', 'password'];

        foreach ($required as $field) {
            $value = trim((string) ($admin[$field] ?? ''));

            if ($value === '' || $value === 'change-me') {
                throw new RuntimeException(
                    'Configure every INITIAL_ADMIN_* variable before running database seeders.'
                );
            }
        }

        $user = User::firstOrCreate(
            ['dni' => $admin['dni']],
            [
                'nombre' => $admin['first_name'],
                'apellido' => $admin['last_name'],
                'usuario' => $admin['username'],
                'email' => $admin['email'],
                'legajo' => $admin['record_id'],
                'password' => Hash::make($admin['password']),
                'remember_token' => Str::random(10),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->forceFill([
                'nombre' => $admin['first_name'],
                'apellido' => $admin['last_name'],
                'usuario' => $admin['username'],
                'email' => $admin['email'],
                'legajo' => $admin['record_id'],
            ])->save();
        }

        $user->syncRoles([Roles::ADMINISTRADOR->value]);
    }
}
