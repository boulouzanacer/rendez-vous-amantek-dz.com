<?php

use App\Enums\InstallationStatus;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Rapports', 'subtitle' => 'Activité commerciale et trésorerie'])] class extends Component
{
    public int $year = 0;

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $year = $this->year;

        $contracted = (float) Quote::query()
            ->where('status', QuoteStatus::Accepte->value)
            ->whereYear('issued_at', $year)
            ->sum('total');

        $quotesIssued = Quote::query()->whereYear('issued_at', $year)->count();

        $quotesDecided = Quote::query()
            ->whereYear('issued_at', $year)
            ->whereIn('status', [QuoteStatus::Accepte->value, QuoteStatus::Refuse->value])
            ->count();

        $quotesAccepted = Quote::query()
            ->where('status', QuoteStatus::Accepte->value)
            ->whereYear('issued_at', $year)
            ->count();

        $collected = (float) Payment::query()->whereYear('paid_at', $year)->sum('amount');

        $outstanding = round(max(
            (float) Quote::query()->where('status', QuoteStatus::Accepte->value)->sum('total') - (float) Payment::query()->sum('amount'),
            0,
        ), 2);

        $monthly = collect(range(1, 12))->map(function (int $month) use ($year): array {
            $quoted = (float) Quote::query()
                ->where('status', QuoteStatus::Accepte->value)
                ->whereYear('issued_at', $year)
                ->whereMonth('issued_at', $month)
                ->sum('total');

            $paid = (float) Payment::query()
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('amount');

            return [
                'label' => \Carbon\Carbon::create($year, $month, 1)->translatedFormat('M'),
                'quoted' => $quoted,
                'collected' => $paid,
            ];
        });

        $pipeline = Quote::query()
            ->whereYear('issued_at', $year)
            ->selectRaw('status, count(*) as total, coalesce(sum(total), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row): string => $row->status->value);

        $installationsByStatus = Installation::query()
            ->whereYear('scheduled_at', $year)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->status->value => (int) $row->aggregate]);

        $topClients = Client::query()
            ->withSum(['quotes as contracted_amount' => fn ($query) => $query->where('status', QuoteStatus::Accepte->value)], 'total')
            ->withSum('payments as collected_amount', 'amount')
            ->orderByDesc('contracted_amount')
            ->limit(5)
            ->get();

        return [
            'contracted' => $contracted,
            'collected' => $collected,
            'outstanding' => $outstanding,
            'quotesIssued' => $quotesIssued,
            'conversionRate' => $quotesDecided > 0 ? round($quotesAccepted / $quotesDecided * 100, 1) : null,
            'monthly' => $monthly,
            'maxMonthly' => max($monthly->max('quoted'), $monthly->max('collected'), 1),
            'pipeline' => $pipeline,
            'quoteStatuses' => QuoteStatus::options(),
            'installationsByStatus' => $installationsByStatus,
            'installationStatuses' => InstallationStatus::options(),
            'installationsPlanned' => (int) $installationsByStatus->sum(),
            'topClients' => $topClients,
        ];
    }
}; ?>

<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Exercice {{ $year }}</p>
    </div>

    {{-- Indicateurs --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Affaires signées" :value="Money::format($contracted)" :hint="'Devis acceptés en '.$year" icon="receipt" tone="brand" />
        <x-stat label="Encaissé" :value="Money::format($collected)" :hint="'Paiements reçus en '.$year" icon="banknote" tone="success" />
        <x-stat label="Reste à encaisser" :value="Money::format($outstanding)" hint="Tous devis acceptés" icon="wallet" tone="warning" />
        <x-stat
            label="Taux d'acceptation"
            :value="$conversionRate === null ? '—' : $conversionRate.' %'"
            :hint="$quotesIssued.' devis émis en '.$year"
            icon="trending-up"
            tone="info"
        />
    </div>

    {{-- Évolution mensuelle --}}
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Évolution mensuelle {{ $year }}</h3>
            <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-brand-600"></span>
                    Affaires signées
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>
                    Encaissé
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="flex items-end justify-between gap-1.5 sm:gap-3">
                @foreach ($monthly as $month)
                    <div class="group flex min-w-0 flex-1 flex-col items-center gap-1.5">
                        <div class="flex h-40 w-full items-end justify-center gap-0.5">
                            <div
                                class="w-1/2 rounded-t bg-brand-600 transition"
                                style="height: {{ max(($month['quoted'] / $maxMonthly) * 100, $month['quoted'] > 0 ? 2 : 0) }}%"
                                title="Signé : {{ Money::format($month['quoted']) }}"
                            ></div>
                            <div
                                class="w-1/2 rounded-t bg-emerald-500 transition"
                                style="height: {{ max(($month['collected'] / $maxMonthly) * 100, $month['collected'] > 0 ? 2 : 0) }}%"
                                title="Encaissé : {{ Money::format($month['collected']) }}"
                            ></div>
                        </div>
                        <span class="truncate text-[10px] font-medium uppercase text-slate-500 dark:text-slate-400 sm:text-xs">
                            {{ $month['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Pipeline devis --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Pipeline des devis {{ $year }}</h3>
            </div>
            @if ($pipeline->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Aucun devis émis cette année.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($quoteStatuses as $value => $label)
                        @php
                            $row = $pipeline->get($value);
                        @endphp
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $label }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row?->total ?? 0 }} devis</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ Money::format((float) ($row->amount ?? 0)) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Interventions --}}
        <div class="card">
            <div class="card-header">
                <h3 class="section-title">Interventions {{ $year }}</h3>
                <span class="badge-neutral">{{ $installationsPlanned }} planifiée(s)</span>
            </div>
            @if ($installationsByStatus->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Aucune intervention planifiée cette année.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($installationStatuses as $value => $label)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $label }}</span>
                            <span class="badge-{{ \App\Enums\InstallationStatus::from($value)->color() }}">
                                {{ $installationsByStatus->get($value, 0) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Meilleurs clients --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="section-title">Meilleurs clients</h3>
            <a href="{{ route('clients.index') }}" wire:navigate class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                Voir tous les clients
            </a>
        </div>
        @if ($topClients->isEmpty() || $topClients->every(fn ($client) => (float) $client->contracted_amount === 0.0))
            <div class="card-body">
                <p class="text-sm text-slate-500 dark:text-slate-400">Aucune affaire signée pour le moment.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th class="text-right">Affaires signées</th>
                            <th class="text-right">Encaissé</th>
                            <th class="text-right">Reste dû</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topClients as $client)
                            <tr>
                                <td>
                                    <a href="{{ route('clients.show', $client) }}" wire:navigate class="font-medium text-slate-800 hover:text-brand-600 dark:text-slate-100 dark:hover:text-brand-400">
                                        {{ $client->name }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap text-right font-semibold text-slate-900 dark:text-white">
                                    {{ Money::format((float) $client->contracted_amount) }}
                                </td>
                                <td class="whitespace-nowrap text-right text-emerald-600 dark:text-emerald-400">
                                    {{ Money::format((float) $client->collected_amount) }}
                                </td>
                                <td class="whitespace-nowrap text-right {{ (float) $client->contracted_amount - (float) $client->collected_amount > 0 ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ Money::format(max((float) $client->contracted_amount - (float) $client->collected_amount, 0)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
