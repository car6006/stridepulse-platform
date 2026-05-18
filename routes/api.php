<?php

use App\Http\Controllers\GarminTelemetryController;
use App\Http\Controllers\LiveTrackInboundEmailController;
use App\Http\Controllers\TrackingSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/garmin/telemetry', [GarminTelemetryController::class, 'store']);
Route::post('/inbound/livetrack-email', [LiveTrackInboundEmailController::class, 'store']);
Route::post('/tracking-sessions/start', [TrackingSessionController::class, 'start']);
Route::get('/tracking-sessions/{session_token}/status', [TrackingSessionController::class, 'status']);
