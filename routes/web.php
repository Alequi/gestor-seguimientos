<?php

use App\Http\Controllers\CsvValidationController;
use App\Http\Controllers\ExecutionReportController;
use App\Http\Controllers\NacexFulfillmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'homeUrl' => route('home'),
    'csvUpload' => [
        'validationUrl' => route('csv.validate'),
        'executionUrl' => route('fulfillments.execute'),
        'reportUrl' => route('reports.execution'),
        'maxFileSizeMb' => round((int) config('nacex.csv.max_size_kilobytes') / 1024, 2),
        'maxRows' => (int) config('nacex.csv.max_rows'),
        'requiredHeaders' => config('nacex.csv.required_headers'),
    ],
]))
    ->name('home');

Route::post('/csv/validate', CsvValidationController::class)
    ->name('csv.validate');

Route::post('/fulfillments/execute', NacexFulfillmentController::class)
    ->name('fulfillments.execute');

Route::post('/reports/execution', ExecutionReportController::class)
    ->name('reports.execution');
