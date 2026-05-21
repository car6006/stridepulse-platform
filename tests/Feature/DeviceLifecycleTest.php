<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\Sport;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('already claimed device cannot be claimed again through claim flow', function () {
    $this->actingAs(User::factory()->create());

    $device = Device::factory()->create([
        'status' => Device::STATUS_CLAIMED,
    ]);

    $this->post(route('devices.claim', $device), [
        'athlete_id' => Athlete::factory()->create()->id,
    ])->assertNotFound();
});

test('device ownership can be explicitly transferred', function () {
    $this->actingAs(User::factory()->create());

    $newAthlete = Athlete::factory()->create();
    $device = Device::factory()->create([
        'status' => Device::STATUS_CLAIMED,
    ]);

    $this->post(route('devices.transfer', $device), [
        'athlete_id' => $newAthlete->id,
    ])->assertRedirect(route('devices.show', $device));

    expect($device->fresh()->athlete_id)->toBe($newAthlete->id)
        ->and($device->fresh()->status)->toBe(Device::STATUS_CLAIMED)
        ->and($device->fresh()->last_claimed_at)->not->toBeNull();
});

test('tracking session requires a claimed device selection', function () {
    $this->actingAs(User::factory()->create());

    $athlete = Athlete::factory()->create();
    $sport = Sport::factory()->create();

    $this->post(route('tracking-sessions.store'), [
        'athlete_id' => $athlete->id,
        'sport_id' => $sport->id,
    ])->assertSessionHasErrors('device_id');
});

test('live sessions page shows assigned device telemetry warning', function () {
    $this->actingAs(User::factory()->create());

    $device = Device::factory()->create([
        'name' => 'Race FR965',
        'last_telemetry_at' => null,
    ]);

    TrackingSession::factory()->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->get(route('live-sessions.index'))
        ->assertOk()
        ->assertSee('Race FR965')
        ->assertSee('Device assigned but no telemetry has been received.');
});

test('telemetry moves claimed device into live state', function () {
    $device = Device::factory()->create([
        'status' => Device::STATUS_READY,
        'device_uuid' => 'sp-fr965-live-001',
        'device_secret' => 'live-secret',
        'last_telemetry_at' => null,
    ]);

    TrackingSession::factory()->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'session_token' => 'live-session-token',
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminLifecycleTelemetryPayload([
        'device_uuid' => 'sp-fr965-live-001',
        'device_secret' => 'live-secret',
        'session_token' => 'live-session-token',
    ]))->assertOk();

    expect($device->fresh()->status)->toBe(Device::STATUS_LIVE)
        ->and($device->fresh()->last_telemetry_at)->not->toBeNull();
});

function garminLifecycleTelemetryPayload(array $overrides = []): array
{
    return array_merge([
        'session_token' => 'live-session-token',
        'ingestion_id' => 'lifecycle-ingestion-001',
        'recorded_at' => '2026-05-21T10:00:00Z',
        'elapsed_seconds' => 10,
        'distance_m' => 20,
        'heart_rate_bpm' => 120,
        'battery_percent' => 80,
        'device_model' => 'fr965',
    ], $overrides);
}
