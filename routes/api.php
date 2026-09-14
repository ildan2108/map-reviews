<?php

use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'data' => $request->user()->only(['id', 'name', 'email']),
        ]);
    });

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'update']);
});
