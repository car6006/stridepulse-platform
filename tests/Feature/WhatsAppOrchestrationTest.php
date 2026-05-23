<?php

use App\Models\Athlete;
use App\Models\Device;
use App\Models\Event;
use App\Models\EventFollower;
use App\Models\RaceEntry;
use App\Models\Supporter;
use App\Models\SupporterConsent;
use App\Models\SupporterInvitation;
use App\Models\TelemetryAlert;
use App\Models\TrackingSession;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageDispatch;
use App\Services\WhatsAppDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('whatsapp webhook verification responds to meta challenge', function () {
    config(['services.whatsapp.webhook_verify_token' => 'verify-token']);

    $this->getJson('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=challenge-123')
        ->assertOk()
        ->assertSee('challenge-123');
});

test('trackme conversation creates athlete state and advances event workflow', function () {
    postWhatsAppText('27820000001', 'TRACKME', 'wamid-1', 'Collen Runner')->assertOk();
    postWhatsAppText('27820000001', 'Cape Town Marathon', 'wamid-2')->assertOk();
    postWhatsAppText('27820000001', '2026-10-18', 'wamid-3')->assertOk();
    postWhatsAppText('27820000001', '42.2km', 'wamid-4')->assertOk();
    postWhatsAppText('27820000001', 'running', 'wamid-5')->assertOk();
    postWhatsAppText('27820000001', '+27825550101, +27825550102', 'wamid-6')->assertOk();

    $conversation = WhatsAppConversation::query()->where('phone_number', '27820000001')->firstOrFail();

    expect($conversation->athlete)->not->toBeNull()
        ->and($conversation->state)->toBe(WhatsAppConversation::STATE_AWAITING_CONSENT)
        ->and($conversation->context['event_name'])->toBe('Cape Town Marathon')
        ->and($conversation->context['distance_m'])->toBe(42200)
        ->and($conversation->context['supporters'])->toHaveCount(2)
        ->and(WhatsAppMessage::query()->where('direction', 'inbound')->count())->toBe(6);
});

test('duplicate inbound whatsapp message is stored once and not processed twice', function () {
    postWhatsAppText('27820000006', 'TRACKME', 'wamid-duplicate', 'Retry Runner')->assertOk();
    postWhatsAppText('27820000006', 'TRACKME', 'wamid-duplicate', 'Retry Runner')->assertOk();

    $conversation = WhatsAppConversation::query()->where('phone_number', '27820000006')->firstOrFail();

    expect(WhatsAppMessage::query()->where('provider_message_id', 'wamid-duplicate')->count())->toBe(1)
        ->and($conversation->state)->toBe(WhatsAppConversation::STATE_AWAITING_EVENT_NAME)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27820000006')->count())->toBe(1);
});

test('trackme workflow rejects invalid date distance and supporter numbers with useful replies', function () {
    postWhatsAppText('27820000007', 'TRACKME', 'wamid-invalid-1')->assertOk();
    postWhatsAppText('27820000007', 'Race Day', 'wamid-invalid-2')->assertOk();
    postWhatsAppText('27820000007', 'next Tuesday', 'wamid-invalid-3')->assertOk();

    $conversation = WhatsAppConversation::query()->where('phone_number', '27820000007')->firstOrFail();
    expect($conversation->state)->toBe(WhatsAppConversation::STATE_AWAITING_EVENT_DATE)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27820000007')->where('body', 'Please send the event date as YYYY-MM-DD.')->exists())->toBeTrue();

    postWhatsAppText('27820000007', '2026-11-01', 'wamid-invalid-4')->assertOk();
    postWhatsAppText('27820000007', 'far', 'wamid-invalid-5')->assertOk();

    $conversation->refresh();
    expect($conversation->state)->toBe(WhatsAppConversation::STATE_AWAITING_EVENT_DISTANCE)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27820000007')->where('body', 'Please send a valid distance like 10km, 21.1km, or 100 miles.')->exists())->toBeTrue();

    postWhatsAppText('27820000007', '10km', 'wamid-invalid-6')->assertOk();
    postWhatsAppText('27820000007', 'running', 'wamid-invalid-7')->assertOk();
    postWhatsAppText('27820000007', 'no numbers here', 'wamid-invalid-8')->assertOk();

    $conversation->refresh();
    expect($conversation->state)->toBe(WhatsAppConversation::STATE_AWAITING_SUPPORTERS)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27820000007')->where('body', 'Please send at least one supporter mobile number, separated by commas.')->exists())->toBeTrue();
});

