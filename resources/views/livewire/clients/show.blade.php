<?php

use App\Models\Client;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Fiche client', 'subtitle' => 'Coordonnées, historique et encours'])] class extends Component
{
    public Client $client;

    public function mount(Client $client): void
    {
        $this->client = $client;

        Gate::authorize('view', $client);
    }

    public function destroy(): void
    {
        Gate::authorize('delete', $this->client);

        $this->client->delete();

        session()->flash('status', 'Client supprimé.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'appointments' => $this->client->appointments()
                ->with('technician')
                ->orderByDesc('scheduled_at')
                ->limit(6)
                ->get(),
            'quotes' => $this->client->quotes()->orderByDesc('issued_at')->get(),
            'installations' => $this->client->installations()->orderByDesc('created_at')->get(),
            'payments' => $this->client->payments()->orderByDesc('paid_at')->limit(6)->get(),
            'contracted' => $this->client->contractedAmount(),
            'collected' => $this->client->paidAmount(),
            'balance' => $this->client->balanceDue(),
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-4">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('clients.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Tous les clients
        </a>

        <div class="flex flex-wrap items-center gap-2">
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" wire:navigate class="btn-secondary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Modifier
                </a>
            @endcan
            <a href="{{ route('appointments.create', ['client' => $client->id]) }}" wire:navigate class="btn-secondary btn-sm">
                <x-icon name="calendar-plus" class="h-4 w-4" />
                Rendez-vous
            </a>
            <a href="{{ route('quotes.create', ['client' => $client->id]) }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Devis
            </a>
        </div>
    </div>

    {{-- Identité --}}
    <div class="card">
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $client->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Client depuis le {{ $client->created_at->translatedFormat('d F Y') }}
                    </p>
                </div>
                <span class="badge-{{ $client->type->color() }}">{{ $client->type->label() }}</span>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Téléphone</dt>
                    <dd class="mt-1 space-y-1">
                        <a href="tel:{{ $client->phone }}" class="flex items-center gap-1.5 text-sm font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                            <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                            {{ $client->phone }}
                        </a>
                        @if ($client->phone_alt)
                            <a href="tel:{{ $client->phone_alt }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                                {{ $client->phone_alt }}
                            </a>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</dt>
                    <dd class="mt-1">
                        @if ($client->email)
                            <a href="mailto:{{ $client->email }}" class="flex items-center gap-1.5 text-sm text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                <x-icon name="mail" class="h-4 w-4 text-slate-400" />
                                {{ $client->email }}
                            </a>
                        @else
                            <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Localisation</dt>
                    <dd class="mt-1 flex items-start gap-1.5 text-sm text-slate-800 dark:text-slate-100">
                        <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <span>
                            {{ $client->address ?: '—' }}
                            @if ($client->location())
                                <span class="block text-slate-500 dark:text-slate-400">{{ $client->location() }}</span>
                            @endif
                        </span>
                    </dd>
                </div>
            </dl>

            @if ($client->notes)
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $client->notes }}</dd>
                </div>
            @endif
        </div>
    </div>

    {{-- Encours --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Total contracté" :value="Money::format($contracted)" icon="file-text" tone="brand" />
        <x-stat label="Total encaissé" :value="Money::format($collected)" icon="banknote" tone="success" />
        <x-stat label="Reste à encaisser" :value="Money::format($balance)" icon="wallet" :tone="$balance > 0 ? 'warning' : 'neutral'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Rendez-vous --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Rendez-vous</h3>
                <a href="{{ route('appointments.index', ['q' => $client->phone]) }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Tout voir
                </a>
            </div>
            @if ($appointments->isEmpty())
                <x-empty-state icon="calendar" title="Aucun rendez-vous" description="Planifiez une visite pour ce client." />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($appointments as $appointment)
                        <li class="flex items-start justify-between gap-3 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <a href="{{ route('appointments.show', $appointment) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $appointment->reference }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $appointment->scheduled_at->translatedFormat('d M Y à H:i') }} · {{ $appointment->type->label() }}
                                </p>
                            </div>
                            <x-status-badge :status="$appointment->status" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Devis --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Devis</h3>
                <a href="{{ route('quotes.create', ['client' => $client->id]) }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Nouveau devis
                </a>
            </div>
            @if ($quotes->isEmpty())
                <x-empty-state icon="file-text" title="Aucun devis" description="Créez un devis pour ce client." />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($quotes as $quote)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $quote->number }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $quote->issued_at->translatedFormat('d M Y') }} · {{ Money::format($quote->total) }}
                                </p>
                            </div>
                            <span class="badge-{{ $quote->status->color() }} shrink-0">{{ $quote->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Interventions --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Interventions</h3>
            </div>
            @if ($installations->isEmpty())
                <x-empty-state icon="wrench" title="Aucune intervention" description="Les chantiers apparaîtront ici." />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($installations as $installation)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <a href="{{ route('installations.show', $installation) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $installation->reference }}
                                </a>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $installation->title }}</p>
                            </div>
                            <span class="badge-{{ $installation->status->color() }} shrink-0">{{ $installation->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Paiements --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Derniers paiements</h3>
                <a href="{{ route('payments.index', ['q' => $client->phone]) }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Tout voir
                </a>
            </div>
            @if ($payments->isEmpty())
                <x-empty-state icon="banknote" title="Aucun paiement" description="Les encaissements apparaîtront ici." />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($payments as $payment)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ Money::format($payment->amount) }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $payment->paid_at->translatedFormat('d M Y') }}@if ($payment->reference) · {{ $payment->reference }}@endif
                                </p>
                            </div>
                            <span class="badge-neutral shrink-0">{{ $payment->method->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @can('delete', $client)
        <div class="card">
            <div class="card-body">
                <button
                    wire:click="destroy"
                    wire:confirm="Supprimer ce client ? Ses rendez-vous et devis resteront enregistrés."
                    class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                >
                    <x-icon name="trash" class="h-4 w-4" />
                    Supprimer le client
                </button>
            </div>
        </div>
    @endcan
</div>
