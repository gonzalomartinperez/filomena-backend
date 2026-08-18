<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::middleware(['web', 'auth', 'can:viewHealth'])->get('/health', HealthCheckResultsController::class);

Route::get('/', function () {
    return view('welcome');
});
