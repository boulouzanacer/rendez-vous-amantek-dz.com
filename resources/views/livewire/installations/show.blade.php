<?php

use App\Enums\InstallationStatus;
use App\Models\Installation;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Intervention', 'subtitle' => 'Suivi du chantier'])] class extends Component
{
    public Installation $installation;

    public function mount(Installation $installation): void
    {
        $this->installation = $installation;

        Gate::authorize('view', $installation);
    }

    public function updateStatus(string $status): void
    {
        Gate::authorize('update', $this->installation);

        $newStatus = InstallationStatus::tryFrom($status);

        if (! $newStatus) {
            return;
        }

        $payload = ['status' => $newStatus];

        if ($newStatus === InstallationStatus::EnCours) {
            $payload['started_at'] = $this->installation->started_at ?? now();
        }

        if ($newStatus === InstallationStatus::Termine) {
            $payload['started_at'] = $this->installation->started_at ?? now();
            $payload['completed_at'] = now();
        }

        $this->installation->update($payload);

        session()->flash('status', 'Statut du chantier mis à jour.');
    }

    public function destroy(): void
    {
        Gate::authorize('delete', $this->installation);

        $this->installation->delete();

        session()->flash('status', 'Intervention supprimée.');

        $this->redirect(route('installations.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $payments = Payment::query()
            ->where('installation_id', $this->installation->id)
            ->orderByDesc('paid_at')
            ->get();

        return [
            'items' => $this->installation->items,
            'payments' => $payments,
            'paidAmount' => (float) $payments->sum('amount'),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-4">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('installations.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Toutes les interventions
        </a>

        <div class="flex flex-wrap items-center gap-2">
            @can('update', $installation)
                <a href="{{ route('installations.edit', $installation) }}" wire:navigate class="btn-secondary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Modifier
                </a>
            @endcan

            <a href="{{ route('payments.create', ['installation' => $installation->id, 'client' => $installation->client_id]) }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="banknote" class="h-4 w-4" />
                Enregistrer un paiement
            </a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Chantier --}}
            <div class="card">
                <div class="card-body space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $installation->reference }}</p>
                            <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $installation->title }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $installation->client?->name ?? 'Client supprimé' }}
                            </p>
                        </div>
                        <span class="badge-{{ $installation->status->color() }}">{{ $installation->status->label() }}</span>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Planifié le</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $installation->scheduled_at?->translatedFormat('d F Y à H:i') ?? 'Non planifié' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Technicien</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $installation->technician?->name ?? 'Non assigné' }}
                            </dd>
                        </div>
                        @if ($installation->started_at)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Démarré le</dt>
                                <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {{ $installation->started_at->translatedFormat('d F Y à H:i') }}
                                </dd>
                            </div>
                        @endif
                        @if ($installation->completed_at)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Terminé le</dt>
                                <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {{ $installation->completed_at->translatedFormat('d F Y à H:i') }}
                                    @if ($installation->durationInHours())
                                        <span class="text-slate-500 dark:text-slate-400">· {{ $installation->durationInHours() }} h</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($installation->site_address || $installation->commune || $installation->wilaya)
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <p class="flex items-start gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                <span>
                                    @if ($installation->site_address)
                                        {{ $installation->site_address }}
                                        <br>
                                    @endif
                                    {{ collect([$installation->commune, $installation->wilaya])->filter()->implode(', ') }}
                                </span>
                            </p>
                        </div>
                    @endif

                    @if ($installation->description)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Travaux à réaliser</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $installation->description }}</p>
                        </div>
                    @endif

                    @if ($installation->notes)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes internes</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400">{{ $installation->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Matériel posé --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Matériel posé</h3>
                    <span class="badge-neutral">{{ $items->count() }} ligne(s)</span>
                </div>
                @if ($items->isEmpty())
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucun matériel enregistré pour ce chantier.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Désignation</th>
                                    <th>Numéro de série</th>
                                    <th class="text-center">Qté</th>
                                    <th>Emplacement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td class="font-medium text-slate-800 dark:text-slate-100">{{ $item->designation }}</td>
                                        <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $item->serial_number ?: '—' }}</td>
                                        <td class="whitespace-nowrap text-center">
                                            {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }} {{ $item->unit }}
                                        </td>
                                        <td class="text-slate-600 dark:text-slate-300">{{ $item->notes ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Paiements --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Paiements liés</h3>
                    <a href="{{ route('payments.create', ['installation' => $installation->id, 'client' => $installation->client_id]) }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                        Ajouter un paiement
                    </a>
                </div>
                @if ($payments->isEmpty())
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucun paiement enregistré pour ce chantier.</p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($payments as $payment)
                            <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ Money::format($payment->amount) }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $payment->paid_at->translatedFormat('d M Y') }}@if ($payment->reference) · {{ $payment->reference }}@endif
                                    </p>
                                </div>
                                <span class="badge-neutral">{{ $payment->method->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="space-y-4">
            <div class="grid gap-3">
                <x-stat label="Encaissé" :value="Money::format($paidAmount)" icon="banknote" tone="success" />
                @if ($installation->quote)
                    <x-stat label="Montant du devis" :value="Money::format($installation->quote->total)" icon="receipt" tone="brand" />
                @endif
            </div>

            @if ($installation->client)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Client</h3>
                    </div>
                    <div class="card-body space-y-2">
                        <a href="{{ route('clients.show', $installation->client) }}" wire:navigate class="block text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                            {{ $installation->client->name }}
                        </a>
                        @if ($installation->client->phone)
                            <a href="tel:{{ $installation->client->phone }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                                {{ $installation->client->phone }}
                            </a>
                        @endif
                        @if ($installation->client->location())
                            <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <x-icon name="map-pin" class="h-4 w-4 text-slate-400" />
                                {{ $installation->client->location() }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @if ($installation->quote)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Devis d'origine</h3>
                    </div>
                    <div class="card-body space-y-2">
                        <a href="{{ route('quotes.show', $installation->quote) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                            {{ $installation->quote->number }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ Money::format($installation->quote->total) }} · {{ $installation->quote->status->label() }}
                        </p>
                    </div>
                </div>
            @endif

            @can('update', $installation)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Avancement</h3>
                    </div>
                    <div class="card-body grid gap-2">
                        @if ($installation->status->value === 'planifie')
                            <button wire:click="updateStatus('en_cours')" class="btn-primary justify-start">
                                <x-icon name="flame" class="h-4 w-4" />
                                Démarrer le chantier
                            </button>
                        @endif

                        @if (in_array($installation->status->value, ['planifie', 'en_cours'], true))
                            <button wire:click="updateStatus('termine')" class="btn-primary justify-start">
                                <x-icon name="check-circle" class="h-4 w-4" />
                                Marquer comme terminé
                            </button>
                        @endif

                        @if ($installation->status->value === 'en_cours')
                            <button wire:click="updateStatus('suspendu')" class="btn-ghost justify-start text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50">
                                <x-icon name="hourglass" class="h-4 w-4" />
                                Suspendre le chantier
                            </button>
                        @endif

                        @if ($installation->status->value === 'suspendu')
                            <button wire:click="updateStatus('en_cours')" class="btn-primary justify-start">
                                <x-icon name="refresh" class="h-4 w-4" />
                                Reprendre le chantier
                            </button>
                        @endif

                        @if ($installation->status->value !== 'annule')
                            <button wire:click="updateStatus('annule')" class="btn-ghost justify-start text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50">
                                <x-icon name="x" class="h-4 w-4" />
                                Annuler le chantier
                            </button>
                        @endif

                        @if (in_array($installation->status->value, ['termine', 'annule'], true))
                            <button wire:click="updateStatus('planifie')" class="btn-ghost justify-start">
                                <x-icon name="refresh" class="h-4 w-4" />
                                Replanifier
                            </button>
                        @endif
                    </div>
                </div>
            @endcan

            @can('delete', $installation)
                <div class="card">
                    <div class="card-body">
                        <button
                            wire:click="destroy"
                            wire:confirm="Supprimer cette intervention ?"
                            class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                        >
                            <x-icon name="trash" class="h-4 w-4" />
                            Supprimer l'intervention
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
