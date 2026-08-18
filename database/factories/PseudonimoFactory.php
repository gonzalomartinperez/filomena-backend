<?php

namespace Database\Factories;

use App\Models\Pseudonimo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pseudonimo>
 */
class PseudonimoFactory extends Factory
{
    protected $model = Pseudonimo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->word(),
        ];
    }
}
