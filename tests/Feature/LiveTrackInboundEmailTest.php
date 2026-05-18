<?php

use App\Models\LiveTrackInboundMessage;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function liveTrackEmailPayload(array $overrides = []): array
{
    return array_merge([
        'recipient_alias' => 'garmin-session-token',
        'from_email' => 'Garmin LiveTrack <no-reply@garmin.com>',
        'subject' => 'Garmin LiveTrack invitation',
        'raw_body' => 'Track this activity: https://livetrack.garmin.com/session/example-123',
        'received_at' => '2026-05-18T10:00:00Z',
    ], $overrides);
}

test('inbound email with Garmin URL stores message', function () {
    $response = $this->postJson('/api/inbound/livetrack-email', liveTrackEmailPayload());

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 'received',
        ]);

    $this->assertDatabaseHas('livetrack_inbound_messages', [
        'recipient_alias' => 'garmin-session-token',
        'from_email' => 'no-reply@garmin.com',
        'subject' => 'Garmin LiveTrack invitation',
        'extracted_url' => 'https://livetrack.garmin.com/session/example-123',
        'status' => 'received',
    ]);
});

test('known recipient alias attaches URL to active tracking session', function () {
    $session = TrackingSession::factory()->create([
        'session_token' => 'garmin-session-token',
        'ended_at' => null,
    ]);

    $this->postJson('/api/inbound/livetrack-email', liveTrackEmailPayload())->assertOk();

    $session->refresh();

    expect($session->livetrack_url)->toBe('https://livetrack.garmin.com/session/example-123')
        ->and($session->livetrack_received_at)->not->toBeNull()
        ->and($session->livetrack_source_email)->toBe('no-reply@garmin.com')
        ->and($session->telemetry_source)->toBe('hybrid');

    $this->assertDatabaseHas('livetrack_inbound_messages', [
        'tracking_session_id' => $session->id,
        'athlete_id' => $session->athlete_id,
        'recipient_alias' => 'garmin-session-token',
    ]);
});

test('unknown alias stores message without session', function () {
    $this->postJson('/api/inbound/livetrack-email', liveTrackEmailPayload([
        'recipient_alias' => 'unknown-token',
    ]))->assertOk();

    $message = LiveTrackInboundMessage::query()->firstOrFail();

    expect($message->tracking_session_id)->toBeNull()
        ->and($message->athlete_id)->toBeNull()
        ->and($message->extracted_url)->toBe('https://livetrack.garmin.com/session/example-123');
});

test('no URL stores message with no url status', function () {
    $this->postJson('/api/inbound/livetrack-email', liveTrackEmailPayload([
        'raw_body' => 'Garmin LiveTrack started, but no link was included.',
    ]))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 'no_url',
        ]);

    $this->assertDatabaseHas('livetrack_inbound_messages', [
        'recipient_alias' => 'garmin-session-token',
        'extracted_url' => null,
        'status' => 'no_url',
    ]);
});
