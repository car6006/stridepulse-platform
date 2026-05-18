<x-layouts::app :title="__('Garmin setup')">
    <div class="flex w-full flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">StridePulse Connect IQ</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Garmin setup</h1>
            <p class="mt-3 max-w-3xl text-zinc-600 dark:text-zinc-400">
                Configure the StridePulse Garmin data field with the telemetry endpoint and a setup token. No Garmin password or OAuth connection is required for this MVP.
            </p>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Telemetry endpoint</h2>
                <p class="mt-3 break-all rounded-md border border-zinc-200 bg-zinc-50 p-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $telemetryEndpoint }}
                </p>
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">The Connect IQ data field posts activity snapshots here during active tracking sessions.</p>
            </article>

            <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Setup token</h2>
                @if ($setupToken)
                    <p class="mt-3 break-all rounded-md border border-zinc-200 bg-zinc-50 p-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                        {{ $setupToken }}
                    </p>
                @else
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">Generate a setup token to paste into the Garmin data field configuration.</p>
                @endif

                <form method="POST" action="{{ route('garmin-setup.generate') }}" class="mt-5">
                    @csrf
                    <flux:button variant="primary" type="submit">
                        {{ __('Generate setup token') }}
                    </flux:button>
                </form>
            </article>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Install and setup instructions</h2>
            <ol class="mt-4 list-decimal space-y-3 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                <li>Install the StridePulse Garmin Connect IQ data field on the athlete watch.</li>
                <li>Open the data field settings and paste the telemetry endpoint shown above.</li>
                <li>Paste the setup token, then start a tracking session from StridePulse.</li>
                <li>Use the session token shown after session creation as the Garmin session token for live telemetry.</li>
            </ol>
        </section>
    </div>
</x-layouts::app>
