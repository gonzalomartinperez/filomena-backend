<?php

namespace Database\Seeders;

use App\Models\Pseudonimo;
use Illuminate\Database\Seeder;

class PseudonimosSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 1000; $i++) {
            $nombre = (string) $i;

            Pseudonimo::query()->updateOrCreate(
                ['nombre' => $nombre],
                []
            );
        }
    }
}
