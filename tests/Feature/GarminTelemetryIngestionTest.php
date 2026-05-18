<?php

use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function garminTelemetryPayload(array $overrides = []): array
{
    return array_merge([
        'session_token' => 'garmin-session-token',
        'ingestion_id' => 'ingestion-001',
        'recorded_at' => '2026-05-17T17:00:00Z',
        'elapsed_seconds' => 1234,
        'distance_m' => 4321.5,
        'pace_sec_per_km' => 315,
        'heart_rate_bpm' => 148,
        'avg_heart_rate_bpm' => 142,
        'cadence' => 172,
        'latitude' => -33.9249,
        'longitude' => 18.4241,
        'gps_status' => 'LOCK',
        'battery_percent' => 87,
        'device_model' => 'fr965',
        'raw_payload' => [
            'source' => 'connect_iq_sim',
            'debug' => true,
        ],
    ], $overrides);
}

test('valid payload creates telemetry point', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $response = $this->postJson('/api/garmin/telemetry', garminTelemetryPayload());

    $response
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'status' => 'received',
            'message' => 'Pulse received',
        ]);

    $this->assertDatabaseHas('telemetry_points', [
        'tracking_session_id' => $session->id,
        'ingestion_id' => 'ingestion-001',
        'elapsed_seconds' => 1234,
        'pace_sec_per_km' => 315,
        'heart_rate_bpm' => 148,
        'gps_status' => 'LOCK',
        'battery_percent' => 87,
        'device_model' => 'fr965',
    ]);
});

test('invalid session token returns 404', function () {
    $response = $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'session_token' => 'missing-token',
    ]));

    $response
        ->assertNotFound()
        ->assertJson([
            'ok' => false,
            'status' => 'not_found',
        ]);
});

test('duplicate ingestion id does not create duplicate telemetry point', function () {
    TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $payload = garminTelemetryPayload(['ingestion_id' => 'dedupe-001']);

    $this->postJson('/api/garmin/telemetry', $payload)->assertOk();
    $this->postJson('/api/garmin/telemetry', $payload)->assertOk();

    expect(TelemetryPoint::query()->where('ingestion_id', 'dedupe-001')->count())->toBe(1);
});

test('last seen at updates', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'last_seen_at' => null,
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload())->assertOk();

    expect($session->fresh()->last_seen_at)->not->toBeNull();
});

test('raw payload is stored', function () {
    TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $payload = garminTelemetryPayload([
        'raw_payload' => [
            'hr_raw' => 148,
            'gps_raw' => ['status' => 'LOCK'],
        ],
    ]);

    $this->postJson('/api/garmin/telemetry', $payload)->assertOk();

    $point = TelemetryPoint::query()->firstOrFail();

    expect($point->raw_payload)
        ->toHaveKey('session_token', 'garmin-session-token')
        ->toHaveKey('raw_payload')
        ->and($point->raw_payload['raw_payload'])
        ->toBe([
            'hr_raw' => 148,
            'gps_raw' => ['status' => 'LOCK'],
        ]);
});
