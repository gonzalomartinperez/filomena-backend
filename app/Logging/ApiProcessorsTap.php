<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;

class ApiProcessorsTap
{
    public function __invoke(Logger $logger): void
    {
        $format = "[%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%\n";
        $dateFormat = 'd/m/Y H:i:s';

        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter($format, $dateFormat, true, true, true));

            $handler->pushProcessor(new UidProcessor);
            $handler->pushProcessor(new MemoryUsageProcessor);
            $handler->pushProcessor(new MemoryPeakUsageProcessor);
            $handler->pushProcessor(new ProcessIdProcessor);

            $handler->pushProcessor(function ($record) {
                if ($record['level'] >= Level::Warning) {
                    $proc = new IntrospectionProcessor;

                    return $proc($record);
                }

                return $record;
            });

            $handler->pushProcessor(new WebProcessor);
        }
    }
}
