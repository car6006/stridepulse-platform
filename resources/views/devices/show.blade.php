<x-layouts::app :title="$device->name">
    <div class="flex w-full flex-col gap-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Device pairing</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">{{ $device->name }}</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">
                Assigned to {{ $device->athlete?->name ?? 'unassigned athlete' }}. Paste these credentials into the StridePulse Garmin data field settings when device authentication is enabled.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Device UUID</h2>
                <p class="mt-3 break-all rounded-md border border-zinc-200 bg-zinc-50 p-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $device->device_uuid ?? $device->uuid }}
                </p>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Device secret</h2>
                <p class="mt-3 break-all rounded-md border border-zinc-200 bg-zinc-50 p-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $device->device_secret }}
                </p>
            </article>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Pairing code</h2>
            <p class="mt-3 inline-flex rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                {{ $device->metadata['pairing_code'] ?? 'Not generated' }}
            </p>
            <dl class="mt-5 grid gap-4 text-sm md:grid-cols-3">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Status</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ Str::headline($device->status) }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Last seen</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Tracking sessions</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ $device->trackingSessions->count() }}</dd>
                </div>
            </dl>
        </section>
    </div>
</x-layouts::app>
