<?php

use App\Enums\InstallationStatus;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Interventions', 'subtitle' => 'Planifier un chantier de pose'])] class extends Component
{
    public ?Installation $installation = null;

    public string $clientId = '';

    public string $quoteId = '';

    public string $technicianId = '';

    public string $title = '';

    public string $status = 'planifie';

    public string $scheduledDate = '';

    public string $scheduledTime = '09:00';

    public string $siteAddress = '';

    public string $commune = '';

    public string $wilaya = '';

    public string $description = '';

    public string $notes = '';

    /** @var array<int, array<string, string>> */
    public array $items = [];

    public function mount(): void
    {
        if ($this->installation) {
            Gate::authorize('update', $this->installation);

            $this->clientId = (string) $this->installation->client_id;
            $this->quoteId = $this->installation->quote_id ? (string) $this->installation->quote_id : '';
            $this->technicianId = $this->installation->user_id ? (string) $this->installation->user_id : '';
            $this->title = $this->installation->title;
            $this->status = $this->installation->status->value;
            $this->scheduledDate = $this->installation->scheduled_at?->toDateString() ?? now()->toDateString();
            $this->scheduledTime = $this->installation->scheduled_at?->format('H:i') ?? '09:00';
            $this->siteAddress = (string) $this->installation->site_address;
            $this->commune = (string) $this->installation->commune;
            $this->wilaya = (string) $this->installation->wilaya;
            $this->description = (string) $this->installation->description;
            $this->notes = (string) $this->installation->notes;
            $this->items = $this->installation->items
                ->map(fn ($item): array => [
                    'designation' => $item->designation,
                    'serial_number' => (string) $item->serial_number,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->unit,
                    'notes' => (string) $item->notes,
                ])
                ->all();

            return;
        }

        Gate::authorize('create', Installation::class);

        $this->title = 'Installation de vidéosurveillance';
        $this->scheduledDate = now()->toDateString();
        $this->items = [$this->blankItem()];

        $quote = request()->query('quote');

        if (is_scalar($quote) && $quote !== '') {
            $model = Quote::query()->find((int) $quote);

            if ($model) {
                $this->quoteId = (string) $model->id;
                $this->clientId = (string) $model->client_id;
            }
        }

        $client = request()->query('client');

        if (is_scalar($client) && $client !== '') {
            $this->clientId = (string) $client;
        }
    }

    /**
     * @return array<string, string>
     */
    private function blankItem(): array
    {
        return [
            'designation' => '',
            'serial_number' => '',
            'quantity' => '1',
            'unit' => 'unité',
            'notes' => '',
        ];
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function updatedClientId(): void
    {
        $this->quoteId = '';
    }

    /**
     * Pré-remplit l'adresse et la localisation depuis le client choisi.
     */
    public function updatedQuoteId(): void
    {
        if ($this->quoteId === '') {
            return;
        }

        $quote = Quote::query()->find((int) $this->quoteId);

        if (! $quote) {
            return;
        }

        $this->clientId = (string) $quote->client_id;

        $client = $quote->client;

        if ($client) {
            if ($this->siteAddress === '') {
                $this->siteAddress = (string) $client->address;
            }

            if ($this->commune === '') {
                $this->commune = (string) $client->commune;
            }

            if ($this->wilaya === '') {
                $this->wilaya = (string) $client->wilaya;
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'quoteId' => ['nullable', 'integer', 'exists:quotes,id'],
            'technicianId' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(InstallationStatus::class)],
            'scheduledDate' => ['required', 'date_format:Y-m-d'],
            'scheduledTime' => ['required', 'date_format:H:i'],
            'siteAddress' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'wilaya' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['array'],
            'items.*.designation' => ['nullable', 'string', 'max:255'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'clientId' => 'client',
            'quoteId' => 'devis',
            'technicianId' => 'technicien',
            'title' => 'intitulé',
            'scheduledDate' => 'date planifiée',
            'scheduledTime' => 'heure planifiée',
            'siteAddress' => 'adresse du site',
            'items.*.designation' => 'désignation',
            'items.*.quantity' => 'quantité',
            'items.*.unit' => 'unité',
        ];
    }

    public function save(): void
    {
        $isEdit = $this->installation !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->installation : Installation::class);

        $validated = $this->validate();

        $payload = [
            'client_id' => (int) $validated['clientId'],
            'quote_id' => $validated['quoteId'] !== '' ? (int) $validated['quoteId'] : null,
            'user_id' => $validated['technicianId'] !== '' ? (int) $validated['technicianId'] : null,
            'title' => $validated['title'],
            'status' => $validated['status'],
            'scheduled_at' => $validated['scheduledDate'].' '.$validated['scheduledTime'].':00',
            'site_address' => $validated['siteAddress'] ?: null,
            'commune' => $validated['commune'] ?: null,
            'wilaya' => $validated['wilaya'] ?: null,
            'description' => $validated['description'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($isEdit) {
            $this->installation->update($payload);
            $installation = $this->installation;
        } else {
            $payload['created_by'] = auth()->id();
            $installation = Installation::create($payload);
        }

        $installation->items()->delete();

        foreach (array_values($validated['items'] ?? []) as $item) {
            if (($item['designation'] ?? '') === '') {
                continue;
            }

            $installation->items()->create([
                'designation' => $item['designation'],
                'serial_number' => ($item['serial_number'] ?? '') ?: null,
                'quantity' => (float) ($item['quantity'] ?? 0) ?: 1,
                'unit' => ($item['unit'] ?? '') ?: 'unité',
                'notes' => ($item['notes'] ?? '') ?: null,
            ]);
        }

        session()->flash('status', $isEdit ? 'Intervention mise à jour.' : 'Intervention planifiée.');

        $this->redirect(route('installations.show', $installation), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'clients' => Client::query()->ordered()->get(['id', 'name', 'phone']),
            'quotes' => $this->clientId !== ''
                ? Quote::query()
                    ->where('client_id', (int) $this->clientId)
                    ->orderByDesc('issued_at')
                    ->limit(20)
                    ->get(['id', 'number', 'total', 'status'])
                : collect(),
            'technicians' => User::query()->active()->orderBy('name')->get(['id', 'name', 'role']),
            'statuses' => InstallationStatus::options(),
            'wilayas' => config('algeria.wilayas'),
            'communes' => config('algeria.communes')[$this->wilaya] ?? [],
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ $installation ? route('installations.show', $installation) : route('installations.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $installation ? 'Modifier '.$installation->reference : 'Nouvelle intervention' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        {{-- Informations du chantier --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Informations du chantier</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="client">Client</label>
                    <select id="client" wire:model.live="clientId" class="input">
                        <option value="">— Sélectionner un client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}@if ($client->phone) — {{ $client->phone }}@endif</option>
                        @endforeach
                    </select>
                    @error('clientId') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="quote">Devis d'origine</label>
                    <select id="quote" wire:model.live="quoteId" class="input">
                        <option value="">— Aucun —</option>
                        @foreach ($quotes as $quote)
                            <option value="{{ $quote->id }}">{{ $quote->number }} · {{ $quote->status->label() }}</option>
                        @endforeach
                    </select>
                    @error('quoteId') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="technician">Technicien assigné</label>
                    <select id="technician" wire:model="technicianId" class="input">
                        <option value="">— Non assigné —</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }} ({{ $technician->role->label() }})</option>
                        @endforeach
                    </select>
                    @error('technicianId') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="title">Intitulé</label>
                    <input id="title" type="text" wire:model="title" placeholder="Ex. Installation 8 caméras + NVR" class="input">
                    @error('title') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="status">Statut</label>
                    <select id="status" wire:model="status" class="input">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="scheduledDate">Date</label>
                        <input id="scheduledDate" type="date" wire:model="scheduledDate" class="input">
                        @error('scheduledDate') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="scheduledTime">Heure</label>
                        <input id="scheduledTime" type="time" wire:model="scheduledTime" class="input">
                        @error('scheduledTime') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Localisation --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Localisation du site</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="siteAddress">Adresse du site</label>
                    <input id="siteAddress" type="text" wire:model="siteAddress" placeholder="Rue, cité, numéro…" class="input">
                    @error('siteAddress') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="wilaya">Wilaya</label>
                    <select id="wilaya" wire:model.live="wilaya" class="input">
                        <option value="">— Sélectionner —</option>
                        @foreach ($wilayas as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('wilaya') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="commune">Commune</label>
                    @if ($communes === [])
                        <input id="commune" type="text" wire:model="commune" placeholder="Commune" class="input">
                    @else
                        <select id="commune" wire:model="commune" class="input">
                            <option value="">— Sélectionner —</option>
                            @foreach ($communes as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('commune') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Matériel posé --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Matériel posé</h3>
                <button type="button" wire:click="addItem" class="btn-secondary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    Ajouter un matériel
                </button>
            </div>
            <div class="card-body space-y-3">
                @foreach ($items as $index => $item)
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800" wire:key="installation-item-{{ $index }}">
                        <div class="grid gap-2 sm:grid-cols-12">
                            <div class="sm:col-span-5">
                                <label class="label" for="item-designation-{{ $index }}">Désignation</label>
                                <input id="item-designation-{{ $index }}" type="text" wire:model="items.{{ $index }}.designation" placeholder="Ex. Caméra IP dôme 4 MP" class="input">
                                @error("items.{$index}.designation") <p class="error">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-4">
                                <label class="label" for="item-serial-{{ $index }}">Numéro de série</label>
                                <input id="item-serial-{{ $index }}" type="text" wire:model="items.{{ $index }}.serial_number" class="input">
                                @error("items.{$index}.serial_number") <p class="error">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-1">
                                <label class="label" for="item-quantity-{{ $index }}">Qté</label>
                                <input id="item-quantity-{{ $index }}" type="number" step="0.01" min="0.01" wire:model="items.{{ $index }}.quantity" class="input">
                                @error("items.{$index}.quantity") <p class="error">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="label" for="item-unit-{{ $index }}">Unité</label>
                                <input id="item-unit-{{ $index }}" type="text" wire:model="items.{{ $index }}.unit" class="input">
                                @error("items.{$index}.unit") <p class="error">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-12">
                                <label class="label" for="item-notes-{{ $index }}">Emplacement / remarque</label>
                                <input id="item-notes-{{ $index }}" type="text" wire:model="items.{{ $index }}.notes" placeholder="Ex. Entrée principale, angle nord-est…" class="input">
                                @error("items.{$index}.notes") <p class="error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if (count($items) > 1)
                            <div class="mt-2 flex justify-end">
                                <button type="button" wire:click="removeItem({{ $index }})" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50">
                                    <x-icon name="trash" class="h-4 w-4" />
                                    Supprimer
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Description et notes --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Description et notes</h3>
            </div>
            <div class="card-body grid gap-4">
                <div>
                    <label class="label" for="description">Travaux à réaliser</label>
                    <textarea id="description" rows="3" wire:model="description" placeholder="Détail des travaux, contraintes du site…" class="input"></textarea>
                    @error('description') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="notes">Notes internes</label>
                    <textarea id="notes" rows="2" wire:model="notes" placeholder="Matériel à prévoir, accès, contact sur site…" class="input"></textarea>
                    @error('notes') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <a href="{{ $installation ? route('installations.show', $installation) : route('installations.index') }}" wire:navigate class="btn-secondary justify-center">
                Annuler
            </a>
            <button type="submit" class="btn-primary justify-center">
                <x-icon name="check" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $installation ? 'Enregistrer les modifications' : 'Planifier l\'intervention' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
