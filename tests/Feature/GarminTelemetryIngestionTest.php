<?php

use App\Models\Athlete;
use App\Models\AthleteActivity;
use App\Models\Device;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use App\Services\TrackingSessionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

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

test('active telemetry still works', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'activity_state' => 'active',
    ]))->assertOk();

    expect($session->fresh()->status)->toBe('active')
        ->and($session->fresh()->ended_at)->toBeNull()
        ->and(TelemetryPoint::query()->count())->toBe(1);
});

test('stopped telemetry marks session stopped without ending it', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'activity_state' => 'stopped',
        'recorded_at' => '2026-05-17T18:00:00Z',
    ]))->assertOk();

    $session->refresh();

    expect($session->status)->toBe('stopped')
        ->and($session->ended_at)->toBeNull()
        ->and($session->notification_suppressed_at)->toBeNull()
        ->and(AthleteActivity::query()->count())->toBe(0);
});

test('active telemetry after stopped resumes the same session', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'stopped-before-resume',
        'activity_state' => 'stopped',
        'recorded_at' => '2026-05-17T18:00:00Z',
        'distance_m' => 5000,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'active-after-stopped',
        'activity_state' => 'active',
        'recorded_at' => '2026-05-17T18:01:00Z',
        'distance_m' => 5050,
    ]))->assertOk();

    $session->refresh();

    expect($session->status)->toBe('active')
        ->and($session->ended_at)->toBeNull()
        ->and($session->notification_suppressed_at)->toBeNull()
        ->and(TelemetryPoint::query()->where('tracking_session_id', $session->id)->count())->toBe(2);
});

test('resumed telemetry after paused continues the same session', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'paused-before-resume',
        'activity_state' => 'paused',
        'recorded_at' => '2026-05-17T18:00:00Z',
        'distance_m' => 5000,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'resumed-after-paused',
        'activity_state' => 'resumed',
        'recorded_at' => '2026-05-17T18:01:00Z',
        'distance_m' => 5050,
    ]))->assertOk();

    $session->refresh();

    expect($session->status)->toBe('active')
        ->and($session->ended_at)->toBeNull()
        ->and(TelemetryPoint::query()->where('tracking_session_id', $session->id)->count())->toBe(2);
});

test('completed telemetry creates activity record', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'started_at' => '2026-05-17 17:00:00',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'activity-start',
        'recorded_at' => '2026-05-17T17:00:00Z',
        'elapsed_seconds' => 0,
        'elapsed_time_seconds' => 0,
        'distance_m' => 0,
        'heart_rate_bpm' => 120,
        'latitude' => -33.9,
        'longitude' => 18.4,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'activity-complete',
        'activity_state' => 'completed',
        'recorded_at' => '2026-05-17T18:00:00Z',
        'elapsed_seconds' => 3600,
        'elapsed_time_seconds' => 3600,
        'distance_m' => 10000,
        'pace_sec_per_km' => 360,
        'average_pace_sec_per_km' => 365,
        'heart_rate_bpm' => 160,
        'avg_heart_rate_bpm' => 145,
        'calories' => 700,
        'ascent_m' => 120,
        'descent_m' => 115,
        'latitude' => -33.91,
        'longitude' => 18.41,
    ]))->assertOk();

    $session->refresh();
    $activity = AthleteActivity::query()->firstOrFail();

    expect($session->status)->toBe('completed')
        ->and($session->ended_at?->toIso8601String())->toBe('2026-05-17T18:00:00+00:00')
        ->and($activity->tracking_session_id)->toBe($session->id)
        ->and($activity->athlete_id)->toBe($session->athlete_id)
        ->and($activity->sport_id)->toBe($session->sport_id)
        ->and($activity->status)->toBe('completed')
        ->and($activity->duration_seconds)->toBe(3600)
        ->and((float) $activity->distance_m)->toBe(10000.0)
        ->and($activity->average_pace_sec_per_km)->toBe(360)
        ->and($activity->average_heart_rate_bpm)->toBe(140)
        ->and($activity->max_heart_rate_bpm)->toBe(160)
        ->and($activity->calories)->toBe(700)
        ->and((float) $activity->ascent_m)->toBe(120.0)
        ->and((float) $activity->descent_m)->toBe(115.0)
        ->and((float) $activity->start_latitude)->toBe(-33.9)
        ->and((float) $activity->end_latitude)->toBe(-33.91)
        ->and($activity->summary_payload['telemetry_points_count'])->toBe(2);
});

