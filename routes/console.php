<?php

use App\Services\OperationsHealthService;
use App\Services\WhatsAppTemplateRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:test-template {template_key} {phone_number} {parameters?*}', function () {
    $templateKey = (string) $this->argument('template_key');
    $phoneNumber = (string) $this->argument('phone_number');
    $parameters = (array) $this->argument('parameters');

    /** @var WhatsAppTemplateRegistry $templates */
    $templates = app(WhatsAppTemplateRegistry::class);
    $token = config('services.whatsapp.token');
    $phoneNumberId = config('services.whatsapp.phone_number_id');
    $businessAccountId = config('services.whatsapp.business_account_id');

    if (blank($token)) {
        $this->error('Missing config: services.whatsapp.token');

        return 1;
    }

    if (blank($phoneNumberId)) {
        $this->error('Missing config: services.whatsapp.phone_number_id');

        return 1;
    }

    try {
        $payload = $templates->payload($templateKey, $phoneNumber, $parameters);
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

    $this->line('Template key: '.$templateKey);
    $this->line('Template name: '.data_get($payload, 'template.name'));
    $this->line('Language code: '.data_get($payload, 'template.language.code'));
    $this->line('Phone number id: '.$phoneNumberId);
    $this->line('Business account id: '.($businessAccountId ?: 'not configured'));
    $this->line('Graph API URL: '.$url);
    $this->line('Payload:');
    $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $response = Http::withToken($token)
        ->acceptJson()
        ->post($url, $payload);

    if ($response->successful()) {
        $this->info('Template sent.');
        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $this->error('Template send failed with HTTP '.$response->status().'.');
    $this->line($response->body());

    return 1;
})->purpose('Send a configured WhatsApp template directly through the Cloud API');

Artisan::command('stridepulse:health', function () {
    /** @var OperationsHealthService $health */
    $health = app(OperationsHealthService::class);
    $summary = $health->summary();

    $this->line('StridePulse health');
    $this->line('WhatsApp config: '.$summary['whatsapp']['api_config_status']['label']);
    $this->line('WhatsApp token configured: '.($summary['whatsapp']['token_configured'] ? 'yes' : 'no'));
    $this->line('WhatsApp phone number id configured: '.($summary['whatsapp']['phone_number_id_configured'] ? 'yes' : 'no'));
    $this->line('WhatsApp business account id configured: '.($summary['whatsapp']['business_account_id_configured'] ? 'yes' : 'no'));
    $this->line('Queue pending jobs: '.$summary['queue']['pending_jobs_count']);
    $this->line('Queue failed jobs: '.$summary['queue']['failed_jobs_count']);
    $this->line('Last inbound WhatsApp: '.($summary['whatsapp']['latest_inbound_message']?->received_at?->toIso8601String() ?? 'none'));
    $this->line('Last outbound WhatsApp: '.($summary['whatsapp']['latest_outbound_dispatch']?->updated_at?->toIso8601String() ?? 'none'));
    $this->line('Garmin last discovery: '.($summary['garmin']['latest_device_heartbeat']?->last_seen_at?->toIso8601String() ?? 'none'));
    $this->line('Garmin last telemetry: '.($summary['garmin']['latest_telemetry_point']?->recorded_at?->toIso8601String() ?? 'none'));
    $this->line('Active sessions: '.$summary['garmin']['active_sessions_count']);
    $this->line('Unclaimed devices: '.$summary['garmin']['unclaimed_devices_count']);

    return 0;
})->purpose('Show StridePulse operational health summary');
