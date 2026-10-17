@php
    $highlights = [
        ['icon' => 'calendar-check', 'title' => 'Rendez-vous maîtrisés', 'text' => 'Toutes vos visites techniques planifiées, avec relances et rappels.'],
        ['icon' => 'file-text', 'title' => 'Devis en quelques clics', 'text' => 'Chiffrez caméras, câblage et main d’œuvre, puis imprimez en un instant.'],
        ['icon' => 'wrench', 'title' => 'Chantiers suivis', 'text' => 'Interventions, paiements et garanties réunis au même endroit.'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1f47e0">

        <title>{{ config('company.name') }} — Gestion des rendez-vous et interventions</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="h-full font-sans antialiased">
        <div class="flex min-h-full flex-col lg:flex-row">

            {{-- Panneau de présentation --}}
            <aside class="relative isolate overflow-hidden bg-brand-700 px-6 py-10 text-white lg:flex lg:w-[46%] lg:flex-col lg:justify-between lg:px-12 lg:py-14">
                <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-brand-500 via-brand-700 to-brand-950"></div>
                <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-72 w-72 rounded-full bg-brand-400/30 blur-3xl animate-float-slow"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-20 -z-10 h-80 w-80 rounded-full bg-sky-400/20 blur-3xl animate-float-slow"></div>

                <div class="animate-fade-in-up">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                            <x-icon name="shield" class="h-6 w-6" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-base font-bold tracking-tight">{{ config('company.name') }}</p>
                            <p class="text-xs text-white/70">Vidéosurveillance &amp; réseaux</p>
                        </div>
                    </div>

                    <h1 class="mt-8 text-2xl font-bold leading-snug tracking-tight sm:text-3xl">
                        Ne ratez plus aucun rendez-vous client.
                    </h1>
                    <p class="mt-3 max-w-md text-sm text-white/75">
                        Visites techniques, devis et installations terrain — depuis le téléphone comme depuis le bureau.
                    </p>
                </div>

                <ul class="mt-10 hidden space-y-5 lg:block">
                    @foreach ($highlights as $index => $highlight)
                        <li
                            class="flex items-start gap-3 animate-fade-in-up"
                            style="animation-delay: {{ 120 + ($index * 110) }}ms"
                        >
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/20">
                                <x-icon :name="$highlight['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold">{{ $highlight['title'] }}</p>
                                <p class="mt-0.5 text-xs text-white/70">{{ $highlight['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-10 hidden text-xs text-white/60 lg:block">
                    © {{ now()->year }} {{ config('company.name') }} — {{ config('company.address') }}
                </p>
            </aside>

            {{-- Formulaire --}}
            <main class="flex flex-1 items-center justify-center bg-slate-50 px-5 py-10 sm:px-8">
                <div class="w-full max-w-sm">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
                            <x-icon name="shield" class="h-5 w-5" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-sm font-bold tracking-tight text-slate-900">{{ config('company.name') }}</p>
                            <p class="text-xs text-slate-500">Vidéosurveillance &amp; réseaux</p>
                        </div>
                    </div>

                    <div class="animate-fade-in-up">
                        {{ $slot }}
                    </div>

                    <p class="mt-8 text-center text-xs text-slate-400">
                        {{ config('company.phone') }} — {{ config('company.email') }}
                    </p>
                </div>
            </main>
        </div>
    </body>
</html>
