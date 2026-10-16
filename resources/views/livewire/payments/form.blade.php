<?php

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Paiements', 'subtitle' => 'Enregistrer un encaissement'])] class extends Component
{
    public ?Payment $payment = null;

    public string $clientId = '';

    public string $quoteId = '';

    public string $installationId = '';

    public string $amount = '';

    public string $method = 'espece';

    public string $paidAt = '';

    public string $reference = '';

    public string $notes = '';

    public function mount(): void
    {
        if ($this->payment) {
            Gate::authorize('update', $this->payment);

            $this->clientId = (string) $this->payment->client_id;
            $this->quoteId = $this->payment->quote_id ? (string) $this->payment->quote_id : '';
            $this->installationId = $this->payment->installation_id ? (string) $this->payment->installation_id : '';
            $this->amount = (string) $this->payment->amount;
            $this->method = $this->payment->method->value;
            $this->paidAt = $this->payment->paid_at->toDateString();
            $this->reference = (string) $this->payment->reference;
            $this->notes = (string) $this->payment->notes;

            return;
        }

        Gate::authorize('create', Payment::class);

        $this->paidAt = now()->toDateString();

        $quote = request()->query('quote');

        if (is_scalar($quote) && $quote !== '') {
            $model = Quote::query()->find((int) $quote);

            if ($model) {
                $this->quoteId = (string) $model->id;
                $this->clientId = (string) $model->client_id;
                $this->amount = (string) $model->balanceDue();
            }
        }

        $installation = request()->query('installation');

        if (is_scalar($installation) && $installation !== '') {
            $this->installationId = (string) $installation;
        }

        $client = request()->query('client');

        if (is_scalar($client) && $client !== '') {
            $this->clientId = (string) $client;
        }
    }

    public function updatedClientId(): void
    {
        $this->quoteId = '';
        $this->installationId = '';
    }

    /**
     * Pré-remplit le montant avec le reste dû sur le devis sélectionné.
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

        if ($this->amount === '' || (float) $this->amount === 0.0) {
            $this->amount = (string) $quote->balanceDue();
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
            'installationId' => ['nullable', 'integer', 'exists:installations,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paidAt' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'installationId' => 'intervention',
            'amount' => 'montant',
            'method' => 'moyen de paiement',
            'paidAt' => 'date de paiement',
            'reference' => 'référence',
        ];
    }

    public function save(): void
    {
        $isEdit = $this->payment !== null;

        Gate::authorize($isEdit ? 'update' : 'create', $isEdit ? $this->payment : Payment::class);

        $validated = $this->validate();

        $payload = [
            'client_id' => (int) $validated['clientId'],
            'quote_id' => $validated['quoteId'] !== '' ? (int) $validated['quoteId'] : null,
            'installation_id' => $validated['installationId'] !== '' ? (int) $validated['installationId'] : null,
            'amount' => (float) $validated['amount'],
            'method' => $validated['method'],
            'paid_at' => $validated['paidAt'],
            'reference' => $validated['reference'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($isEdit) {
            $this->payment->update($payload);
        } else {
            $payload['recorded_by'] = auth()->id();
            Payment::create($payload);
        }

        session()->flash('status', $isEdit ? 'Paiement mis à jour.' : 'Paiement enregistré.');

        $this->redirect(route('payments.index'), navigate: true);
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
                    ->with('client:id,name')
                    ->orderByDesc('issued_at')
                    ->limit(20)
                    ->get(['id', 'number', 'total', 'client_id', 'status'])
                : collect(),
            'installations' => $this->clientId !== ''
                ? Installation::query()
                    ->where('client_id', (int) $this->clientId)
                    ->orderByDesc('scheduled_at')
                    ->limit(20)
                    ->get(['id', 'reference', 'title'])
                : collect(),
            'methods' => PaymentMethod::options(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('payments.index') }}" wire:navigate class="btn-ghost btn-sm">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Retour
        </a>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $payment ? 'Modifier le paiement' : 'Nouveau paiement' }}
        </h2>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Encaissement</h3>
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
                    <label class="label" for="quote">Devis rattaché</label>
                    <select id="quote" wire:model.live="quoteId" class="input">
                        <option value="">— Aucun —</option>
                        @foreach ($quotes as $quote)
                            <option value="{{ $quote->id }}">
                                {{ $quote->number }} · {{ Money::format($quote->total) }} · reste {{ Money::format($quote->balanceDue()) }}
                            </option>
                        @endforeach
                    </select>
                    @error('quoteId') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="installation">Intervention rattachée</label>
                    <select id="installation" wire:model="installationId" class="input">
                        <option value="">— Aucune —</option>
                        @foreach ($installations as $installation)
                            <option value="{{ $installation->id }}">{{ $installation->reference }} · {{ $installation->title }}</option>
                        @endforeach
                    </select>
                    @error('installationId') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="amount">Montant ({{ Money::symbol() }})</label>
                    <input id="amount" type="number" step="0.01" min="0.01" wire:model="amount" class="input">
                    @error('amount') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="method">Moyen de paiement</label>
                    <select id="method" wire:model="method" class="input">
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('method') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="paidAt">Date de paiement</label>
                    <input id="paidAt" type="date" wire:model="paidAt" class="input">
                    @error('paidAt') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="reference">Référence</label>
                    <input id="reference" type="text" wire:model="reference" placeholder="N° de chèque, bordereau…" class="input">
                    @error('reference') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" rows="2" wire:model="notes" placeholder="Acompte, solde, précisions…" class="input"></textarea>
                    @error('notes') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <a href="{{ route('payments.index') }}" wire:navigate class="btn-secondary justify-center">
                Annuler
            </a>
            <button type="submit" class="btn-primary justify-center">
                <x-icon name="check" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $payment ? 'Enregistrer les modifications' : 'Enregistrer le paiement' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
