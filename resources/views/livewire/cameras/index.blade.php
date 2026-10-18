<?php

use App\Models\Camera;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Caméras', 'subtitle' => 'Catalogue des références'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $reference = '';

    public string $brand = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', Camera::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function edit(int $id): void
    {
        $camera = Camera::query()->findOrFail($id);

        Gate::authorize('update', $camera);

        $this->editingId = $camera->id;
        $this->reference = (string) $camera->reference;
        $this->brand = (string) $camera->brand;
        $this->isActive = (bool) $camera->is_active;

        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'reference', 'brand');
        $this->isActive = true;

        $this->resetErrorBag();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $camera = Camera::query()->findOrFail($this->editingId);

            Gate::authorize('update', $camera);
        } else {
            Gate::authorize('create', Camera::class);

            $camera = null;
        }

        $validated = $this->validate([
            'reference' => ['required', 'string', 'max:255', Rule::unique('cameras', 'reference')->ignore($this->editingId)],
            'brand' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ], [], [
            'reference' => 'référence',
            'brand' => 'marque',
            'isActive' => 'disponibilité',
        ]);

        $payload = [
            'reference' => $validated['reference'],
            'brand' => $validated['brand'] ?: null,
            'is_active' => $this->isActive,
        ];

        if ($camera) {
            $camera->update($payload);

            session()->flash('status', 'Référence mise à jour.');
        } else {
            Camera::create($payload);

            session()->flash('status', 'Référence ajoutée au catalogue.');
        }

        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $camera = Camera::query()->findOrFail($id);

        Gate::authorize('update', $camera);

        $camera->update(['is_active' => ! $camera->is_active]);

        session()->flash('status', 'Disponibilité mise à jour.');
    }

    public function destroy(int $id): void
    {
        $camera = Camera::query()->findOrFail($id);

        Gate::authorize('delete', $camera);

        $camera->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', 'Référence supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'cameras' => Camera::query()
                ->search($this->search)
                ->ordered()
                ->paginate(20),
            'canManage' => Gate::allows('create', Camera::class),
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('settings') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Paramètres
        </a>
    </div>

    @can('create', App\Models\Camera::class)
        {{-- Ajout / modification --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">{{ $editingId ? 'Modifier une référence' : 'Ajouter une référence' }}</h3>
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Annuler
                    </button>
                @endif
            </div>
            <form wire:submit="save" class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="reference">Référence</label>
                    <input id="reference" type="text" wire:model="reference" placeholder="Ex. DS-2CD1043G0-I" class="input">
                    @error('reference') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="brand">Marque</label>
                    <input id="brand" type="text" wire:model="brand" placeholder="Ex. Hikvision" class="input">
                    @error('brand') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2 sm:col-span-2">
                    <input id="isActive" type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                    <label for="isActive" class="text-sm text-slate-700 dark:text-slate-300">Disponible à la sélection</label>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary justify-center sm:justify-start">
                        <x-icon name="check" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Enregistrer' : 'Ajouter' }}</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </button>
                </div>
            </form>
        </div>
    @endcan

    {{-- Recherche --}}
    <div class="card p-4">
        <label class="label" for="search">Recherche</label>
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Référence ou marque…" class="input pl-9">
        </div>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $cameras->total() }} référence(s)</p>
    </div>

    @if ($cameras->isEmpty())
        <div class="card">
            <x-empty-state
                icon="camera"
                title="Aucune référence"
                :description="$search !== '' ? 'Aucun résultat pour cette recherche.' : 'Ajoutez les références de caméras pour les sélectionner dans les dossiers d\'autorisation.'"
            />
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($cameras as $camera)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $camera->reference }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $camera->brand ?: '—' }}</p>
                        </div>
                        <span class="badge-{{ $camera->is_active ? 'success' : 'neutral' }} shrink-0">
                            {{ $camera->is_active ? 'Disponible' : 'Retirée' }}
                        </span>
                    </div>

                    @can('update', $camera)
                        <div class="mt-3 flex flex-wrap items-center justify-end gap-1.5">
                            <button wire:click="edit({{ $camera->id }})" class="btn-secondary btn-sm">
                                <x-icon name="pencil" class="h-4 w-4" />
                                Modifier
                            </button>
                            <button wire:click="toggleActive({{ $camera->id }})" class="btn-secondary btn-sm">
                                <x-icon name="refresh" class="h-4 w-4" />
                                {{ $camera->is_active ? 'Retirer' : 'Activer' }}
                            </button>
                            @can('delete', $camera)
                                <button
                                    wire:click="destroy({{ $camera->id }})"
                                    wire:confirm="Supprimer cette référence ?"
                                    class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                                >
                                    <x-icon name="trash" class="h-4 w-4" />
                                    Supprimer
                                </button>
                            @endcan
                        </div>
                    @endcan
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
                            <th>Marque</th>
                            <th>Disponibilité</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cameras as $camera)
                            <tr>
                                <td class="font-semibold text-slate-800 dark:text-slate-100">{{ $camera->reference }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ $camera->brand ?: '—' }}</td>
                                <td>
                                    <span class="badge-{{ $camera->is_active ? 'success' : 'neutral' }}">
                                        {{ $camera->is_active ? 'Disponible' : 'Retirée' }}
                                    </span>
                                </td>
                                <td>
                                    @can('update', $camera)
                                        <div class="flex items-center justify-end gap-1">
                                            <button wire:click="edit({{ $camera->id }})" class="btn-icon" title="Modifier">
                                                <x-icon name="pencil" class="h-4 w-4" />
                                                <span class="sr-only">Modifier</span>
                                            </button>
                                            <button wire:click="toggleActive({{ $camera->id }})" class="btn-icon" title="{{ $camera->is_active ? 'Retirer' : 'Activer' }}">
                                                <x-icon name="refresh" class="h-4 w-4" />
                                                <span class="sr-only">{{ $camera->is_active ? 'Retirer' : 'Activer' }}</span>
                                            </button>
                                            @can('delete', $camera)
                                                <button
                                                    wire:click="destroy({{ $camera->id }})"
                                                    wire:confirm="Supprimer cette référence ?"
                                                    class="btn-icon hover:text-rose-600 dark:hover:text-rose-400"
                                                    title="Supprimer"
                                                >
                                                    <x-icon name="trash" class="h-4 w-4" />
                                                    <span class="sr-only">Supprimer</span>
                                                </button>
                                            @endcan
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pt-1">
            {{ $cameras->links() }}
        </div>
    @endif
</div>
