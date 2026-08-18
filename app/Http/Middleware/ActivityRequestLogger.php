<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\LogBatch;
use Symfony\Component\HttpFoundation\Response;

final class ActivityRequestLogger
{
    /** Métodos que no registramos por defecto (idempotentes / infraestructura). */
    private const SKIP_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Headers que sí permitimos registrar (whitelist). */
    private const SAFE_HEADERS = [
        'x-request-id', 'x-forwarded-for', 'user-agent', 'referer',
        'accept', 'accept-encoding', 'accept-language', 'content-type',
    ];

    /** Claves sensibles a enmascarar en body/query/headers. */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'access_token', 'refresh_token', 'authorization',
        'cookie', 'cookies', 'session', 'api_key', 'secret', 'bearer',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Correlación
        $requestId = $this->resolveRequestId($request);
        $start = microtime(true);

        // Batch de Spatie para enlazar logs del mismo request
        LogBatch::startBatch();
        $batchId = LogBatch::getUuid();

        // Guardamos para acceso posterior (controladores, etc.)
        $request->attributes->set('activity_batch_uuid', $batchId);
        $request->attributes->set('request_id', $requestId);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            // Logueo de error de request
            $this->logHttp($request, null, $batchId, $requestId, microtime(true) - $start, $e);
            LogBatch::endBatch();

            throw $e;
        }

        // Logueo de request exitoso/erróneo según status
        $this->logHttp($request, $response, $batchId, $requestId, microtime(true) - $start, null);

        // Exponemos correlación al cliente
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Activity-Batch-Id', (string) $batchId);

        LogBatch::endBatch();

        return $response;
    }

    private function logHttp(
        Request $request,
        ?Response $response,
        string $batchId,
        string $requestId,
        float $durationSeconds,
        ?\Throwable $exception
    ): void {
        $method = strtoupper($request->getMethod());
        $status = $response?->getStatusCode() ?? 500;
        $isError = $exception !== null || $status >= 500;
        $logGets = (bool) config('activitylog.log_http_get', false);
        $shouldLog = $logGets || ! in_array($method, self::SKIP_METHODS, true);

        if (! $shouldLog) {
            return;
        }

        $domainCtx = $this->domainContextFromRequest($request);

        $props = [
            'request' => [
                'id' => $requestId,
                'ip' => $request->ip(),
                'method' => $method,
                'path' => '/'.$request->path(),
                'route' => [
                    'name' => $request->route()?->getName(),
                    'action' => optional($request->route())->getActionName(),
                    'params' => $this->sanitize($request->route()?->parameters() ?? []),
                ],
                'headers' => $this->sanitize($this->filterHeaders($request->headers->all())),
                'query' => $this->sanitize($request->query()),
                'payload' => $this->sanitize($request->request->all()),
                'domain' => $domainCtx,
                'duration_ms' => (int) round($durationSeconds * 1000),
            ],
            'response' => [
                'status' => $status,
                'content_type' => $response?->headers->get('content-type'),
            ],
            'exception' => $exception ? [
                'class' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500),
                'code' => (int) $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null,
            'batch_uuid' => $batchId,
        ];

        activity('http')
            ->event($isError ? 'http_error' : 'http_request')
            ->causedBy(Auth::user())
            ->withProperties($props)
            ->log($this->httpDescription($request, $response, $exception));

        try {
            Log::channel('activity')->info('http', [
                'log_name' => 'http',
                'event' => $isError ? 'http_error' : 'http_request',
                'description' => $this->httpDescription($request, $response, $exception),
                'batch_uuid' => $batchId,
                'created_at' => now()->toISOString(),
                'subject' => null,
                'causer' => optional(Auth::user())->only(['id', 'name', 'email']),
                'request' => Arr::except($props['request'], ['payload']),
                'status' => $status,
            ]);
        } catch (\Throwable) {
            // nunca romper el request por fallos de logging
        }
    }

    private function httpDescription(Request $request, ?Response $response, ?\Throwable $e): string
    {
        $base = sprintf('%s %s', strtoupper($request->getMethod()), '/'.$request->path());
        if ($e) {
            return $base.' → ERROR '.($response?->getStatusCode() ?? 500);
        }

        return $base.' → '.$response?->getStatusCode();
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get('X-Request-Id');

        return $incoming && is_string($incoming) ? $incoming : (string) Str::ulid();
    }

    private function filterHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $k => $v) {
            $flat[strtolower($k)] = is_array($v) ? (count($v) === 1 ? $v[0] : $v) : $v;
        }

        return Arr::only($flat, self::SAFE_HEADERS);
    }

    private function sanitize(array $data): array
    {
        $mask = function (&$value, $key) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $value = '***';

                return;
            }

            if (is_string($value) && mb_strlen($value) > 2000) {
                $value = mb_substr($value, 0, 2000).'…';
            }
        };

        array_walk_recursive($data, $mask);

        return $data;
    }

    private function domainContextFromRequest(Request $request): array
    {
        $keys = ['examen_id', 'caso_id', 'pregunta_id', 'seccion_id', 'tabla_cotejo_id', 'item_id', 'pseudonimo_id'];
        $ctx = [];

        foreach ($keys as $k) {
            $v = $request->route($k) ?? $request->query($k);
            if ($v !== null && $v !== '') {
                $ctx[$k] = is_numeric($v) ? (int) $v : $v;
            }
        }

        return $ctx;
    }
}
