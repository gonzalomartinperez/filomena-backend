<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory; // TODO: Spatie

/**
 * @extends Factory<Caso>
 */
class CasoFactory extends Factory
{
    protected $model = Caso::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(4),
            'orden_relativo' => $this->faker->numberBetween(1, 10),
            'examen_id' => Examen::factory(),
            'evaluador_id' => User::factory()->evaluador(),
        ];
    }
}
