<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Str;

class WhatsAppIdentityResolver
{
    public function __construct(private PhoneNumberNormalizer $phoneNumbers) {}

    public function resolve(string $phoneNumber, ?string $profileName = null): ?Athlete
    {
        $normalized = $this->phoneNumbers->normalize($phoneNumber);

        if ($normalized === null) {
            return null;
        }

        $athlete = Athlete::query()
            ->where('whatsapp_phone_e164', $normalized)
            ->first();

        if ($athlete instanceof Athlete) {
            return $this->fillMissingWhatsAppIdentity($athlete, $phoneNumber, $normalized, $profileName);
        }

        $athlete = Athlete::query()
            ->where('mobile_phone_e164', $normalized)
            ->first();

        if ($athlete instanceof Athlete) {
            return $this->fillMissingWhatsAppIdentity($athlete, $phoneNumber, $normalized, $profileName);
        }

        $conversation = WhatsAppConversation::query()
            ->whereIn('phone_number', array_values(array_unique([$normalized, $phoneNumber])))
            ->whereNotNull('athlete_id')
            ->with('athlete')
            ->first();

        if ($conversation?->athlete instanceof Athlete) {
            return $this->fillMissingWhatsAppIdentity($conversation->athlete, $phoneNumber, $normalized, $profileName);
        }

        $displayName = $profileName ?: $normalized;

        return Athlete::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $displayName,
            'display_name' => $displayName,
            'mobile_phone_e164' => $normalized,
            'whatsapp_phone' => $phoneNumber,
            'whatsapp_phone_e164' => $normalized,
            'onboarding_status' => 'whatsapp_started',
            'subscription_status' => 'trial',
            'metadata' => [
                'source' => 'whatsapp',
            ],
        ]);
    }

    private function fillMissingWhatsAppIdentity(Athlete $athlete, string $phoneNumber, string $normalized, ?string $profileName): Athlete
    {
        $updates = [];

        foreach ([
            'display_name' => $profileName ?: $athlete->display_name ?: $athlete->name,
            'mobile_phone_e164' => $athlete->mobile_phone_e164 ?: $normalized,
            'whatsapp_phone' => $athlete->whatsapp_phone ?: $phoneNumber,
            'whatsapp_phone_e164' => $athlete->whatsapp_phone_e164 ?: $normalized,
            'onboarding_status' => $athlete->onboarding_status ?: 'whatsapp_started',
            'subscription_status' => $athlete->subscription_status ?: 'trial',
        ] as $field => $value) {
            if (($athlete->{$field} ?? null) !== $value) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $athlete->forceFill($updates)->save();
        }

        return $athlete->fresh();
    }
}
