<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LorawanIngestController;
use App\Http\Controllers\Api\PropertyBoundaryController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Middleware\VerifyLorawanKey;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/properties/boundaries', [PropertyBoundaryController::class, 'index']);
    Route::post('/visits', [VisitController::class, 'store']);
});

// LoRaWAN gateway events (docs/lorawan-ingest-spec.md). Authenticated by a
// shared-secret header, not Sanctum. Every request comes from the farm's one
// Starlink IP, so this uses its own limit instead of the group's 60/min.
Route::post('/lorawan/{event}', [LorawanIngestController::class, 'store'])
    ->whereIn('event', ['uplink', 'join', 'ack', 'status'])
    ->middleware([VerifyLorawanKey::class, 'throttle:120,1'])
    ->withoutMiddleware('throttle:api');
