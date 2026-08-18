<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMetricsBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.prometheus.token', '');

        if ($expected === '') {
            return $next($request);
        }

        $provided = (string) $request->bearerToken();

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            logger()->channel('prometheus')->notice('metrics.auth.denied', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'hasToken' => $provided !== '',
                'ua' => substr((string) $request->userAgent(), 0, 200),
            ]);

            return response('Unauthorized', 401)->withHeaders([
                'WWW-Authenticate' => 'Bearer',
            ]);
        }

        return $next($request);
    }
}
