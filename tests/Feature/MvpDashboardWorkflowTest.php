<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\Sport;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can manage athletes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('athletes.index'))
        ->assertOk()
        ->assertSee('Athletes');

    $this->post(route('athletes.store'), [
        'name' => 'Casey Runner',
    ])->assertRedirect();

    $athlete = Athlete::query()->where('name', 'Casey Runner')->firstOrFail();

    $this->get(route('athletes.edit', $athlete))
        ->assertOk()
        ->assertSee('Casey Runner');

    $this->put(route('athletes.update', $athlete), [
        'name' => 'Casey Sprinter',
    ])->assertRedirect(route('athletes.edit', $athlete));

    $this->assertDatabaseHas('athletes', [
        'id' => $athlete->id,
        'name' => 'Casey Sprinter',
    ]);
});

test('garmin setup page shows endpoint and can generate setup token', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('garmin-setup.index'))
        ->assertOk()
        ->assertSee('/api/garmin/telemetry')
        ->assertSee('No Garmin password or OAuth connection is required');

    $this->followingRedirects()
        ->post(route('garmin-setup.generate'))
        ->assertOk()
        ->assertSee('Garmin setup token generated.')
        ->assertSee(session('garmin_setup_token'));
});

test('device can be registered from dashboard', function () {
    $this->actingAs(User::factory()->create());

    $athlete = Athlete::factory()->create(['name' => 'Casey Device']);

    $this->get(route('devices.create'))
        ->assertOk()
        ->assertSee('Register Garmin Device')
        ->assertSee('Casey Device');

    $this->followingRedirects()
        ->post(route('devices.store'), [
            'athlete_id' => $athlete->id,
            'name' => 'Casey FR965',
            'type' => 'watch',
            'provider' => 'garmin',
        ])
        ->assertOk()
        ->assertSee('Garmin device registered.')
        ->assertSee('Casey FR965')
        ->assertSee('Device UUID')
        ->assertSee('Device secret');

    $device = Device::query()->firstOrFail();

    expect($device->athlete_id)->toBe($athlete->id)
        ->and($device->provider)->toBe('garmin')
        ->and($device->device_secret)->not->toBeEmpty()
        ->and($device->metadata)->toHaveKey('pairing_code');
});

test('authenticated user can start and list a tracking session', function () {
    $this->actingAs(User::factory()->create());

    $athlete = Athlete::factory()->create(['name' => 'Morgan Athlete']);
    $sport = Sport::factory()->create(['name' => 'running']);
    $device = Device::factory()->for($athlete)->create(['name' => 'Morgan Garmin']);

    $this->get(route('tracking-sessions.create'))
        ->assertOk()
        ->assertSee('Morgan Athlete')
        ->assertSee('Morgan Garmin')
        ->assertSee('Running');

    $this->post(route('tracking-sessions.store'), [
        'athlete_id' => $athlete->id,
        'device_id' => $device->id,
        'sport_id' => $sport->id,
    ])->assertRedirect(route('live-sessions.index'));

    $session = TrackingSession::query()->firstOrFail();

    expect($session->status)->toBe('active')
        ->and($session->session_token)->not->toBeNull()
        ->and($session->device_id)->toBe($device->id);

    $this->get(route('live-sessions.index'))
        ->assertOk()
        ->assertSee('Morgan Athlete')
        ->assertSee(route('live.session', $session->session_token));
});

test('public live page auto refreshes and shows offline indicator for stale sessions', function () {
    $session = TrackingSession::factory()->create([
        'last_seen_at' => now()->subMinutes(10),
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->get(route('live.session', $session->session_token))
        ->assertOk()
        ->assertSee('http-equiv="refresh"', false)
        ->assertSee('content="60"', false)
        ->assertSee('Offline')
        ->assertSee('offline or stale');
});
