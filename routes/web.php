<?php

use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\GarminSetupController;
use App\Http\Controllers\LiveSessionsController;
use App\Http\Controllers\TrackingSessionWebController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/live/{session_token}', [LiveSessionController::class, 'show'])->name('live.session');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::resource('athletes', AthleteController::class)->except(['show']);
    Route::get('devices/unclaimed', [DeviceController::class, 'unclaimed'])->name('devices.unclaimed');
    Route::post('devices/{device}/claim', [DeviceController::class, 'claim'])->name('devices.claim');
    Route::resource('devices', DeviceController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('garmin-setup', [GarminSetupController::class, 'index'])->name('garmin-setup.index');
    Route::post('garmin-setup/token', [GarminSetupController::class, 'generate'])->name('garmin-setup.generate');
    Route::get('tracking-sessions/start', [TrackingSessionWebController::class, 'create'])->name('tracking-sessions.create');
    Route::post('tracking-sessions/start', [TrackingSessionWebController::class, 'store'])->name('tracking-sessions.store');
    Route::get('live-sessions', [LiveSessionsController::class, 'index'])->name('live-sessions.index');
    Route::post('live-sessions/{trackingSession}/complete', [LiveSessionsController::class, 'complete'])->name('live-sessions.complete');
    Route::post('live-sessions/{trackingSession}/discard', [LiveSessionsController::class, 'discard'])->name('live-sessions.discard');
});

require __DIR__.'/settings.php';
