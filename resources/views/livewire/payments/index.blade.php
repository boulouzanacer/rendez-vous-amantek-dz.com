<?php

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Paiements', 'subtitle' => 'Encaissements et suivi de trésorerie'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'moyen', except: '')]
    public string $method = '';

    #[Url(as: 'periode', except: '')]
    public string $period = 'mois';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'method', 'period');
        $this->resetPage();
    }

    public function destroy(int $paymentId): void
    {
        $payment = Payment::query()->findOrFail($paymentId);

        Gate::authorize('delete', $payment);

        $payment->delete();

        session()->flash('status', 'Paiement supprimé.');
    }

    /**
     * Applique le filtre de période à la requête.
     */
    private function applyPeriod(\Illuminate\Database\Eloquent\Builder $query): void
    {
        match ($this->period) {
            'mois' => $query->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month),
            'mois-1' => $query->whereYear('paid_at', now()->subMonthNoOverflow()->year)->whereMonth('paid_at', now()->subMonthNoOverflow()->month),
            'annee' => $query->whereYear('paid_at', now()->year),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = Payment::query()
            ->with(['client:id,name', 'quote:id,number'])
            ->search($this->search)
            ->when(filled($this->method), fn ($query) => $query->where('method', $this->method));

        $this->applyPeriod($query);

        $payments = (clone $query)->orderByDesc('paid_at')->orderByDesc('id')->paginate(20);

        return [
            'payments' => $payments,
            'methods' => PaymentMethod::options(),
            'hasFilters' => collect([$this->search, $this->method])->contains(fn ($value) => $value !== '') || $this->period !== 'mois',
            'filteredTotal' => (float) (clone $query)->sum('amount'),
            'monthTotal' => (float) Payment::query()
                ->whereYear('paid_at', now()->year)
                ->whereMonth('paid_at', now()->month)
                ->sum('amount'),
            'yearTotal' => (float) Payment::query()->whereYear('paid_at', now()->year)->sum('amount'),
        ];
    }
}; ?>

<div class="space-y-4">
    {{-- Indicateurs --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat label="Encaissé ce mois" :value="Money::format($monthTotal)" icon="banknote" tone="success" />
        <x-stat label="Encaissé cette année" :value="Money::format($yearTotal)" icon="trending-up" tone="brand" />
        <x-stat label="Résultat du filtre" :value="Money::format($filteredTotal)" icon="wallet" tone="neutral" />
    </div>

    {{-- Filtres --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="label" for="search">Recherche</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Client, référence, note…"
                        class="input pl-9"
                    >
                </div>
            </div>

            <div>
                <label class="label" for="method">Moyen de paiement</label>
                <select id="method" wire:model.live="method" class="input">
                    <option value="">Tous</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="period">Période</label>
                <select id="period" wire:model.live="period" class="input">
                    <option value="mois">Ce mois</option>
                    <option value="mois-1">Le mois dernier</option>
                    <option value="annee">Cette année</option>
                    <option value="tout">Tout l'historique</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $payments->total() }} paiement(s) · {{ Money::format($filteredTotal) }}
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('payments.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau paiement
                </a>
            </div>
        </div>
    </div>

    @if ($payments->isEmpty())
        <div class="card">
            <x-empty-state
                icon="wallet"
                title="Aucun paiement"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Enregistrez un encaissement depuis un devis ou une intervention.'"
            >
                <a href="{{ route('payments.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau paiement
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($payments as $payment)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $payment->client?->name ?? 'Client supprimé' }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $payment->paid_at->translatedFormat('d M Y') }} · {{ $payment->method->label() }}
                            </p>
                        </div>
                        <p class="shrink-0 text-base font-bold text-emerald-600 dark:text-emerald-400">{{ Money::format($payment->amount) }}</p>
                    </div>

                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @if ($payment->quote)
                                Devis {{ $payment->quote->number }}
                            @elseif ($payment->reference)
                                {{ $payment->reference }}
                            @else
                                Encaissement direct
                            @endif
                        </p>

                        <div class="flex items-center gap-1.5">
                            @can('update', $payment)
                                <a href="{{ route('payments.edit', $payment) }}" wire:navigate class="btn-icon" title="Modifier">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                            @endcan
                            @can('delete', $payment)
                                <button wire:click="destroy({{ $payment->id }})" wire:confirm="Supprimer ce paiement ?" class="btn-icon text-rose-600 dark:text-rose-400" title="Supprimer">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tableau desktop --}}
        <div class="card hidden overflow-hidden lg:block">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Rattachement</th>
                            <th>Moyen</th>
                            <th>Référence</th>
                            <th class="text-right">Montant</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $payment->paid_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    @if ($payment->client)
                                        <a href="{{ route('clients.show', $payment->client) }}" wire:navigate class="font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                            {{ $payment->client->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($payment->quote)
                                        <a href="{{ route('quotes.show', $payment->quote) }}" wire:navigate class="text-slate-700 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                            Devis {{ $payment->quote->number }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td><span class="badge-{{ $payment->method->color() }}">{{ $payment->method->label() }}</span></td>
                                <td class="text-slate-600 dark:text-slate-300">{{ $payment->reference ?: '—' }}</td>
                                <td class="whitespace-nowrap text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ Money::format($payment->amount) }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $payment)
                                            <a href="{{ route('payments.edit', $payment) }}" wire:navigate class="btn-icon" title="Modifier">
                                                <x-icon name="pencil" class="h-4 w-4" />
                                                <span class="sr-only">Modifier</span>
                                            </a>
                                        @endcan
                                        @can('delete', $payment)
                                            <button wire:click="destroy({{ $payment->id }})" wire:confirm="Supprimer ce paiement ?" class="btn-icon text-rose-600 dark:text-rose-400" title="Supprimer">
                                                <x-icon name="trash" class="h-4 w-4" />
                                                <span class="sr-only">Supprimer</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pt-1">
            {{ $payments->links() }}
        </div>
    @endif
</div>
