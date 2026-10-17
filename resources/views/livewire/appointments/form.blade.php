<?php

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\Priority;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Rendez-vous', 'subtitle' => 'Planifier une visite, un devis ou une installation'])] class extends Component
{
    public ?Appointment $appointment = null;

    public string $clientId = '';

    public string $type = 'visite';

    public string $status = 'planifie';

    public string $priority = 'normale';

    /** @var array<int, string> */
    public array $technicianIds = [];

    public bool $technicianPending = false;

    public string $scheduledDate = '';

    public string $scheduledTime = '09:00';

    public int $durationMinutes = 60;

    public bool $timeNotFixed = false;

    public bool $openEnded = false;

    public string $address = '';

    public string $commune = '';

    public string $wilaya = '';

    public string $description = '';

    public string $internalNotes = '';

    public string $cancelledReason = '';

    public function mount(): void
    {
        if ($this->appointment) {
            Gate::authorize('update', $this->appointment);

            $this->fillFromAppointment();

            return;
        }

        Gate::authorize('create', Appointment::class);

        $this->scheduledDate = $this->queryDate() ?? now()->toDateString();
        $this->scheduledTime = now()->addHour()->format('H:00');

        $client = request()->query('client');

        if (is_scalar($client) && $client !== '') {
            $this->clientId = (string) $client;
        }
    }

    private function queryDate(): ?string
    {
        $date = request()->query('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return null;
    }

    private function fillFromAppointment(): void
    {
        $appointment = $this->appointment;

        $this->clientId = (string) $appointment->client_id;
        $this->type = $appointment->type->value;
        $this->status = $appointment->status->value;
        $this->priority = $appointment->priority->value;
        $this->technicianIds = $appointment->technicians
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
        $this->technicianPending = (bool) $appointment->technician_pending;
        $this->timeNotFixed = (bool) $appointment->time_not_fixed;
        $this->openEnded = (bool) $appointment->open_ended;
        $this->scheduledDate = $appointment->scheduled_at?->toDateString() ?? '';
        $this->scheduledTime = $appointment->scheduled_at?->format('H:i') ?? '09:00';
        $this->durationMinutes = (int) $appointment->duration_minutes;
        $this->address = (string) $appointment->address;
        $this->commune = (string) $appointment->commune;
        $this->wilaya = (string) $appointment->wilaya;
        $this->description = (string) $appointment->description;
        $this->internalNotes = (string) $appointment->internal_notes;
        $this->cancelledReason = (string) $appointment->cancelled_reason;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'technicianIds' => ['array'],
            'technicianIds.*' => ['integer', 'exists:users,id'],
            'scheduledDate' => [
                Rule::requiredIf(fn (): bool => ! $this->timeNotFixed),
                'nullable',
                'date_format:Y-m-d',
            ],
            'scheduledTime' => [
                Rule::requiredIf(fn (): bool => ! $this->timeNotFixed),
                'nullable',
                'date_format:H:i',
            ],
            'durationMinutes' => [
                Rule::requiredIf(fn (): bool => ! $this->timeNotFixed),
                'nullable',
                'integer',
                'min:15',
                'max:1440',
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'wilaya' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'internalNotes' => ['nullable', 'string', 'max:2000'],
            'cancelledReason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'clientId' => 'client',
            'technicianIds' => 'technicien',
            'technicianIds.*' => 'technicien',
            'scheduledDate' => 'date',
            'scheduledTime' => 'heure',
            'durationMinutes' => 'durée',
            'internalNotes' => 'notes internes',
            'cancelledReason' => 'motif d\'annulation',
        ];
    }

    public function save(): void
    {
        $isEdit = $this->appointment !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->appointment : Appointment::class);

        $validated = $this->validate();

        $status = AppointmentStatus::from($validated['status']);

        $technicianIds = $this->technicianPending
            ? []
            : array_values(array_unique(array_map('intval', $validated['technicianIds'] ?? [])));

        $scheduledAt = (! $this->timeNotFixed && filled($validated['scheduledDate']) && filled($validated['scheduledTime']))
            ? Carbon::createFromFormat('Y-m-d H:i', $validated['scheduledDate'].' '.$validated['scheduledTime'])
            : null;

        $durationMinutes = (! $this->timeNotFixed && ! $this->openEnded)
            ? (int) $validated['durationMinutes']
            : ($this->appointment?->duration_minutes ?: 60);

        $payload = [
            'client_id' => (int) $validated['clientId'],
            'type' => $validated['type'],
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'user_id' => $technicianIds[0] ?? null,
            'technician_pending' => $this->technicianPending,
            'time_not_fixed' => $this->timeNotFixed,
            'open_ended' => $this->openEnded,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'address' => $validated['address'] ?: null,
            'commune' => $validated['commune'] ?: null,
            'wilaya' => $validated['wilaya'] ?: null,
            'description' => $validated['description'] ?: null,
            'internal_notes' => $validated['internalNotes'] ?: null,
            'cancelled_reason' => $status === AppointmentStatus::Annule
                ? ($validated['cancelledReason'] ?: null)
                : null,
            'completed_at' => $status === AppointmentStatus::Termine
                ? ($this->appointment?->completed_at ?? now())
                : null,
        ];

        if ($isEdit) {
            $this->appointment->update($payload);
            $appointment = $this->appointment;
        } else {
            $payload['created_by'] = auth()->id();
            $appointment = Appointment::create($payload);
        }

        $appointment->technicians()->sync($technicianIds);

        session()->flash('status', $isEdit ? 'Rendez-vous mis à jour.' : 'Rendez-vous créé.');

        $this->redirect(route('appointments.show', $appointment), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'clients' => Client::query()->ordered()->get(['id', 'name', 'phone', 'commune', 'wilaya']),
            'technicians' => User::query()->technicians()->orderBy('name')->get(['id', 'name']),
            'wilayas' => config('algeria.wilayas'),
            'communes' => config('algeria.communes')[$this->wilaya] ?? [],
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ $appointment ? route('appointments.show', $appointment) : route('appointments.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $appointment ? 'Modifier '.$appointment->reference : 'Nouveau rendez-vous' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        {{-- Informations principales --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Informations principales</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="client">Client</label>
                    <select id="client" wire:model="clientId" class="input">
                        <option value="">— Sélectionner un client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">
                                {{ $client->name }}@if ($client->phone) — {{ $client->phone }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('clientId') <p class="error">{{ $message }}</p> @enderror
                    <p class="hint">
                        Le client n'existe pas encore ?
                        <a href="{{ route('clients.create') }}" wire:navigate class="font-medium text-brand-600 hover:underline dark:text-brand-400">Créer une fiche client</a>
                    </p>
                </div>

                <div>
                    <label class="label" for="type">Type de prestation</label>
                    <select id="type" wire:model="type" class="input">
                        @foreach (AppointmentType::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="priority">Priorité</label>
                    <select id="priority" wire:model="priority" class="input">
                        @foreach (Priority::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Planification --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Planification</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2" x-data>
                <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
                    <label class="flex items-center gap-2.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="timeNotFixed" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        Heure pas encore fixée
                    </label>
                    <p class="mt-1 ps-6 text-xs text-slate-500 dark:text-slate-400">
                        La date, l'heure et la durée restent à définir avec le client.
                    </p>
                </div>

                <div class="transition-opacity" :class="{ 'opacity-50': $wire.timeNotFixed }">
                    <label class="label" for="scheduledDate">Date</label>
                    <input id="scheduledDate" type="date" wire:model="scheduledDate" class="input" :disabled="$wire.timeNotFixed">
                    @error('scheduledDate') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="transition-opacity" :class="{ 'opacity-50': $wire.timeNotFixed }">
                    <label class="label" for="scheduledTime">Heure</label>
                    <input id="scheduledTime" type="time" wire:model="scheduledTime" class="input" :disabled="$wire.timeNotFixed">
                    @error('scheduledTime') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2 transition-opacity" :class="{ 'opacity-50': $wire.timeNotFixed || $wire.openEnded }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="label mb-0" for="duration">Durée (minutes)</label>
                        <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="openEnded" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                            Ouvert
                        </label>
                    </div>
                    <input id="duration" type="number" min="15" max="1440" step="15" wire:model="durationMinutes" class="input mt-1" :disabled="$wire.timeNotFixed || $wire.openEnded">
                    @error('durationMinutes') <p class="error">{{ $message }}</p> @enderror
                </div>

                {{-- Techniciens assignés (multi-sélection) --}}
                <div class="sm:col-span-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="label mb-0">Techniciens assignés</span>
                        <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="technicianPending" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                            Pas encore décidé
                        </label>
                    </div>
                    <div class="mt-2 grid gap-2 transition-opacity sm:grid-cols-2" :class="{ 'opacity-50': $wire.technicianPending }">
                        @forelse ($technicians as $technician)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                <input
                                    type="checkbox"
                                    value="{{ $technician->id }}"
                                    wire:model="technicianIds"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900"
                                    :disabled="$wire.technicianPending"
                                >
                                <span>{{ $technician->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">Aucun technicien disponible.</p>
                        @endforelse
                    </div>
                    @error('technicianIds') <p class="error">{{ $message }}</p> @enderror
                    @error('technicianIds.*') <p class="error">{{ $message }}</p> @enderror
                    <p class="hint">Cochez un ou plusieurs techniciens. « Pas encore décidé » si l'affectation reste à trancher.</p>
                </div>
            </div>
        </div>

        {{-- Localisation --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Lieu d'intervention</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="address">Adresse</label>
                    <input id="address" type="text" wire:model="address" placeholder="Rue, cité, numéro…" class="input">
                    @error('address') <p class="error">{{ $message }}</p> @enderror
                    <p class="hint">Laissez vide pour utiliser l'adresse du client.</p>
                </div>

                <div>
                    <label class="label" for="wilaya">Wilaya</label>
                    <select id="wilaya" wire:model.live="wilaya" class="input">
                        <option value="">— Sélectionner —</option>
                        @foreach ($wilayas as $item)
                            <option value="{{ $item }}">{{ $item }}</option>
                        @endforeach
                    </select>
                    @error('wilaya') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="commune">Commune</label>
                    @if ($communes)
                        <select id="commune" wire:model="commune" class="input">
                            <option value="">— Sélectionner —</option>
                            @foreach ($communes as $item)
                                <option value="{{ $item }}">{{ $item }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="commune" type="text" wire:model="commune" placeholder="Commune" class="input">
                    @endif
                    @error('commune') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Détail et statut --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Détail et suivi</h3>
            </div>
            <div class="card-body grid gap-4">
                <div>
                    <label class="label" for="description">Description de la demande</label>
                    <textarea id="description" rows="3" wire:model="description" placeholder="Besoin exprimé par le client, matériel souhaité…" class="input"></textarea>
                    @error('description') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="status">Statut</label>
                        <select id="status" wire:model.live="status" class="input">
                            @foreach (AppointmentStatus::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <div x-data x-show="$wire.status === 'annule'" x-cloak>
                        <label class="label" for="cancelledReason">Motif d'annulation</label>
                        <input id="cancelledReason" type="text" wire:model="cancelledReason" placeholder="Client injoignable, report…" class="input">
                        @error('cancelledReason') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="label" for="internalNotes">Notes internes</label>
                    <textarea id="internalNotes" rows="2" wire:model="internalNotes" placeholder="Visibles uniquement par l'équipe." class="input"></textarea>
                    @error('internalNotes') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ $appointment ? route('appointments.show', $appointment) : route('appointments.index') }}" wire:navigate class="btn-secondary justify-center">
                Annuler
            </a>
            <button type="submit" class="btn-primary justify-center">
                <x-icon name="check" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $appointment ? 'Enregistrer les modifications' : 'Créer le rendez-vous' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
