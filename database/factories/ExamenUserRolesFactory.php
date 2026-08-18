<?php

namespace Database\Factories;

use App\Enums\Roles;
use App\Models\EstudianteAnonimo;
use App\Models\Examen;
use App\Models\ExamenUserRoles;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstudianteAnonimo>
 */
class ExamenUserRolesFactory extends Factory
{
    protected $model = ExamenUserRoles::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'examen_id' => Examen::factory(),
            'user_id' => User::factory(),
            'role_id' => array_rand(Roles::values()),
        ];
    }
}
