<?php

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
