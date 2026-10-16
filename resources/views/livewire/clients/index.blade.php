<?php

use App\Enums\ClientType;
use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Clients', 'subtitle' => 'Votre portefeuille clients'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'wilaya', except: '')]
    public string $wilaya = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'wilaya');
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $clients = Client::query()
            ->withCount(['appointments', 'quotes', 'installations'])
            ->search($this->search)
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->wilaya !== '', fn ($query) => $query->where('wilaya', $this->wilaya))
            ->ordered()
            ->paginate(15);

        return [
            'clients' => $clients,
            'wilayas' => config('algeria.wilayas'),
            'hasFilters' => collect([$this->search, $this->type, $this->wilaya])->contains(fn ($value) => $value !== ''),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="label" for="search">Recherche</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Nom, téléphone, email…"
                        class="input pl-9"
                    >
                </div>
            </div>

            <div>
                <label class="label" for="type">Type</label>
                <select id="type" wire:model.live="type" class="input">
                    <option value="">Tous</option>
                    @foreach (ClientType::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="wilaya">Wilaya</label>
                <select id="wilaya" wire:model.live="wilaya" class="input">
                    <option value="">Toutes</option>
                    @foreach ($wilayas as $item)
                        <option value="{{ $item }}">{{ $item }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $clients->total() }} client(s) trouvé(s)
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('clients.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau client
                </a>
            </div>
        </div>
    </div>

    @if ($clients->isEmpty())
        <div class="card">
            <x-empty-state
                icon="users"
                title="Aucun client"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Ajoutez votre premier client pour commencer.'"
            >
                <a href="{{ route('clients.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau client
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($clients as $client)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('clients.show', $client) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $client->name }}
                            </a>
                            @if ($client->email)
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $client->email }}</p>
                            @endif
                        </div>
                        <span class="badge-{{ $client->type->color() }} shrink-0">{{ $client->type->label() }}</span>
                    </div>

                    <div class="mt-3 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <p class="flex items-center gap-1.5">
                            <x-icon name="phone" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $client->phone }}
                        </p>
                        @if ($client->location())
                            <p class="flex items-center gap-1.5">
                                <x-icon name="map-pin" class="h-3.5 w-3.5 text-slate-400" />
                                {{ $client->location() }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <span class="badge-neutral">{{ $client->appointments_count }} RDV</span>
                        <span class="badge-neutral">{{ $client->quotes_count }} devis</span>
                        <div class="ms-auto flex items-center gap-1.5">
                            <a href="tel:{{ $client->phone }}" class="btn-icon" title="Appeler">
                                <x-icon name="phone" class="h-4 w-4" />
                            </a>
                            <a href="{{ route('clients.show', $client) }}" wire:navigate class="btn-secondary btn-sm">Fiche</a>
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
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Localisation</th>
                            <th class="text-center">RDV</th>
                            <th class="text-center">Devis</th>
                            <th class="text-center">Chantiers</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr>
                                <td>
                                    <a href="{{ route('clients.show', $client) }}" wire:navigate class="font-semibold text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                        {{ $client->name }}
                                    </a>
                                    <span class="badge-{{ $client->type->color() }} ms-1">{{ $client->type->label() }}</span>
                                </td>
                                <td>
                                    <span class="block text-slate-700 dark:text-slate-300">{{ $client->phone }}</span>
                                    @if ($client->email)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $client->email }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-600 dark:text-slate-300">{{ $client->location() ?: '—' }}</td>
                                <td class="text-center">{{ $client->appointments_count }}</td>
                                <td class="text-center">{{ $client->quotes_count }}</td>
                                <td class="text-center">{{ $client->installations_count }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="tel:{{ $client->phone }}" class="btn-icon" title="Appeler">
                                            <x-icon name="phone" class="h-4 w-4" />
                                            <span class="sr-only">Appeler</span>
                                        </a>
                                        <a href="{{ route('appointments.create', ['client' => $client->id]) }}" wire:navigate class="btn-icon" title="Planifier un rendez-vous">
                                            <x-icon name="calendar-plus" class="h-4 w-4" />
                                            <span class="sr-only">Planifier un rendez-vous</span>
                                        </a>
                                        <a href="{{ route('clients.show', $client) }}" wire:navigate class="btn-icon" title="Consulter">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            <span class="sr-only">Consulter</span>
                                        </a>
                                        <a href="{{ route('clients.edit', $client) }}" wire:navigate class="btn-icon" title="Modifier">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                            <span class="sr-only">Modifier</span>
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
            {{ $clients->links() }}
        </div>
    @endif
</div>
