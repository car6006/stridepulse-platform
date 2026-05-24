@php
    $filters = ['all' => 'All', 'whatsapp' => 'WhatsApp', 'garmin' => 'Garmin', 'sessions' => 'Sessions', 'failed' => 'Failed'];
    $limits = [50, 100, 250];
@endphp

<x-layouts::app :title="__('Operations Logs')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="mb-2 text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Operations</p>
                    <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Operations logs</h1>
                    <p class="mt-3 text-zinc-600 dark:text-zinc-400">Recent operational records for WhatsApp, Garmin, sessions, and failed jobs.</p>
                </div>
                <form method="GET" action="{{ route('operations.logs') }}" class="flex flex-wrap gap-3">
                    <select name="filter" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="limit" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @foreach ($limits as $value)
                            <option value="{{ $value }}" @selected($limit === $value)>Last {{ $value }}</option>
                        @endforeach
                    </select>
                    <flux:button type="submit" variant="primary">{{ __('Apply') }}</flux:button>
                </form>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Copy diagnostics</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Plain text summary. Secrets are not included.</p>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('diagnostics-text').innerText)" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm dark:bg-white dark:text-zinc-900">
                    Copy diagnostics
                </button>
            </div>
            <pre id="diagnostics-text" class="mt-4 max-h-96 overflow-auto rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ $diagnostics }}</pre>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Laravel log excerpts</h2>
                <div class="mt-4 max-h-96 space-y-2 overflow-auto text-xs">
                    @forelse ($logs['laravel'] as $line)
                        <pre class="whitespace-pre-wrap rounded-md bg-zinc-50 p-2 text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">{{ $line }}</pre>
                    @empty
                        <p class="text-sm text-zinc-500">No readable Laravel log excerpts.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">WhatsApp dispatches</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-2">Time</th><th>Status</th><th>Phone</th><th>Error</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($logs['dispatches'] as $dispatch)
                                <tr><td class="py-2">{{ $dispatch->updated_at?->diffForHumans() }}</td><td>{{ $dispatch->status }}</td><td>{{ $dispatch->phone_number }}</td><td>{{ \Illuminate\Support\Str::limit($dispatch->last_error, 80) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-zinc-500">No dispatch records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Inbound WhatsApp messages</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-2">Time</th><th>Phone</th><th>Message</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($logs['inbound_messages'] as $message)
                                <tr><td class="py-2">{{ $message->received_at?->diffForHumans() }}</td><td>{{ $message->phone_number }}</td><td>{{ \Illuminate\Support\Str::limit($message->body, 90) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-zinc-500">No inbound messages.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Garmin device heartbeats</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-2">Seen</th><th>Device</th><th>Status</th><th>Telemetry</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($logs['devices'] as $device)
                                <tr><td class="py-2">{{ $device->last_seen_at?->diffForHumans() }}</td><td>{{ $device->name }}</td><td>{{ $device->status }}</td><td>{{ $device->last_telemetry_at?->diffForHumans() ?? 'None' }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-zinc-500">No device heartbeats.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Tracking sessions</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-2">Updated</th><th>Athlete</th><th>Device</th><th>Status</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($logs['sessions'] as $session)
                                <tr><td class="py-2">{{ $session->updated_at?->diffForHumans() }}</td><td>{{ $session->athlete?->name }}</td><td>{{ $session->device?->name ?? 'Unassigned' }}</td><td>{{ $session->status }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-zinc-500">No sessions.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Failed jobs</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-2">Failed</th><th>Queue</th><th>Exception</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($logs['failed_jobs'] as $job)
                                <tr><td class="py-2">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</td><td>{{ $job->queue }}</td><td>{{ \Illuminate\Support\Str::limit($job->exception, 120) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-zinc-500">No failed jobs.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-layouts::app>
