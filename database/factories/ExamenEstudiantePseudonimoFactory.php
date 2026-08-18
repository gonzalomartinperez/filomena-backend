<?php

namespace Database\Factories;

use App\Models\EstudianteAnonimo;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\Pseudonimo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstudianteAnonimo>
 */
class ExamenEstudiantePseudonimoFactory extends Factory
{
    protected $model = ExamenEstudiantePseudonimo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'estudiante_id' => User::factory()->estudiante(),
            'pseudonimo_id' => Pseudonimo::factory(),
            'examen_id' => Examen::factory(),
        ];
    }
}
