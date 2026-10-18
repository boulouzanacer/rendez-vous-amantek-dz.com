<?php

use App\Enums\AuthorizationStatus;
use App\Models\Authorization;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Autorisation', 'subtitle' => 'Suivi du dossier'])] class extends Component
{
    public Authorization $authorization;

    public function mount(Authorization $authorization): void
    {
        $this->authorization = $authorization;

        Gate::authorize('view', $authorization);
    }

    public function updateStatus(string $status): void
    {
        Gate::authorize('update', $this->authorization);

        $newStatus = AuthorizationStatus::tryFrom($status);

        if (! $newStatus) {
            return;
        }

        $this->authorization->update(['status' => $newStatus]);

        session()->flash('status', 'Statut du dossier mis à jour.');
    }

    public function destroy(): void
    {
        Gate::authorize('delete', $this->authorization);

        $this->authorization->delete();

        session()->flash('status', 'Dossier d\'autorisation supprimé.');

        $this->redirect(route('authorizations.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'statuses' => AuthorizationStatus::options(),
            'insideList' => $this->authorization->insideList(),
            'outsideList' => $this->authorization->outsideList(),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-4">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('authorizations.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Toutes les autorisations
        </a>

        @can('update', $authorization)
            <a href="{{ route('authorizations.edit', $authorization) }}" wire:navigate class="btn-secondary btn-sm">
                <x-icon name="pencil" class="h-4 w-4" />
                Modifier
            </a>
        @endcan
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Dossier --}}
            <div class="card">
                <div class="card-body space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $authorization->reference }}</p>
                            <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $authorization->client?->name ?? 'Client supprimé' }}</h2>
                            @if ($authorization->location())
                                <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                                    <x-icon name="map-pin" class="h-4 w-4 text-slate-400" />
                                    {{ $authorization->location() }}
                                </p>
                            @endif
                        </div>
                        <span class="badge-{{ $authorization->status->color() }}">{{ $authorization->status->label() }}</span>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Créé le</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $authorization->created_at->translatedFormat('d F Y') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total caméras</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $authorization->cameraCount() }}</dd>
                        </div>
                    </dl>

                    @if ($authorization->address)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Adresse</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $authorization->address }}</p>
                        </div>
                    @endif

                    @if ($authorization->notes)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400">{{ $authorization->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Caméras intérieures --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Caméras intérieures</h3>
                    <span class="badge-neutral">{{ collect($insideList)->sum('quantity') }} caméra(s)</span>
                </div>
                @if ($insideList === [])
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucune caméra intérieure déclarée.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th class="text-center">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($insideList as $item)
                                    <tr>
                                        <td class="font-medium text-slate-800 dark:text-slate-100">{{ $item['reference'] }}</td>
                                        <td class="text-center text-slate-600 dark:text-slate-300">{{ $item['quantity'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Caméras extérieures --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Caméras extérieures</h3>
                    <span class="badge-neutral">{{ collect($outsideList)->sum('quantity') }} caméra(s)</span>
                </div>
                @if ($outsideList === [])
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucune caméra extérieure déclarée.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th class="text-center">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($outsideList as $item)
                                    <tr>
                                        <td class="font-medium text-slate-800 dark:text-slate-100">{{ $item['reference'] }}</td>
                                        <td class="text-center text-slate-600 dark:text-slate-300">{{ $item['quantity'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="space-y-4">
            <div class="grid gap-3">
                <x-stat label="Caméras déclarées" :value="$authorization->cameraCount()" icon="camera" tone="brand" />
                <x-stat label="Intérieur / Extérieur" :value="$authorization->cameraSummary()" icon="shield" tone="info" />
            </div>

            @if ($authorization->client)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Client</h3>
                    </div>
                    <div class="card-body space-y-2">
                        <a href="{{ route('clients.show', $authorization->client) }}" wire:navigate class="block text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                            {{ $authorization->client->name }}
                        </a>
                        @if ($authorization->client->phone)
                            <a href="tel:{{ $authorization->client->phone }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400">
                                <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                                {{ $authorization->client->phone }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @can('update', $authorization)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Statut du dossier</h3>
                    </div>
                    <div class="card-body">
                        <select wire:change="updateStatus($event.target.value)" class="input">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($authorization->status->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Le statut évolue au fil des dépôts et des décisions.
                        </p>
                    </div>
                </div>
            @endcan

            @can('delete', $authorization)
                <div class="card">
                    <div class="card-body">
                        <button
                            wire:click="destroy"
                            wire:confirm="Supprimer ce dossier d'autorisation ?"
                            class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                        >
                            <x-icon name="trash" class="h-4 w-4" />
                            Supprimer le dossier
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
