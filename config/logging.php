<?php

use App\Logging\ApiProcessorsTap;
use Freshbitsweb\LaravelLogEnhancer\LogEnhancer;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$apiTaps = [
    ApiProcessorsTap::class,
    LogEnhancer::class,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily')),
            'ignore_exceptions' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/single/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'tap' => $apiTaps,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/daily/laravel-log-daily.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'tap' => $apiTaps,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_SLACK_LEVEL', 'critical'),
            'replace_placeholders' => true,
            'tap' => $apiTaps,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_PAPERTRAIL_LEVEL', 'notice'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            'level' => env('LOG_STDERR_LEVEL', 'warning'),
            'replace_placeholders' => true,
        ],

        'stderr_json' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            'formatter' => JsonFormatter::class,
            'level' => env('LOG_STDERR_LEVEL', 'warning'),
            'replace_placeholders' => true,
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'stdout' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stdout'],
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'stdout_json' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stdout'],
            'formatter' => JsonFormatter::class,
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
        ],

        'activity' => [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'with' => [
                'filename' => storage_path('logs/activity/laravel-log-activity.log'),
                'maxFiles' => env('ACTIVITY_LOG_DAYS', 30),
            ],
            'formatter' => JsonFormatter::class,
            'level' => env('ACTIVITY_LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
            'tap' => $apiTaps,
        ],

        'prometheus' => [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'with' => [
                'filename' => storage_path('logs/prometheus/laravel-log-prometheus.log'),
                'maxFiles' => 30,
            ],
            'formatter' => JsonFormatter::class,
            'level' => 'warning',
            'replace_placeholders' => true,
            'tap' => $apiTaps,
        ],

    ],

];
