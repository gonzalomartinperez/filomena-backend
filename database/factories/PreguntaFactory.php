<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Pregunta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pregunta>
 */
class PreguntaFactory extends Factory
{
    protected $model = Pregunta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consigna' => $this->faker->sentence(8),
            'descripcion' => $this->faker->paragraph(3),
            'orden_relativo' => $this->faker->numberBetween(1, 20),
            'examen_id' => Examen::factory(),
            'caso_id' => Caso::factory(),
        ];
    }
}
