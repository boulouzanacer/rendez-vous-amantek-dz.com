<?php

use App\Enums\AuthorizationStatus;
use App\Models\Authorization;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Autorisations', 'subtitle' => 'Dossiers d\'autorisation de vidéosurveillance'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Authorization::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $authorizations = Authorization::query()
            ->with('client:id,name')
            ->search($this->search)
            ->when(filled($this->status), fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return [
            'authorizations' => $authorizations,
            'statuses' => AuthorizationStatus::options(),
            'hasFilters' => collect([$this->search, $this->status])->contains(fn ($value) => $value !== ''),
            'openCount' => Authorization::query()->open()->count(),
            'preparingCount' => Authorization::query()->where('status', AuthorizationStatus::EnPreparation->value)->count(),
            'grantedCount' => Authorization::query()->where('status', AuthorizationStatus::AutorisationRetenue->value)->count(),
        ];
    }
}; ?>

<div class="space-y-4">
    {{-- Indicateurs --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat label="Dossiers ouverts" :value="$openCount" icon="clipboard" tone="warning" />
        <x-stat label="En préparation" :value="$preparingCount" icon="hourglass" tone="brand" />
        <x-stat label="Autorisations retenues" :value="$grantedCount" icon="shield" tone="success" />
    </div>

    {{-- Filtres --}}
    <div class="card p-4" x-data="{ filtersOpen: false }">
        <div class="flex flex-wrap items-center justify-between gap-2 lg:hidden">
            <button
                type="button"
                @click="filtersOpen = ! filtersOpen"
                x-bind:aria-expanded="filtersOpen"
                aria-controls="authorizations-filters"
                class="btn-secondary btn-sm"
            >
                <x-icon name="filter" class="h-4 w-4" />
                Filtres
                @if ($hasFilters)
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                @endif
                <x-icon name="chevron-down" class="h-4 w-4 transition-transform duration-200" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>

            <a href="{{ route('authorizations.create') }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Nouvelle autorisation
            </a>
        </div>

        <div
            id="authorizations-filters"
            class="mt-3 grid gap-3 max-lg:hidden sm:grid-cols-2 xl:grid-cols-3 lg:mt-0"
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
                        placeholder="Référence ou client…"
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
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $authorizations->total() }} dossier(s)
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('authorizations.create') }}" wire:navigate class="btn-primary btn-sm hidden lg:inline-flex">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle autorisation
                </a>
            </div>
        </div>
    </div>

    @if ($authorizations->isEmpty())
        <div class="card">
            <x-empty-state
                icon="shield"
                title="Aucune autorisation"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Créez un dossier d\'autorisation pour un client.'"
            >
                <a href="{{ route('authorizations.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle autorisation
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($authorizations as $authorization)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('authorizations.show', $authorization) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $authorization->reference }}
                            </a>
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $authorization->client?->name ?? 'Client supprimé' }}</p>
                        </div>
                        <span class="badge-{{ $authorization->status->color() }} shrink-0">{{ $authorization->status->label() }}</span>
                    </div>

                    <div class="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                        @if ($authorization->location())
                            <p class="flex items-center gap-1.5">
                                <x-icon name="map-pin" class="h-3.5 w-3.5 text-slate-400" />
                                {{ $authorization->location() }}
                            </p>
                        @endif
                        <p class="flex items-center gap-1.5">
                            <x-icon name="camera" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $authorization->cameraSummary() }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-end">
                        <a href="{{ route('authorizations.show', $authorization) }}" wire:navigate class="btn-secondary btn-sm">Consulter</a>
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
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Localisation</th>
                            <th>Caméras</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($authorizations as $authorization)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('authorizations.show', $authorization) }}" wire:navigate class="font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $authorization->reference }}
                                    </a>
                                </td>
                                <td>
                                    @if ($authorization->client)
                                        <a href="{{ route('clients.show', $authorization->client) }}" wire:navigate class="text-slate-700 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                            {{ $authorization->client->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="text-slate-600 dark:text-slate-300">{{ $authorization->location() ?: '—' }}</td>
                                <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $authorization->cameraSummary() }}</td>
                                <td><span class="badge-{{ $authorization->status->color() }}">{{ $authorization->status->label() }}</span></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('authorizations.show', $authorization) }}" wire:navigate class="btn-icon" title="Consulter">
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
            {{ $authorizations->links() }}
        </div>
    @endif
</div>
