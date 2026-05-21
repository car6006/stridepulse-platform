<x-layouts::app :title="__('Unclaimed Devices')">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:flex-row md:items-end md:justify-between dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Device discovery</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Unclaimed Devices</h1>
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">Garmin watches appear here automatically after telemetry is received from an unknown device identity.</p>
            </div>

            <flux:button :href="route('devices.index')" wire:navigate>
                {{ __('All devices') }}
            </flux:button>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($devices as $device)
                <div class="flex flex-col gap-4 border-b border-zinc-200 p-5 last:border-b-0 lg:flex-row lg:items-center lg:justify-between dark:border-zinc-700">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $device->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Pairing code <span class="font-mono font-semibold text-zinc-950 dark:text-white">{{ $device->pairing_code ?? 'Not generated' }}</span> · Last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }} · {{ Str::headline($device->status) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <form method="POST" action="{{ route('devices.claim', $device) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            @csrf
                            <select name="athlete_id" required class="min-w-56 rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                                <option value="">Select athlete</option>
                                @foreach ($athletes as $athlete)
                                    <option value="{{ $athlete->id }}">{{ $athlete->name }}</option>
                                @endforeach
                            </select>
                            <flux:button variant="primary" type="submit" :disabled="$athletes->isEmpty()">
                                {{ __('Claim') }}
                            </flux:button>
                        </form>
                        <form method="POST" action="{{ route('devices.archive', $device) }}">
                            @csrf
                            <flux:button type="submit" variant="danger">
                                {{ __('Archive') }}
                            </flux:button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-6 text-zinc-600 dark:text-zinc-400">
                    No unclaimed devices have reported telemetry yet.
                </div>
            @endforelse
        </div>

        {{ $devices->links() }}
    </div>
</x-layouts::app>
