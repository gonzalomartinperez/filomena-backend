<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class PreguntasPorCasoCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('preguntas_por_caso')
            ->helpText('Cantidad de preguntas por caso')
            ->label('caso_id')
            ->value(fn () => $this->safeTuples('preguntas_por_caso', function () {
                if (! Schema::hasTable('preguntas')) {
                    return [];
                }
                $rows = DB::table('preguntas')
                    ->select('caso_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('caso_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->caso_id]])->all();
            }));
    }
}
