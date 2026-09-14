<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return response()->json([
        'data' => $request->user()->only(['id', 'name', 'email']),
    ]);
})->middleware('auth:sanctum');