test('saved telemetry ends session and creates activity record', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'started_at' => '2026-05-17 17:00:00',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'activity-saved',
        'activity_state' => 'saved',
        'recorded_at' => '2026-05-17T18:00:00Z',
        'elapsed_seconds' => 3600,
        'elapsed_time_seconds' => 3600,
        'distance_m' => 10000,
    ]))->assertOk();

    $session->refresh();
    $activity = AthleteActivity::query()->firstOrFail();

    expect($session->status)->toBe('saved')
        ->and($session->ended_at?->toIso8601String())->toBe('2026-05-17T18:00:00+00:00')
        ->and($session->notification_suppressed_at)->not->toBeNull()
        ->and($activity->tracking_session_id)->toBe($session->id)
        ->and($activity->status)->toBe('completed')
        ->and($activity->duration_seconds)->toBe(3600)
        ->and((float) $activity->distance_m)->toBe(10000.0);
});

test('discarded telemetry marks session ended without creating public final summary', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'activity_state' => 'discarded',
        'recorded_at' => '2026-05-17T18:00:00Z',
    ]))->assertOk();

    $session->refresh();

    expect($session->status)->toBe('discarded')
        ->and($session->ended_at?->toIso8601String())->toBe('2026-05-17T18:00:00+00:00')
        ->and(AthleteActivity::query()->count())->toBe(0);
});

test('no movement for configured window marks session stationary', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
        'status' => 'active',
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'stationary-start',
        'recorded_at' => '2026-05-17T17:00:00Z',
        'distance_m' => 1000,
        'latitude' => -33.9249000,
        'longitude' => 18.4241000,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'ingestion_id' => 'stationary-later',
        'recorded_at' => '2026-05-17T17:05:01Z',
        'distance_m' => 1005,
        'latitude' => -33.9249100,
        'longitude' => 18.4241100,
    ]))->assertOk();

    expect($session->fresh()->status)->toBe('stationary');
});

test('no telemetry for abandon threshold marks session abandoned via service', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'active',
        'ended_at' => null,
        'last_direct_telemetry_at' => Carbon::parse('2026-05-17T17:00:00Z'),
    ]);

    app(TrackingSessionLifecycleService::class)->evaluateAbandoned(
        $session,
        Carbon::parse('2026-05-17T18:00:01Z'),
    );

    $session->refresh();

    expect($session->status)->toBe('abandoned')
        ->and($session->ended_at?->toIso8601String())->toBe('2026-05-17T18:00:01+00:00')
        ->and($session->notification_suppressed_at)->not->toBeNull();
});

test('progress notifications are blocked after terminal states', function (string $state) {
    $session = TrackingSession::factory()->create([
        'status' => $state,
        'ended_at' => now(),
    ]);

    expect(app(TrackingSessionLifecycleService::class)->canSendSupporterUpdate($session, 'progress'))->toBeFalse();
})->with(['saved', 'completed', 'discarded', 'abandoned']);

test('stopped sessions are not treated as terminal for telemetry lifecycle', function () {
    $session = TrackingSession::factory()->create([
        'status' => 'stopped',
        'ended_at' => null,
        'notification_suppressed_at' => null,
    ]);

    expect(app(TrackingSessionLifecycleService::class)->canSendSupporterUpdate($session, 'progress'))->toBeFalse()
        ->and($session->fresh()->ended_at)->toBeNull()
        ->and($session->fresh()->notification_suppressed_at)->toBeNull();
});

test('stationary alert is allowed only once per cooldown window', function () {
    $service = app(TrackingSessionLifecycleService::class);
    $session = TrackingSession::factory()->create([
        'status' => 'stationary',
        'ended_at' => null,
    ]);

    expect($service->canSendSupporterUpdate($session, 'stationary', true, Carbon::parse('2026-05-17T17:00:00Z')))->toBeTrue()
        ->and($service->canSendSupporterUpdate($session, 'stationary', true, Carbon::parse('2026-05-17T17:10:00Z')))->toBeFalse()
        ->and($service->canSendSupporterUpdate($session, 'stationary', true, Carbon::parse('2026-05-17T17:15:01Z')))->toBeTrue();
});

