<?php

use App\Http\Controllers\CsvValidationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'homeUrl' => route('home'),
    'csvUpload' => [
        'validationUrl' => route('csv.validate'),
        'maxFileSizeMb' => round((int) config('nacex.csv.max_size_kilobytes') / 1024, 2),
        'maxRows' => (int) config('nacex.csv.max_rows'),
        'requiredHeaders' => config('nacex.csv.required_headers'),
    ],
]))
    ->name('home');

Route::post('/csv/validate', CsvValidationController::class)
    ->name('csv.validate');
