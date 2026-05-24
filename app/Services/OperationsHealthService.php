<?php

namespace App\Services;

use App\Models\Device;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OperationsHealthService
{
    public function summary(): array
    {
        $pendingJobs = $this->pendingJobsCount();
        $failedJobs = $this->failedJobsCount();
        $latestProcessedDispatch = $this->latestProcessedDispatch();
        $latestFailedDispatch = $this->latestFailedDispatch();
        $latestInbound = $this->latestInboundMessage();
        $latestOutbound = $this->latestOutboundDispatch();
        $latestDeviceHeartbeat = $this->latestDeviceHeartbeat();
        $latestTelemetryPoint = $this->latestTelemetryPoint();
        $latestDeviceTelemetry = $this->latestDeviceTelemetry();
        $sessionsWithNoTelemetry = $this->sessionsWithNoTelemetryCount();
        $activeSessions = $this->activeSessionsCount();
        $activeDevices = $this->activeDevicesCount();
        $unclaimedDevices = $this->unclaimedDevicesCount();

        return [
            'generated_at' => now(),
            'queue' => [
                'connection' => config('queue.default'),
                'pending_jobs_count' => $pendingJobs,
                'failed_jobs_count' => $failedJobs,
                'latest_processed_dispatch' => $latestProcessedDispatch,
                'latest_failed_dispatch' => $latestFailedDispatch,
                'stale' => $this->whatsappQueueIsStale($pendingJobs, $latestProcessedDispatch),
                'status' => $this->queueStatus($pendingJobs, $failedJobs, $latestProcessedDispatch, $latestFailedDispatch),
            ],
            'whatsapp' => [
                'webhook_status' => 'configured',
                'webhook_status_level' => 'green',
                'token_configured' => filled(config('services.whatsapp.token')),
                'phone_number_id_configured' => filled(config('services.whatsapp.phone_number_id')),
                'business_account_id_configured' => filled(config('services.whatsapp.business_account_id')),
                'template_language' => config('services.whatsapp.language', 'en_US'),
                'supporter_invite_template' => config('stridepulse.whatsapp.templates.supporter_invite'),
                'latest_inbound_message' => $latestInbound,
                'latest_outbound_dispatch' => $latestOutbound,
                'latest_failed_dispatch' => $latestFailedDispatch,
                'api_config_status' => $this->whatsappConfigStatus(),
            ],
            'garmin' => [
                'latest_device_heartbeat' => $latestDeviceHeartbeat,
                'latest_telemetry_point' => $latestTelemetryPoint,
                'latest_device_telemetry' => $latestDeviceTelemetry,
                'active_devices_count' => $activeDevices,
                'unclaimed_devices_count' => $unclaimedDevices,
                'active_sessions_count' => $activeSessions,
                'sessions_with_no_telemetry_count' => $sessionsWithNoTelemetry,
                'heartbeat_status' => $latestDeviceHeartbeat ? 'green' : 'amber',
                'telemetry_status' => $latestTelemetryPoint || $latestDeviceTelemetry ? 'green' : 'amber',
            ],
            'cards' => $this->cards($pendingJobs, $failedJobs, $latestInbound, $latestOutbound, $latestFailedDispatch, $latestDeviceHeartbeat, $latestTelemetryPoint, $activeSessions, $unclaimedDevices),
        ];
    }

    public function diagnosticsText(): string
    {
        $summary = $this->summary();
        $latestActiveSession = $this->latestActiveSession();

        return implode("\n", [
            'StridePulse diagnostics',
            'App env: '.config('app.env'),
            'Current time: '.now()->toIso8601String(),
            'Queue connection: '.$summary['queue']['connection'],
            'Queue pending jobs: '.$summary['queue']['pending_jobs_count'],
            'Queue failed jobs: '.$summary['queue']['failed_jobs_count'],
            'WhatsApp token configured: '.($summary['whatsapp']['token_configured'] ? 'yes' : 'no'),
            'WhatsApp phone number id configured: '.($summary['whatsapp']['phone_number_id_configured'] ? 'yes' : 'no'),
            'WhatsApp business account id configured: '.($summary['whatsapp']['business_account_id_configured'] ? 'yes' : 'no'),
            'WhatsApp template language: '.$summary['whatsapp']['template_language'],
            'Supporter invite template: '.($summary['whatsapp']['supporter_invite_template'] ?: 'not configured'),
            'Latest dispatch error: '.$this->redact($summary['whatsapp']['latest_failed_dispatch']?->last_error ?: 'none'),
            'Latest device heartbeat: '.($summary['garmin']['latest_device_heartbeat']?->last_seen_at?->toIso8601String() ?: 'none'),
            'Latest active session: '.($latestActiveSession?->session_token ? 'session '.$latestActiveSession->id : 'none'),
            'Active sessions: '.$summary['garmin']['active_sessions_count'],
            'Unclaimed devices: '.$summary['garmin']['unclaimed_devices_count'],
            'Failed jobs count: '.$summary['queue']['failed_jobs_count'],
        ]);
    }

    public function logs(string $filter = 'all', int $limit = 50): array
    {
        $limit = in_array($limit, [50, 100, 250], true) ? $limit : 50;

        return [
            'laravel' => $this->laravelLogExcerpts($filter, $limit),
            'dispatches' => $this->whenTableFilter('whatsapp_message_dispatches', $filter, ['all', 'whatsapp', 'failed'], fn () => WhatsAppMessageDispatch::query()
                ->when($filter === 'failed', fn ($query) => $query->whereIn('status', [WhatsAppMessageDispatch::STATUS_FAILED, WhatsAppMessageDispatch::STATUS_SKIPPED]))
                ->latest('updated_at')
                ->latest('id')
                ->limit($limit)
                ->get()),
            'inbound_messages' => $this->whenTableFilter('whatsapp_messages', $filter, ['all', 'whatsapp'], fn () => WhatsAppMessage::query()
                ->where('direction', 'inbound')
                ->latest('received_at')
                ->latest('id')
                ->limit($limit)
                ->get()),
            'devices' => $this->whenTableFilter('devices', $filter, ['all', 'garmin'], fn () => Device::query()
                ->whereNotNull('last_seen_at')
                ->latest('last_seen_at')
                ->latest('id')
                ->limit($limit)
                ->get()),
            'sessions' => $this->whenTableFilter('tracking_sessions', $filter, ['all', 'sessions'], fn () => TrackingSession::query()
                ->with('athlete', 'device')
                ->latest('updated_at')
                ->latest('id')
                ->limit($limit)
                ->get()),
            'failed_jobs' => $this->whenFilter($filter, ['all', 'failed'], fn () => $this->failedJobs($limit)),
        ];
    }

    private function cards(int $pendingJobs, int $failedJobs, ?WhatsAppMessage $latestInbound, ?WhatsAppMessageDispatch $latestOutbound, ?WhatsAppMessageDispatch $latestFailedDispatch, ?Device $latestDeviceHeartbeat, ?TelemetryPoint $latestTelemetryPoint, int $activeSessions, int $unclaimedDevices): array
    {
        return [
            ['label' => 'WhatsApp webhook status', 'value' => 'Configured', 'level' => 'green', 'detail' => 'GET/POST webhook routes are registered.'],
            ['label' => 'WhatsApp API config loaded', 'value' => $this->whatsappConfigStatus()['label'], 'level' => $this->whatsappConfigStatus()['level'], 'detail' => 'Token hidden. Phone and business IDs are checked.'],
            ['label' => 'WhatsApp queue health', 'value' => $pendingJobs.' pending', 'level' => $this->queueStatus($pendingJobs, $failedJobs, $this->latestProcessedDispatch(), $latestFailedDispatch)['level'], 'detail' => $failedJobs.' failed jobs.'],
            ['label' => 'Last inbound WhatsApp', 'value' => $latestInbound?->received_at?->diffForHumans() ?? 'None', 'level' => $latestInbound ? 'green' : 'amber', 'detail' => $latestInbound?->phone_number ?? 'No inbound messages yet.'],
            ['label' => 'Last outbound WhatsApp', 'value' => $latestOutbound?->updated_at?->diffForHumans() ?? 'None', 'level' => $latestOutbound ? 'green' : 'amber', 'detail' => $latestOutbound?->status ?? 'No dispatches yet.'],
            ['label' => 'Last failed dispatch', 'value' => $latestFailedDispatch?->updated_at?->diffForHumans() ?? 'None', 'level' => $latestFailedDispatch?->updated_at?->gt(now()->subMinutes(10)) ? 'red' : ($latestFailedDispatch ? 'amber' : 'green'), 'detail' => Str::limit($latestFailedDispatch?->last_error ?? 'No failed dispatches.', 90)],
            ['label' => 'Garmin discovery heartbeat', 'value' => $latestDeviceHeartbeat?->last_seen_at?->diffForHumans() ?? 'None', 'level' => $latestDeviceHeartbeat ? 'green' : 'amber', 'detail' => $latestDeviceHeartbeat?->name ?? 'No device discovery yet.'],
            ['label' => 'Last Garmin telemetry', 'value' => $latestTelemetryPoint?->recorded_at?->diffForHumans() ?? 'None', 'level' => $latestTelemetryPoint ? 'green' : 'amber', 'detail' => $latestTelemetryPoint ? 'Tracking session '.$latestTelemetryPoint->tracking_session_id : 'No telemetry samples yet.'],
            ['label' => 'Active tracking sessions', 'value' => (string) $activeSessions, 'level' => $activeSessions > 0 ? 'green' : 'amber', 'detail' => 'Active, armed, live, paused, stopped, or stationary.'],
            ['label' => 'Unclaimed devices', 'value' => (string) $unclaimedDevices, 'level' => $unclaimedDevices > 0 ? 'amber' : 'green', 'detail' => 'Devices waiting for athlete claim.'],
            ['label' => 'Failed jobs', 'value' => (string) $failedJobs, 'level' => $failedJobs > 0 ? 'amber' : 'green', 'detail' => 'Laravel failed_jobs table.'],
        ];
    }

    private function whatsappConfigStatus(): array
    {
        $missing = collect([
            'token' => filled(config('services.whatsapp.token')),
            'phone_number_id' => filled(config('services.whatsapp.phone_number_id')),
            'business_account_id' => filled(config('services.whatsapp.business_account_id')),
        ])->filter(fn (bool $configured) => ! $configured);

        return [
            'label' => $missing->isEmpty() ? 'Loaded' : 'Missing '.$missing->count(),
            'level' => $missing->isEmpty() ? 'green' : 'red',
        ];
    }

    private function queueStatus(int $pendingJobs, int $failedJobs, ?WhatsAppMessageDispatch $latestProcessedDispatch, ?WhatsAppMessageDispatch $latestFailedDispatch): array
    {
        if ($latestFailedDispatch?->updated_at?->gt(now()->subMinutes(10))) {
            return ['label' => 'Recent failure', 'level' => 'red'];
        }

        if ($this->whatsappQueueIsStale($pendingJobs, $latestProcessedDispatch)) {
            return ['label' => 'Stale', 'level' => 'red'];
        }

        if ($failedJobs > 0 || $pendingJobs > 0) {
            return ['label' => 'Attention', 'level' => 'amber'];
        }

        return ['label' => 'Healthy', 'level' => 'green'];
    }

    private function whatsappQueueIsStale(int $pendingJobs, ?WhatsAppMessageDispatch $latestProcessedDispatch): bool
    {
        if ($pendingJobs <= 0) {
            return false;
        }

        return ! $latestProcessedDispatch?->updated_at || $latestProcessedDispatch->updated_at->lte(now()->subMinutes(5));
    }

    private function latestProcessedDispatch(): ?WhatsAppMessageDispatch
    {
        if (! $this->tableExists('whatsapp_message_dispatches')) {
            return null;
        }

        return WhatsAppMessageDispatch::query()
            ->whereIn('status', [WhatsAppMessageDispatch::STATUS_SENT, WhatsAppMessageDispatch::STATUS_FAILED, WhatsAppMessageDispatch::STATUS_SKIPPED])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function latestFailedDispatch(): ?WhatsAppMessageDispatch
    {
        if (! $this->tableExists('whatsapp_message_dispatches')) {
            return null;
        }

        return WhatsAppMessageDispatch::query()
            ->whereIn('status', [WhatsAppMessageDispatch::STATUS_FAILED, WhatsAppMessageDispatch::STATUS_SKIPPED])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function latestActiveSession(): ?TrackingSession
    {
        if (! $this->tableExists('tracking_sessions')) {
            return null;
        }

        return TrackingSession::query()
            ->whereIn('status', ['active', 'armed', 'live', 'paused', 'stopped', 'stationary'])
            ->whereNull('ended_at')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function pendingJobsCount(): int
    {
        return $this->tableCount('jobs');
    }

    private function failedJobsCount(): int
    {
        return $this->tableCount('failed_jobs');
    }

    private function tableCount(string $table): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function failedJobs(int $limit): Collection
    {
        if (! $this->tableExists('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit($limit)
            ->get();
    }

    private function latestInboundMessage(): ?WhatsAppMessage
    {
        if (! $this->tableExists('whatsapp_messages')) {
            return null;
        }

        return WhatsAppMessage::query()
            ->where('direction', 'inbound')
            ->latest('received_at')
            ->latest('id')
            ->first();
    }

    private function latestOutboundDispatch(): ?WhatsAppMessageDispatch
    {
        if (! $this->tableExists('whatsapp_message_dispatches')) {
            return null;
        }

        return WhatsAppMessageDispatch::query()
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function latestDeviceHeartbeat(): ?Device
    {
        if (! $this->tableExists('devices')) {
            return null;
        }

        return Device::query()
            ->whereNotNull('last_seen_at')
            ->latest('last_seen_at')
            ->latest('id')
            ->first();
    }

    private function latestDeviceTelemetry(): ?Device
    {
        if (! $this->tableExists('devices')) {
            return null;
        }

        return Device::query()
            ->whereNotNull('last_telemetry_at')
            ->latest('last_telemetry_at')
            ->latest('id')
            ->first();
    }

    private function latestTelemetryPoint(): ?TelemetryPoint
    {
        if (! $this->tableExists('telemetry_points')) {
            return null;
        }

        return TelemetryPoint::query()
            ->latest('recorded_at')
            ->latest('id')
            ->first();
    }

    private function activeSessionsCount(): int
    {
        if (! $this->tableExists('tracking_sessions')) {
            return 0;
        }

        return TrackingSession::query()
            ->whereIn('status', ['active', 'armed', 'live', 'paused', 'stopped', 'stationary'])
            ->whereNull('ended_at')
            ->count();
    }

    private function sessionsWithNoTelemetryCount(): int
    {
        if (! $this->tableExists('tracking_sessions')) {
            return 0;
        }

        return TrackingSession::query()
            ->whereIn('status', ['active', 'armed', 'live', 'paused', 'stopped', 'stationary'])
            ->whereNull('ended_at')
            ->whereNull('last_direct_telemetry_at')
            ->count();
    }

    private function unclaimedDevicesCount(): int
    {
        if (! $this->tableExists('devices')) {
            return 0;
        }

        return Device::query()->where('status', Device::STATUS_UNCLAIMED)->count();
    }

    private function activeDevicesCount(): int
    {
        if (! $this->tableExists('devices')) {
            return 0;
        }

        return Device::query()
            ->whereIn('status', [Device::STATUS_READY, Device::STATUS_LIVE, Device::STATUS_OFFLINE, Device::STATUS_CLAIMED, 'active'])
            ->count();
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function whenFilter(string $filter, array $allowed, callable $callback): Collection
    {
        return in_array($filter, $allowed, true) ? $callback() : collect();
    }

    private function whenTableFilter(string $table, string $filter, array $allowed, callable $callback): Collection
    {
        if (! $this->tableExists($table)) {
            return collect();
        }

        return $this->whenFilter($filter, $allowed, $callback);
    }

    private function laravelLogExcerpts(string $filter, int $limit): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::isReadable($path)) {
            return [];
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -1000);

        if ($filter !== 'all') {
            $needle = match ($filter) {
                'whatsapp' => 'WhatsApp',
                'garmin' => 'Garmin',
                'sessions' => 'session',
                'failed' => 'fail',
                default => '',
            };

            if ($needle !== '') {
                $lines = array_values(array_filter($lines, fn (string $line) => str_contains(Str::lower($line), Str::lower($needle))));
            }
        }

        return array_slice($lines, -$limit);
    }

    private function redact(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._-]+/i', 'Bearer [redacted]', $value) ?? $value;
        $value = preg_replace('/("?(?:token|secret|access_token)"?\s*[:=]\s*)("[^"]+"|[^\s,}]+)/i', '$1[redacted]', $value) ?? $value;

        return $value;
    }
}
