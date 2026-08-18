<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Seccion;
use App\Models\TablaCotejo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TablaCotejo>
 */
class TablaCotejoFactory extends Factory
{
    protected $model = TablaCotejo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'examen_id' => Examen::factory(),
            'caso_id' => Caso::factory(),
            'seccion_id' => Seccion::factory(),
        ];
    }
}
