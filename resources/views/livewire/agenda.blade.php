<?php

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Agenda', 'subtitle' => 'Planifiez et suivez toutes vos interventions'])] class extends Component
{
    #[Url(as: 'vue')]
    public string $view = 'month';

    #[Url(as: 'date')]
    public string $anchor = '';

    #[Url(as: 'technicien')]
    public ?int $technicianId = null;

    public function mount(): void
    {
        if (! in_array($this->view, ['month', 'week', 'day'], true)) {
            $this->view = 'month';
        }

        try {
            $date = $this->anchor !== '' ? Carbon::createFromFormat('Y-m-d', $this->anchor) : null;
        } catch (\Throwable) {
            $date = null;
        }

        if (! $date || $date->year < 2000 || $date->year > 2100) {
            $date = now();
        }

        $this->anchor = $date->toDateString();
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['month', 'week', 'day'], true)) {
            $this->view = $view;
        }
    }

    public function previous(): void
    {
        $this->shift(-1);
    }

    public function next(): void
    {
        $this->shift(1);
    }

    public function today(): void
    {
        $this->anchor = now()->toDateString();
    }

    public function showDay(string $date): void
    {
        $this->anchor = Carbon::parse($date)->toDateString();
        $this->view = 'day';
    }

    private function shift(int $direction): void
    {
        $date = Carbon::parse($this->anchor);

        $this->anchor = match ($this->view) {
            'week' => $date->addWeeks($direction)->toDateString(),
            'day' => $date->addDays($direction)->toDateString(),
            default => $date->addMonthsNoOverflow($direction)->startOfMonth()->toDateString(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $anchor = Carbon::parse($this->anchor);

        [$start, $end] = match ($this->view) {
            'week' => [
                $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            default => [
                $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
        };

        $appointments = Appointment::query()
            ->between($start, $end)
            ->with(['client', 'technician', 'technicians'])
            ->forTechnician($this->technicianId)
            ->orderBy('scheduled_at')
            ->get();

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return [
            'periodStart' => $start,
            'periodEnd' => $end,
            'anchorDate' => $anchor,
            'appointments' => $appointments,
            'appointmentsByDay' => $appointments->groupBy(fn (Appointment $appointment): string => $appointment->scheduled_at?->toDateString() ?? 'non-planifie'),
            'days' => $days,
            'technicians' => User::query()->technicians()->active()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-4">
    {{-- Barre d'outils --}}
    <div class="card flex flex-wrap items-center gap-3 p-3 sm:p-4">
        <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
            @foreach (['month' => 'Mois', 'week' => 'Semaine', 'day' => 'Jour'] as $mode => $label)
                <button
                    type="button"
                    wire:click="setView('{{ $mode }}')"
                    @class([
                        'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                        'bg-white text-brand-700 shadow-sm dark:bg-slate-900 dark:text-brand-300' => $view === $mode,
                        'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100' => $view !== $mode,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-1">
            <button type="button" wire:click="previous" class="btn-icon" title="Période précédente">
                <x-icon name="chevron-left" class="h-5 w-5" />
                <span class="sr-only">Période précédente</span>
            </button>
            <button type="button" wire:click="today" class="btn-secondary btn-sm">Aujourd'hui</button>
            <button type="button" wire:click="next" class="btn-icon" title="Période suivante">
                <x-icon name="chevron-right" class="h-5 w-5" />
                <span class="sr-only">Période suivante</span>
            </button>
        </div>

        <p class="text-sm font-semibold capitalize text-slate-900 dark:text-white">
            @if ($view === 'month')
                {{ $anchorDate->translatedFormat('F Y') }}
            @elseif ($view === 'week')
                {{ $periodStart->translatedFormat('d M') }} – {{ $periodEnd->translatedFormat('d M Y') }}
            @else
                {{ $anchorDate->translatedFormat('l d F Y') }}
            @endif
        </p>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <select wire:model.live="technicianId" class="input input-sm w-auto">
                <option value="">Tous les techniciens</option>
                @foreach ($technicians as $technician)
                    <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                @endforeach
            </select>

            <span class="badge-neutral">{{ $appointments->count() }} rendez-vous</span>

            <a href="{{ route('appointments.create', ['date' => $anchorDate->toDateString()]) }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Planifier
            </a>
        </div>
    </div>

    {{-- Vue mensuelle --}}
    @if ($view === 'month')
        <div class="card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                @foreach (['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'] as $label)
                    <div class="py-2 sm:py-2.5">{{ $label }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php
                        $dayAppointments = $appointmentsByDay->get($day->toDateString(), collect());
                        $inMonth = $day->month === $anchorDate->month && $day->year === $anchorDate->year;
                    @endphp

                    <div @class([
                        'min-h-[4.5rem] border-b border-r border-slate-100 p-1 sm:min-h-[6.5rem] sm:p-1.5 dark:border-slate-800',
                        'bg-slate-50/60 dark:bg-slate-900/40' => ! $inMonth,
                        'bg-brand-50/40 dark:bg-brand-950/20' => $day->isToday(),
                    ])>
                        <div class="mb-1 flex items-center justify-between">
                            <button
                                type="button"
                                wire:click="showDay('{{ $day->toDateString() }}')"
                                @class([
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold transition',
                                    'bg-brand-600 text-white' => $day->isToday(),
                                    'text-slate-700 hover:bg-slate-200 dark:text-slate-200 dark:hover:bg-slate-700' => ! $day->isToday() && $inMonth,
                                    'text-slate-400 dark:text-slate-600' => ! $day->isToday() && ! $inMonth,
                                ])
                            >
                                {{ $day->format('j') }}
                            </button>

                            <a
                                href="{{ route('appointments.create', ['date' => $day->toDateString()]) }}"
                                wire:navigate
                                class="hidden h-5 w-5 items-center justify-center rounded text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 sm:flex dark:hover:bg-slate-700 dark:hover:text-slate-200"
                                title="Planifier un rendez-vous ce jour"
                            >
                                <x-icon name="plus" class="h-3 w-3" />
                            </a>
                        </div>

                        <div class="space-y-1">
                            @foreach ($dayAppointments->take(3) as $appointment)
                                <a
                                    href="{{ route('appointments.show', $appointment) }}"
                                    wire:navigate
                                    @class([
                                        'block truncate rounded px-1.5 py-1 text-[11px] font-medium transition',
                                        'bg-brand-100 text-brand-800 hover:bg-brand-200 dark:bg-brand-900 dark:text-brand-100' => $appointment->status->isOpen(),
                                        'bg-slate-100 text-slate-500 line-through hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400' => $appointment->status->isClosed(),
                                    ])
                                    title="{{ $appointment->timeLabel() }} · {{ $appointment->client?->name }} · {{ $appointment->type->label() }}"
                                >
                                    <span class="font-semibold">{{ $appointment->timeLabel() }}</span>
                                    {{ $appointment->client?->name ?? 'Client supprimé' }}
                                </a>
                            @endforeach

                            @if ($dayAppointments->count() > 3)
                                <button
                                    type="button"
                                    wire:click="showDay('{{ $day->toDateString() }}')"
                                    class="w-full rounded px-1.5 py-0.5 text-left text-[11px] font-semibold text-brand-600 hover:underline dark:text-brand-400"
                                >
                                    +{{ $dayAppointments->count() - 3 }} autre(s)
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Vues semaine et jour --}}
    @if ($view === 'week')
        <div class="grid gap-3 lg:grid-cols-7">
            @foreach ($days as $day)
                @php $dayAppointments = $appointmentsByDay->get($day->toDateString(), collect()); @endphp

                <div @class([
                    'card flex min-h-[10rem] flex-col',
                    'ring-2 ring-brand-500' => $day->isToday(),
                ])>
                    <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                {{ $day->translatedFormat('D') }}
                            </p>
                            <p @class([
                                'text-sm font-bold',
                                'text-brand-600 dark:text-brand-400' => $day->isToday(),
                                'text-slate-900 dark:text-white' => ! $day->isToday(),
                            ])>{{ $day->format('d/m') }}</p>
                        </div>
                        <a
                            href="{{ route('appointments.create', ['date' => $day->toDateString()]) }}"
                            wire:navigate
                            class="btn-icon h-7 w-7"
                            title="Planifier"
                        >
                            <x-icon name="plus" class="h-4 w-4" />
                        </a>
                    </div>

                    <div class="flex-1 space-y-1.5 p-2">
                        @forelse ($dayAppointments as $appointment)
                            <a
                                href="{{ route('appointments.show', $appointment) }}"
                                wire:navigate
                                class="block rounded-lg border border-slate-200 px-2 py-1.5 transition hover:border-brand-300 hover:shadow-sm dark:border-slate-700"
                            >
                                <p class="text-xs font-semibold text-slate-900 dark:text-white">
                                    {{ $appointment->timeLabel() }}
                                </p>
                                <p class="truncate text-xs text-slate-600 dark:text-slate-300">
                                    {{ $appointment->client?->name ?? 'Client supprimé' }}
                                </p>
                                <div class="mt-1"><x-status-badge :status="$appointment->status" class="text-[10px]" /></div>
                            </a>
                        @empty
                            <p class="py-4 text-center text-xs text-slate-400 dark:text-slate-500">Libre</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($view === 'day')
        <div class="card">
            <div class="card-header">
                <h2 class="text-sm font-semibold capitalize text-slate-900 dark:text-white">
                    {{ $anchorDate->translatedFormat('l d F Y') }}
                </h2>
                <span class="badge-neutral">{{ $appointments->count() }} rendez-vous</span>
            </div>

            @if ($appointments->isEmpty())
                <x-empty-state
                    icon="calendar"
                    title="Aucun rendez-vous ce jour"
                    description="Planifiez une visite, un devis ou une installation."
                >
                    <a href="{{ route('appointments.create', ['date' => $anchorDate->toDateString()]) }}" wire:navigate class="btn-primary btn-sm">
                        <x-icon name="plus" class="h-4 w-4" />
                        Planifier
                    </a>
                </x-empty-state>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($appointments as $appointment)
                        <li class="flex flex-wrap items-start gap-4 px-4 py-4 sm:px-5">
                            <div class="w-24 shrink-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $appointment->timeRange() }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $appointment->durationLabel() }}</p>
                            </div>

                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        href="{{ route('appointments.show', $appointment) }}"
                                        wire:navigate
                                        class="text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                    >
                                        {{ $appointment->client?->name ?? 'Client supprimé' }}
                                    </a>
                                    <x-status-badge :status="$appointment->status" />
                                    <x-type-chip :type="$appointment->type" />
                                    <span class="badge-{{ $appointment->priority->color() }}">{{ $appointment->priority->label() }}</span>
                                </div>

                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-medium text-slate-600 dark:text-slate-300">Réf. {{ $appointment->reference }}</span>
                                    · {{ $appointment->siteAddress() }}
                                    @if ($appointment->siteLocation())
                                        — {{ $appointment->siteLocation() }}
                                    @endif
                                </p>

                                @if ($appointment->description)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $appointment->description }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                @if ($appointment->client?->phone)
                                    <a href="tel:{{ $appointment->client->phone }}" class="btn-secondary btn-sm">
                                        <x-icon name="phone" class="h-4 w-4" />
                                        Appeler
                                    </a>
                                @endif
                                <a href="{{ route('appointments.edit', $appointment) }}" wire:navigate class="btn-secondary btn-sm">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                    Modifier
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
