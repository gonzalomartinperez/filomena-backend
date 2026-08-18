<?php

namespace Database\Factories;

use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RespuestaEstudiante>
 */
class RespuestaEstudianteFactory extends Factory
{
    protected $model = RespuestaEstudiante::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'respuesta' => $this->faker->paragraph(2),
            'pregunta_id' => Pregunta::factory(),
            // 'estudiante_id' => User::factory()->estudiante(),
        ];
    }
}
