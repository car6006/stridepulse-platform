<x-layouts::app :title="__('Register Garmin Device')">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">StridePulse devices</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Register Garmin Device</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Assign a Garmin watch to an athlete and generate the device credentials used by Connect IQ telemetry.</p>
        </div>

        <form method="POST" action="{{ route('devices.store') }}" class="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @csrf

            <div class="space-y-2">
                <label for="athlete_id" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Athlete</label>
                <select id="athlete_id" name="athlete_id" required class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                    <option value="">Select athlete</option>
                    @foreach ($athletes as $athlete)
                        <option value="{{ $athlete->id }}" @selected(old('athlete_id') == $athlete->id)>{{ $athlete->name }}</option>
                    @endforeach
                </select>
                @error('athlete_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="name" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Device name</label>
                <input id="name" name="name" value="{{ old('name', 'Garmin Forerunner') }}" required class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                @error('name')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label for="type" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Type</label>
                    <input id="type" name="type" value="{{ old('type', 'watch') }}" required class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                </div>

                <div class="space-y-2">
                    <label for="provider" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Provider</label>
                    <input id="provider" name="provider" value="{{ old('provider', 'garmin') }}" required class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                </div>
            </div>

            @if ($athletes->isEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Create an athlete before registering a Garmin device.
                </div>
            @endif

            <flux:button variant="primary" type="submit" :disabled="$athletes->isEmpty()">
                {{ __('Register device') }}
            </flux:button>
        </form>
    </div>
</x-layouts::app>
