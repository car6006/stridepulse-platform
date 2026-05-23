<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $dispatchId) {}

    public function handle(): void
    {
        $dispatch = WhatsAppMessageDispatch::query()->findOrFail($this->dispatchId);

        if ($dispatch->status === WhatsAppMessageDispatch::STATUS_SENT) {
            return;
        }

        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $businessAccountId = config('services.whatsapp.business_account_id');

        if (blank($token)) {
            Log::warning('WhatsApp outbound dispatch missing API token', [
                'dispatch_id' => $dispatch->id,
                'dedupe_key' => $dispatch->dedupe_key,
                'phone_number' => $dispatch->phone_number,
                'config_key' => 'services.whatsapp.token',
            ]);

            $dispatch->forceFill([
                'status' => WhatsAppMessageDispatch::STATUS_SKIPPED,
                'last_error' => 'WhatsApp Cloud API token is not configured.',
            ])->save();

            return;
        }

        if (blank($phoneNumberId)) {
            Log::warning('WhatsApp outbound dispatch missing phone number id', [
                'dispatch_id' => $dispatch->id,
                'dedupe_key' => $dispatch->dedupe_key,
                'phone_number' => $dispatch->phone_number,
                'config_key' => 'services.whatsapp.phone_number_id',
            ]);

            $dispatch->forceFill([
                'status' => WhatsAppMessageDispatch::STATUS_SKIPPED,
                'last_error' => 'WhatsApp phone number id is not configured.',
            ])->save();

            return;
        }

        $payload = $dispatch->payload ?: [
            'messaging_product' => 'whatsapp',
            'to' => $dispatch->phone_number,
            'type' => 'text',
            'text' => [
                'body' => $dispatch->body,
                'preview_url' => false,
            ],
        ];

        $graphApiUrl = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";
        $templateName = data_get($payload, 'template.name');
        $languageCode = data_get($payload, 'template.language.code');

        $dispatch->increment('attempts');

        Log::info('WhatsApp outbound dispatch sending', [
            'dispatch_id' => $dispatch->id,
            'dedupe_key' => $dispatch->dedupe_key,
            'phone_number' => $dispatch->phone_number,
            'template_name' => $templateName ?? $dispatch->template_name,
            'language_code' => $languageCode,
            'phone_number_id' => $phoneNumberId,
            'business_account_id' => $businessAccountId,
            'graph_api_url' => $graphApiUrl,
            'payload' => $payload,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($graphApiUrl, $payload);

        Log::info('WhatsApp outbound dispatch response received', [
            'dispatch_id' => $dispatch->id,
            'dedupe_key' => $dispatch->dedupe_key,
            'template_name' => $templateName ?? $dispatch->template_name,
            'language_code' => $languageCode,
            'phone_number_id' => $phoneNumberId,
            'business_account_id' => $businessAccountId,
            'graph_api_url' => $graphApiUrl,
            'status_code' => $response->status(),
            'successful' => $response->successful(),
        ]);

        if ($response->successful()) {
            $dispatch->forceFill([
                'status' => WhatsAppMessageDispatch::STATUS_SENT,
                'provider_message_id' => data_get($response->json(), 'messages.0.id'),
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            return;
        }

        $dispatch->forceFill([
            'status' => WhatsAppMessageDispatch::STATUS_FAILED,
            'last_error' => $response->body(),
        ])->save();

        $response->throw();
    }
}
