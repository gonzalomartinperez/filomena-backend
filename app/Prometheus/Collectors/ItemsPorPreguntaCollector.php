<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ItemsPorPreguntaCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('items_por_pregunta')
            ->helpText('Cantidad de items por pregunta')
            ->label('pregunta_id')
            ->value(fn () => $this->safeTuples('items_por_pregunta', function () {
                if (! Schema::hasTable('items')) {
                    return [];
                }
                $rows = DB::table('items')
                    ->select('pregunta_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('pregunta_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->pregunta_id]])->all();
            }));
    }
}
