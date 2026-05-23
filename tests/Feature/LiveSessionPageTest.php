<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\Sport;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('live session page renders session status and athlete', function () {
    $athlete = Athlete::factory()->create(['name' => 'Casey Runner']);
    $sport = Sport::factory()->create(['name' => 'Run']);
    $session = TrackingSession::factory()
        ->for($athlete)
        ->for($sport)
        ->create([
            'status' => 'active',
            'last_seen_at' => now(),
        ]);

    $this->get("/live/{$session->session_token}")
        ->assertOk()
        ->assertSee('StridePulse Live')
        ->assertSee('Casey Runner')
        ->assertSee('active')
        ->assertSee('Last seen');
});

test('live session page shows latest telemetry summary', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'active',
        'last_seen_at' => now(),
        'ended_at' => null,
    ]);

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now()->subMinute(),
        'distance_m' => 1000,
        'heart_rate_bpm' => 130,
    ]);

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now(),
        'distance_m' => 5250,
        'pace_sec_per_km' => 304,
        'heart_rate_bpm' => 151,
        'cadence' => 176,
        'gps_status' => 'LOCK',
        'battery_percent' => 82,
        'latitude' => -33.9249,
        'longitude' => 18.4241,
    ]);

    $this->get("/live/{$session->session_token}")
        ->assertOk()
        ->assertSee('5.25')
        ->assertSee('5:04 /km')
        ->assertSee('151')
        ->assertSee('176')
        ->assertSee('LOCK')
        ->assertSee('82%')
        ->assertSee('LIVE')
        ->assertSee('GPS READY')
        ->assertSee('HR 151 BPM')
        ->assertSee('Location received')
        ->assertSee('-33.9249000')
        ->assertSee('18.4241000');
});

test('live session page shows device status and livetrack link when available', function () {
    $device = Device::factory()->create([
        'name' => 'Forerunner 965',
        'last_telemetry_at' => now()->subSeconds(8),
    ]);

    $session = TrackingSession::factory()->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'livetrack_url' => 'https://livetrack.garmin.com/session/example',
    ]);

    $this->get("/live/{$session->session_token}")
        ->assertOk()
        ->assertSee('Device Status')
        ->assertSee('Forerunner 965')
        ->assertSee('Last telemetry')
        ->assertDontSee('Garmin LiveTrack')
        ->assertSee('Open LiveTrack')
        ->assertSee('https://livetrack.garmin.com/session/example');
});

test('live session page shows waiting for movement while telemetry is live', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'active',
        'last_seen_at' => now(),
        'ended_at' => null,
    ]);

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now(),
        'distance_m' => null,
        'pace_sec_per_km' => null,
        'heart_rate_bpm' => 118,
        'cadence' => null,
        'gps_status' => 'LOCK',
        'battery_percent' => 91,
        'latitude' => -33.9249,
        'longitude' => 18.4241,
    ]);

    $this->get("/live/{$session->session_token}")
        ->assertOk()
        ->assertSee('Waiting for movement')
        ->assertSee('Movement not detected yet')
        ->assertSee('Pace appears after movement')
        ->assertSee('HR 118 BPM');
});

test('completed live page shows final state', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'completed',
        'ended_at' => now()->subMinutes(20),
        'last_seen_at' => now()->subMinutes(20),
    ]);

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now()->subMinutes(20),
        'distance_m' => 10000,
        'pace_sec_per_km' => 360,
        'heart_rate_bpm' => 150,
    ]);

    $this->get("/live/{$session->session_token}")
        ->assertOk()
        ->assertSee('COMPLETED')
        ->assertSee('activity is complete')
        ->assertDontSee('offline or stale');
});

test('live session page returns 404 for invalid token', function () {
    $this->get('/live/not-a-real-token')->assertNotFound();
});
