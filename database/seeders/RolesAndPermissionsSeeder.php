<?php

namespace Database\Seeders;

use App\Enums\Permisos;
use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Crear todos los permisos a partir del enum Permisos
        foreach (Permisos::cases() as $permiso) {
            PermissionModel::firstOrCreate(['name' => $permiso->value]);
        }
        // Asegurar que no haya basura en cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        // Crear permisos con guard explícito (idempotente por nombre+guard)
        foreach (Permisos::cases() as $permiso) {
            PermissionModel::findOrCreate($permiso->value, $guard);
        }

        // Crear roles con guard y sincronizar permisos (idempotente)
        foreach (Roles::cases() as $rol) {
            $roleModel = Role::findOrCreate($rol->value, $guard);
            $roleModel->syncPermissions($rol->permissions());
        }

        // Volver a limpiar cache por si cambió algo
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