test('trackme consent arms event session and supporter invitations', function () {
    $athlete = Athlete::factory()->create([
        'name' => 'Collen Runner',
        'metadata' => ['whatsapp_phone' => '27820000002'],
    ]);
    Device::factory()->for($athlete)->create([
        'status' => Device::STATUS_READY,
        'name' => 'Forerunner 965',
    ]);

    WhatsAppConversation::query()->create([
        'athlete_id' => $athlete->id,
        'phone_number' => '27820000002',
        'state' => WhatsAppConversation::STATE_AWAITING_CONSENT,
        'context' => [
            'event_name' => 'Two Oceans',
            'event_date' => '2026-04-11',
            'distance_m' => 56000,
            'event_type' => 'running',
            'supporters' => ['27825550101'],
        ],
    ]);

    postWhatsAppText('27820000002', 'YES', 'wamid-arm')->assertOk();

    $event = Event::query()->firstOrFail();
    $session = TrackingSession::query()->firstOrFail();

    expect($event->name)->toBe('Two Oceans')
        ->and($event->metadata['distance_m'])->toBe(56000)
        ->and(RaceEntry::query()->where('event_id', $event->id)->where('athlete_id', $athlete->id)->exists())->toBeTrue()
        ->and($session->device_id)->not->toBeNull()
        ->and(SupporterInvitation::query()->where('phone_number', '27825550101')->where('status', 'pending')->exists())->toBeTrue()
        ->and(WhatsAppMessageDispatch::query()->where('template_name', 'supporter_invite')->count())->toBe(1);
});

test('supporter yes records consent and creates event follower', function () {
    $athlete = Athlete::factory()->create();
    $event = Event::factory()->create();
    $session = TrackingSession::factory()->for($athlete)->create();

    SupporterInvitation::query()->create([
        'athlete_id' => $athlete->id,
        'event_id' => $event->id,
        'tracking_session_id' => $session->id,
        'phone_number' => '27825550103',
        'status' => SupporterInvitation::STATUS_PENDING,
    ]);

    postWhatsAppText('27825550103', 'YES', 'wamid-supporter-yes')->assertOk();

    expect(SupporterConsent::query()->where('phone_number', '27825550103')->where('status', SupporterConsent::STATUS_OPTED_IN)->exists())->toBeTrue()
        ->and(EventFollower::query()->where('event_id', $event->id)->where('phone_number', '27825550103')->where('status', 'active')->exists())->toBeTrue()
        ->and(SupporterInvitation::query()->where('phone_number', '27825550103')->firstOrFail()->status)->toBe(SupporterInvitation::STATUS_ACCEPTED);
});

test('supporter no maps to pending invitation without creating follower', function () {
    $athlete = Athlete::factory()->create();
    $event = Event::factory()->create();

    SupporterInvitation::query()->create([
        'athlete_id' => $athlete->id,
        'event_id' => $event->id,
        'phone_number' => '27825550106',
        'status' => SupporterInvitation::STATUS_PENDING,
    ]);

    postWhatsAppText('27825550106', 'NO', 'wamid-supporter-no')->assertOk();

    expect(SupporterConsent::query()->where('phone_number', '27825550106')->where('status', SupporterConsent::STATUS_DECLINED)->exists())->toBeTrue()
        ->and(EventFollower::query()->where('event_id', $event->id)->where('phone_number', '27825550106')->exists())->toBeFalse()
        ->and(SupporterInvitation::query()->where('phone_number', '27825550106')->firstOrFail()->status)->toBe(SupporterInvitation::STATUS_DECLINED);
});

