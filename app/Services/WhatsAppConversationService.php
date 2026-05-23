<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Event;
use App\Models\EventFollower;
use App\Models\RaceEntry;
use App\Models\Sport;
use App\Models\Supporter;
use App\Models\SupporterConsent;
use App\Models\SupporterInvitation;
use App\Models\TrackingSession;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppConversationService
{
    public function __construct(
        private WhatsAppDispatchService $dispatch,
        private PhoneNumberNormalizer $phoneNumbers,
        private WhatsAppIdentityResolver $identity,
    ) {}

    public function handleInboundText(string $phoneNumber, string $text, array $payload = [], ?string $profileName = null, ?string $providerMessageId = null): WhatsAppConversation
    {
        return DB::transaction(function () use ($phoneNumber, $text, $payload, $profileName, $providerMessageId) {
            $conversation = $this->conversationFor($phoneNumber, $profileName);
            $normalized = Str::upper(trim($text));

            $message = WhatsAppMessage::query()->firstOrCreate(
                ['provider_message_id' => $providerMessageId],
                [
                    'whatsapp_conversation_id' => $conversation->id,
                    'direction' => 'inbound',
                    'phone_number' => $phoneNumber,
                    'message_type' => 'text',
                    'body' => $text,
                    'payload' => $payload,
                    'received_at' => now(),
                ],
            );

            $conversation->forceFill(['last_inbound_at' => now()])->save();

            if ($providerMessageId !== null && ! $message->wasRecentlyCreated) {
                return $conversation->fresh();
            }

            if (in_array($normalized, ['STOP', 'UNSUBSCRIBE'], true)) {
                $this->unsubscribe($phoneNumber, $text, $payload);
                $this->reply($conversation, 'You have been unsubscribed from StridePulse event updates. Reply TRACKME if you are an athlete starting a new event.');

                return $conversation->fresh();
            }

            if (in_array($normalized, ['YES', 'Y', 'NO', 'N'], true) && $this->handleSupporterConsentReply($phoneNumber, $normalized, $text, $payload)) {
                return $conversation->fresh();
            }

            if (in_array($normalized, ['HI', 'HELLO', 'LINK', 'STRIDEPULSE'], true)) {
                $conversation->forceFill(['state' => WhatsAppConversation::STATE_IDLE])->save();
                $this->reply($conversation, 'Welcome to StridePulse Live. Reply TRACKME to register an event and arm live tracking.');

                return $conversation->fresh();
            }

            if ($normalized === 'TRACKME') {
                $conversation->forceFill([
                    'state' => WhatsAppConversation::STATE_AWAITING_EVENT_NAME,
                    'context' => [],
                ])->save();
                $this->reply($conversation, 'What is the event name?');

                return $conversation->fresh();
            }

            return $this->advanceTrackMeFlow($conversation, $text);
        });
    }

    private function conversationFor(string $phoneNumber, ?string $profileName): WhatsAppConversation
    {
        $normalizedPhoneNumber = $this->phoneNumbers->normalize($phoneNumber);
        $conversationPhoneNumber = $normalizedPhoneNumber ?? $phoneNumber;
        $athlete = $this->identity->resolve($phoneNumber, $profileName);

        $conversation = WhatsAppConversation::query()
            ->whereIn('phone_number', array_values(array_unique([$conversationPhoneNumber, $phoneNumber])))
            ->first();

        if (! $conversation) {
            $conversation = new WhatsAppConversation([
                'phone_number' => $conversationPhoneNumber,
            ]);
        }

        if (! $conversation->exists) {
            $conversation->fill([
                'athlete_id' => $athlete?->id,
                'profile_name' => $profileName,
                'state' => WhatsAppConversation::STATE_IDLE,
                'context' => [],
            ])->save();
        } else {
            $updates = [];

            if ($normalizedPhoneNumber !== null && $conversation->phone_number !== $normalizedPhoneNumber) {
                $updates['phone_number'] = $normalizedPhoneNumber;
            }

            if ($athlete && (int) $conversation->athlete_id !== (int) $athlete->id) {
                $updates['athlete_id'] = $athlete->id;
            }

            if ($profileName && $conversation->profile_name !== $profileName) {
                $updates['profile_name'] = $profileName;
            }

            if ($updates !== []) {
                $conversation->forceFill($updates)->save();
            }
        }

        return $conversation->fresh('athlete');
    }

    private function advanceTrackMeFlow(WhatsAppConversation $conversation, string $text): WhatsAppConversation
    {
        $context = $conversation->context ?? [];

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_EVENT_NAME) {
            $context['event_name'] = trim($text);
            $conversation->forceFill([
                'state' => WhatsAppConversation::STATE_AWAITING_EVENT_DATE,
                'context' => $context,
            ])->save();
            $this->reply($conversation, 'What date is the event? Use YYYY-MM-DD.');

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_EVENT_DATE) {
            try {
                $eventDate = Carbon::createFromFormat('Y-m-d', trim($text));

                if ($eventDate === false || $eventDate->format('Y-m-d') !== trim($text)) {
                    throw new \InvalidArgumentException('Invalid event date format.');
                }

                $context['event_date'] = $eventDate->toDateString();
            } catch (\Throwable) {
                $this->reply($conversation, 'Please send the event date as YYYY-MM-DD.');

                return $conversation->fresh();
            }

            $conversation->forceFill([
                'state' => WhatsAppConversation::STATE_AWAITING_EVENT_DISTANCE,
                'context' => $context,
            ])->save();
            $this->reply($conversation, 'What is the event distance? Example: 21.1km or 42 km.');

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_EVENT_DISTANCE) {
            $distanceM = $this->parseDistanceMeters($text);

            if ($distanceM === null) {
                $this->reply($conversation, 'Please send a valid distance like 10km, 21.1km, or 100 miles.');

                return $conversation->fresh();
            }

            $context['distance_m'] = $distanceM;
            $conversation->forceFill([
                'state' => WhatsAppConversation::STATE_AWAITING_EVENT_TYPE,
                'context' => $context,
            ])->save();
            $this->reply($conversation, 'What type of event is it? Running, cycling, triathlon, trail, or ultra?');

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_EVENT_TYPE) {
            $context['event_type'] = Str::lower(trim($text));
            $conversation->forceFill([
                'state' => WhatsAppConversation::STATE_AWAITING_SUPPORTERS,
                'context' => $context,
            ])->save();
            $this->reply($conversation, 'Send supporter mobile numbers separated by commas. They will only receive updates after they reply YES.');

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_SUPPORTERS) {
            $numbers = $this->parsePhoneNumbers($text);

            if ($numbers === []) {
                $this->reply($conversation, 'Please send at least one supporter mobile number, separated by commas.');

                return $conversation->fresh();
            }

            $context['supporters'] = array_slice($numbers, 0, (int) config('stridepulse.whatsapp.max_supporters', 5));
            $conversation->forceFill([
                'state' => WhatsAppConversation::STATE_AWAITING_CONSENT,
                'context' => $context,
            ])->save();
            $this->reply($conversation, 'Reply YES to confirm you have permission to invite these supporters and arm this event.');

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_AWAITING_CONSENT) {
            if (! in_array(Str::upper(trim($text)), ['YES', 'Y'], true)) {
                $this->reply($conversation, 'Event setup is paused. Reply YES when you are ready to invite supporters.');

                return $conversation->fresh();
            }

            $this->createEventAndArmSession($conversation);

            return $conversation->fresh();
        }

        if ($conversation->state === WhatsAppConversation::STATE_SESSION_ARMED) {
            $this->reply($conversation, 'Your StridePulse session is already armed. Start the Garmin activity when ready.');

            return $conversation->fresh();
        }

        $this->reply($conversation, 'Reply TRACKME to register an event and arm live tracking.');

        return $conversation->fresh();
    }

    private function createEventAndArmSession(WhatsAppConversation $conversation): void
    {
        $conversation->loadMissing('athlete');
        $context = $conversation->context ?? [];
        $athlete = $conversation->athlete;

        $event = Event::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $context['event_name'],
            'event_date' => $context['event_date'],
            'metadata' => [
                'distance_m' => $context['distance_m'],
                'event_type' => $context['event_type'],
                'source' => 'whatsapp_trackme',
            ],
        ]);

        $raceEntry = RaceEntry::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'athlete_id' => $athlete->id,
            'metadata' => [
                'source' => 'whatsapp_trackme',
            ],
        ]);

        $sport = Sport::query()->firstOrCreate(['name' => $context['event_type']]);
        $device = $athlete->devices()
            ->whereIn('status', [Device::STATUS_CLAIMED, Device::STATUS_READY, Device::STATUS_LIVE, Device::STATUS_OFFLINE, 'active'])
            ->latest('last_seen_at')
            ->latest('id')
            ->first();

        $trackingSession = TrackingSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'session_token' => Str::random(48),
            'athlete_id' => $athlete->id,
            'device_id' => $device?->id,
            'sport_id' => $sport->id,
            'race_entry_id' => $raceEntry->id,
            'device_source' => 'garmin_connect_iq',
            'activity_type' => Str::slug($sport->name, '_'),
            'status' => 'active',
            'started_at' => now(),
            'telemetry_source' => 'connect_iq',
            'metadata' => [
                'armed_by' => 'whatsapp_trackme',
                'event_distance_m' => $context['distance_m'],
            ],
        ]);

        foreach ($context['supporters'] as $number) {
            $invitation = SupporterInvitation::query()->create([
                'athlete_id' => $athlete->id,
                'event_id' => $event->id,
                'tracking_session_id' => $trackingSession->id,
                'phone_number' => $number,
                'status' => SupporterInvitation::STATUS_PENDING,
                'metadata' => [
                    'athlete_name' => $athlete->name,
                    'event_name' => $event->name,
                ],
            ]);

            $this->dispatch->sendTemplate(
                $number,
                config('stridepulse.whatsapp.templates.supporter_invite', 'supporter_invite'),
                "supporter_invite:{$invitation->id}",
                [$athlete->name, $event->name],
                event: $event,
                trackingSession: $trackingSession,
            );

            $invitation->forceFill(['sent_at' => now()])->save();
        }

        $conversation->forceFill([
            'state' => WhatsAppConversation::STATE_SESSION_ARMED,
            'context' => array_merge($context, [
                'event_id' => $event->id,
                'race_entry_id' => $raceEntry->id,
                'tracking_session_id' => $trackingSession->id,
            ]),
        ])->save();

        $this->reply($conversation, "Your StridePulse session is armed for {$event->name}. Start the Garmin activity when ready.");
    }

    private function handleSupporterConsentReply(string $phoneNumber, string $normalized, string $text, array $payload): bool
    {
        $invitation = SupporterInvitation::query()
            ->where('phone_number', $phoneNumber)
            ->where('status', SupporterInvitation::STATUS_PENDING)
            ->latest()
            ->first();

        if (! $invitation) {
            return false;
        }

        $accepted = in_array($normalized, ['YES', 'Y'], true);
        $supporter = Supporter::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supporter '.$this->lastDigits($phoneNumber),
                'metadata' => ['phone_number' => $phoneNumber],
            ],
        );

        $consent = SupporterConsent::query()->create([
            'supporter_id' => $supporter->id,
            'supporter_invitation_id' => $invitation->id,
            'event_id' => $invitation->event_id,
            'phone_number' => $phoneNumber,
            'status' => $accepted ? SupporterConsent::STATUS_OPTED_IN : SupporterConsent::STATUS_DECLINED,
            'response_text' => $text,
            'consented_at' => $accepted ? now() : null,
            'audit_payload' => $payload,
        ]);

        $invitation->forceFill([
            'supporter_id' => $supporter->id,
            'status' => $accepted ? SupporterInvitation::STATUS_ACCEPTED : SupporterInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ])->save();

        if ($accepted) {
            EventFollower::query()->updateOrCreate(
                ['event_id' => $invitation->event_id, 'phone_number' => $phoneNumber],
                [
                    'athlete_id' => $invitation->athlete_id,
                    'supporter_id' => $supporter->id,
                    'supporter_consent_id' => $consent->id,
                    'status' => 'active',
                    'opted_in_at' => now(),
                ],
            );

            $this->dispatch->sendText($phoneNumber, 'You are opted in for StridePulse updates. Reply STOP anytime to unsubscribe.', "supporter_consent_yes:{$consent->id}");
        } else {
            $this->dispatch->sendText($phoneNumber, 'No problem. You will not receive StridePulse updates for this event.', "supporter_consent_no:{$consent->id}");
        }

        return true;
    }

    private function unsubscribe(string $phoneNumber, string $text, array $payload): void
    {
        EventFollower::query()
            ->where('phone_number', $phoneNumber)
            ->where('status', 'active')
            ->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);

        SupporterConsent::query()->create([
            'phone_number' => $phoneNumber,
            'status' => SupporterConsent::STATUS_UNSUBSCRIBED,
            'source' => 'whatsapp_stop',
            'response_text' => $text,
            'revoked_at' => now(),
            'audit_payload' => $payload,
        ]);
    }

    private function reply(WhatsAppConversation $conversation, string $body): void
    {
        $this->dispatch->sendText(
            $conversation->phone_number,
            $body,
            'conversation_reply:'.$conversation->id.':'.sha1($conversation->state.'|'.$body.'|'.now()->format('YmdHi')),
            $conversation,
        );
    }

    private function parseDistanceMeters(string $text): ?int
    {
        if (! preg_match('/^\s*(\d+(?:\.\d+)?)\s*(km|k|miles|mile|mi|m)?\s*$/i', $text, $matches)) {
            return null;
        }

        $distance = (float) $matches[1];

        if ($distance <= 0) {
            return null;
        }
        $unit = Str::lower($matches[2] ?? 'km');

        return (int) round(match ($unit) {
            'm' => $distance,
            'mile', 'miles', 'mi' => $distance * 1609.344,
            default => $distance * 1000,
        });
    }

    private function parsePhoneNumbers(string $text): array
    {
        preg_match_all('/\+?\d[\d\s-]{6,}\d/', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($number) => preg_replace('/[^\d+]/', '', $number))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function lastDigits(string $phoneNumber): string
    {
        return substr(preg_replace('/\D/', '', $phoneNumber), -4) ?: 'user';
    }
}
