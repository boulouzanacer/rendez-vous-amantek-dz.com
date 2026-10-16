@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart',
    'tone' => 'brand',
    'href' => null,
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300',
        'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300',
        'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300',
        'danger' => 'bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-300',
        'info' => 'bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-300',
        'neutral' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-4 sm:p-5'.($href ? ' transition hover:border-brand-300 hover:shadow-pop dark:hover:border-brand-800' : '')]) }}
>
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>

    <span class="min-w-0">
        <span class="block truncate text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ $label }}
        </span>
        <span class="mt-1 block truncate text-xl font-semibold text-slate-900 sm:text-2xl dark:text-white">
            {{ $value }}
        </span>
        @if ($hint)
            <span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
