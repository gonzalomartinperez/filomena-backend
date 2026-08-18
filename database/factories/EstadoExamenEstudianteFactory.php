<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\Examen;
use App\Models\Pregunta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadoExamenEstudiante>
 */
class EstadoExamenEstudianteFactory extends Factory
{
    protected $model = EstadoExamenEstudiante::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // Re chateado
        $start = $this->faker->dateTimeBetween('08:00', '10:00');
        $end = (clone $start)->modify('+'.rand(30, 120).' minutes');

        return [
            'inicio' => $this->faker->boolean(90),
            'hora_inicio' => $start->format('H:i:s'),
            'finalizado' => $this->faker->boolean(80),
            'hora_finalizacion' => $end->format('H:i:s'),
            'posicion_finalizacion' => $this->faker->numberBetween(1, 50),
            'alertado' => $this->faker->boolean(10),
            'alertas_disponibles' => $this->faker->numberBetween(0, 3),
            'defendido' => $this->faker->boolean(50),
            'estudiante_id' => User::factory()->estudiante(),
            'examen_id' => Examen::factory(),
            'caso_id' => Caso::factory(),
            'pregunta_id' => Pregunta::factory(),
        ];
    }
}
