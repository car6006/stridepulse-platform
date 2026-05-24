<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\TrackingSession;
use App\Models\User;
use App\Models\WhatsAppMessageDispatch;
use App\Services\OperationsHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('health service detects missing whatsapp config', function () {
    config([
        'services.whatsapp.token' => null,
        'services.whatsapp.phone_number_id' => null,
        'services.whatsapp.business_account_id' => null,
    ]);

    $summary = app(OperationsHealthService::class)->summary();

    expect($summary['whatsapp']['token_configured'])->toBeFalse()
        ->and($summary['whatsapp']['phone_number_id_configured'])->toBeFalse()
        ->and($summary['whatsapp']['business_account_id_configured'])->toBeFalse()
        ->and($summary['whatsapp']['api_config_status']['level'])->toBe('red');
});

test('health service detects failed dispatch', function () {
    WhatsAppMessageDispatch::query()->create([
        'phone_number' => '27830000000',
        'body' => 'Failure',
        'dedupe_key' => 'failed-dispatch-test',
        'status' => WhatsAppMessageDispatch::STATUS_FAILED,
        'last_error' => 'Meta API failed',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    $summary = app(OperationsHealthService::class)->summary();

    expect($summary['whatsapp']['latest_failed_dispatch'])->not->toBeNull()
        ->and($summary['queue']['status']['level'])->toBe('red');
});

test('health service detects pending jobs', function () {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $summary = app(OperationsHealthService::class)->summary();

    expect($summary['queue']['pending_jobs_count'])->toBe(1)
        ->and($summary['queue']['stale'])->toBeTrue()
        ->and($summary['queue']['status']['level'])->toBe('red');
});

test('health service detects active sessions with no telemetry', function () {
    TrackingSession::factory()->create([
        'status' => 'active',
        'ended_at' => null,
        'last_direct_telemetry_at' => null,
    ]);

    $summary = app(OperationsHealthService::class)->summary();

    expect($summary['garmin']['active_sessions_count'])->toBe(1)
        ->and($summary['garmin']['sessions_with_no_telemetry_count'])->toBe(1);
});

test('diagnostics output hides token and secret values', function () {
    config([
        'services.whatsapp.token' => 'super-secret-token',
        'services.whatsapp.phone_number_id' => '12345',
        'services.whatsapp.business_account_id' => '67890',
    ]);

    WhatsAppMessageDispatch::query()->create([
        'phone_number' => '27830000001',
        'body' => 'Failure',
        'dedupe_key' => 'secret-redaction-test',
        'status' => WhatsAppMessageDispatch::STATUS_FAILED,
        'last_error' => 'Authorization Bearer super-secret-token device_secret=abc123',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    $diagnostics = app(OperationsHealthService::class)->diagnosticsText();

    expect($diagnostics)->not->toContain('super-secret-token')
        ->and($diagnostics)->not->toContain('abc123')
        ->and($diagnostics)->toContain('Bearer [redacted]');
});

test('operations pages render for authenticated users', function () {
    $user = User::factory()->create();
    $athlete = Athlete::factory()->create();
    Device::factory()->for($athlete)->create([
        'status' => Device::STATUS_READY,
        'last_seen_at' => now(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('StridePulse operations health')
        ->assertSee('WhatsApp queue health');

    $this->actingAs($user)->get(route('operations.logs'))
        ->assertOk()
        ->assertSee('Operations logs')
        ->assertSee('Copy diagnostics');
});
