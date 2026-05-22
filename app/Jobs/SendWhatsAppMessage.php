<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

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

        if (blank($token) || blank($phoneNumberId)) {
            $dispatch->forceFill([
                'status' => WhatsAppMessageDispatch::STATUS_SKIPPED,
                'last_error' => 'WhatsApp Cloud API credentials are not configured.',
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

        $dispatch->increment('attempts');

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", $payload);

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
