<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ItemsPorExamenCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('items_por_examen')
            ->helpText('Cantidad de items por examen')
            ->label('examen_id')
            ->value(fn () => $this->safeTuples('items_por_examen', function () {
                if (! (Schema::hasTable('items') && Schema::hasTable('preguntas') && Schema::hasTable('casos'))) {
                    return [];
                }
                $rows = DB::table('items')
                    ->join('preguntas', 'items.pregunta_id', '=', 'preguntas.id')
                    ->join('casos', 'preguntas.caso_id', '=', 'casos.id')
                    ->select('casos.examen_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('casos.examen_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->examen_id]])->all();
            }));
    }
}
