<?php

use App\Models\Athlete;
use App\Models\Sport;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('start creates active tracking session', function () {
    $athlete = Athlete::factory()->create();
    $sport = Sport::factory()->create(['name' => 'Run']);

    $this->postJson('/api/tracking-sessions/start', [
        'athlete_id' => $athlete->id,
        'sport_id' => $sport->id,
    ])->assertCreated();

    $this->assertDatabaseHas('tracking_sessions', [
        'athlete_id' => $athlete->id,
        'sport_id' => $sport->id,
        'device_source' => 'garmin_connect_iq',
        'activity_type' => 'run',
        'status' => 'active',
        'telemetry_source' => 'connect_iq',
    ]);
});

test('start returns session token and setup data', function () {
    $athlete = Athlete::factory()->create();
    $sport = Sport::factory()->create();

    $response = $this->postJson('/api/tracking-sessions/start', [
        'athlete_id' => $athlete->id,
        'sport_id' => $sport->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'ok',
            'status',
            'session_token',
            'tracking_session',
            'garmin_setup_token',
            'livetrack_inbound_alias',
            'telemetry_endpoint_url',
        ])
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('telemetry_endpoint_url', url('/api/garmin/telemetry'));

    $payload = $response->json();

    expect($payload['session_token'])->not->toBeEmpty()
        ->and($payload['garmin_setup_token'])->toBe($payload['session_token'])
        ->and($payload['livetrack_inbound_alias'])->toBe($payload['session_token']);
});

test('status returns active session', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'active',
        'livetrack_url' => 'https://livetrack.garmin.com/session/example',
    ]);

    $this->getJson("/api/tracking-sessions/{$session->session_token}/status")
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('session_token', $session->session_token)
        ->assertJsonPath('tracking_session', $session->uuid)
        ->assertJsonPath('livetrack_url', 'https://livetrack.garmin.com/session/example');
});

test('status includes latest telemetry when available', function () {
    $session = TrackingSession::factory()->create();

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now()->subMinute(),
        'heart_rate_bpm' => 140,
        'distance_m' => 1000,
    ]);

    TelemetryPoint::factory()->for($session, 'trackingSession')->create([
        'recorded_at' => now(),
        'elapsed_seconds' => 1800,
        'distance_m' => 5000,
        'pace_sec_per_km' => 300,
        'heart_rate_bpm' => 152,
        'avg_heart_rate_bpm' => 145,
        'cadence' => 174,
        'latitude' => -33.9249,
        'longitude' => 18.4241,
        'gps_status' => 'LOCK',
        'battery_percent' => 81,
        'device_model' => 'fr965',
    ]);

    $this->getJson("/api/tracking-sessions/{$session->session_token}/status")
        ->assertOk()
        ->assertJsonPath('latest_telemetry.elapsed_seconds', 1800)
        ->assertJsonPath('latest_telemetry.distance_m', '5000.00')
        ->assertJsonPath('latest_telemetry.pace_sec_per_km', 300)
        ->assertJsonPath('latest_telemetry.heart_rate_bpm', 152)
        ->assertJsonPath('latest_telemetry.gps_status', 'LOCK')
        ->assertJsonPath('latest_telemetry.battery_percent', 81)
        ->assertJsonPath('latest_telemetry.device_model', 'fr965');
});

test('invalid token returns 404', function () {
    $this->getJson('/api/tracking-sessions/not-a-real-token/status')
        ->assertNotFound()
        ->assertJson([
            'ok' => false,
            'status' => 'not_found',
        ]);
});
