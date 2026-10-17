<?php

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Devis', 'subtitle' => 'Propositions commerciales et suivi'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'period');
        $this->resetPage();
    }

    public function markAccepted(int $quoteId): void
    {
        $quote = Quote::query()->findOrFail($quoteId);

        Gate::authorize('update', $quote);

        $quote->update(['status' => QuoteStatus::Accepte]);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = Quote::query()
            ->with('client')
            ->search($this->search)
            ->withStatus($this->status);

        match ($this->period) {
            'expire' => $query->where('valid_until', '<', now()->startOfDay())
                ->where('status', QuoteStatus::Envoye->value),
            'bientot' => $query->where('status', QuoteStatus::Envoye->value)
                ->whereBetween('valid_until', [now()->startOfDay(), now()->addDays(7)->endOfDay()]),
            'mois' => $query->whereBetween('issued_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => null,
        };

        $quotes = $query->orderByDesc('issued_at')->orderByDesc('id')->paginate(15);

        return [
            'quotes' => $quotes,
            'statuses' => QuoteStatus::options(),
            'hasFilters' => collect([$this->search, $this->status, $this->period])->contains(fn ($value) => $value !== ''),
            'totalAmount' => (float) Quote::query()
                ->search($this->search)
                ->withStatus($this->status)
                ->sum('total'),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="card p-4" x-data="{ filtersOpen: false }">
        {{-- Barre mobile : accès aux filtres et action principale --}}
        <div class="flex flex-wrap items-center justify-between gap-2 lg:hidden">
            <button
                type="button"
                @click="filtersOpen = ! filtersOpen"
                x-bind:aria-expanded="filtersOpen"
                aria-controls="quotes-filters"
                class="btn-secondary btn-sm"
            >
                <x-icon name="filter" class="h-4 w-4" />
                Filtres
                @if ($hasFilters)
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                @endif
                <x-icon name="chevron-down" class="h-4 w-4 transition-transform duration-200" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>

            <a href="{{ route('quotes.create') }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Nouveau devis
            </a>
        </div>

        <div
            id="quotes-filters"
            class="mt-3 grid gap-3 max-lg:hidden sm:grid-cols-2 xl:grid-cols-4 lg:mt-0"
            x-bind:class="{ 'max-lg:!grid': filtersOpen }"
        >
            <div class="xl:col-span-2">
                <label class="label" for="search">Recherche</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Numéro de devis ou client…"
                        class="input pl-9"
                    >
                </div>
            </div>

            <div>
                <label class="label" for="status">Statut</label>
                <select id="status" wire:model.live="status" class="input">
                    <option value="">Tous</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="period">Période</label>
                <select id="period" wire:model.live="period" class="input">
                    <option value="">Toutes</option>
                    <option value="mois">Ce mois</option>
                    <option value="bientot">Expire bientôt</option>
                    <option value="expire">Expirés</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $quotes->total() }} devis · total {{ Money::format($totalAmount) }}
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('quotes.create') }}" wire:navigate class="btn-primary btn-sm hidden lg:inline-flex">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau devis
                </a>
            </div>
        </div>
    </div>

    @if ($quotes->isEmpty())
        <div class="card">
            <x-empty-state
                icon="file-text"
                title="Aucun devis"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Créez votre premier devis après une visite technique.'"
            >
                <a href="{{ route('quotes.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau devis
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($quotes as $quote)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $quote->client?->name ?? 'Client supprimé' }}
                            </a>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $quote->number }}</p>
                        </div>
                        <span class="badge-{{ $quote->status->color() }} shrink-0">{{ $quote->status->label() }}</span>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div class="text-xs text-slate-600 dark:text-slate-300">
                            <p>Émis le {{ $quote->issued_at->translatedFormat('d M Y') }}</p>
                            <p class="{{ $quote->isExpired() ? 'font-medium text-rose-600 dark:text-rose-400' : '' }}">
                                Valable jusqu'au {{ $quote->valid_until->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <p class="text-base font-bold text-slate-900 dark:text-white">{{ Money::format($quote->total) }}</p>
                    </div>

                    <div class="mt-3 flex items-center justify-end gap-1.5">
                        <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="btn-icon" title="Imprimer">
                            <x-icon name="printer" class="h-4 w-4" />
                        </a>
                        @if ($quote->status->isEditable())
                            <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="btn-icon" title="Modifier">
                                <x-icon name="pencil" class="h-4 w-4" />
                            </a>
                        @endif
                        <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="btn-secondary btn-sm">Consulter</a>
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
                            <th>Numéro</th>
                            <th>Client</th>
                            <th>Émis le</th>
                            <th>Valable jusqu'au</th>
                            <th class="text-right">Total</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotes as $quote)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $quote->number }}
                                    </a>
                                </td>
                                <td>
                                    @if ($quote->client)
                                        <a href="{{ route('clients.show', $quote->client) }}" wire:navigate class="font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                            {{ $quote->client->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $quote->issued_at->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="{{ $quote->isExpired() ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $quote->valid_until->translatedFormat('d M Y') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-right font-semibold text-slate-900 dark:text-white">{{ Money::format($quote->total) }}</td>
                                <td><span class="badge-{{ $quote->status->color() }}">{{ $quote->status->label() }}</span></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($quote->status->value === 'envoye')
                                            <button wire:click="markAccepted({{ $quote->id }})" class="btn-icon hover:text-emerald-600 dark:hover:text-emerald-400" title="Marquer accepté">
                                                <x-icon name="check-circle" class="h-4 w-4" />
                                                <span class="sr-only">Marquer accepté</span>
                                            </button>
                                        @endif

                                        <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="btn-icon" title="Imprimer">
                                            <x-icon name="printer" class="h-4 w-4" />
                                            <span class="sr-only">Imprimer</span>
                                        </a>

                                        @if ($quote->status->isEditable())
                                            <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="btn-icon" title="Modifier">
                                                <x-icon name="pencil" class="h-4 w-4" />
                                                <span class="sr-only">Modifier</span>
                                            </a>
                                        @endif

                                        <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="btn-icon" title="Consulter">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            <span class="sr-only">Consulter</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pt-1">
            {{ $quotes->links() }}
        </div>
    @endif
</div>
