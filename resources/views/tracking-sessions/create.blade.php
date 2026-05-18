<x-layouts::app :title="__('Start tracking session')">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">StridePulse live</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Start tracking session</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Create an active session and share the public live URL with supporters.</p>
        </div>

        <form method="POST" action="{{ route('tracking-sessions.store') }}" class="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
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
                <label for="sport_id" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Sport</label>
                <select id="sport_id" name="sport_id" required class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                    <option value="">Select sport</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport->id }}" @selected(old('sport_id') == $sport->id)>{{ Str::headline($sport->name) }}</option>
                    @endforeach
                </select>
                @error('sport_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if ($athletes->isEmpty() || $sports->isEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Create at least one athlete and one sport before starting a tracking session.
                </div>
            @endif

            <flux:button variant="primary" type="submit" :disabled="$athletes->isEmpty() || $sports->isEmpty()">
                {{ __('Start tracking session') }}
            </flux:button>
        </form>
    </div>
</x-layouts::app>
