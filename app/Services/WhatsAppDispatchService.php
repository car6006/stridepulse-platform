<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Event;
use App\Models\TrackingSession;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessageDispatch;

class WhatsAppDispatchService
{
    public function __construct(private WhatsAppTemplateRegistry $templates) {}

    public function sendText(
        string $phoneNumber,
        string $body,
        string $dedupeKey,
        ?WhatsAppConversation $conversation = null,
        ?TrackingSession $trackingSession = null,
        ?Event $event = null,
    ): WhatsAppMessageDispatch {
        $dispatch = WhatsAppMessageDispatch::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'whatsapp_conversation_id' => $conversation?->id,
                'tracking_session_id' => $trackingSession?->id,
                'event_id' => $event?->id,
                'phone_number' => $phoneNumber,
                'body' => $body,
                'status' => WhatsAppMessageDispatch::STATUS_QUEUED,
                'payload' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $phoneNumber,
                    'type' => 'text',
                    'text' => [
                        'body' => $body,
                        'preview_url' => false,
                    ],
                ],
            ],
        );

        if ($dispatch->wasRecentlyCreated) {
            SendWhatsAppMessage::dispatch($dispatch->id);
            $conversation?->forceFill(['last_outbound_at' => now()])->save();
        }

        return $dispatch;
    }

    public function sendTemplate(
        string $phoneNumber,
        string $templateKey,
        string $dedupeKey,
        array $parameters = [],
        ?WhatsAppConversation $conversation = null,
        ?TrackingSession $trackingSession = null,
        ?Event $event = null,
    ): WhatsAppMessageDispatch {
        $payload = $this->templates->payload($templateKey, $phoneNumber, $parameters);
        $templateName = data_get($payload, 'template.name');

        $dispatch = WhatsAppMessageDispatch::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'whatsapp_conversation_id' => $conversation?->id,
                'tracking_session_id' => $trackingSession?->id,
                'event_id' => $event?->id,
                'phone_number' => $phoneNumber,
                'template_name' => $templateName,
                'status' => WhatsAppMessageDispatch::STATUS_QUEUED,
                'payload' => $payload,
            ],
        );

        if ($dispatch->wasRecentlyCreated) {
            SendWhatsAppMessage::dispatch($dispatch->id);
            $conversation?->forceFill(['last_outbound_at' => now()])->save();
        }

        return $dispatch;
    }
}
