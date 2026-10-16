<?php

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Fiche devis', 'subtitle' => 'Détail de la proposition commerciale'])] class extends Component
{
    public Quote $quote;

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;

        Gate::authorize('view', $quote);
    }

    public function updateStatus(string $status): void
    {
        Gate::authorize('update', $this->quote);

        $newStatus = QuoteStatus::tryFrom($status);

        if (! $newStatus) {
            return;
        }

        $this->quote->update(['status' => $newStatus]);

        session()->flash('status', 'Statut du devis mis à jour.');
    }

    public function destroy(): void
    {
        Gate::authorize('delete', $this->quote);

        $this->quote->delete();

        session()->flash('status', 'Devis supprimé.');

        $this->redirect(route('quotes.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'items' => $this->quote->items,
            'payments' => $this->quote->payments()->orderByDesc('paid_at')->get(),
            'paidAmount' => $this->quote->paidAmount(),
            'balanceDue' => $this->quote->balanceDue(),
            'installations' => $this->quote->installations()->orderByDesc('created_at')->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-4">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('quotes.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Tous les devis
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="btn-secondary btn-sm">
                <x-icon name="printer" class="h-4 w-4" />
                Imprimer
            </a>

            @can('update', $quote)
                <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="btn-secondary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Modifier
                </a>
            @endcan

            <a href="{{ route('payments.create', ['quote' => $quote->id]) }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="banknote" class="h-4 w-4" />
                Encaisser
            </a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Devis --}}
            <div class="card">
                <div class="card-body space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $quote->number }}</p>
                            <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">
                                {{ $quote->client?->name ?? 'Client supprimé' }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Émis le {{ $quote->issued_at->translatedFormat('d F Y') }} ·
                                valable jusqu'au {{ $quote->valid_until->translatedFormat('d F Y') }}
                            </p>
                        </div>
                        <span class="badge-{{ $quote->status->color() }}">{{ $quote->status->label() }}</span>
                    </div>

                    @if ($quote->isExpired())
                        <p class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <x-icon name="hourglass" class="h-4 w-4 shrink-0" />
                            Ce devis a dépassé sa date de validité.
                        </p>
                    @endif

                    @if ($items->isEmpty())
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucune ligne enregistrée.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th class="text-center">Qté</th>
                                        <th class="text-right">P.U.</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td>
                                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $item->designation }}</span>
                                                @if ($item->description)
                                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $item->description }}</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }} {{ $item->unit }}</td>
                                            <td class="whitespace-nowrap text-right">{{ Money::format($item->unit_price) }}</td>
                                            <td class="whitespace-nowrap text-right font-semibold text-slate-900 dark:text-white">{{ Money::format($item->total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <dl class="ms-auto max-w-xs space-y-1.5 border-t border-slate-200 pt-3 text-sm dark:border-slate-800">
                            <div class="flex justify-between">
                                <dt class="text-slate-600 dark:text-slate-300">Sous-total</dt>
                                <dd class="font-medium text-slate-900 dark:text-white">{{ Money::format($quote->subtotal) }}</dd>
                            </div>
                            @if ((float) $quote->discount_amount > 0)
                                <div class="flex justify-between">
                                    <dt class="text-slate-600 dark:text-slate-300">Remise</dt>
                                    <dd class="font-medium text-rose-600 dark:text-rose-400">− {{ Money::format($quote->discount_amount) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-slate-600 dark:text-slate-300">TVA {{ (float) $quote->tax_rate }} %</dt>
                                <dd class="font-medium text-slate-900 dark:text-white">{{ Money::format($quote->tax_amount) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-1.5 dark:border-slate-800">
                                <dt class="font-semibold text-slate-700 dark:text-slate-200">Total</dt>
                                <dd class="text-base font-bold text-slate-900 dark:text-white">{{ Money::format($quote->total) }}</dd>
                            </div>
                        </dl>
                    @endif

                    @if ($quote->notes)
                        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $quote->notes }}</p>
                        </div>
                    @endif

                    @if ($quote->terms)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Conditions</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400">{{ $quote->terms }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Encaissements --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Encaissements</h3>
                    <a href="{{ route('payments.create', ['quote' => $quote->id]) }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                        Ajouter un paiement
                    </a>
                </div>
                @if ($payments->isEmpty())
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucun paiement enregistré pour ce devis.</p>
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
                <x-stat label="Montant total" :value="Money::format($quote->total)" icon="receipt" tone="brand" />
                <x-stat label="Encaissé" :value="Money::format($paidAmount)" icon="banknote" tone="success" />
                <x-stat label="Reste dû" :value="Money::format($balanceDue)" icon="wallet" :tone="$balanceDue > 0 ? 'warning' : 'neutral'" />
            </div>

            @if ($quote->client)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Client</h3>
                    </div>
                    <div class="card-body space-y-2">
                        <a href="{{ route('clients.show', $quote->client) }}" wire:navigate class="block text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                            {{ $quote->client->name }}
                        </a>
                        @if ($quote->client->phone)
                            <a href="tel:{{ $quote->client->phone }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                                {{ $quote->client->phone }}
                            </a>
                        @endif
                        @if ($quote->client->location())
                            <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <x-icon name="map-pin" class="h-4 w-4 text-slate-400" />
                                {{ $quote->client->location() }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @can('update', $quote)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Statut du devis</h3>
                    </div>
                    <div class="card-body grid gap-2">
                        @if ($quote->status->value === 'brouillon')
                            <button wire:click="updateStatus('envoye')" class="btn-primary justify-start">
                                <x-icon name="send" class="h-4 w-4" />
                                Marquer comme envoyé
                            </button>
                        @endif

                        @if ($quote->status->value === 'envoye')
                            <button wire:click="updateStatus('accepte')" class="btn-primary justify-start">
                                <x-icon name="check-circle" class="h-4 w-4" />
                                Marquer comme accepté
                            </button>
                            <button wire:click="updateStatus('refuse')" class="btn-ghost justify-start text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50">
                                <x-icon name="x" class="h-4 w-4" />
                                Marquer comme refusé
                            </button>
                        @endif

                        @if ($quote->status->value !== 'brouillon')
                            <button wire:click="updateStatus('brouillon')" class="btn-ghost justify-start">
                                <x-icon name="refresh" class="h-4 w-4" />
                                Repasser en brouillon
                            </button>
                        @endif
                    </div>
                </div>
            @endcan

            @if ($quote->status->value === 'accepte')
                <div class="card">
                    <div class="card-body space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Suite du chantier</p>
                        @if ($installations->isEmpty())
                            <a href="{{ route('installations.create', ['quote' => $quote->id]) }}" wire:navigate class="btn-primary btn-sm w-full justify-center">
                                <x-icon name="wrench" class="h-4 w-4" />
                                Planifier l'intervention
                            </a>
                        @else
                            <ul class="space-y-2">
                                @foreach ($installations as $installation)
                                    <li class="flex items-center justify-between gap-2">
                                        <a href="{{ route('installations.show', $installation) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                            {{ $installation->reference }}
                                        </a>
                                        <span class="badge-{{ $installation->status->color() }}">{{ $installation->status->label() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif

            @can('delete', $quote)
                <div class="card">
                    <div class="card-body">
                        <button
                            wire:click="destroy"
                            wire:confirm="Supprimer ce devis ?"
                            class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                        >
                            <x-icon name="trash" class="h-4 w-4" />
                            Supprimer le devis
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
