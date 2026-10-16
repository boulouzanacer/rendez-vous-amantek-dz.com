<?php

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Clients', 'subtitle' => 'Fiche client'])] class extends Component
{
    public ?Client $client = null;

    public string $name = '';

    public string $type = 'particulier';

    public string $phone = '';

    public string $phoneAlt = '';

    public string $email = '';

    public string $address = '';

    public string $commune = '';

    public string $wilaya = '';

    public string $notes = '';

    public function mount(): void
    {
        if ($this->client) {
            Gate::authorize('update', $this->client);

            $this->name = $this->client->name;
            $this->type = $this->client->type->value;
            $this->phone = $this->client->phone;
            $this->phoneAlt = (string) $this->client->phone_alt;
            $this->email = (string) $this->client->email;
            $this->address = (string) $this->client->address;
            $this->commune = (string) $this->client->commune;
            $this->wilaya = (string) $this->client->wilaya;
            $this->notes = (string) $this->client->notes;

            return;
        }

        Gate::authorize('create', Client::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ClientType::class)],
            'phone' => ['required', 'string', 'max:30'],
            'phoneAlt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'wilaya' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom',
            'type' => 'type',
            'phone' => 'téléphone',
            'phoneAlt' => 'téléphone secondaire',
            'email' => 'email',
        ];
    }

    public function save(): void
    {
        $isEdit = $this->client !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->client : Client::class);

        $validated = $this->validate();

        $payload = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'phone' => $validated['phone'],
            'phone_alt' => $validated['phoneAlt'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'commune' => $validated['commune'] ?: null,
            'wilaya' => $validated['wilaya'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($isEdit) {
            $this->client->update($payload);
            $client = $this->client;
        } else {
            $payload['created_by'] = auth()->id();
            $client = Client::create($payload);
        }

        session()->flash('status', $isEdit ? 'Fiche client mise à jour.' : 'Client créé.');

        $this->redirect(route('clients.show', $client), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'wilayas' => config('algeria.wilayas'),
            'communes' => config('algeria.communes')[$this->wilaya] ?? [],
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $client ? 'Modifier '.$client->name : 'Nouveau client' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Identité</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Nom / Raison sociale</label>
                    <input id="name" type="text" wire:model="name" placeholder="Ex. SARL Horizon Sécurité" class="input">
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="type">Type de client</label>
                    <select id="type" wire:model="type" class="input">
                        @foreach (ClientType::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="phone">Téléphone</label>
                    <input id="phone" type="tel" wire:model="phone" placeholder="0555 00 00 00" class="input">
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="phoneAlt">Téléphone secondaire</label>
                    <input id="phoneAlt" type="tel" wire:model="phoneAlt" placeholder="Facultatif" class="input">
                    @error('phoneAlt') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" wire:model="email" placeholder="Facultatif" class="input">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

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

        <div class="card">
            <div class="card-body">
                <label class="label" for="notes">Notes</label>
                <textarea id="notes" rows="3" wire:model="notes" placeholder="Informations utiles : étage, accès, interlocuteur…" class="input"></textarea>
                @error('notes') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" wire:navigate class="btn-secondary justify-center">
                Annuler
            </a>
            <button type="submit" class="btn-primary justify-center">
                <x-icon name="check" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $client ? 'Enregistrer les modifications' : 'Créer le client' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
