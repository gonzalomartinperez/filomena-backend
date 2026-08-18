<?php

namespace App\Traits;

trait SafeMetrics
{
    /** Loguea y evita romper /metrics cuando un gauge con labels falla. */
    private function safeTuples(string $metric, callable $fn): array
    {
        try {
            $res = $fn();

            return is_array($res) ? $res : [];
        } catch (\Throwable $e) {
            logger()->channel('prometheus')->warning('metrics.collect.error', [
                'metric' => $metric,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /** Loguea y evita romper /metrics cuando un gauge simple falla. */
    private function safeNumber(string $metric, callable $fn): float|int
    {
        try {
            return (float) $fn();
        } catch (\Throwable $e) {
            logger()->channel('prometheus')->warning('metrics.collect.error', [
                'metric' => $metric,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
