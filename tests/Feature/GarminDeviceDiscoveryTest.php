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
            'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-001'),
            'session_status' => null,
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
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-002'),
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

test('ready device discovery returns the single active session token', function () {
    $athlete = Athlete::factory()->create(['name' => 'Casey Runner']);
    $device = Device::factory()->for($athlete)->create([
        'device_uuid' => 'sp-fr965-discovery-003',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-003'),
        'device_secret' => 'active-secret',
        'name' => 'Race Watch',
        'status' => Device::STATUS_READY,
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
            'status' => 'live',
            'device_name' => 'Race Watch',
            'athlete_name' => 'Casey Runner',
            'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-003'),
            'session_status' => 'active',
            'active_session_token' => 'auto-session-token',
        ]);

    expect($device->fresh()->last_seen_at)->not->toBeNull()
        ->and($device->fresh()->status)->toBe(Device::STATUS_LIVE);
});

test('claimed device discovery returns the single active session token', function () {
    $athlete = Athlete::factory()->create(['name' => 'Claimed Runner']);
    $device = Device::factory()->for($athlete)->create([
        'device_uuid' => 'sp-fr965-discovery-claimed',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-claimed'),
        'device_secret' => 'claimed-secret',
        'name' => 'Claimed Watch',
        'status' => Device::STATUS_CLAIMED,
    ]);

    TrackingSession::factory()->for($athlete)->create([
        'device_id' => $device->id,
        'session_token' => 'claimed-session-token',
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-claimed',
        'device_secret' => 'claimed-secret',
    ]))
        ->assertOk()
        ->assertJsonPath('status', Device::STATUS_LIVE)
        ->assertJsonPath('athlete_name', 'Claimed Runner')
        ->assertJsonPath('session_status', 'active')
        ->assertJsonPath('active_session_token', 'claimed-session-token');
});

test('ready device discovery returns armed session token', function () {
    $athlete = Athlete::factory()->create(['name' => 'Armed Runner']);
    $device = Device::factory()->for($athlete)->create([
        'device_uuid' => 'sp-fr965-discovery-armed',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-armed'),
        'device_secret' => 'armed-secret',
        'name' => 'Armed Watch',
        'status' => Device::STATUS_READY,
    ]);

    TrackingSession::factory()->for($athlete)->create([
        'device_id' => $device->id,
        'session_token' => 'armed-session-token',
        'status' => 'armed',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-armed',
        'device_secret' => 'armed-secret',
    ]))
        ->assertOk()
        ->assertJsonPath('session_status', 'armed')
        ->assertJsonPath('active_session_token', 'armed-session-token');
});

test('active device discovery omits session token when multiple active sessions exist', function () {
    $device = Device::factory()->create([
        'device_uuid' => 'sp-fr965-discovery-004',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-004'),
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
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('session_status', null)
        ->assertJsonPath('active_session_token', null);
});

test('claimed device discovery omits session token when no active session exists', function () {
    $athlete = Athlete::factory()->create(['name' => 'Waiting Runner']);
    $device = Device::factory()->for($athlete)->create([
        'device_uuid' => 'sp-fr965-discovery-no-session',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-no-session'),
        'device_secret' => 'no-session-secret',
        'name' => 'Waiting Watch',
        'status' => Device::STATUS_CLAIMED,
    ]);

    TrackingSession::factory()->for($athlete)->create([
        'device_id' => $device->id,
        'session_token' => 'completed-session-token',
        'status' => 'completed',
        'ended_at' => now(),
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-no-session',
        'device_secret' => 'no-session-secret',
    ]))
        ->assertOk()
        ->assertJsonPath('status', Device::STATUS_READY)
        ->assertJsonPath('athlete_name', 'Waiting Runner')
        ->assertJsonPath('session_status', null)
        ->assertJsonPath('active_session_token', null);
});

test('discovery with existing device uuid updates existing row without duplicate', function () {
    Device::factory()->create([
        'device_uuid' => 'sp-fr965-discovery-006',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-006'),
        'device_secret' => 'same-secret',
        'status' => Device::STATUS_UNCLAIMED,
        'metadata' => ['app_version' => 'old'],
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'sp-fr965-discovery-006',
        'device_secret' => 'same-secret',
        'app_version' => '2.0.0',
    ]))->assertOk();

    expect(Device::query()->where('device_uuid', 'sp-fr965-discovery-006')->count())->toBe(1)
        ->and(Device::query()->where('device_uuid', 'sp-fr965-discovery-006')->firstOrFail()->metadata['app_version'])->toBe('2.0.0');
});

test('new uuid with same pairing code archives old device and preserves active session delivery', function () {
    $athlete = Athlete::factory()->create(['name' => 'Replacement Runner']);
    $oldDevice = Device::factory()->for($athlete)->create([
        'device_uuid' => 'old-device-95db7a',
        'pairing_code' => '95DB7A',
        'device_secret' => 'old-secret',
        'status' => Device::STATUS_READY,
    ]);

    TrackingSession::factory()->for($athlete)->create([
        'device_id' => $oldDevice->id,
        'session_token' => 'replacement-session-token',
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/device-discovery', garminDeviceDiscoveryPayload([
        'device_uuid' => 'new-device-95db7a',
        'device_secret' => 'new-secret',
    ]))
        ->assertOk()
        ->assertJsonPath('status', 'live')
        ->assertJsonPath('athlete_name', 'Replacement Runner')
        ->assertJsonPath('active_session_token', 'replacement-session-token');

    $newDevice = Device::query()->where('device_uuid', 'new-device-95db7a')->firstOrFail();

    expect($oldDevice->fresh()->status)->toBe(Device::STATUS_ARCHIVED)
        ->and($oldDevice->fresh()->archived_at)->not->toBeNull()
        ->and($newDevice->athlete_id)->toBe($athlete->id)
        ->and(TrackingSession::query()->where('session_token', 'replacement-session-token')->firstOrFail()->device_id)->toBe($newDevice->id);
});

test('active device discovery rejects invalid secret', function () {
    Device::factory()->create([
        'device_uuid' => 'sp-fr965-discovery-005',
        'pairing_code' => Device::derivePairingCode('sp-fr965-discovery-005'),
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
