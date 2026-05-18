<x-layouts::app :title="__('Create athlete')">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <p class="text-sm font-semibold uppercase text-cyan-700 dark:text-cyan-300">Future Fit Sports</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">Create athlete</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Add an athlete profile before starting a live tracking session.</p>
        </div>

        <form method="POST" action="{{ route('athletes.store') }}" class="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @include('athletes._form')
        </form>
    </div>
</x-layouts::app>
