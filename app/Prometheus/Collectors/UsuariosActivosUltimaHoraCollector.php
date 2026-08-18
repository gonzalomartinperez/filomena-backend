<?php

namespace App\Prometheus\Collectors;

use App\Models\User;
use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class UsuariosActivosUltimaHoraCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('usuarios_activos_ultima_hora')
            ->helpText('Usuarios con actividad registrada en los últimos 60 minutos')
            ->value(fn () => $this->safeNumber('usuarios_activos_ultima_hora', function () {
                if (! Schema::hasTable('activity_log')) {
                    return 0;
                }

                return (int) DB::table('activity_log')
                    ->where('causer_type', '=', User::class)
                    ->whereNotNull('causer_id')
                    ->where('created_at', '>=', now()->subHour())
                    ->distinct()
                    ->count('causer_id');
            }));
    }
}