test('telemetry is accepted for correct device and session', function () {
    $device = Device::factory()->create();
    $session = TrackingSession::factory()->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'device_uuid' => $device->uuid,
        'device_secret' => $device->device_secret,
    ]))->assertOk();

    expect(TelemetryPoint::query()->count())->toBe(1)
        ->and(TelemetryPoint::query()->first()->tracking_session_id)->toBe($session->id)
        ->and($device->fresh()->last_seen_at)->not->toBeNull();
});

test('unknown device telemetry creates unclaimed device', function () {
    TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'device_uuid' => 'sp-fr965-sim-discovery-001',
        'device_secret' => 'discovered-secret',
        'device_model' => 'fr965_sim',
        'app_version' => '1.0.0',
    ]))->assertOk();

    $device = Device::query()->where('device_uuid', 'sp-fr965-sim-discovery-001')->firstOrFail();

    expect($device->status)->toBe('unclaimed')
        ->and($device->athlete_id)->toBeNull()
        ->and($device->provider)->toBe('garmin')
        ->and($device->type)->toBe('watch')
        ->and($device->name)->toBe('fr965_sim')
        ->and($device->device_secret)->toBe('discovered-secret')
        ->and($device->last_seen_at)->not->toBeNull()
        ->and($device->metadata['app_version'])->toBe('1.0.0')
        ->and(TelemetryPoint::query()->count())->toBe(1);
});

test('unknown device heartbeat without valid session does not create telemetry point', function () {
    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'session_token' => 'missing-token',
        'device_uuid' => 'sp-fr965-sim-heartbeat-001',
        'device_secret' => 'heartbeat-secret',
    ]))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 'device_seen',
        ]);

    expect(Device::query()->where('device_uuid', 'sp-fr965-sim-heartbeat-001')->where('status', 'unclaimed')->exists())->toBeTrue()
        ->and(TelemetryPoint::query()->count())->toBe(0);
});

test('telemetry is rejected for wrong device secret', function () {
    $device = Device::factory()->create();
    TrackingSession::factory()->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'device_uuid' => $device->uuid,
        'device_secret' => 'wrong-secret',
    ]))
        ->assertForbidden()
        ->assertJson([
            'ok' => false,
            'status' => 'device_forbidden',
        ]);

    expect(TelemetryPoint::query()->count())->toBe(0)
        ->and($device->fresh()->last_seen_at)->toBeNull();
});

test('telemetry is rejected if session does not belong to device', function () {
    $device = Device::factory()->create();
    $otherDevice = Device::factory()->create();

    TrackingSession::factory()->create([
        'athlete_id' => $otherDevice->athlete_id,
        'device_id' => $otherDevice->id,
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'device_uuid' => $device->uuid,
        'device_secret' => $device->device_secret,
    ]))
        ->assertForbidden()
        ->assertJson([
            'ok' => false,
            'status' => 'device_session_mismatch',
        ]);

    expect(TelemetryPoint::query()->count())->toBe(0);
});

test('telemetry is accepted after device is claimed and session belongs to device', function () {
    $device = Device::factory()->create([
        'athlete_id' => null,
        'device_uuid' => 'sp-fr965-sim-claimed-001',
        'device_secret' => 'claimed-secret',
        'status' => 'unclaimed',
    ]);

    $athlete = Athlete::factory()->create();
    $device->forceFill([
        'athlete_id' => $athlete->id,
        'status' => 'active',
    ])->save();

    $session = TrackingSession::factory()->create([
        'athlete_id' => $athlete->id,
        'device_id' => $device->id,
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminTelemetryPayload([
        'device_uuid' => 'sp-fr965-sim-claimed-001',
        'device_secret' => 'claimed-secret',
    ]))->assertOk();

    expect(TelemetryPoint::query()->first()?->tracking_session_id)->toBe($session->id);
});