test('supporter stop unsubscribes and preserves consent audit trail', function () {
    $athlete = Athlete::factory()->create();
    $event = Event::factory()->create();
    $supporter = Supporter::query()->create([
        'uuid' => (string) str()->uuid(),
        'name' => 'Supporter',
        'phone_number' => '27825550104',
    ]);

    EventFollower::query()->create([
        'event_id' => $event->id,
        'athlete_id' => $athlete->id,
        'supporter_id' => $supporter->id,
        'phone_number' => '27825550104',
        'status' => 'active',
        'opted_in_at' => now(),
    ]);

    postWhatsAppText('27825550104', 'STOP', 'wamid-stop')->assertOk();

    expect(EventFollower::query()->where('phone_number', '27825550104')->firstOrFail()->status)->toBe('unsubscribed')
        ->and(SupporterConsent::query()->where('phone_number', '27825550104')->where('status', SupporterConsent::STATUS_UNSUBSCRIBED)->exists())->toBeTrue();
});

test('unsubscribed followers do not receive future telemetry dispatches', function () {
    $athlete = Athlete::factory()->create(['name' => 'Quiet Athlete']);
    $event = Event::factory()->create([
        'metadata' => ['distance_m' => 10000],
    ]);
    $raceEntry = RaceEntry::factory()->for($event)->for($athlete)->create();
    $session = TrackingSession::factory()->for($athlete)->for($raceEntry)->create([
        'session_token' => 'unsubscribed-telemetry-session',
        'status' => 'active',
        'ended_at' => null,
    ]);
    $supporter = Supporter::query()->create([
        'uuid' => (string) str()->uuid(),
        'name' => 'Former Supporter',
        'phone_number' => '27825550107',
    ]);

    EventFollower::query()->create([
        'event_id' => $event->id,
        'athlete_id' => $athlete->id,
        'supporter_id' => $supporter->id,
        'phone_number' => '27825550107',
        'status' => 'unsubscribed',
        'unsubscribed_at' => now(),
    ]);

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'unsubscribed-telemetry-session',
        'distance_m' => 5100,
    ]))->assertOk();

    expect(WhatsAppMessageDispatch::query()->where('phone_number', '27825550107')->exists())->toBeFalse();
});

test('dispatch dedupe key prevents duplicate sends', function () {
    app(WhatsAppDispatchService::class)->sendText('27825550108', 'First body', 'dedupe-test-key');
    app(WhatsAppDispatchService::class)->sendText('27825550108', 'Second body', 'dedupe-test-key');

    expect(WhatsAppMessageDispatch::query()->where('dedupe_key', 'dedupe-test-key')->count())->toBe(1)
        ->and(WhatsAppMessageDispatch::query()->where('dedupe_key', 'dedupe-test-key')->firstOrFail()->body)->toBe('First body');
});

test('queue job skips safely when whatsapp token is missing', function () {
    config([
        'services.whatsapp.token' => null,
        'services.whatsapp.phone_number_id' => '123456789',
    ]);
    Http::fake();

    $dispatch = app(WhatsAppDispatchService::class)->sendText('27825550109', 'Credential test', 'missing-token-test');

    $dispatch->refresh();

    expect($dispatch->status)->toBe(WhatsAppMessageDispatch::STATUS_SKIPPED)
        ->and($dispatch->last_error)->toBe('WhatsApp Cloud API token is not configured.');

    Http::assertNothingSent();
});

test('queue job skips safely when whatsapp phone number id is missing', function () {
    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => null,
    ]);
    Http::fake();

    $dispatch = app(WhatsAppDispatchService::class)->sendText('27825550110', 'Credential test', 'missing-phone-number-id-test');

    $dispatch->refresh();

    expect($dispatch->status)->toBe(WhatsAppMessageDispatch::STATUS_SKIPPED)
        ->and($dispatch->last_error)->toBe('WhatsApp phone number id is not configured.');

    Http::assertNothingSent();
});

