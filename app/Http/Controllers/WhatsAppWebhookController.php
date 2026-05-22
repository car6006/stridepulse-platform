<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        if (
            ($request->query('hub.mode') ?? $request->query('hub_mode')) === 'subscribe'
            && hash_equals((string) config('services.whatsapp.webhook_verify_token'), (string) ($request->query('hub.verify_token') ?? $request->query('hub_verify_token')))
        ) {
            return response((string) ($request->query('hub.challenge') ?? $request->query('hub_challenge')), 200);
        }

        return response('Forbidden', 403);
    }

    public function store(Request $request, WhatsAppConversationService $conversations): JsonResponse
    {
        foreach ($this->messagesFromPayload($request->all()) as $message) {
            if (($message['type'] ?? null) !== 'text') {
                continue;
            }

            $conversations->handleInboundText(
                phoneNumber: $message['from'],
                text: data_get($message, 'text.body', ''),
                payload: $message,
                profileName: $message['profile_name'] ?? null,
                providerMessageId: $message['id'] ?? null,
            );
        }

        return response()->json(['ok' => true]);
    }

    private function messagesFromPayload(array $payload): array
    {
        $messages = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $contacts = collect(data_get($change, 'value.contacts', []))->keyBy('wa_id');

                foreach (data_get($change, 'value.messages', []) as $message) {
                    $contact = $contacts->get($message['from'] ?? '');
                    $message['profile_name'] = data_get($contact, 'profile.name');
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }
}
