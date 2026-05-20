<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unknown device discovery creates unclaimed device', function () {
    $response = $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-001',
        'device_secret' => 'new-device-secret',
        'device_model' => 'fr965',
        'firmware_version' => '19.18',
    ]));

    $response
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'status' => 'unclaimed',
            'device_name' => 'fr965',
            'athlete_name' => null,
            'active_session_token' => null,
        ]);

    $device = Device::query()->where('device_uuid', 'sp-fr965-discovery-001')->firstOrFail();

    expect($device->status)->toBe('unclaimed')
        ->and($device->athlete_id)->toBeNull()
        ->and($device->device_secret)->toBe('new-device-secret')
        ->and($device->last_seen_at)->not->toBeNull()
        ->and($device->metadata['device_model'])->toBe('fr965')
        ->and($device->metadata['app_version'])->toBe('1.2.3')
        ->and($device->metadata['firmware_version'])->toBe('19.18')
        ->and($device->metadata['battery_percent'])->toBe(76);
});

test('known unclaimed device discovery updates last seen and metadata', function () {
    $device = Device::factory()->create([
        'athlete_id' => null,
        'device_uuid' => 'sp-fr965-discovery-002',
        'device_secret' => 'existing-secret',
        'name' => 'Unknown Garmin Device',
        'status' => 'unclaimed',
        'last_seen_at' => null,
        'metadata' => ['app_version' => 'old'],
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-002',
        'device_secret' => 'existing-secret',
        'device_model' => 'fenix8',
        'app_version' => '2.0.0',
    ]))->assertOk()
        ->assertJson([
            'status' => 'unclaimed',
            'device_name' => 'fenix8',
            'active_session_token' => null,
        ]);

    $device->refresh();

    expect($device->last_seen_at)->not->toBeNull()
        ->and($device->name)->toBe('fenix8')
        ->and($device->metadata['device_model'])->toBe('fenix8')
        ->and($device->metadata['app_version'])->toBe('2.0.0');
});

test('active device discovery returns the single active session token', function () {
    $athlete = Athlete::factory()->create(['name' => 'Casey Runner']);
    $device = Device::factory()->for($athlete)->create([
        'device_uuid' => 'sp-fr965-discovery-003',
        'device_secret' => 'active-secret',
        'name' => 'Race Watch',
        'status' => 'active',
    ]);

    TrackingSession::factory()->for($athlete)->create([
        'device_id' => $device->id,
        'session_token' => 'auto-session-token',
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-003',
        'device_secret' => 'active-secret',
    ]))
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'status' => 'active',
            'device_name' => 'Race Watch',
            'athlete_name' => 'Casey Runner',
            'active_session_token' => 'auto-session-token',
        ]);

    expect($device->fresh()->last_seen_at)->not->toBeNull();
});

test('active device discovery omits session token when multiple active sessions exist', function () {
    $device = Device::factory()->create([
        'device_uuid' => 'sp-fr965-discovery-004',
        'device_secret' => 'active-secret',
        'status' => 'active',
    ]);

    TrackingSession::factory()->count(2)->create([
        'athlete_id' => $device->athlete_id,
        'device_id' => $device->id,
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-004',
        'device_secret' => 'active-secret',
    ]))
        ->assertOk()
        ->assertJsonPath('active_session_token', null);
});

test('active device discovery rejects invalid secret', function () {
    Device::factory()->create([
        'device_uuid' => 'sp-fr965-discovery-005',
        'device_secret' => 'correct-secret',
        'status' => 'active',
        'last_seen_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-005',
        'device_secret' => 'wrong-secret',
    ]))
        ->assertForbidden()
        ->assertJson([
            'ok' => false,
            'status' => 'device_forbidden',
        ]);

    expect(Device::query()->where('device_uuid', 'sp-fr965-discovery-005')->firstOrFail()->last_seen_at)->toBeNull();
});

test('device discovery validates required payload fields', function () {
    $this->postJson('/api/garmin/device-discovery', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'device_uuid',
            'device_secret',
            'device_model',
            'app_version',
            'battery_percent',
        ]);
});

function garminDeviceDiscoveryPayload(array $overrides = []): array
{
    return array_merge([
        'device_uuid' => 'sp-fr965-discovery-default',
        'device_secret' => 'device-secret',
        'device_model' => 'fr965',
        'app_version' => '1.2.3',
        'battery_percent' => 76,
        'firmware_version' => null,
    ], $overrides);
}
