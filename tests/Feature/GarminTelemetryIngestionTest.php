test('optional telemetry fields can be null', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $payload = garminTelemetryPayload([
        'altitude_m' => null,
        'heading_degrees' => null,
        'average_pace_sec_per_km' => null,
        'ascent_m' => null,
        'descent_m' => null,
        'elapsed_time_seconds' => null,
        'device_model' => null,
    ]);

    $response = $this->postJson('/api/garmin/telemetry', $payload);
    $response->assertOk();
    $point = TelemetryPoint::query()->firstOrFail();
    expect($point->altitude_m)->toBeNull()
        ->and($point->heading_degrees)->toBeNull()
        ->and($point->average_pace_sec_per_km)->toBeNull()
        ->and($point->ascent_m)->toBeNull()
        ->and($point->descent_m)->toBeNull()
        ->and($point->elapsed_time_seconds)->toBeNull()
        ->and($point->device_model)->toBeNull();
});

test('unrealistic ascent/descent are ignored', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $payload = garminTelemetryPayload([
        'ascent_m' => 999999,
        'descent_m' => -100,
    ]);

    $response = $this->postJson('/api/garmin/telemetry', $payload);
    $response->assertOk();
    $point = TelemetryPoint::query()->firstOrFail();
    expect($point->ascent_m)->toBeNull()
        ->and($point->descent_m)->toBeNull();
});

test('required fields are enforced', function () {
    $response = $this->postJson('/api/garmin/telemetry', [
        // missing session_token and recorded_at
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['session_token', 'recorded_at']);
});

test('successful ingestion with edge values', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $payload = garminTelemetryPayload([
        'ascent_m' => 9999.99,
        'descent_m' => 0,
        'altitude_m' => 8848,
        'heading_degrees' => 360,
        'average_pace_sec_per_km' => 3599,
        'elapsed_time_seconds' => 86399,
        'device_model' => str_repeat('a', 255),
    ]);

    $response = $this->postJson('/api/garmin/telemetry', $payload);
    $response->assertOk();
    $point = TelemetryPoint::query()->firstOrFail();
    expect((float) $point->ascent_m)->toBe(9999.99)
        ->and((float) $point->descent_m)->toBe(0.0)
        ->and((float) $point->altitude_m)->toBe(8848.0)
        ->and((float) $point->heading_degrees)->toBe(360.0)
        ->and((int) $point->average_pace_sec_per_km)->toBe(3599)
        ->and((int) $point->elapsed_time_seconds)->toBe(86399)
        ->and($point->device_model)->toBe(str_repeat('a', 255));
});
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
        'elapsed_time_seconds' => 1235,
        'distance_m' => 4321.5,
        'pace_sec_per_km' => 315,
        'average_pace_sec_per_km' => 330,
        'current_speed_mps' => 3.175,
        'heart_rate_bpm' => 148,
        'avg_heart_rate_bpm' => 142,
        'cadence' => 172,
        'latitude' => -33.9249,
        'longitude' => 18.4241,
        'altitude_m' => 47.5,
        'heading_degrees' => 186.25,
        'ascent_m' => 82.4,
        'descent_m' => 79.2,
        'calories' => 410,
        'lap_number' => 3,
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
        'elapsed_time_seconds' => 1235,
        'pace_sec_per_km' => 315,
        'average_pace_sec_per_km' => 330,
        'heart_rate_bpm' => 148,
        'gps_status' => 'LOCK',
        'battery_percent' => 87,
        'device_model' => 'fr965',
        'calories' => 410,
        'lap_number' => 3,
    ]);

    $point = TelemetryPoint::query()->firstOrFail();

    expect((float) $point->current_speed_mps)->toBe(3.175)
        ->and((float) $point->altitude_m)->toBe(47.5)
        ->and((float) $point->heading_degrees)->toBe(186.25)
        ->and((float) $point->ascent_m)->toBe(82.4)
        ->and((float) $point->descent_m)->toBe(79.2);
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
