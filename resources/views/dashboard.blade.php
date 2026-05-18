@php
    use App\Models\Athlete;
    use App\Models\LiveTrackInboundMessage;
    use App\Models\TelemetryPoint;
    use App\Models\TrackingSession;

    $activeSessions = TrackingSession::query()
        ->where(function ($query) {
            $query->where('status', 'active')
                ->orWhereNull('ended_at');
        })
        ->count();

    $athletes = Athlete::query()->count();
    $latestTelemetry = TelemetryPoint::query()->latest('recorded_at')->first();
    $latestLiveTrack = LiveTrackInboundMessage::query()->latest('received_at')->first();
    $latestLiveSession = TrackingSession::query()
        ->whereNotNull('session_token')
        ->latest('last_seen_at')
        ->latest('started_at')
        ->first();

    $telemetryOnline = $latestTelemetry !== null;
    $liveTrackReady = $latestLiveTrack !== null || TrackingSession::query()->whereNotNull('livetrack_url')->exists();
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-2 text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Future Fit Sports</p>
                    <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">StridePulse operations dashboard</h1>
                    <p class="mt-3 text-base text-zinc-600 dark:text-zinc-300">
                        Monitor athlete tracking, Garmin Connect IQ telemetry, and LiveTrack fallback readiness from one authenticated workspace.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <flux:button variant="filled" type="button" disabled>
                        {{ __('Create athlete') }}
                    </flux:button>

                    <flux:button variant="primary" type="button" disabled>
                        {{ __('Start tracking session') }}
                    </flux:button>

                    @if ($latestLiveSession)
                        <flux:button :href="route('live.session', $latestLiveSession->session_token)" wire:navigate>
                            {{ __('View live sessions') }}
                        </flux:button>
                    @else
                        <flux:button type="button" disabled>
                            {{ __('View live sessions') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="StridePulse platform status">
            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active tracking sessions</p>
                        <p class="mt-3 text-4xl font-semibold text-zinc-950 dark:text-white">{{ $activeSessions }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                        {{ $activeSessions > 0 ? 'Live' : 'Idle' }}
                    </span>
                </div>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">Sessions marked active or not yet ended.</p>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Athletes</p>
                        <p class="mt-3 text-4xl font-semibold text-zinc-950 dark:text-white">{{ $athletes }}</p>
                    </div>
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                        Roster
                    </span>
                </div>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">Registered athlete profiles available for tracking.</p>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Garmin telemetry endpoint status</p>
                        <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">
                            {{ $telemetryOnline ? 'Receiving' : 'Ready' }}
                        </p>
                    </div>
                    <span class="rounded-full {{ $telemetryOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }} px-3 py-1 text-xs font-semibold">
                        API
                    </span>
                </div>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $latestTelemetry?->recorded_at ? 'Last sample '.$latestTelemetry->recorded_at->diffForHumans().'.' : 'POST /api/garmin/telemetry is configured and waiting for device samples.' }}
                </p>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">LiveTrack fallback status</p>
                        <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">
                            {{ $liveTrackReady ? 'Available' : 'Standby' }}
                        </p>
                    </div>
                    <span class="rounded-full {{ $liveTrackReady ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }} px-3 py-1 text-xs font-semibold">
                        Fallback
                    </span>
                </div>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $latestLiveTrack?->received_at ? 'Last inbound email '.$latestLiveTrack->received_at->diffForHumans().'.' : 'Inbound LiveTrack email route is ready for fallback links.' }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Tracking overview</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Direct telemetry</p>
                        <p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $telemetryOnline ? 'Connected' : 'Awaiting first sample' }}</p>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Fallback channel</p>
                        <p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $liveTrackReady ? 'Configured' : 'No links received' }}</p>
                    </div>
                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Public live page</p>
                        <p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $latestLiveSession ? 'Session available' : 'No session yet' }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Operational notes</h2>
                <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    Quick creation workflows are reserved for the next admin screen. Existing authenticated access, settings, and API ingestion endpoints remain unchanged.
                </p>
            </article>
        </section>
    </div>
</x-layouts::app>
