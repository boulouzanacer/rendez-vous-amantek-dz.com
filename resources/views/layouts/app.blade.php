@php
    use Illuminate\Support\Str;

    /** @var array<int, array{label: string|null, items: array<int, array{label: string, route: string, icon: string}>}> $navigation */
    $navigation = [
        [
            'label' => null,
            'items' => [
                ['label' => 'Tableau de bord', 'route' => 'dashboard', 'icon' => 'dashboard'],
                ['label' => 'Agenda', 'route' => 'agenda', 'icon' => 'calendar'],
            ],
        ],
        [
            'label' => 'Gestion',
            'items' => [
                ['label' => 'Rendez-vous', 'route' => 'appointments.index', 'icon' => 'calendar-check'],
                ['label' => 'Clients', 'route' => 'clients.index', 'icon' => 'users'],
                ['label' => 'Devis', 'route' => 'quotes.index', 'icon' => 'file-text'],
                ['label' => 'Interventions', 'route' => 'installations.index', 'icon' => 'wrench'],
                ['label' => 'Paiements', 'route' => 'payments.index', 'icon' => 'wallet'],
            ],
        ],
        [
            'label' => 'Analyse',
            'items' => [
                ['label' => 'Rapports', 'route' => 'reports.index', 'icon' => 'chart'],
                ['label' => 'Paramètres', 'route' => 'settings', 'icon' => 'settings'],
            ],
        ],
    ];

    $isActive = function (string $route): bool {
        if (request()->routeIs($route)) {
            return true;
        }

        return str_contains($route, '.')
            && request()->routeIs(Str::before($route, '.').'.*');
    };

    $overdueAppointments = collect();
    $expiringQuotes = collect();

    if (auth()->check()) {
        $overdueAppointments = \App\Models\Appointment::query()
            ->open()
            ->where('scheduled_at', '<', now())
            ->with('client:id,name')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $expiringQuotes = \App\Models\Quote::query()
            ->where('status', 'envoye')
            ->whereDate('valid_until', '<=', now()->addDays(7))
            ->with('client:id,name')
            ->orderBy('valid_until')
            ->limit(5)
            ->get();
    }

    $alertsCount = $overdueAppointments->count() + $expiringQuotes->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1f47e0">

        <title>{{ isset($title) && $title ? $title.' — '.config('company.name') : config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.getItem('theme') === 'dark' || (! localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="h-full font-sans antialiased">
        <div x-data="{ sidebarOpen: false, dark: document.documentElement.classList.contains('dark') }" class="min-h-full">

            {{-- Voile mobile --}}
            <div
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-sm lg:hidden"
            ></div>

            {{-- Barre latérale --}}
            <aside
                x-cloak
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white">
                        <x-icon name="shield" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ config('company.name') }}</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">Rendez-vous &amp; Interventions</span>
                    </span>
                    <button type="button" @click="sidebarOpen = false" class="btn-icon ms-auto lg:hidden">
                        <x-icon name="x" />
                        <span class="sr-only">Fermer le menu</span>
                    </button>
                </div>

                <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                    @foreach ($navigation as $group)
                        @php
                            $items = array_values(array_filter($group['items'], fn (array $item): bool => Route::has($item['route'])));
                        @endphp

                        @if ($items !== [])
                            <div class="space-y-1">
                                @if ($group['label'])
                                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                        {{ $group['label'] }}
                                    </p>
                                @endif

                                @foreach ($items as $item)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @class(['nav-link', 'nav-link-active' => $isActive($item['route'])])
                                    >
                                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center gap-1">
                        <a
                            href="{{ route('profile') }}"
                            wire:navigate
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                                {{ auth()->user()->initials() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->role->label() }}</span>
                            </span>
                        </a>

                        @livewire('layout.logout')
                    </div>
                </div>
            </aside>

            {{-- Colonne de contenu --}}
            <div class="flex min-h-screen flex-col lg:pl-72">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">
                    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button type="button" @click="sidebarOpen = true" class="btn-icon lg:hidden">
                            <x-icon name="menu" />
                            <span class="sr-only">Ouvrir le menu</span>
                        </button>

                        <div class="min-w-0 flex-1">
                            <h1 class="truncate text-base font-semibold text-slate-900 sm:text-lg dark:text-white">
                                {{ $title ?? 'Tableau de bord' }}
                            </h1>
                            @if (! empty($subtitle))
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('appointments.create') }}" wire:navigate class="btn-primary hidden sm:inline-flex">
                                <x-icon name="plus" class="h-4 w-4" />
                                Nouveau rendez-vous
                            </a>

                            <button
                                type="button"
                                @click="dark = ! dark; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                                class="btn-icon"
                                title="Basculer le thème"
                            >
                                <x-icon name="sun" class="h-5 w-5" x-show="! dark" />
                                <x-icon name="moon" class="h-5 w-5" x-show="dark" x-cloak />
                                <span class="sr-only">Basculer le thème</span>
                            </button>

                            {{-- Alertes réelles : rendez-vous en retard et devis arrivant à échéance --}}
                            <div x-data="{ open: false }" class="relative">
                                <button type="button" @click="open = ! open" class="btn-icon relative" title="Alertes">
                                    <x-icon name="bell" class="h-5 w-5" />
                                    @if ($alertsCount > 0)
                                        <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                                            {{ $alertsCount > 9 ? '9+' : $alertsCount }}
                                        </span>
                                    @endif
                                    <span class="sr-only">Alertes</span>
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    @click.outside="open = false"
                                    x-transition.origin.top.right
                                    class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-pop dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">Alertes</span>
                                        <span class="badge-neutral">{{ $alertsCount }}</span>
                                    </div>

                                    <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                                        @foreach ($overdueAppointments as $appointment)
                                            <a href="{{ route('appointments.show', $appointment) }}" wire:navigate class="block px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                <p class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                                    <x-icon name="alert" class="h-4 w-4 shrink-0 text-rose-500" />
                                                    {{ $appointment->client?->name ?? 'Client supprimé' }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $appointment->type->label() }} · prévu le {{ $appointment->dateLabel() }} à {{ $appointment->timeLabel() }}
                                                </p>
                                            </a>
                                        @endforeach

                                        @foreach ($expiringQuotes as $quote)
                                            <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="block px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                <p class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                                    <x-icon name="hourglass" class="h-4 w-4 shrink-0 text-amber-500" />
                                                    Devis {{ $quote->number }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $quote->client?->name ?? 'Client supprimé' }} · expire le {{ $quote->valid_until->translatedFormat('d M Y') }}
                                                </p>
                                            </a>
                                        @endforeach

                                        @if ($alertsCount === 0)
                                            <p class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                                                Aucune alerte : votre planning est à jour.
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 pb-24 pt-6 sm:px-6 lg:px-8 lg:pb-8">
                    {{ $slot }}
                </main>
            </div>

            {{-- Navigation rapide mobile --}}
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden dark:border-slate-800 dark:bg-slate-900/95">
                <div class="grid grid-cols-5">
                    @php
                        $mobileNavigation = [
                            ['label' => 'Accueil', 'route' => 'dashboard', 'icon' => 'dashboard'],
                            ['label' => 'Agenda', 'route' => 'agenda', 'icon' => 'calendar'],
                            ['label' => 'Rendez-vous', 'route' => 'appointments.index', 'icon' => 'calendar-check'],
                            ['label' => 'Clients', 'route' => 'clients.index', 'icon' => 'users'],
                            ['label' => 'Devis', 'route' => 'quotes.index', 'icon' => 'file-text'],
                        ];
                    @endphp

                    @foreach (array_filter($mobileNavigation, fn (array $item): bool => Route::has($item['route'])) as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @class([
                                'flex flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium transition',
                                'text-brand-600 dark:text-brand-400' => $isActive($item['route']),
                                'text-slate-500 dark:text-slate-400' => ! $isActive($item['route']),
                            ])
                        >
                            <x-icon :name="$item['icon']" class="h-5 w-5" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        </div>

        @livewireScripts
    </body>
</html>
