<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyBoundaryController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/properties/boundaries', [PropertyBoundaryController::class, 'index']);
    Route::post('/visits', [VisitController::class, 'store']);
});
