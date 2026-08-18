<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class ActivityEventsTotalCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('activity_events_total')
            ->helpText('Total de activity log agrupado por evento')
            ->label('event')
            ->value(fn () => $this->safeTuples('activity_events_total', function () {
                $conn = Config::get('activitylog.database_connection');
                $table = Config::get('activitylog.table_name', 'activity_log');
                if (! Schema::connection($conn)->hasTable($table)) {
                    return [];
                }
                $rows = DB::connection($conn)->table($table)
                    ->selectRaw("CASE WHEN event IS NULL OR event = '' THEN description ELSE event END AS ev, COUNT(*) AS total")
                    ->groupBy('ev')->get();

                return $rows->map(fn ($r) => [(int) $r->total, [(string) $r->ev]])->all();
            }));
    }
}
