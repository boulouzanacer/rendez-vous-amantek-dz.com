<?php

use App\Enums\AuthorizationStatus;
use App\Models\Authorization;
use App\Models\Camera;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Autorisations', 'subtitle' => 'Dossier d\'autorisation de vidéosurveillance'])] class extends Component
{
    public ?Authorization $authorization = null;

    public string $clientId = '';

    public string $status = 'en_preparation';

    public string $wilaya = '';

    public string $commune = '';

    public string $address = '';

    public bool $hasInside = false;

    public bool $hasOutside = false;

    /** @var array<int, array<string, string>> */
    public array $insideItems = [];

    /** @var array<int, array<string, string>> */
    public array $outsideItems = [];

    public string $notes = '';

    public function mount(): void
    {
        if ($this->authorization) {
            Gate::authorize('update', $this->authorization);

            $this->clientId = (string) $this->authorization->client_id;
            $this->status = $this->authorization->status->value;
            $this->wilaya = (string) $this->authorization->wilaya;
            $this->commune = (string) $this->authorization->commune;
            $this->address = (string) $this->authorization->address;
            $this->hasInside = (bool) $this->authorization->has_inside_cameras;
            $this->hasOutside = (bool) $this->authorization->has_outside_cameras;
            $this->insideItems = $this->toRows($this->authorization->insideList());
            $this->outsideItems = $this->toRows($this->authorization->outsideList());
            $this->notes = (string) $this->authorization->notes;

            return;
        }

        Gate::authorize('create', Authorization::class);

        $client = request()->query('client');

        if (is_scalar($client) && $client !== '') {
            $this->clientId = (string) $client;
            $this->updatedClientId();
        }
    }

    /**
     * @param  array<int, array{reference: string, quantity: int}>  $items
     * @return array<int, array<string, string>>
     */
    private function toRows(array $items): array
    {
        return array_map(fn (array $item): array => [
            'reference' => (string) ($item['reference'] ?? ''),
            'quantity' => (string) ($item['quantity'] ?? 1),
        ], $items);
    }

    /**
     * @return array<string, string>
     */
    private function blankItem(): array
    {
        return ['reference' => '', 'quantity' => '1'];
    }

    public function addInsideItem(): void
    {
        $this->insideItems[] = $this->blankItem();
    }

    public function removeInsideItem(int $index): void
    {
        unset($this->insideItems[$index]);
        $this->insideItems = array_values($this->insideItems);
    }

    public function addOutsideItem(): void
    {
        $this->outsideItems[] = $this->blankItem();
    }

    public function removeOutsideItem(int $index): void
    {
        unset($this->outsideItems[$index]);
        $this->outsideItems = array_values($this->outsideItems);
    }

    public function updatedHasInside(bool $value): void
    {
        if ($value && $this->insideItems === []) {
            $this->addInsideItem();
        }
    }

    public function updatedHasOutside(bool $value): void
    {
        if ($value && $this->outsideItems === []) {
            $this->addOutsideItem();
        }
    }

    /**
     * Pré-remplit la localisation depuis le client choisi.
     */
    public function updatedClientId(): void
    {
        $client = Client::query()->find((int) $this->clientId);

        if (! $client) {
            return;
        }

        if ($this->wilaya === '') {
            $this->wilaya = (string) $client->wilaya;
        }

        if ($this->commune === '') {
            $this->commune = (string) $client->commune;
        }

        if ($this->address === '') {
            $this->address = (string) $client->address;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'status' => ['required', Rule::enum(AuthorizationStatus::class)],
            'wilaya' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'hasInside' => ['boolean'],
            'hasOutside' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'insideItems' => ['array'],
            'insideItems.*.reference' => ['nullable', 'string', 'max:255'],
            'insideItems.*.quantity' => ['nullable', 'integer', 'min:1'],
            'outsideItems' => ['array'],
            'outsideItems.*.reference' => ['nullable', 'string', 'max:255'],
            'outsideItems.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'clientId' => 'client',
            'status' => 'statut',
            'wilaya' => 'wilaya',
            'commune' => 'commune',
            'address' => 'adresse',
            'insideItems.*.reference' => 'référence de caméra intérieure',
            'insideItems.*.quantity' => 'nombre de caméras intérieures',
            'outsideItems.*.reference' => 'référence de caméra extérieure',
            'outsideItems.*.quantity' => 'nombre de caméras extérieures',
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $items
     * @return array<int, array{reference: string, quantity: int}>
     */
    private function normalize(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'reference' => trim((string) ($item['reference'] ?? '')),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            ])
            ->filter(fn (array $item): bool => $item['reference'] !== '')
            ->values()
            ->all();
    }

    public function save(): void
    {
        $isEdit = $this->authorization !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->authorization : Authorization::class);

        $this->validate();

        if (! $this->hasInside && ! $this->hasOutside) {
            $this->addError('hasInside', 'Sélectionnez au moins un type de caméras (intérieur ou extérieur).');

            return;
        }

        $inside = $this->hasInside ? $this->normalize($this->insideItems) : [];
        $outside = $this->hasOutside ? $this->normalize($this->outsideItems) : [];

        if ($this->hasInside && $inside === []) {
            $this->addError('insideItems', 'Sélectionnez au moins une référence de caméra intérieure.');

            return;
        }

        if ($this->hasOutside && $outside === []) {
            $this->addError('outsideItems', 'Sélectionnez au moins une référence de caméra extérieure.');

            return;
        }

        $payload = [
            'client_id' => (int) $this->clientId,
            'status' => $this->status,
            'wilaya' => $this->wilaya ?: null,
            'commune' => $this->commune ?: null,
            'address' => $this->address ?: null,
            'has_inside_cameras' => $this->hasInside,
            'has_outside_cameras' => $this->hasOutside,
            'inside_cameras' => $inside ?: null,
            'outside_cameras' => $outside ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($isEdit) {
            $this->authorization->update($payload);
            $authorization = $this->authorization;
        } else {
            $payload['created_by'] = auth()->id();
            $authorization = Authorization::create($payload);
        }

        session()->flash('status', $isEdit ? 'Dossier d\'autorisation mis à jour.' : 'Dossier d\'autorisation créé.');

        $this->redirect(route('authorizations.show', $authorization), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'clients' => Client::query()->ordered()->get(['id', 'name', 'phone']),
            'cameras' => Camera::query()->active()->ordered()->get(['id', 'reference', 'brand']),
            'statuses' => AuthorizationStatus::options(),
            'wilayas' => config('algeria.wilayas'),
            'communes' => config('algeria.communes')[$this->wilaya] ?? [],
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ $authorization ? route('authorizations.show', $authorization) : route('authorizations.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $authorization ? 'Modifier '.$authorization->reference : 'Nouvelle autorisation' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        {{-- Client et statut --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Client et statut</h3>
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

                <div class="sm:col-span-2">
                    <label class="label" for="status">Statut du dossier</label>
                    <select id="status" wire:model="status" class="input">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Localisation --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Localisation</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="address">Adresse</label>
                    <input id="address" type="text" wire:model="address" placeholder="Rue, cité, numéro…" class="input">
                    @error('address') <p class="error">{{ $message }}</p> @enderror
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

        {{-- Caméras --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Caméras concernées</h3>
            </div>
            <div class="card-body space-y-5">
                @error('hasInside') <p class="error">{{ $message }}</p> @enderror

                {{-- Caméras intérieures --}}
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                        <input type="checkbox" wire:model.live="hasInside" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        Caméras intérieures
                    </label>

                    @if ($hasInside)
                        <div class="mt-3 space-y-3">
                            @foreach ($insideItems as $index => $item)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-900/40" wire:key="inside-item-{{ $index }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Caméra intérieure {{ $index + 1 }}</p>
                                        <button type="button" wire:click="removeInsideItem({{ $index }})" class="btn-icon text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50" title="Retirer cette référence">
                                            <x-icon name="trash" class="h-4 w-4" />
                                            <span class="sr-only">Retirer</span>
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        <label class="label" for="inside-ref-{{ $index }}">Référence de la caméra</label>
                                        <select id="inside-ref-{{ $index }}" wire:model="insideItems.{{ $index }}.reference" class="input w-full py-2.5">
                                            <option value="">— Sélectionner une référence dans le catalogue —</option>
                                            @foreach ($cameras as $camera)
                                                <option value="{{ $camera->reference }}">{{ $camera->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error("insideItems.{$index}.reference") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="mt-3">
                                        <label class="label" for="inside-qty-{{ $index }}">Nombre de caméras de cette référence</label>
                                        <input id="inside-qty-{{ $index }}" type="number" min="1" wire:model="insideItems.{{ $index }}.quantity" class="input w-full py-2.5 sm:max-w-32">
                                        @error("insideItems.{$index}.quantity") <p class="error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endforeach

                            @error('insideItems') <p class="error">{{ $message }}</p> @enderror

                            <button type="button" wire:click="addInsideItem" class="btn-secondary btn-sm">
                                <x-icon name="plus" class="h-4 w-4" />
                                Ajouter une référence
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Caméras extérieures --}}
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                        <input type="checkbox" wire:model.live="hasOutside" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        Caméras extérieures
                    </label>

                    @if ($hasOutside)
                        <div class="mt-3 space-y-3">
                            @foreach ($outsideItems as $index => $item)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-900/40" wire:key="outside-item-{{ $index }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Caméra extérieure {{ $index + 1 }}</p>
                                        <button type="button" wire:click="removeOutsideItem({{ $index }})" class="btn-icon text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50" title="Retirer cette référence">
                                            <x-icon name="trash" class="h-4 w-4" />
                                            <span class="sr-only">Retirer</span>
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        <label class="label" for="outside-ref-{{ $index }}">Référence de la caméra</label>
                                        <select id="outside-ref-{{ $index }}" wire:model="outsideItems.{{ $index }}.reference" class="input w-full py-2.5">
                                            <option value="">— Sélectionner une référence dans le catalogue —</option>
                                            @foreach ($cameras as $camera)
                                                <option value="{{ $camera->reference }}">{{ $camera->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error("outsideItems.{$index}.reference") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="mt-3">
                                        <label class="label" for="outside-qty-{{ $index }}">Nombre de caméras de cette référence</label>
                                        <input id="outside-qty-{{ $index }}" type="number" min="1" wire:model="outsideItems.{{ $index }}.quantity" class="input w-full py-2.5 sm:max-w-32">
                                        @error("outsideItems.{$index}.quantity") <p class="error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endforeach

                            @error('outsideItems') <p class="error">{{ $message }}</p> @enderror

                            <button type="button" wire:click="addOutsideItem" class="btn-secondary btn-sm">
                                <x-icon name="plus" class="h-4 w-4" />
                                Ajouter une référence
                            </button>
                        </div>
                    @endif
                </div>

                @if ($cameras->isEmpty())
                    <p class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-xs text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                        Le catalogue de caméras est vide. Ajoutez des références depuis Paramètres › Caméras.
                    </p>
                @endif
            </div>
        </div>

        {{-- Notes --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Notes</h3>
            </div>
            <div class="card-body">
                <textarea id="notes" rows="3" wire:model="notes" placeholder="Numéro de dossier, dépôt, remarques…" class="input"></textarea>
                @error('notes') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <a href="{{ $authorization ? route('authorizations.show', $authorization) : route('authorizations.index') }}" wire:navigate class="btn-secondary justify-center">
                Annuler
            </a>
            <button type="submit" class="btn-primary justify-center">
                <x-icon name="check" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $authorization ? 'Enregistrer les modifications' : 'Créer le dossier' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
