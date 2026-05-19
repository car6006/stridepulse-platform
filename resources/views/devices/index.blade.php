<x-layouts::app :title="__('Athlete Devices')">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:flex-row md:items-end md:justify-between dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">StridePulse devices</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Athlete Devices</h1>
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">Register Garmin watches and bind tracking sessions to athlete-owned devices.</p>
            </div>

            <flux:button variant="primary" :href="route('devices.create')" wire:navigate>
                {{ __('Register Garmin device') }}
            </flux:button>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($devices as $device)
                <div class="flex flex-col gap-4 border-b border-zinc-200 p-5 last:border-b-0 md:flex-row md:items-center md:justify-between dark:border-zinc-700">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $device->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $device->athlete?->name ?? 'Unassigned athlete' }} · {{ Str::headline($device->provider) }} {{ Str::headline($device->type) }} · {{ Str::headline($device->status) }}
                        </p>
                        <p class="mt-1 text-xs font-mono text-zinc-500 dark:text-zinc-500">{{ $device->uuid }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ $device->tracking_sessions_count }} sessions</span>
                        <span>Last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}</span>
                        <flux:button :href="route('devices.show', $device)" wire:navigate>
                            {{ __('Pairing details') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-zinc-600 dark:text-zinc-400">
                    No devices yet. Register a Garmin device before binding telemetry to a watch.
                </div>
            @endforelse
        </div>

        {{ $devices->links() }}
    </div>
</x-layouts::app>
