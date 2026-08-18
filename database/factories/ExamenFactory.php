<?php

namespace Database\Factories;

use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examen>
 */
class ExamenFactory extends Factory
{
    protected $model = Examen::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'fecha' => $this->faker->date(),
            'codigo' => $this->faker->unique()->numberBetween(100000, 999999),
            'publicado' => $this->faker->boolean(),
            'finalizado' => $this->faker->boolean(),
            'defendido' => $this->faker->boolean(),
            'descargable' => $this->faker->boolean(),
            'retrocedible' => $this->faker->boolean(),
            'creado_por' => User::factory()->administrador(),
        ];
    }
}
