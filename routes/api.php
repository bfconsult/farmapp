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

// LoRaWAN gateway events (docs/lorawan-ingest-spec.md). Authenticated by
// each gateway's own key in a header, not Sanctum, and rate limited per key
// instead of the group's 60/min per IP.
Route::post('/lorawan/{event}', [LorawanIngestController::class, 'store'])
    ->name('lorawan.ingest')
    ->whereIn('event', ['uplink', 'join', 'ack', 'status'])
    ->middleware([VerifyLorawanKey::class, 'throttle:lorawan'])
    ->withoutMiddleware('throttle:api');