test('queue job sends whatsapp message and persists provider message id', function () {
    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '123456789',
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/123456789/messages' => Http::response([
            'messages' => [
                ['id' => 'wamid.sent.123'],
            ],
        ], 200),
    ]);

    $dispatch = app(WhatsAppDispatchService::class)->sendText('27825550111', 'Send test', 'send-success-test');

    $dispatch->refresh();

    expect($dispatch->status)->toBe(WhatsAppMessageDispatch::STATUS_SENT)
        ->and($dispatch->provider_message_id)->toBe('wamid.sent.123')
        ->and($dispatch->sent_at)->not->toBeNull()
        ->and($dispatch->attempts)->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '27825550111'
            && $request['text']['body'] === 'Send test';
    });
});

test('telemetry automation sends deduped checkpoint updates only to opted in followers', function () {
    createWhatsAppProgressSession(eventDistanceM: 10000, supporterPhone: '27825550105');

    $payload = garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'distance_m' => 5100,
        'elapsed_time_seconds' => 1800,
        'gps_status' => 'LOCK',
    ]);

    $this->postJson('/api/garmin/telemetry', $payload)->assertOk();
    $this->postJson('/api/garmin/telemetry', array_merge($payload, ['ingestion_id' => 'whatsapp-auto-002']))->assertOk();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(1)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27825550105')->where('dedupe_key', 'like', 'event_update:checkpoint_progress:%')->count())->toBe(1);
});

test('checkpoint progress sends no update before 5km', function () {
    createWhatsAppProgressSession(eventDistanceM: 10000, supporterPhone: '27825550120');

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'distance_m' => 4900,
        'elapsed_seconds' => null,
        'elapsed_time_seconds' => null,
        'gps_status' => null,
    ]))->assertOk();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(0)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27825550120')->count())->toBe(0);
});

test('checkpoint progress sends update at 5km with supporter context', function () {
    $session = createWhatsAppProgressSession(eventDistanceM: 10000, supporterPhone: '27825550121');

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'distance_m' => 5000,
        'elapsed_time_seconds' => 1800,
        'gps_status' => null,
    ]))->assertOk();

    $dispatch = WhatsAppMessageDispatch::query()
        ->where('phone_number', '27825550121')
        ->where('dedupe_key', 'like', 'event_update:checkpoint_progress:%')
        ->firstOrFail();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(1)
        ->and($dispatch->body)->toContain('Race Athlete checkpoint update for Race Day')
        ->and($dispatch->body)->toContain('Completed: 5.0 km')
        ->and($dispatch->body)->toContain('Average pace: 6:00/km')
        ->and($dispatch->body)->toContain('Remaining: 5.0 km')
        ->and($dispatch->body)->toContain('Estimated finish: 10:30')
        ->and($dispatch->body)->toContain(route('live.session', $session->session_token));
});

test('checkpoint progress does not duplicate at 5 point 1km', function () {
    createWhatsAppProgressSession(eventDistanceM: 10000, supporterPhone: '27825550122');

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'ingestion_id' => 'checkpoint-5km',
        'distance_m' => 5000,
        'elapsed_time_seconds' => 1800,
        'gps_status' => null,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'ingestion_id' => 'checkpoint-5-1km',
        'distance_m' => 5100,
        'elapsed_time_seconds' => 1836,
        'gps_status' => null,
    ]))->assertOk();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(1)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27825550122')->where('dedupe_key', 'like', 'event_update:checkpoint_progress:%')->count())->toBe(1);
});

test('checkpoint progress sends another update at 10km', function () {
    createWhatsAppProgressSession(eventDistanceM: 15000, supporterPhone: '27825550123');

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'ingestion_id' => 'checkpoint-5km',
        'distance_m' => 5000,
        'elapsed_time_seconds' => 1800,
        'gps_status' => null,
    ]))->assertOk();

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'ingestion_id' => 'checkpoint-10km',
        'distance_m' => 10000,
        'elapsed_time_seconds' => 3600,
        'gps_status' => null,
    ]))->assertOk();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(2)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27825550123')->where('dedupe_key', 'like', 'event_update:checkpoint_progress:%')->count())->toBe(2)
        ->and(TelemetryAlert::query()->where('dedupe_key', 'checkpoint_progress:'.TrackingSession::query()->firstOrFail()->id.':10000')->exists())->toBeTrue();
});

