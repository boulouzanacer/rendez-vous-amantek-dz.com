@props([
    'icon' => 'info',
    'title',
    'description' => null,
])

<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>

    <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
