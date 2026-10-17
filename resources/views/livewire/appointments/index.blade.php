<?php

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app', ['title' => 'Rendez-vous', 'subtitle' => 'Tous vos rendez-vous clients'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

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
        $this->reset('search', 'status', 'type', 'period', 'technicianId');
        $this->resetPage();
    }

    public function complete(int $appointmentId): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        Gate::authorize('update', $appointment);

        $appointment->update([
            'status' => AppointmentStatus::Termine,
            'completed_at' => now(),
        ]);
    }

    public function destroy(int $appointmentId): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        Gate::authorize('delete', $appointment);

        $appointment->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = Appointment::query()
            ->with(['client', 'technician', 'technicians'])
            ->search($this->search)
            ->withStatus($this->status)
            ->withType($this->type)
            ->forTechnician($this->technicianId !== '' ? (int) $this->technicianId : null);

        match ($this->period) {
            'aujourdhui' => $query->onDay(now()),
            'semaine' => $query->between(
                now()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                now()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ),
            'avenir' => $query->where('scheduled_at', '>=', now()),
            'retard' => $query->open()->where('scheduled_at', '<', now()),
            'passes' => $query->where('scheduled_at', '<', now()->startOfDay()),
            default => null,
        };

        $appointments = $query
            ->orderByRaw('CASE WHEN scheduled_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('scheduled_at')
            ->paginate(15);

        return [
            'appointments' => $appointments,
            'technicians' => User::query()->technicians()->orderBy('name')->get(),
            'hasFilters' => collect([$this->search, $this->status, $this->type, $this->period, $this->technicianId])->contains(fn ($value) => $value !== ''),
        ];
    }
}; ?>

<div class="space-y-4">
    {{-- Filtres --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="label" for="search">Recherche</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Référence, client, téléphone…"
                        class="input pl-9"
                    >
                </div>
            </div>

            <div>
                <label class="label" for="period">Période</label>
                <select id="period" wire:model.live="period" class="input">
                    <option value="">Toutes</option>
                    <option value="aujourdhui">Aujourd'hui</option>
                    <option value="semaine">Cette semaine</option>
                    <option value="avenir">À venir</option>
                    <option value="retard">En retard</option>
                    <option value="passes">Passés</option>
                </select>
            </div>

            <div>
                <label class="label" for="status">Statut</label>
                <select id="status" wire:model.live="status" class="input">
                    <option value="">Tous</option>
                    @foreach (AppointmentStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="type">Type</label>
                <select id="type" wire:model.live="type" class="input">
                    <option value="">Tous</option>
                    @foreach (AppointmentType::options() as $value => $label)
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
                {{ $appointments->total() }} rendez-vous trouvé(s)
            </p>

            <div class="flex items-center gap-2">
                @if ($hasFilters)
                    <button type="button" wire:click="resetFilters" class="btn-ghost btn-sm">
                        <x-icon name="x" class="h-4 w-4" />
                        Réinitialiser
                    </button>
                @endif

                <a href="{{ route('appointments.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau rendez-vous
                </a>
            </div>
        </div>
    </div>

    @if ($appointments->isEmpty())
        <div class="card">
            <x-empty-state
                icon="calendar-check"
                title="Aucun rendez-vous"
                :description="$hasFilters ? 'Aucun résultat pour ces filtres.' : 'Commencez par planifier votre premier rendez-vous.'"
            >
                <a href="{{ route('appointments.create') }}" wire:navigate class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau rendez-vous
                </a>
            </x-empty-state>
        </div>
    @else
        {{-- Liste mobile --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($appointments as $appointment)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a
                                href="{{ route('appointments.show', $appointment) }}"
                                wire:navigate
                                class="block truncate text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ $appointment->client?->name ?? 'Client supprimé' }}
                            </a>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $appointment->reference }}</p>
                        </div>
                        <x-status-badge :status="$appointment->status" class="shrink-0" />
                    </div>

                    <div class="mt-3 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <p class="flex items-center gap-1.5">
                            <x-icon name="clock" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $appointment->dateLabel() }} · {{ $appointment->timeRange() }} · {{ $appointment->durationLabel() }}
                        </p>
                        <p class="flex items-center gap-1.5">
                            <x-icon name="map-pin" class="h-3.5 w-3.5 text-slate-400" />
                            {{ $appointment->siteAddress() }}
                        </p>
                        @if ($appointment->technicianLabel() !== 'Non assigné')
                            <p class="flex items-center gap-1.5">
                                <x-icon name="user" class="h-3.5 w-3.5 text-slate-400" />
                                {{ $appointment->technicianLabel() }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-type-chip :type="$appointment->type" />

                        <div class="ms-auto flex items-center gap-1.5">
                            @if ($appointment->client?->phone)
                                <a href="tel:{{ $appointment->client->phone }}" class="btn-secondary btn-sm">
                                    <x-icon name="phone" class="h-4 w-4" />
                                    Appeler
                                </a>
                            @endif
                            @if ($appointment->status->isOpen())
                                <button wire:click="complete({{ $appointment->id }})" class="btn-primary btn-sm">
                                    <x-icon name="check" class="h-4 w-4" />
                                    Terminer
                                </button>
                            @endif
                            <a href="{{ route('appointments.edit', $appointment) }}" wire:navigate class="btn-icon" title="Modifier">
                                <x-icon name="pencil" class="h-4 w-4" />
                            </a>
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
                            <th>Référence</th>
                            <th>Date et heure</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Technicien</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('appointments.show', $appointment) }}" wire:navigate class="font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $appointment->reference }}
                                    </a>
                                    @if ($appointment->priority->weight() >= 3)
                                        <span class="badge-{{ $appointment->priority->color() }} ms-1">{{ $appointment->priority->label() }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="block font-medium text-slate-800 dark:text-slate-100">
                                        {{ $appointment->dateLabel() }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $appointment->timeRange() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($appointment->client)
                                        <a href="{{ route('clients.show', $appointment->client) }}" wire:navigate class="font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                            {{ $appointment->client->name }}
                                        </a>
                                        @if ($appointment->client->phone)
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $appointment->client->phone }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td><x-type-chip :type="$appointment->type" /></td>
                                <td class="whitespace-nowrap">{{ $appointment->technicianLabel() }}</td>
                                <td><x-status-badge :status="$appointment->status" /></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($appointment->status->isOpen())
                                            <button wire:click="complete({{ $appointment->id }})" class="btn-icon" title="Marquer comme terminé">
                                                <x-icon name="check" class="h-4 w-4" />
                                                <span class="sr-only">Marquer comme terminé</span>
                                            </button>
                                        @endif

                                        <a href="{{ route('appointments.edit', $appointment) }}" wire:navigate class="btn-icon" title="Modifier">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                            <span class="sr-only">Modifier</span>
                                        </a>

                                        <a href="{{ route('appointments.show', $appointment) }}" wire:navigate class="btn-icon" title="Consulter">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            <span class="sr-only">Consulter</span>
                                        </a>

                                        @can('delete', $appointment)
                                            <button
                                                wire:click="destroy({{ $appointment->id }})"
                                                wire:confirm="Supprimer définitivement ce rendez-vous ?"
                                                class="btn-icon hover:text-rose-600 dark:hover:text-rose-400"
                                                title="Supprimer"
                                            >
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
            {{ $appointments->links() }}
        </div>
    @endif
</div>
