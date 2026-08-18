<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class TablasCotejoPorExamenCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('tablas_cotejo_por_examen')
            ->helpText('Cantidad de tablas de cotejo por examen')
            ->label('examen_id')
            ->value(fn () => $this->safeTuples('tablas_cotejo_por_examen', function () {
                if (! Schema::hasTable('tablas_cotejo')) {
                    return [];
                }
                $rows = DB::table('tablas_cotejo')
                    ->select('examen_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('examen_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->examen_id]])->all();
            }));
    }
}
