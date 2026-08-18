<?php

namespace Database\Factories;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'legajo' => fake()->unique()->numerify('######'),
            'dni' => fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function administrador()
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => Roles::ADMINISTRADOR->value]);
            $user->assignRole($role);
        });
    }

    public function evaluador()
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => Roles::EVALUADOR->value]);
            $user->assignRole($role);
        });
    }

    public function estudiante()
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => Roles::ESTUDIANTE->value]);
            $user->assignRole($role);
        });
    }
}
