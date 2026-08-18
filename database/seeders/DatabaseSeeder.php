<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // SIEMPRE: Roles y Permisos
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->environment('production')) {
            // PRODUCCIÓN
            $this->call([
                PseudonimosSeeder::class,
                AdministradorSeeder::class,
            ]);
        } else {
            // LOCAL/TESTING
            $this->call([
                PseudonimosSeeder::class,
                AdministradorSeeder::class,
                LegacySeeder::class,
            ]);
        }
    }
}
