<x-layouts::app :title="__('Athletes')">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:flex-row md:items-end md:justify-between dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Future Fit Sports</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Athletes</h1>
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">Manage the roster available for Garmin tracking sessions.</p>
            </div>

            <flux:button variant="primary" :href="route('athletes.create')" wire:navigate>
                {{ __('Create athlete') }}
            </flux:button>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($athletes as $athlete)
                <div class="flex flex-col gap-4 border-b border-zinc-200 p-5 last:border-b-0 md:flex-row md:items-center md:justify-between dark:border-zinc-700">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $athlete->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $athlete->tracking_sessions_count }} tracking {{ Str::plural('session', $athlete->tracking_sessions_count) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button :href="route('athletes.edit', $athlete)" wire:navigate>
                            {{ __('Edit') }}
                        </flux:button>

                        <form method="POST" action="{{ route('athletes.destroy', $athlete) }}">
                            @csrf
                            @method('DELETE')
                            <flux:button variant="danger" type="submit">
                                {{ __('Delete') }}
                            </flux:button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-6 text-zinc-600 dark:text-zinc-400">
                    No athletes yet. Create the first athlete to start a tracking session.
                </div>
            @endforelse
        </div>

        {{ $athletes->links() }}
    </div>
</x-layouts::app>
