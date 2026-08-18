<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    PrometheusServiceProvider::class,
    HealthServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
    FortifyServiceProvider::class,
];
