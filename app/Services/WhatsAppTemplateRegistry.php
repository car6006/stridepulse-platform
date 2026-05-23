<?php

namespace App\Services;

use InvalidArgumentException;

class WhatsAppTemplateRegistry
{
    public const SUPPORTER_INVITE = 'supporter_invite';

    public const TRACKING_STARTED = 'tracking_started';

    public const CHECKPOINT_PROGRESS = 'checkpoint_progress';

    public const FINISH_TIME = 'finish_time';

    public const ESTIMATED_FINISH = 'estimated_finish';

    public const TELEMETRY_LOST = 'telemetry_lost';

    public const TELEMETRY_RESTORED = 'telemetry_restored';

    public const STOPPED_MOVING = 'stopped_moving';

    public const EVENT_COMPLETED = 'event_completed';

    public const DEVICE_AVAILABLE = 'device_available';

    public function resolve(string $key): string
    {
        $templateName = config("stridepulse.whatsapp.templates.{$key}");

        if (! is_string($templateName) || trim($templateName) === '') {
            throw new InvalidArgumentException("WhatsApp template [{$key}] is not configured.");
        }

        return trim($templateName);
    }

    public function languageCode(): string
    {
        $language = config('services.whatsapp.language', 'en_US');

        return is_string($language) && trim($language) !== '' ? trim($language) : 'en_US';
    }

    public function payload(string $key, string $phoneNumber, array $parameters = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $this->resolve($key),
                'language' => ['code' => $this->languageCode()],
            ],
        ];

        if ($parameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($value) => [
                    'type' => 'text',
                    'text' => (string) $value,
                ], $parameters),
            ]];
        }

        return $payload;
    }

    public function configuredTemplates(): array
    {
        return (array) config('stridepulse.whatsapp.templates', []);
    }
}
