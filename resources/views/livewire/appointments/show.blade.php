<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Fiche rendez-vous', 'subtitle' => 'Détail, suivi et actions'])] class extends Component
{
    public Appointment $appointment;

    public string $cancelledReason = '';

    public function mount(Appointment $appointment): void
    {
        $this->appointment = $appointment;

        Gate::authorize('view', $appointment);
    }

    public function setStatus(string $status): void
    {
        Gate::authorize('update', $this->appointment);

        $newStatus = AppointmentStatus::tryFrom($status);

        if (! $newStatus) {
            return;
        }

        $this->appointment->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === AppointmentStatus::Termine
                ? ($this->appointment->completed_at ?? now())
                : null,
        ]);

        session()->flash('status', 'Le statut a été mis à jour.');
    }

    public function cancel(): void
    {
        Gate::authorize('update', $this->appointment);

        $this->validate(
            ['cancelledReason' => ['required', 'string', 'max:255']],
            [],
            ['cancelledReason' => 'motif d\'annulation'],
        );

        $this->appointment->update([
            'status' => AppointmentStatus::Annule,
            'cancelled_reason' => $this->cancelledReason,
            'completed_at' => null,
        ]);

        $this->cancelledReason = '';

        session()->flash('status', 'Le rendez-vous a été annulé.');
    }

    public function destroy(): void
    {
        Gate::authorize('delete', $this->appointment);

        $this->appointment->delete();

        session()->flash('status', 'Rendez-vous supprimé.');

        $this->redirect(route('appointments.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-4">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('appointments.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Tous les rendez-vous
        </a>

        <div class="flex items-center gap-2">
            @can('update', $appointment)
                <a href="{{ route('appointments.edit', $appointment) }}" wire:navigate class="btn-secondary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Modifier
                </a>
            @endcan

            @if ($appointment->client?->phone)
                <a href="tel:{{ $appointment->client->phone }}" class="btn-primary btn-sm">
                    <x-icon name="phone" class="h-4 w-4" />
                    Appeler
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Colonne principale --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <div class="card-body space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ $appointment->reference }}
                            </p>
                            <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">
                                {{ $appointment->client?->name ?? 'Client supprimé' }}
                            </h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$appointment->status" />
                            <x-type-chip :type="$appointment->type" />
                            <span class="badge-{{ $appointment->priority->color() }}">{{ $appointment->priority->label() }}</span>
                        </div>
                    </div>

                    @if ($appointment->isOverdue())
                        <p class="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                            <x-icon name="alert" class="h-4 w-4 shrink-0" />
                            Ce rendez-vous est en retard.
                        </p>
                    @endif

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Date et heure</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ $appointment->longDateLabel() }}
                            </dd>
                            <dd class="text-sm text-slate-600 dark:text-slate-300">
                                {{ $appointment->timeRange() }} · {{ $appointment->durationLabel() }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Techniciens</dt>
                            <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100">
                                {{ $appointment->technicianLabel() }}
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Lieu d'intervention</dt>
                            <dd class="mt-1 flex items-start gap-1.5 text-sm text-slate-800 dark:text-slate-100">
                                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                <span>
                                    {{ $appointment->siteAddress() }}
                                    @if ($appointment->siteLocation())
                                        <span class="block text-slate-500 dark:text-slate-400">{{ $appointment->siteLocation() }}</span>
                                    @endif
                                </span>
                            </dd>
                        </div>
                    </dl>

                    @if ($appointment->description)
                        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Demande du client</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $appointment->description }}</dd>
                        </div>
                    @endif

                    @if ($appointment->cancelled_reason)
                        <div class="rounded-lg bg-rose-50 px-3 py-2 dark:bg-rose-950/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Motif d'annulation</p>
                            <p class="mt-0.5 text-sm text-rose-800 dark:text-rose-200">{{ $appointment->cancelled_reason }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if ($appointment->internal_notes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Notes internes</h3>
                    </div>
                    <div class="card-body">
                        <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $appointment->internal_notes }}</p>
                    </div>
                </div>
            @endif

            {{-- Devis liés --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Devis liés</h3>
                    <a href="{{ route('quotes.create', ['client' => $appointment->client_id, 'appointment' => $appointment->id]) }}" wire:navigate class="btn-secondary btn-sm">
                        <x-icon name="plus" class="h-4 w-4" />
                        Nouveau devis
                    </a>
                </div>
                @if ($appointment->quotes->isEmpty())
                    <div class="card-body">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucun devis pour ce rendez-vous.</p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($appointment->quotes as $quote)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5">
                                <div>
                                    <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $quote->number }}
                                    </a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $quote->issued_at->translatedFormat('d M Y') }} · {{ Money::format($quote->total) }}
                                    </p>
                                </div>
                                <span class="badge-{{ $quote->status->color() }}">{{ $quote->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Interventions liées --}}
            @if ($appointment->installations->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Interventions liées</h3>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($appointment->installations as $installation)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5">
                                <div>
                                    <a href="{{ route('installations.show', $installation) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $installation->reference }}
                                    </a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $installation->title }}</p>
                                </div>
                                <span class="badge-{{ $installation->status->color() }}">{{ $installation->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Colonne latérale --}}
        <div class="space-y-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Client</h3>
                </div>
                <div class="card-body space-y-2">
                    @if ($appointment->client)
                        <a href="{{ route('clients.show', $appointment->client) }}" wire:navigate class="block text-sm font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                            {{ $appointment->client->name }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $appointment->client->type->label() }}</p>
                        @if ($appointment->client->phone)
                            <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                                {{ $appointment->client->phone }}
                            </p>
                        @endif
                        @if ($appointment->client->email)
                            <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <x-icon name="mail" class="h-4 w-4 text-slate-400" />
                                {{ $appointment->client->email }}
                            </p>
                        @endif
                        @if ($appointment->client->location())
                            <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <x-icon name="map-pin" class="h-4 w-4 text-slate-400" />
                                {{ $appointment->client->location() }}
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">Client supprimé.</p>
                    @endif
                </div>
            </div>

            @can('update', $appointment)
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Actions</h3>
                    </div>
                    <div class="card-body space-y-3">
                        <div class="grid gap-2">
                            @if ($appointment->status->value === 'planifie')
                                <button wire:click="setStatus('confirme')" class="btn-secondary justify-start">
                                    <x-icon name="check-circle" class="h-4 w-4" />
                                    Confirmer le rendez-vous
                                </button>
                            @endif

                            @if (in_array($appointment->status->value, ['planifie', 'confirme'], true))
                                <button wire:click="setStatus('en_cours')" class="btn-secondary justify-start">
                                    <x-icon name="hourglass" class="h-4 w-4" />
                                    Démarrer l'intervention
                                </button>
                            @endif

                            @if ($appointment->status->isOpen())
                                <button wire:click="setStatus('termine')" class="btn-primary justify-start">
                                    <x-icon name="check" class="h-4 w-4" />
                                    Marquer comme terminé
                                </button>
                                <button wire:click="setStatus('reporte')" class="btn-ghost justify-start">
                                    <x-icon name="refresh" class="h-4 w-4" />
                                    Reporter
                                </button>
                                <button wire:click="setStatus('absent')" class="btn-ghost justify-start">
                                    <x-icon name="alert" class="h-4 w-4" />
                                    Client absent
                                </button>
                            @endif

                            @if ($appointment->status->isClosed())
                                <button wire:click="setStatus('planifie')" class="btn-ghost justify-start">
                                    <x-icon name="refresh" class="h-4 w-4" />
                                    Réouvrir le rendez-vous
                                </button>
                            @endif
                        </div>

                        @if ($appointment->status->isOpen())
                            <div x-data="{ open: false }" class="border-t border-slate-100 pt-3 dark:border-slate-800">
                                <button type="button" x-on:click="open = ! open" class="btn-ghost btn-sm justify-start text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50">
                                    <x-icon name="x" class="h-4 w-4" />
                                    Annuler le rendez-vous
                                </button>

                                <div x-show="open" x-cloak class="mt-3 space-y-2">
                                    <textarea rows="2" wire:model="cancelledReason" placeholder="Motif de l'annulation…" class="input"></textarea>
                                    @error('cancelledReason') <p class="error">{{ $message }}</p> @enderror
                                    <button wire:click="cancel" class="btn-danger btn-sm w-full justify-center">
                                        Confirmer l'annulation
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-body space-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <p>Créé par {{ $appointment->creator?->name ?? '—' }} le {{ $appointment->created_at->translatedFormat('d M Y à H:i') }}</p>
                    @if ($appointment->completed_at)
                        <p>Clôturé le {{ $appointment->completed_at->translatedFormat('d M Y à H:i') }}</p>
                    @endif
                </div>
            </div>

            @can('delete', $appointment)
                <div class="card">
                    <div class="card-body">
                        <button
                            wire:click="destroy"
                            wire:confirm="Supprimer définitivement ce rendez-vous ?"
                            class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
                        >
                            <x-icon name="trash" class="h-4 w-4" />
                            Supprimer le rendez-vous
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
