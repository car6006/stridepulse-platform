<x-layouts::app :title="__('Edit athlete')">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Athlete profile</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">{{ $athlete->name }}</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Update athlete details used by tracking sessions and live pages.</p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('athletes.update', $athlete) }}" class="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @method('PUT')
            @include('athletes._form')
        </form>
    </div>
</x-layouts::app>
