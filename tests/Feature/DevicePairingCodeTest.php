<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('garmin pairing code is derived from device uuid', function () {
    $device = Device::factory()->create([
        'provider' => 'garmin',
        'device_uuid' => 'sp-fr965-sim-03d8bb1f107195db7a',
        'pairing_code' => null,
        'metadata' => [],
    ]);

    expect($device->pairing_code)->toBe('95DB7A');
});

test('stored pairing code is preferred over derived code', function () {
    $device = Device::factory()->create([
        'provider' => 'garmin',
        'device_uuid' => 'sp-fr965-sim-03d8bb1f107195db7a',
        'pairing_code' => null,
        'metadata' => [
            'pairing_code' => 'stored1',
        ],
    ]);

    expect($device->pairing_code)->toBe('STORED1');
});

test('derived pairing code excludes ambiguous characters', function () {
    expect(Device::derivePairingCode('garmin-OO00IIll1122ABCD'))->toBe('22ABCD');
});

test('device details page displays pairing code and hides raw credentials in advanced section', function () {
    $this->actingAs(User::factory()->create());

    $device = Device::factory()->create([
        'name' => 'Casey FR965',
        'provider' => 'garmin',
        'device_uuid' => 'sp-fr965-sim-03d8bb1f107195db7a',
        'pairing_code' => null,
        'metadata' => [],
    ]);

    $this->get(route('devices.show', $device))
        ->assertOk()
        ->assertSee('Casey FR965')
        ->assertSee('Pairing code')
        ->assertSee('95DB7A')
        ->assertSee('Advanced / Developer details')
        ->assertSee('Device UUID')
        ->assertSee('Device secret');
});

test('device list displays pairing code', function () {
    $this->actingAs(User::factory()->create());

    Device::factory()->create([
        'name' => 'Casey FR965',
        'provider' => 'garmin',
        'device_uuid' => 'sp-fr965-sim-03d8bb1f107195db7a',
        'pairing_code' => null,
        'metadata' => [],
    ]);

    $this->get(route('devices.index'))
        ->assertOk()
        ->assertSee('Casey FR965')
        ->assertSee('Pairing code')
        ->assertSee('95DB7A');
});

test('unclaimed devices page displays pairing code', function () {
    $this->actingAs(User::factory()->create());

    Device::factory()->create([
        'athlete_id' => null,
        'name' => 'fr965_sim',
        'provider' => 'garmin',
        'device_uuid' => 'sp-fr965-sim-03d8bb1f107195db7a',
        'pairing_code' => null,
        'status' => 'unclaimed',
        'metadata' => [],
    ]);

    $this->get(route('devices.unclaimed'))
        ->assertOk()
        ->assertSee('fr965_sim')
        ->assertSee('Pairing code')
        ->assertSee('95DB7A');
});
