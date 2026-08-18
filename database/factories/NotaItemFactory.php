<?php

namespace Database\Factories;

use App\Enums\Roles;
use App\Models\Item;
use App\Models\NotaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaItemFactory extends Factory
{
    protected $model = NotaItem::class;

    public function definition(): array
    {
        /** @var Item $item */
        $item = Item::inRandomOrder()->first()
            ?? Item::factory()->create();

        /** @var User $student */
        $student = User::role(Roles::ESTUDIANTE->value)
            ->inRandomOrder()->first()
            ?? User::factory()->estudiante()->create();

        return [
            'item_id' => $item->id,
            'estudiante_id' => $student->id,
            'aprobado' => $this->faker->boolean(80),
        ];
    }
}
