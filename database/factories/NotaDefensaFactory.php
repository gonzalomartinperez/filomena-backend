<?php

namespace Database\Factories;

use App\Models\Caso;
use App\Models\NotaDefensa;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaDefensaFactory extends Factory
{
    protected $model = NotaDefensa::class;

    public function definition(): array
    {
        return [
            'nota' => $this->faker->numberBetween(0, 20),
            'estudiante_id' => User::factory(),
            'caso_id' => Caso::factory(),
            'seccion_id' => Seccion::factory(),
        ];
    }
}
