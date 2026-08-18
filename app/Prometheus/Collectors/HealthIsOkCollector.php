<?php

namespace App\Prometheus\Collectors;

use App\Traits\SafeMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class HealthIsOkCollector implements Collector
{
    use SafeMetrics;

    public function register(): void
    {
        Prometheus::addGauge('health_is_ok')
            ->helpText('1 si todos los checks están OK en el último batch')
            ->value(fn () => $this->safeNumber('health_is_ok', function () {
                $instance = EloquentHealthResultStore::getHistoryItemInstance();
                $connection = $instance->getConnectionName();
                $table = $instance->getTable();

                if (! Schema::connection($connection)->hasTable($table)) {
                    return 0;
                }

                $db = DB::connection($connection);
                $latestBatch = $db->table($table)->orderByDesc('created_at')->value('batch');
                if (! $latestBatch) {
                    return 0;
                }

                $hasIssues = $db->table($table)
                    ->where('batch', $latestBatch)
                    ->whereIn('status', ['warning', 'failed', 'unknown', 'crashed', 'skipped'])
                    ->exists();

                return $hasIssues ? 0 : 1;
            }));
    }
}
