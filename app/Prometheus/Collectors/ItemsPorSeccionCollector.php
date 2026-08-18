<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ItemsPorSeccionCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('items_por_seccion')
            ->helpText('Cantidad de items por sección')
            ->label('seccion_id')
            ->value(fn () => $this->safeTuples('items_por_seccion', function () {
                if (! Schema::hasTable('items')) {
                    return [];
                }
                $rows = DB::table('items')
                    ->select('seccion_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('seccion_id')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->seccion_id]])->all();
            }));
    }
}
