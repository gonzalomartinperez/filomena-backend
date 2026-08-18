<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class HealthCheckStatusCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('health_check_status')
            ->helpText('Estado de cada health check: 0 OK, 1 WARN, 2 FAIL')
            ->label('check')
            ->value(fn () => $this->safeTuples('health_check_status', function () {
                $instance = EloquentHealthResultStore::getHistoryItemInstance();
                $connection = $instance->getConnectionName(); // puede ser null
                $table = $instance->getTable();

                if (! Schema::connection($connection)->hasTable($table)) {
                    return [];
                }

                // Tomamos el último batch por created_at (igual que tu provider actual)
                $latestBatch = DB::connection($connection)
                    ->table($table)->orderByDesc('created_at')->value('batch');

                if (! $latestBatch) {
                    return [];
                }

                $rows = DB::connection($connection)
                    ->table($table)
                    ->where('batch', $latestBatch)
                    ->select('check_name', 'status')
                    ->get();

                $mapStatus = static function (string $s): int {
                    return match (strtolower($s)) {
                        'ok' => 0,
                        'warning' => 1,
                        'failed', 'unknown', 'crashed', 'skipped' => 2,
                        default => 2,
                    };
                };

                return $rows->map(fn ($r) => [
                    (float) $mapStatus((string) $r->status),
                    [(string) $r->check_name],
                ])->all();
            }));
    }
}
