<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Item;
use App\Models\Pregunta;
use App\Models\Seccion;
use App\Models\TablaCotejo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'descripcion' => $this->faker->sentence(6),
            'valor' => $this->faker->randomFloat(2, 0, 3),
            'examen_id' => Examen::factory(),
            'seccion_id' => Seccion::factory(),
            'tabla_cotejo_id' => TablaCotejo::factory(),
            'caso_id' => Caso::factory(),
            'pregunta_id' => Pregunta::factory(),
        ];
    }
}
