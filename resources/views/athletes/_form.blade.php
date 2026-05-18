@csrf

<div class="space-y-2">
    <label for="name" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Athlete name</label>
    <input
        id="name"
        name="name"
        type="text"
        value="{{ old('name', $athlete->name) }}"
        required
        autofocus
        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
    >
    @error('name')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div class="flex flex-wrap gap-3">
    <flux:button variant="primary" type="submit">
        {{ $athlete->exists ? __('Update athlete') : __('Create athlete') }}
    </flux:button>

    <flux:button :href="route('athletes.index')" wire:navigate>
        {{ __('Cancel') }}
    </flux:button>
</div>
