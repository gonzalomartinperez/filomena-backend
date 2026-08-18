<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ModelCountCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('model_count')
            ->helpText('Total de filas por modelo clásico')
            ->label('model')
            ->value(fn () => $this->safeTuples('model_count', function () {
                $out = [];
                $push = function (string $label, string $table) use (&$out) {
                    if (Schema::hasTable($table)) {
                        $out[] = [(int) DB::table($table)->count(), [$label]];
                    }
                };
                $push('examen', 'examenes');
                $push('caso', 'casos');
                $push('pregunta', 'preguntas');
                $push('item', 'items');
                $push('seccion', 'secciones');
                $push('tabla_cotejo', 'tablas_cotejo');
                $push('usuario', 'users');
                $push('pseudonimo', 'pseudonimos');

                return $out;
            }));
    }
}
