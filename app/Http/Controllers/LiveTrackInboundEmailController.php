<?php

namespace App\Http\Controllers;

use App\Models\LiveTrackInboundMessage;
use App\Models\TrackingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LiveTrackInboundEmailController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_alias' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'raw_body' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'text' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'received_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $rawBody = $this->bodyFrom($validated);
        $recipientAlias = $this->recipientAliasFrom($validated);
        $fromEmail = $this->emailFrom($validated['from_email'] ?? $validated['from'] ?? null);
        $extractedUrl = $this->extractGarminLiveTrackUrl($rawBody);
        $trackingSession = $this->findActiveTrackingSession($recipientAlias);
        $receivedAt = isset($validated['received_at'])
            ? Carbon::parse($validated['received_at'])
            : now();

        $message = LiveTrackInboundMessage::query()->create([
            'tracking_session_id' => $trackingSession?->id,
            'athlete_id' => $trackingSession?->athlete_id,
            'recipient_alias' => $recipientAlias,
            'from_email' => $fromEmail,
            'subject' => $validated['subject'] ?? null,
            'raw_body' => $rawBody,
            'extracted_url' => $extractedUrl,
            'received_at' => $receivedAt,
            'processed_at' => now(),
            'status' => $extractedUrl ? 'received' : 'no_url',
            'metadata' => $validated['metadata'] ?? [],
        ]);

        if ($trackingSession && $extractedUrl) {
            $trackingSession->forceFill([
                'livetrack_url' => $extractedUrl,
                'livetrack_received_at' => $receivedAt,
                'livetrack_source_email' => $fromEmail,
                'telemetry_source' => 'hybrid',
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'status' => $message->status,
            'message' => 'LiveTrack email received',
        ]);
    }

    private function bodyFrom(array $payload): string
    {
        return (string) (
            $payload['raw_body']
            ?? $payload['body']
            ?? $payload['text']
            ?? $payload['html']
            ?? ''
        );
    }

    private function recipientAliasFrom(array $payload): ?string
    {
        $alias = $payload['recipient_alias'] ?? null;

        if (! $alias) {
            $recipient = $payload['recipient'] ?? $payload['to'] ?? null;
            $alias = $this->aliasFromEmail($recipient);
        }

        return $alias ? Str::lower(trim($alias)) : null;
    }

    private function aliasFromEmail(?string $value): ?string
    {
        $email = $this->emailFrom($value);

        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        $localPart = Str::before($email, '@');

        return Str::contains($localPart, '+')
            ? Str::after($localPart, '+')
            : $localPart;
    }

    private function emailFrom(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);

        return Arr::first($matches);
    }

    private function extractGarminLiveTrackUrl(string $body): ?string
    {
        preg_match_all('/https?:\/\/[^\s<>"\']+/i', $body, $matches);

        foreach ($matches[0] as $url) {
            $cleanUrl = rtrim($url, '.,);]');
            $host = Str::lower(parse_url($cleanUrl, PHP_URL_HOST) ?? '');
            $path = Str::lower(parse_url($cleanUrl, PHP_URL_PATH) ?? '');

            if (str_contains($host, 'garmin') && (str_contains($host, 'livetrack') || str_contains($path, 'livetrack'))) {
                return $cleanUrl;
            }
        }

        return null;
    }

    private function findActiveTrackingSession(?string $recipientAlias): ?TrackingSession
    {
        if (! $recipientAlias) {
            return null;
        }

        return TrackingSession::query()
            ->whereNull('ended_at')
            ->where(function ($query) use ($recipientAlias) {
                $query
                    ->where('session_token', $recipientAlias)
                    ->orWhere('uuid', $recipientAlias);
            })
            ->first();
    }
}
