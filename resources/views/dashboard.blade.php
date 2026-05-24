@php
    $badgeClasses = [
        'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    ];
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-2 text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Future Fit Sports</p>
                    <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">StridePulse operations health</h1>
                    <p class="mt-3 text-base text-zinc-600 dark:text-zinc-300">
                        Monitor WhatsApp dispatches, queue health, Garmin discovery, and live session readiness from one authenticated workspace.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <flux:button variant="primary" :href="route('tracking-sessions.create')" wire:navigate>
                        {{ __('Start tracking session') }}
                    </flux:button>
                    <flux:button :href="route('operations.logs')" wire:navigate>
                        {{ __('Operations logs') }}
                    </flux:button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="StridePulse service indicators">
            @foreach ($health['cards'] as $card)
                <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                            <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $card['value'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses[$card['level']] ?? $badgeClasses['amber'] }}">
                            {{ strtoupper($card['level']) }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $card['detail'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">WhatsApp status</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Token</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['whatsapp']['token_configured'] ? 'Configured' : 'Missing' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Phone number id</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['whatsapp']['phone_number_id_configured'] ? 'Configured' : 'Missing' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Business account id</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['whatsapp']['business_account_id_configured'] ? 'Configured' : 'Missing' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Language</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['whatsapp']['template_language'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Supporter invite</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['whatsapp']['supporter_invite_template'] ?: 'Not configured' }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Garmin status</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Latest discovery</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['garmin']['latest_device_heartbeat']?->last_seen_at?->diffForHumans() ?? 'None' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Latest telemetry</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['garmin']['latest_telemetry_point']?->recorded_at?->diffForHumans() ?? 'None' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Active devices</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['garmin']['active_devices_count'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Unclaimed devices</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['garmin']['unclaimed_devices_count'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">No telemetry sessions</dt><dd class="font-medium text-zinc-950 dark:text-white">{{ $health['garmin']['sessions_with_no_telemetry_count'] }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Queue worker guidance</h2>
                <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    Production queue workers should be managed by Supervisor/systemd or hosting process manager.
                </p>
                <pre class="mt-4 overflow-x-auto rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">php artisan queue:work --tries=1</pre>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    The web dashboard monitors queue health. It does not start queue workers from a web request.
                </p>
            </article>
        </section>
    </div>
</x-layouts::app>
