<?php

use App\Enums\DiscountType;
use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Devis', 'subtitle' => 'Composer une proposition chiffrée'])] class extends Component
{
    public ?Quote $quote = null;

    public string $clientId = '';

    public string $appointmentId = '';

    public string $issuedAt = '';

    public string $validUntil = '';

    public string $status = 'brouillon';

    public string $discountType = '';

    public string $discountValue = '0';

    public string $taxRate = '19';

    public string $notes = '';

    public string $terms = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public function mount(): void
    {
        if ($this->quote) {
            Gate::authorize('update', $this->quote);

            $this->clientId = (string) $this->quote->client_id;
            $this->appointmentId = $this->quote->appointment_id ? (string) $this->quote->appointment_id : '';
            $this->issuedAt = $this->quote->issued_at->toDateString();
            $this->validUntil = $this->quote->valid_until->toDateString();
            $this->status = $this->quote->status->value;
            $this->discountType = $this->quote->discount_type?->value ?? '';
            $this->discountValue = (string) $this->quote->discount_value;
            $this->taxRate = (string) $this->quote->tax_rate;
            $this->notes = (string) $this->quote->notes;
            $this->terms = (string) $this->quote->terms;
            $this->items = $this->quote->items
                ->map(fn ($item): array => [
                    'designation' => $item->designation,
                    'description' => (string) $item->description,
                    'unit' => $item->unit,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                ])
                ->all();

            return;
        }

        Gate::authorize('create', Quote::class);

        $this->issuedAt = now()->toDateString();
        $this->validUntil = now()->addDays(30)->toDateString();
        $this->taxRate = (string) config('company.tax_rate', 19);
        $this->items = [$this->blankItem()];

        $client = request()->query('client');

        if (is_scalar($client) && $client !== '') {
            $this->clientId = (string) $client;
        }

        $appointment = request()->query('appointment');

        if (is_scalar($appointment) && $appointment !== '') {
            $this->appointmentId = (string) $appointment;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return [
            'designation' => '',
            'description' => '',
            'unit' => 'unité',
            'quantity' => '1',
            'unit_price' => '0',
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
        $this->appointmentId = '';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'appointmentId' => ['nullable', 'integer', 'exists:appointments,id'],
            'issuedAt' => ['required', 'date_format:Y-m-d'],
            'validUntil' => ['required', 'date_format:Y-m-d', 'after_or_equal:issuedAt'],
            'status' => ['required', Rule::enum(QuoteStatus::class)],
            'discountType' => ['nullable', Rule::enum(DiscountType::class)],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.designation' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'clientId' => 'client',
            'appointmentId' => 'rendez-vous',
            'issuedAt' => 'date d\'émission',
            'validUntil' => 'date de validité',
            'discountType' => 'type de remise',
            'discountValue' => 'valeur de la remise',
            'taxRate' => 'taux de TVA',
            'items.*.designation' => 'désignation',
            'items.*.unit' => 'unité',
            'items.*.quantity' => 'quantité',
            'items.*.unit_price' => 'prix unitaire',
        ];
    }

    public function save(): void
    {
        $isEdit = $this->quote !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->quote : Quote::class);

        $validated = $this->validate();

        $payload = [
            'client_id' => (int) $validated['clientId'],
            'appointment_id' => $validated['appointmentId'] !== '' ? (int) $validated['appointmentId'] : null,
            'issued_at' => $validated['issuedAt'],
            'valid_until' => $validated['validUntil'],
            'status' => $validated['status'],
            'discount_type' => $validated['discountType'] ?: null,
            'discount_value' => (float) ($validated['discountValue'] ?: 0),
            'tax_rate' => (float) $validated['taxRate'],
            'notes' => $validated['notes'] ?: null,
            'terms' => $validated['terms'] ?: null,
        ];

        if ($isEdit) {
            $this->quote->update($payload);
            $quote = $this->quote;
        } else {
            $payload['created_by'] = auth()->id();
            $quote = Quote::create($payload);
        }

        $quote->items()->delete();

        foreach ($validated['items'] as $index => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            $quote->items()->create([
                'position' => $index + 1,
                'designation' => $item['designation'],
                'description' => ($item['description'] ?? '') ?: null,
                'unit' => $item['unit'] ?: 'unité',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($quantity * $unitPrice, 2),
            ]);
        }

        $quote->recalculateTotals();
        $quote->refresh();

        session()->flash('status', $isEdit ? 'Devis mis à jour.' : 'Devis créé.');

        $this->redirect(route('quotes.show', $quote), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'clients' => Client::query()->ordered()->get(['id', 'name', 'phone']),
            'appointments' => $this->clientId !== ''
                ? Appointment::query()
                    ->where('client_id', (int) $this->clientId)
                    ->orderByDesc('scheduled_at')
                    ->limit(20)
                    ->get(['id', 'reference', 'scheduled_at', 'type'])
                : collect(),
            'discountTypes' => DiscountType::options(),
            'totals' => $this->totals(),
        ];
    }

    /**
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    private function totals(): array
    {
        $subtotal = 0.0;

        foreach ($this->items as $item) {
            $subtotal += (float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0);
        }

        $subtotal = round($subtotal, 2);

        $discount = match ($this->discountType) {
            DiscountType::Percent->value => round($subtotal * ((float) $this->discountValue / 100), 2),
            DiscountType::Fixed->value => round((float) $this->discountValue, 2),
            default => 0.0,
        };

        $discount = min($discount, $subtotal);
        $taxable = $subtotal - $discount;
        $tax = round($taxable * ((float) $this->taxRate / 100), 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ $quote ? route('quotes.show', $quote) : route('quotes.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $quote ? 'Modifier '.$quote->number : 'Nouveau devis' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                {{-- En-tête du devis --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Informations générales</h3>
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
                            <label class="label" for="appointment">Rendez-vous lié</label>
                            <select id="appointment" wire:model="appointmentId" class="input">
                                <option value="">— Aucun —</option>
                                @foreach ($appointments as $appointment)
                                    <option value="{{ $appointment->id }}">
                                        {{ $appointment->reference }} · {{ $appointment->dateLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('appointmentId') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="status">Statut</label>
                            <select id="status" wire:model="status" class="input">
                                @foreach (QuoteStatus::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="issuedAt">Date d'émission</label>
                            <input id="issuedAt" type="date" wire:model="issuedAt" class="input">
                            @error('issuedAt') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="validUntil">Valable jusqu'au</label>
                            <input id="validUntil" type="date" wire:model="validUntil" class="input">
                            @error('validUntil') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Lignes du devis --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Lignes du devis</h3>
                        <button type="button" wire:click="addItem" class="btn-secondary btn-sm">
                            <x-icon name="plus" class="h-4 w-4" />
                            Ajouter une ligne
                        </button>
                    </div>
                    <div class="card-body space-y-3">
                        @error('items') <p class="error">{{ $message }}</p> @enderror

                        @foreach ($items as $index => $item)
                            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800" wire:key="quote-item-{{ $index }}">
                                <div class="grid gap-2 sm:grid-cols-12">
                                    <div class="sm:col-span-5">
                                        <label class="label" for="designation-{{ $index }}">Désignation</label>
                                        <input
                                            id="designation-{{ $index }}"
                                            type="text"
                                            wire:model="items.{{ $index }}.designation"
                                            placeholder="Ex. Caméra IP 4MP POE"
                                            class="input"
                                        >
                                        @error("items.{$index}.designation") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="label" for="unit-{{ $index }}">Unité</label>
                                        <input id="unit-{{ $index }}" type="text" wire:model="items.{{ $index }}.unit" class="input">
                                        @error("items.{$index}.unit") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="label" for="quantity-{{ $index }}">Qté</label>
                                        <input id="quantity-{{ $index }}" type="number" step="0.01" min="0.01" wire:model.live.debounce.500ms="items.{{ $index }}.quantity" class="input">
                                        @error("items.{$index}.quantity") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label class="label" for="unit-price-{{ $index }}">Prix unitaire ({{ Money::symbol() }})</label>
                                        <input id="unit-price-{{ $index }}" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="items.{{ $index }}.unit_price" class="input">
                                        @error("items.{$index}.unit_price") <p class="error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-12">
                                        <label class="label" for="description-{{ $index }}">Précisions (facultatif)</label>
                                        <input
                                            id="description-{{ $index }}"
                                            type="text"
                                            wire:model="items.{{ $index }}.description"
                                            placeholder="Caractéristiques, marque, garantie…"
                                            class="input"
                                        >
                                        @error("items.{$index}.description") <p class="error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Total ligne :
                                        <span class="font-semibold text-slate-800 dark:text-slate-100">
                                            {{ Money::format((float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0)) }}
                                        </span>
                                    </p>

                                    @if (count($items) > 1)
                                        <button type="button" wire:click="removeItem({{ $index }})" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50">
                                            <x-icon name="trash" class="h-4 w-4" />
                                            Supprimer
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Notes et conditions --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Notes et conditions</h3>
                    </div>
                    <div class="card-body grid gap-4">
                        <div>
                            <label class="label" for="notes">Notes visibles sur le devis</label>
                            <textarea id="notes" rows="2" wire:model="notes" placeholder="Délai d'exécution, garantie, précisions…" class="input"></textarea>
                            @error('notes') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="terms">Conditions générales</label>
                            <textarea id="terms" rows="2" wire:model="terms" placeholder="Modalités de paiement, validité…" class="input"></textarea>
                            @error('terms') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Récapitulatif --}}
            <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title">Récapitulatif</h3>
                    </div>
                    <div class="card-body space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-300">Sous-total</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ Money::format($totals['subtotal']) }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label" for="discountType">Remise</label>
                                <select id="discountType" wire:model.live="discountType" class="input">
                                    <option value="">Aucune</option>
                                    @foreach ($discountTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('discountType') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="discountValue">Valeur</label>
                                <input
                                    id="discountValue"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live.debounce.500ms="discountValue"
                                    class="input"
                                    @disabled($discountType === '')
                                >
                                @error('discountValue') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($totals['discount'] > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-300">Remise appliquée</span>
                                <span class="font-medium text-rose-600 dark:text-rose-400">− {{ Money::format($totals['discount']) }}</span>
                            </div>
                        @endif

                        <div>
                            <label class="label" for="taxRate">Taux de TVA (%)</label>
                            <input id="taxRate" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.500ms="taxRate" class="input">
                            @error('taxRate') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-300">TVA</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ Money::format($totals['tax']) }}</span>
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Total</span>
                            <span class="text-lg font-bold text-slate-900 dark:text-white">{{ Money::format($totals['total']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn-primary justify-center">
                        <x-icon name="check" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="save">{{ $quote ? 'Enregistrer les modifications' : 'Créer le devis' }}</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </button>
                    <a href="{{ $quote ? route('quotes.show', $quote) : route('quotes.index') }}" wire:navigate class="btn-secondary justify-center">
                        Annuler
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