test('checkpoint progress does not send to declined supporter', function () {
    createWhatsAppProgressSession(
        eventDistanceM: 10000,
        supporterPhone: '27825550124',
        followerStatus: 'declined',
        consentStatus: SupporterConsent::STATUS_DECLINED,
        optedIn: false,
    );

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'whatsapp-telemetry-session',
        'distance_m' => 5000,
        'elapsed_time_seconds' => 1800,
        'gps_status' => null,
    ]))->assertOk();

    expect(TelemetryAlert::query()->where('alert_type', 'checkpoint_progress')->count())->toBe(1)
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27825550124')->where('dedupe_key', 'like', 'event_update:checkpoint_progress:%')->exists())->toBeFalse();
});

test('telemetry hook leaves non event garmin ingestion path untouched', function () {
    TrackingSession::factory()->create([
        'session_token' => 'plain-garmin-session',
        'race_entry_id' => null,
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->postJson('/api/garmin/telemetry', garminWhatsappTelemetryPayload([
        'session_token' => 'plain-garmin-session',
    ]))->assertOk();

    expect(TelemetryAlert::query()->count())->toBe(0)
        ->and(WhatsAppMessageDispatch::query()->count())->toBe(0);
});

function postWhatsAppText(string $from, string $body, string $messageId, ?string $profileName = null)
{
    return \Pest\Laravel\postJson('/api/whatsapp/webhook', whatsappWebhookPayload($from, $body, $messageId, $profileName));
}

function whatsappWebhookPayload(string $from, string $body, string $messageId, ?string $profileName = null): array
{
    return [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'contacts' => [[
                        'wa_id' => $from,
                        'profile' => ['name' => $profileName ?? 'WhatsApp User'],
                    ]],
                    'messages' => [[
                        'from' => $from,
                        'id' => $messageId,
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]],
                ],
            ]],
        ]],
    ];
}

function garminWhatsappTelemetryPayload(array $overrides = []): array
{
    return array_merge([
        'session_token' => 'whatsapp-telemetry-session',
        'ingestion_id' => 'whatsapp-auto-001',
        'recorded_at' => '2026-05-22T10:00:00Z',
        'elapsed_seconds' => 1800,
        'elapsed_time_seconds' => 1800,
        'distance_m' => 5000,
        'pace_sec_per_km' => 360,
        'heart_rate_bpm' => 150,
        'gps_status' => 'LOCK',
        'battery_percent' => 90,
    ], $overrides);
}

function createWhatsAppProgressSession(
    int $eventDistanceM,
    string $supporterPhone,
    string $followerStatus = 'active',
    string $consentStatus = SupporterConsent::STATUS_OPTED_IN,
    bool $optedIn = true,
): TrackingSession {
    $athlete = Athlete::factory()->create(['name' => 'Race Athlete']);
    $event = Event::factory()->create([
        'name' => 'Race Day',
        'metadata' => ['distance_m' => $eventDistanceM],
    ]);
    $raceEntry = RaceEntry::factory()->for($event)->for($athlete)->create();
    $session = TrackingSession::factory()->for($athlete)->for($raceEntry)->create([
        'session_token' => 'whatsapp-telemetry-session',
        'status' => 'active',
        'ended_at' => null,
    ]);
    $supporter = Supporter::query()->create([
        'uuid' => (string) str()->uuid(),
        'name' => 'Supporter',
        'phone_number' => $supporterPhone,
    ]);
    $consent = SupporterConsent::query()->create([
        'event_id' => $event->id,
        'supporter_id' => $supporter->id,
        'phone_number' => $supporterPhone,
        'status' => $consentStatus,
        'source' => 'whatsapp',
        'consented_at' => $optedIn ? now() : null,
        'revoked_at' => $optedIn ? null : now(),
    ]);

    EventFollower::query()->create([
        'event_id' => $event->id,
        'athlete_id' => $athlete->id,
        'supporter_id' => $supporter->id,
        'supporter_consent_id' => $consent->id,
        'phone_number' => $supporterPhone,
        'status' => $followerStatus,
        'opted_in_at' => $optedIn ? now() : null,
        'unsubscribed_at' => $followerStatus === 'unsubscribed' ? now() : null,
    ]);

    return $session;
}
