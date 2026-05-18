<x-layouts::app :title="__('Live sessions')">
    <div class="flex w-full flex-col gap-6">
        <section class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:flex-row md:items-end md:justify-between dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">StridePulse live</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Live sessions</h1>
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">Active tracking sessions with shareable public URLs.</p>
            </div>

            <flux:button variant="primary" :href="route('tracking-sessions.create')" wire:navigate>
                {{ __('Start tracking session') }}
            </flux:button>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($trackingSessions as $trackingSession)
                @php($liveUrl = route('live.session', $trackingSession->session_token))
                <article class="border-b border-zinc-200 p-5 last:border-b-0 dark:border-zinc-700">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">
                                {{ $trackingSession->athlete?->name ?? 'Athlete' }}
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $trackingSession->sport?->name ? Str::headline($trackingSession->sport->name) : Str::headline((string) $trackingSession->activity_type) }}
                                · Last seen {{ $trackingSession->last_seen_at?->diffForHumans() ?? 'not yet' }}
                            </p>
                            <p class="mt-3 break-all rounded-md border border-zinc-200 bg-zinc-50 p-3 font-mono text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                                {{ $liveUrl }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <flux:button :href="$liveUrl" target="_blank">
                                {{ __('Open live URL') }}
                            </flux:button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-6 text-zinc-600 dark:text-zinc-400">
                    No active sessions yet. Start a tracking session to generate a shareable live URL.
                </div>
            @endforelse
        </div>

        {{ $trackingSessions->links() }}
    </div>
</x-layouts::app>
