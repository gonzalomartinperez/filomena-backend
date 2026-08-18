<?php

use App\Enums\HTTP;
use App\Http\Controllers\Api\ApiBaseController;
use App\Http\Middleware\ActivityRequestLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(HandleCors::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->appendToGroup('api', ActivityRequestLogger::class);

        $proxies = env('TRUSTED_PROXIES', '*');
        $list = array_filter(array_map('trim', explode(',', $proxies)));
        $at = ($list === [] || $list === ['*']) ? '*' : $list;

        $headers = Request::HEADER_X_FORWARDED_FOR
                 | Request::HEADER_X_FORWARDED_HOST
                 | Request::HEADER_X_FORWARDED_PORT
                 | Request::HEADER_X_FORWARDED_PROTO;

        $middleware->trustProxies(
            at: $at,
            headers: $headers,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::BAD_REQUEST, []);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::NOT_FOUND, []);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::UNAUTHORIZED, []);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::FORBIDDEN, []);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::TOO_MANY_REQUESTS, []);
            }
        });

        $exceptions->render(function (SpatieUnauthorizedException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::UNAUTHORIZED, []);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::METHOD_NOT_ALLOWED, []);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::NOT_FOUND, []);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiBaseController::sendResponse(HTTP::INTERNAL_SERVER_ERROR, []);
            }
        });
    })
    ->create();
