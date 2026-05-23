<?php

use App\Models\Athlete;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessageDispatch;
use App\Services\PhoneNumberNormalizer;
use App\Services\WhatsAppIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('phone normalizer handles south african local numbers', function () {
    $normalizer = app(PhoneNumberNormalizer::class);

    expect($normalizer->normalize('0837002400'))->toBe('27837002400')
        ->and($normalizer->normalize('073 102 1704'))->toBe('27731021704');
});

test('phone normalizer handles plus 27 and existing e164 numbers', function () {
    $normalizer = app(PhoneNumberNormalizer::class);

    expect($normalizer->normalize('+27837002400'))->toBe('27837002400')
        ->and($normalizer->normalize('27837002400'))->toBe('27837002400')
        ->and($normalizer->normalize(''))->toBeNull()
        ->and($normalizer->normalize('not a phone'))->toBeNull();
});

test('whatsapp identity resolver finds athlete by whatsapp phone', function () {
    $athlete = Athlete::factory()->create([
        'name' => 'WhatsApp Runner',
        'whatsapp_phone_e164' => '27837002400',
    ]);

    $resolved = app(WhatsAppIdentityResolver::class)->resolve('+27837002400', 'Runner Profile');

    expect($resolved?->id)->toBe($athlete->id)
        ->and($resolved->fresh()->display_name)->toBe('Runner Profile');
});

test('whatsapp identity resolver finds athlete by mobile phone', function () {
    $athlete = Athlete::factory()->create([
        'name' => 'Mobile Runner',
        'mobile_phone_e164' => '27837002400',
    ]);

    $resolved = app(WhatsAppIdentityResolver::class)->resolve('0837002400', 'Mobile Profile');

    expect($resolved?->id)->toBe($athlete->id)
        ->and($resolved->fresh()->whatsapp_phone_e164)->toBe('27837002400');
});

test('inbound whatsapp creates provisional athlete', function () {
    postIdentityWhatsAppText('0837002400', 'HI', 'wamid-identity-1', 'Casey Runner')->assertOk();

    $athlete = Athlete::query()->where('whatsapp_phone_e164', '27837002400')->firstOrFail();

    expect($athlete->display_name)->toBe('Casey Runner')
        ->and($athlete->whatsapp_phone)->toBe('0837002400')
        ->and($athlete->mobile_phone_e164)->toBe('27837002400')
        ->and($athlete->onboarding_status)->toBe('whatsapp_started')
        ->and($athlete->subscription_status)->toBe('trial');
});

test('inbound whatsapp links conversation to athlete', function () {
    postIdentityWhatsAppText('0837002400', 'HI', 'wamid-identity-2', 'Casey Runner')->assertOk();

    $conversation = WhatsAppConversation::query()->where('phone_number', '27837002400')->firstOrFail();
    $athlete = Athlete::query()->where('whatsapp_phone_e164', '27837002400')->firstOrFail();

    expect($conversation->athlete_id)->toBe($athlete->id)
        ->and($conversation->profile_name)->toBe('Casey Runner')
        ->and(WhatsAppMessageDispatch::query()->where('phone_number', '27837002400')->exists())->toBeTrue();
});

test('repeated inbound whatsapp reuses same athlete', function () {
    postIdentityWhatsAppText('0837002400', 'HI', 'wamid-identity-3', 'Casey Runner')->assertOk();
    postIdentityWhatsAppText('+27837002400', 'TRACKME', 'wamid-identity-4', 'Casey Runner')->assertOk();

    expect(Athlete::query()->where('whatsapp_phone_e164', '27837002400')->count())->toBe(1)
        ->and(WhatsAppConversation::query()->where('phone_number', '27837002400')->firstOrFail()->athlete_id)
        ->toBe(Athlete::query()->where('whatsapp_phone_e164', '27837002400')->firstOrFail()->id);
});

function postIdentityWhatsAppText(string $from, string $body, string $messageId, ?string $profileName = null)
{
    return test()->postJson('/api/whatsapp/webhook', [
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
    ]);
}
