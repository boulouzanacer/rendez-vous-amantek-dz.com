<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Tableau de bord', 'subtitle' => 'Votre programme et votre activité en temps réel'])] class extends Component
{
    public function start(int $appointmentId): void
    {
        $this->updateStatus($appointmentId, AppointmentStatus::EnCours);
    }

    public function complete(int $appointmentId): void
    {
        $this->updateStatus($appointmentId, AppointmentStatus::Termine);
    }

    public function markAbsent(int $appointmentId): void
    {
        $this->updateStatus($appointmentId, AppointmentStatus::Absent);
    }

    private function updateStatus(int $appointmentId, AppointmentStatus $status): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        Gate::authorize('update', $appointment);

        $appointment->update([
            'status' => $status,
            'completed_at' => $status === AppointmentStatus::Termine ? now() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $today = now();

        $todayAppointments = Appointment::query()
            ->onDay($today)
            ->with(['client', 'technician', 'technicians'])
            ->orderBy('scheduled_at')
            ->get();

        $upcoming = Appointment::query()
            ->open()
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(7)->endOfDay())
            ->with(['client', 'technician', 'technicians'])
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $collectedThisMonth = (float) Payment::query()
            ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->sum('amount');

        $collectedLastMonth = (float) Payment::query()
            ->whereBetween('paid_at', [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->sum('amount');

        $monthlyCollection = collect(range(5, 0))->map(function (int $offset) use ($today): array {
            $month = $today->copy()->subMonthsNoOverflow($offset);

            return [
                'label' => $month->translatedFormat('M'),
                'amount' => (float) Payment::query()
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month)
                    ->sum('amount'),
            ];
        });

        return [
            'todayAppointments' => $todayAppointments,
            'upcoming' => $upcoming,
            'overdueCount' => Appointment::query()->open()->where('scheduled_at', '<', now())->count(),
            'collectedThisMonth' => $collectedThisMonth,
            'collectedLastMonth' => $collectedLastMonth,
            'monthlyCollection' => $monthlyCollection,
            'monthlyMax' => max((float) $monthlyCollection->max('amount'), 1),
            'pendingQuotes' => Quote::query()->where('status', 'envoye')->count(),
            'pendingQuotesAmount' => (float) Quote::query()->where('status', 'envoye')->sum('total'),
            'openInstallations' => Installation::query()->open()->count(),
            'clientsCount' => Client::query()->count(),
            'totalContracted' => (float) Quote::query()->where('status', 'accepte')->sum('total'),
            'totalCollected' => (float) Payment::query()->sum('amount'),
            'recentPayments' => Payment::query()
                ->with(['client', 'quote'])
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <p class="text-sm text-slate-600 dark:text-slate-400">
        Bonjour <span class="font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>,
        nous sommes le {{ now()->translatedFormat('l d F Y') }}.
    </p>

    {{-- Indicateurs --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat
            label="Rendez-vous aujourd'hui"
            :value="$todayAppointments->count()"
            :hint="$todayAppointments->filter(fn ($item) => $item->status->isOpen())->count().' encore à traiter'"
            icon="calendar-check"
            tone="brand"
            :href="route('agenda')"
        />

        <x-stat
            label="En retard"
            :value="$overdueCount"
            hint="Rendez-vous dépassés non clôturés"
            icon="alert"
            tone="danger"
            :href="route('appointments.index', ['period' => 'retard'])"
        />

        <x-stat
            label="Encaissé ce mois"
            :value="Money::compact($collectedThisMonth)"
            :hint="$collectedLastMonth > 0 ? 'Mois précédent : '.Money::compact($collectedLastMonth) : 'Aucun encaissement le mois précédent'"
            icon="wallet"
            tone="success"
            :href="route('payments.index')"
        />

        <x-stat
            label="Reste à encaisser"
            :value="Money::compact(max($totalContracted - $totalCollected, 0))"
            :hint="'Sur '.Money::compact($totalContracted).' de devis acceptés'"
            icon="trending-up"
            tone="warning"
            :href="route('reports.index')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Programme du jour --}}
        <section class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Programme du jour</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ now()->translatedFormat('d F Y') }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('agenda') }}" wire:navigate class="btn-secondary btn-sm">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Agenda
                    </a>
                    <a href="{{ route('appointments.create') }}" wire:navigate class="btn-primary btn-sm">
                        <x-icon name="plus" class="h-4 w-4" />
                        Ajouter
                    </a>
                </div>
            </div>

            @if ($todayAppointments->isEmpty())
                <x-empty-state
                    icon="calendar"
                    title="Aucun rendez-vous aujourd'hui"
                    description="Votre journée est libre. Profitez-en pour relancer les devis en attente."
                >
                    <a href="{{ route('appointments.create') }}" wire:navigate class="btn-primary btn-sm">
                        <x-icon name="plus" class="h-4 w-4" />
                        Nouveau rendez-vous
                    </a>
                </x-empty-state>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($todayAppointments as $appointment)
                        <li class="flex flex-wrap items-start gap-3 px-4 py-3.5 sm:px-5">
                            <div class="w-20 shrink-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $appointment->scheduled_at?->format('H:i') ?? '—' }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $appointment->durationLabel() }}
                                </p>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        href="{{ route('appointments.show', $appointment) }}"
                                        wire:navigate
                                        class="text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                    >
                                        {{ $appointment->client?->name ?? 'Client supprimé' }}
                                    </a>
                                    <x-status-badge :status="$appointment->status" />
                                    <span class="badge-{{ $appointment->type->color() }}">{{ $appointment->type->label() }}</span>
                                    @if ($appointment->priority->weight() >= 3)
                                        <span class="badge-{{ $appointment->priority->color() }}">{{ $appointment->priority->label() }}</span>
                                    @endif
                                </div>

                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="map-pin" class="h-3.5 w-3.5" />
                                        {{ $appointment->siteAddress() }}
                                        @if ($appointment->siteLocation())
                                            — {{ $appointment->siteLocation() }}
                                        @endif
                                    </span>
                                    @if ($appointment->technicianLabel() !== 'Non assigné')
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="user" class="h-3.5 w-3.5" />
                                            {{ $appointment->technicianLabel() }}
                                        </span>
                                    @endif
                                    @if ($appointment->client?->phone)
                                        <a href="tel:{{ $appointment->client->phone }}" class="inline-flex items-center gap-1 text-brand-600 hover:underline dark:text-brand-400">
                                            <x-icon name="phone" class="h-3.5 w-3.5" />
                                            {{ $appointment->client->phone }}
                                        </a>
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                @if ($appointment->status === AppointmentStatus::Planifie || $appointment->status === AppointmentStatus::Confirme || $appointment->status === AppointmentStatus::Reporte)
                                    <button wire:click="start({{ $appointment->id }})" class="btn-secondary btn-sm" title="Démarrer l'intervention">
                                        <x-icon name="flame" class="h-4 w-4" />
                                        Démarrer
                                    </button>
                                @endif

                                @if ($appointment->status->isOpen())
                                    <button wire:click="complete({{ $appointment->id }})" class="btn-primary btn-sm" title="Marquer comme terminé">
                                        <x-icon name="check" class="h-4 w-4" />
                                        Terminer
                                    </button>
                                    <button
                                        wire:click="markAbsent({{ $appointment->id }})"
                                        wire:confirm="Confirmer que le client était absent ?"
                                        class="btn-icon"
                                        title="Client absent"
                                    >
                                        <x-icon name="x" class="h-4 w-4" />
                                    </button>
                                @endif

                                <a href="{{ route('appointments.edit', $appointment) }}" wire:navigate class="btn-icon" title="Modifier">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Prochains rendez-vous --}}
        <section class="card">
            <div class="card-header">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">À venir — 7 jours</h2>
                <a href="{{ route('appointments.index') }}" wire:navigate class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">
                    Tout voir
                </a>
            </div>

            @if ($upcoming->isEmpty())
                <x-empty-state icon="calendar-check" title="Aucun rendez-vous planifié" description="Les 7 prochains jours sont libres." />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($upcoming as $appointment)
                        <li class="flex items-start gap-3 px-4 py-3">
                            <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-100 text-center leading-none dark:bg-slate-800">
                                <span class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">
                                    {{ $appointment->scheduled_at?->translatedFormat('M') ?? '—' }}
                                </span>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $appointment->scheduled_at?->format('d') ?? '—' }}
                                </span>
                            </span>

                            <span class="min-w-0 flex-1">
                                <a
                                    href="{{ route('appointments.show', $appointment) }}"
                                    wire:navigate
                                    class="block truncate text-sm font-medium text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                >
                                    {{ $appointment->client?->name ?? 'Client supprimé' }}
                                </a>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $appointment->timeRange() }} · {{ $appointment->type->label() }}
                                </span>
                            </span>

                            <x-status-badge :status="$appointment->status" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Encaissements --}}
        <section class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Encaissements — 6 derniers mois</h2>
                <a href="{{ route('payments.index') }}" wire:navigate class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">
                    Détail des paiements
                </a>
            </div>

            <div class="card-body">
                @php $hasCollection = $monthlyCollection->sum('amount') > 0; @endphp

                @if (! $hasCollection)
                    <p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                        Aucun encaissement enregistré sur les six derniers mois.
                    </p>
                @else
                    <div class="flex h-40 items-end gap-3">
                        @foreach ($monthlyCollection as $month)
                            <div class="flex flex-1 flex-col items-center gap-2">
                                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                    {{ $month['amount'] > 0 ? Money::compact($month['amount']) : '' }}
                                </span>
                                <div
                                    class="w-full rounded-t-md bg-brand-500/80 transition hover:bg-brand-600"
                                    style="height: {{ max(round($month['amount'] / $monthlyMax * 100), $month['amount'] > 0 ? 4 : 1) }}%"
                                ></div>
                                <span class="text-xs font-medium capitalize text-slate-500 dark:text-slate-400">
                                    {{ $month['label'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Devis et interventions --}}
        <section class="space-y-4">
            <div class="card p-4 sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-300">
                        <x-icon name="file-text" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Devis en attente</p>
                        <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $pendingQuotes }}</p>
                    </div>
                    <a href="{{ route('quotes.index') }}" wire:navigate class="btn-secondary btn-sm">Ouvrir</a>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Montant potentiel : <span class="font-semibold text-slate-700 dark:text-slate-200">{{ Money::format($pendingQuotesAmount) }}</span>
                </p>
            </div>

            <div class="card p-4 sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300">
                        <x-icon name="wrench" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Interventions en cours</p>
                        <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $openInstallations }}</p>
                    </div>
                    <a href="{{ route('installations.index') }}" wire:navigate class="btn-secondary btn-sm">Ouvrir</a>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $clientsCount }}</span> clients enregistrés
                </p>
            </div>
        </section>
    </div>

    {{-- Derniers encaissements --}}
    <section class="card">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Derniers encaissements</h2>
            <a href="{{ route('payments.create') }}" wire:navigate class="btn-secondary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Enregistrer
            </a>
        </div>

        @if ($recentPayments->isEmpty())
            <x-empty-state icon="wallet" title="Aucun paiement enregistré" description="Les encaissements apparaîtront ici." />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Devis</th>
                            <th>Méthode</th>
                            <th class="text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPayments as $payment)
                            <tr>
                                <td class="whitespace-nowrap">{{ $payment->paid_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('clients.show', $payment->client) }}" wire:navigate class="font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                        {{ $payment->client?->name ?? '—' }}
                                    </a>
                                </td>
                                <td>
                                    @if ($payment->quote)
                                        <a href="{{ route('quotes.show', $payment->quote) }}" wire:navigate class="text-brand-600 hover:underline dark:text-brand-400">
                                            {{ $payment->quote->number }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="badge-{{ $payment->method->color() }}">{{ $payment->method->label() }}</span></td>
                                <td class="whitespace-nowrap text-right font-semibold text-slate-900 dark:text-white">
                                    {{ Money::format($payment->amount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
