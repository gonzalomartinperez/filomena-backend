<?php

namespace App\Providers;

use App\Prometheus\Collectors\ActivityEventsTotalCollector;
use App\Prometheus\Collectors\CasosPorExamenCollector;
use App\Prometheus\Collectors\HealthCheckStatusCollector;
use App\Prometheus\Collectors\HealthIsOkCollector;
use App\Prometheus\Collectors\ItemsPorCasoCollector;
use App\Prometheus\Collectors\ItemsPorExamenCollector;
use App\Prometheus\Collectors\ItemsPorPreguntaCollector;
use App\Prometheus\Collectors\ItemsPorSeccionCollector;
use App\Prometheus\Collectors\ItemsPorSeccionYCasoCollector;
use App\Prometheus\Collectors\ModelCountCollector;
use App\Prometheus\Collectors\PreguntasPorCasoCollector;
use App\Prometheus\Collectors\PreguntasPorExamenCollector;
use App\Prometheus\Collectors\SeccionesPorExamenCollector;
use App\Prometheus\Collectors\TablasCotejoPorExamenCollector;
use App\Prometheus\Collectors\UsuariosActivosUltimaHoraCollector;
use App\Prometheus\Collectors\UsuariosPorExamenCollector;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedJobsPerHourCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Prometheus::registerCollectorClasses([
            ModelCountCollector::class,
            UsuariosPorExamenCollector::class,
            CasosPorExamenCollector::class,
            PreguntasPorExamenCollector::class,
            PreguntasPorCasoCollector::class,
            ItemsPorExamenCollector::class,
            ItemsPorPreguntaCollector::class,
            ItemsPorCasoCollector::class,
            SeccionesPorExamenCollector::class,
            TablasCotejoPorExamenCollector::class,
            ItemsPorSeccionCollector::class,
            ItemsPorSeccionYCasoCollector::class,
            UsuariosActivosUltimaHoraCollector::class,
            ActivityEventsTotalCollector::class,
            HealthCheckStatusCollector::class,
            HealthIsOkCollector::class,
        ]);

        $this->registerHorizonCollectorsSafely();
    }

    private function registerHorizonCollectorsSafely(): void
    {
        if (! class_exists(Horizon::class)) {
            return;
        }
        if (! extension_loaded('redis')) {
            logger()->channel('prometheus')->warning('metrics.collect.warn', [
                'metric' => 'horizon_collectors',
                'message' => 'Redis extension not loaded; Horizon collectors skipped',
            ]);

            return;
        }
        try {
            Prometheus::registerCollectorClasses([
                CurrentMasterSupervisorCollector::class,
                CurrentProcessesPerQueueCollector::class,
                CurrentWorkloadCollector::class,
                FailedJobsPerHourCollector::class,
                HorizonStatusCollector::class,
                JobsPerMinuteCollector::class,
                RecentJobsCollector::class,
            ]);
        } catch (\Throwable $e) {
            logger()->channel('prometheus')->warning('metrics.collect.error', [
                'metric' => 'horizon_collectors',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
