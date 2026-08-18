<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ItemsPorCasoCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('items_por_caso')
            ->helpText('Cantidad de items por caso')
            ->label('caso_id')
            ->value(fn () => $this->safeTuples('items_por_caso', function () {
                if (! (Schema::hasTable('items') && Schema::hasTable('preguntas'))) {
                    return [];
                }
                $rows = DB::table('items')
                    ->join('preguntas', 'items.pregunta_id', '=', 'preguntas.id')
                    ->select('preguntas.caso_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('preguntas.caso_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->caso_id]])->all();
            }));
    }
}
