<?php

use App\Enums\InstallationStatus;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Interventions', 'subtitle' => 'Chantiers de pose et de mise en service'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'technicien', except: '')]
    public string $technicianId = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'technicianId');
        $this->resetPage();
    }

    /**
     * Marque le chantier comme démarré par le technicien en charge.
     */
    public function start(int $installationId): void
    {
        $installation = Installation::query()->findOrFail($installationId);

        Gate::authorize('update', $installation);

        $installation->update([
            'status' => InstallationStatus::EnCours,
            'started_at' => $installation->started_at ?? now(),
        ]);

        session()->flash('status', 'Chantier démarré.');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = Installation::query()
            ->with(['client:id,name', 'technician:id,name'])
            ->search($this->search)
            ->when(filled($this->status), fn ($query) => $query->where('status', $this->status))
            ->when(filled($this->technicianId), fn ($query) => $query->where('user_id', (int) $this->technicianId));

        $installations = $query->orderByDesc('scheduled_at')->orderByDesc('id')->paginate(15);

        return [
            'installations' => $installations,
            'statuses' => InstallationStatus::options(),
            'technicians' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'hasFilters' => collect([$this->search, $this->status, $this->technicianId])->contains(fn ($value) => $value !== ''),
            'openCount' => Installation::query()->open()->count(),
            'runningCount' => Installation::query()->where('status', InstallationStatus::EnCours->value)->count(),
            'completedThisMonth' => Installation::query()
                ->where('status', InstallationStatus::Termine->value)
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];
    }
}; ?>

<div class="space-y-4">
    {{-- Indicateurs --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat label="Chantiers ouverts" :value="$openCount" icon="wrench" tone="warning" />
        <x-stat label="En cours" :value="$runningCount" icon="flame" tone="brand" />
        <x-stat label="Terminés ce mois" :value="$completedThisMonth" icon="check-circle" tone="success" />
    </div>

    {{-- Filtres --}}
    <div class="card p-4" x-data="{ filtersOpen: false }">
        {{-- Barre mobile : accès aux filtres et action principale --}}
        <div class="flex flex-wrap items-center justify-between gap-2 lg:hidden">
            <button
                type="button"
                @click="filtersOpen = ! filtersOpen"
                x-bind:aria-expanded="filtersOpen"
                aria-controls="installations-filters"
                class="btn-secondary btn-sm"
            >
                <x-icon name="filter" class="h-4 w-4" />
                Filtres
                @if ($hasFilters)
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                @endif
                <x-icon name="chevron-down" class="h-4 w-4 transition-transform duration-200" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>

            <a href="{{ route('installations.create') }}" wire:navigate class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                Nouvelle intervention
            </a>
        </div>

        <div
            id="installations-filters"
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
                        placeholder="Référence, intitulé ou client…"
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
                <label class="label" for="technician">Technicien</label>
                <select id="technician" wire:model.live="technicianId" class="input">
                    <option value="">Tous</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $installations->total() }} intervention(s)
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('installations.create') }}" wire:navigate class="btn-primary btn-sm hidden lg:inline-flex">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle intervention
                </a>
            </div>
        </div>
    </div>

    @if ($installations->isEmpty())
        <div class="card">
            <x-empty-state
                icon="wrench"
                title="Aucune intervention"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Planifiez un chantier depuis un devis accepté.'"
            >
                <a href="{{ route('installations.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle intervention
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($installations as $installation)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('installations.show', $installation) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $installation->title }}
                            </a>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $installation->reference }}</p>
                        </div>
                        <span class="badge-{{ $installation->status->color() }} shrink-0">{{ $installation->status->label() }}</span>
                    </div>

                    <div class="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                        <p class="flex items-center gap-1.5">
                            <x-icon name="user" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $installation->client?->name ?? 'Client supprimé' }}
                        </p>
                        <p class="flex items-center gap-1.5">
                            <x-icon name="calendar" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $installation->scheduled_at?->translatedFormat('d M Y à H:i') ?? 'Non planifié' }}
                        </p>
                        @if ($installation->technician)
                            <p class="flex items-center gap-1.5">
                                <x-icon name="briefcase" class="h-3.5 w-3.5 text-slate-400" />
                                {{ $installation->technician->name }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-end gap-1.5">
                        @if ($installation->status->value === 'planifie')
                            <button wire:click="start({{ $installation->id }})" class="btn-secondary btn-sm">
                                <x-icon name="flame" class="h-4 w-4" />
                                Démarrer
                            </button>
                        @endif
                        <a href="{{ route('installations.show', $installation) }}" wire:navigate class="btn-secondary btn-sm">Consulter</a>
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
                            <th>Intitulé</th>
                            <th>Client</th>
                            <th>Technicien</th>
                            <th>Planifié le</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($installations as $installation)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('installations.show', $installation) }}" wire:navigate class="font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $installation->reference }}
                                    </a>
                                </td>
                                <td class="font-medium text-slate-800 dark:text-slate-100">{{ $installation->title }}</td>
                                <td>
                                    @if ($installation->client)
                                        <a href="{{ route('clients.show', $installation->client) }}" wire:navigate class="text-slate-700 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                            {{ $installation->client->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $installation->technician?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ $installation->scheduled_at?->translatedFormat('d M Y à H:i') ?? '—' }}
                                </td>
                                <td><span class="badge-{{ $installation->status->color() }}">{{ $installation->status->label() }}</span></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($installation->status->value === 'planifie')
                                            <button wire:click="start({{ $installation->id }})" class="btn-icon hover:text-brand-600 dark:hover:text-brand-400" title="Démarrer">
                                                <x-icon name="flame" class="h-4 w-4" />
                                                <span class="sr-only">Démarrer</span>
                                            </button>
                                        @endif

                                        <a href="{{ route('installations.show', $installation) }}" wire:navigate class="btn-icon" title="Consulter">
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
            {{ $installations->links() }}
        </div>
    @endif
</div>
