<?php

use Illuminate\Support\Facades\Route;
use Nsd7\AiMeter\Http\Controllers\DashboardController;
use Nsd7\AiMeter\Http\Middleware\Authorize;

Route::group([
    'prefix' => config('ai-meter.route.prefix', 'ai-meter'),
    'domain' => config('ai-meter.route.domain'),
    'middleware' => array_merge((array) config('ai-meter.route.middleware', ['web', 'auth']), [Authorize::class]),
    'as' => 'ai-meter.',
], function () {
    Route::get('/', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/calls', [DashboardController::class, 'calls'])->name('calls');
    Route::get('/calls/{call}', [DashboardController::class, 'show'])->whereNumber('call')->name('calls.show');
    Route::get('/runs', [DashboardController::class, 'runs'])->name('runs');
    Route::get('/runs/{run}', [DashboardController::class, 'runShow'])->whereNumber('run')->name('runs.show');
});
