@props(['type'])

@php
    $palette = [
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-900',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-900',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-900',
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($palette[$type->color()] ?? $palette['neutral'])]) }}>
    <x-icon :name="$type->icon()" class="h-3.5 w-3.5" />
    {{ $type->label() }}
</span>
