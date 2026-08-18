<?php

namespace Database\Factories;

use App\Models\Examen;
use App\Models\Pseudonimo;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pseudonimo>
 */
class SeccionFactory extends Factory
{
    protected $model = Seccion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->word(),
            'orden_relativo' => $this->faker->numberBetween(1, 10),
            'examen_id' => Examen::factory(),
        ];
    }
}
