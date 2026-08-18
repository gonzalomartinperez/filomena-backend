<?php

namespace Database\Factories;

use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadoExamenEvaluador>
 */
class EstadoExamenEvaluadorFactory extends Factory
{
    protected $model = EstadoExamenEvaluador::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'finalizado' => $this->faker->boolean(70),
            'correcciones_asignadas' => $this->faker->numberBetween(50, 100),
            'correcciones_realizadas' => $this->faker->numberBetween(0, 50),
            'evaluador_id' => User::factory()->evaluador(),
            'examen_id' => Examen::factory(),
        ];
    }
}
