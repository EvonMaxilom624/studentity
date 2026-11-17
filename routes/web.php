<?php

use Illuminate\Support\Facades\Route;

// M0: safe, synthetic-only foundation endpoints. NO authentication, student, documents,
// certificates or other business endpoints are enabled by this overlay.
Route::get('/m0/health', fn () => response()->json([
    'status' => 'ok',
    'phase' => 'M0',
    'mode' => 'synthetic-only',
]))->name('m0.health');

Route::view('/', 'studentity-m0');
