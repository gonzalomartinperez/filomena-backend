<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class PreguntasPorExamenCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('preguntas_por_examen')
            ->helpText('Cantidad de preguntas por examen')
            ->label('examen_id')
            ->value(fn () => $this->safeTuples('preguntas_por_examen', function () {
                if (! (Schema::hasTable('preguntas') && Schema::hasTable('casos'))) {
                    return [];
                }
                $rows = DB::table('preguntas')
                    ->join('casos', 'preguntas.caso_id', '=', 'casos.id')
                    ->select('casos.examen_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('casos.examen_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->examen_id]])->all();
            }));
    }
}
