<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'app')->where('any', '^(?!(?:api|sanctum)(?:/|$)).*$');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth');
