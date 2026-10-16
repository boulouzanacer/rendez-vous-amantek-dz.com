@php
    use App\Support\Money;

    /** @var \App\Models\Quote $quote */
    $client = $quote->client;
@endphp

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Devis {{ $quote->number }} — {{ config('company.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])

        <style>
            @page {
                size: A4;
                margin: 12mm;
            }
        </style>
    </head>

    <body class="bg-slate-100 font-sans text-slate-800 antialiased print:bg-white">
        {{-- Barre d'actions (masquée à l'impression) --}}
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 print:hidden">
            <a href="{{ route('quotes.show', $quote) }}" class="btn-ghost btn-sm">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Retour au devis
            </a>

            <button type="button" onclick="window.print()" class="btn-primary btn-sm">
                <x-icon name="printer" class="h-4 w-4" />
                Imprimer / Enregistrer en PDF
            </button>
        </div>

        {{-- Document --}}
        <main class="mx-auto my-6 max-w-[210mm] bg-white px-10 py-10 shadow-card print:my-0 print:max-w-none print:px-0 print:py-0 print:shadow-none">
            {{-- En-tête société --}}
            <header class="flex flex-wrap items-start justify-between gap-6 border-b-2 border-brand-600 pb-5">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 text-white">
                            <x-icon name="shield" class="h-5 w-5" />
                        </span>
                        <span class="text-xl font-bold text-slate-900">{{ config('company.name') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">Vidéosurveillance &amp; réseaux informatiques</p>
                </div>

                <div class="text-right text-sm text-slate-600">
                    @if (config('company.address'))
                        <p>{{ config('company.address') }}</p>
                    @endif
                    @if (config('company.phone'))
                        <p>Tél. {{ config('company.phone') }}</p>
                    @endif
                    @if (config('company.email'))
                        <p>{{ config('company.email') }}</p>
                    @endif
                </div>
            </header>

            {{-- Titre --}}
            <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold uppercase tracking-tight text-slate-900">Devis</h1>
                    <p class="mt-1 text-sm font-semibold text-brand-700">{{ $quote->number }}</p>
                </div>

                <dl class="text-right text-sm">
                    <div class="flex justify-end gap-3">
                        <dt class="text-slate-500">Date d'émission</dt>
                        <dd class="font-medium text-slate-900">{{ $quote->issued_at->translatedFormat('d F Y') }}</dd>
                    </div>
                    <div class="flex justify-end gap-3">
                        <dt class="text-slate-500">Valable jusqu'au</dt>
                        <dd class="font-medium text-slate-900">{{ $quote->valid_until->translatedFormat('d F Y') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Client --}}
            <section class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client</p>
                <p class="mt-1 text-base font-semibold text-slate-900">{{ $client?->name ?? 'Client supprimé' }}</p>

                @if ($client)
                    <div class="mt-1 space-y-0.5 text-sm text-slate-600">
                        @if ($client->phone)
                            <p>Tél. {{ $client->phone }}</p>
                        @endif
                        @if ($client->phone_alt)
                            <p>Tél. 2 : {{ $client->phone_alt }}</p>
                        @endif
                        @if ($client->email)
                            <p>{{ $client->email }}</p>
                        @endif
                        @if ($client->address)
                            <p>{{ $client->address }}</p>
                        @endif
                        @if ($client->location())
                            <p>{{ $client->location() }}</p>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Lignes --}}
            <table class="mt-6 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b-2 border-slate-300 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="w-10 py-2">#</th>
                        <th class="py-2">Désignation</th>
                        <th class="py-2 text-center">Unité</th>
                        <th class="py-2 text-center">Qté</th>
                        <th class="py-2 text-right">P.U.</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quote->items as $item)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-2 text-slate-400">{{ $loop->iteration }}</td>
                            <td class="py-2">
                                <span class="font-medium text-slate-800">{{ $item->designation }}</span>
                                @if ($item->description)
                                    <span class="block text-xs text-slate-500">{{ $item->description }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-center text-slate-600">{{ $item->unit }}</td>
                            <td class="py-2 text-center text-slate-600">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="whitespace-nowrap py-2 text-right text-slate-600">{{ Money::format($item->unit_price) }}</td>
                            <td class="whitespace-nowrap py-2 text-right font-semibold text-slate-900">{{ Money::format($item->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-slate-500">Aucune ligne enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Totaux --}}
            <div class="mt-5 flex justify-end">
                <dl class="w-full max-w-xs space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-600">Sous-total</dt>
                        <dd class="font-medium text-slate-900">{{ Money::format($quote->subtotal) }}</dd>
                    </div>

                    @if ((float) $quote->discount_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-slate-600">
                                Remise
                                @if ($quote->discount_type?->value === 'percent')
                                    ({{ rtrim(rtrim(number_format((float) $quote->discount_value, 2, ',', ' '), '0'), ',') }} %)
                                @endif
                            </dt>
                            <dd class="font-medium text-rose-600">− {{ Money::format($quote->discount_amount) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <dt class="text-slate-600">TVA {{ rtrim(rtrim(number_format((float) $quote->tax_rate, 2, ',', ' '), '0'), ',') }} %</dt>
                        <dd class="font-medium text-slate-900">{{ Money::format($quote->tax_amount) }}</dd>
                    </div>

                    <div class="mt-1 flex justify-between border-t-2 border-slate-300 pt-2">
                        <dt class="text-base font-bold text-slate-900">Total à payer</dt>
                        <dd class="text-base font-bold text-brand-700">{{ Money::format($quote->total) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Notes et conditions --}}
            @if ($quote->notes)
                <section class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $quote->notes }}</p>
                </section>
            @endif

            @if ($quote->terms)
                <section class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conditions</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $quote->terms }}</p>
                </section>
            @endif

            {{-- Signatures --}}
            <section class="mt-10 grid grid-cols-2 gap-10 text-sm">
                <div>
                    <p class="font-medium text-slate-700">Le client</p>
                    <p class="mt-1 text-xs text-slate-500">Bon pour accord (date et signature)</p>
                    <div class="mt-12 border-t border-slate-300"></div>
                </div>
                <div class="text-right">
                    <p class="font-medium text-slate-700">Pour {{ config('company.name') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Cachet et signature</p>
                    <div class="mt-12 border-t border-slate-300"></div>
                </div>
            </section>

            <footer class="mt-8 border-t border-slate-200 pt-3 text-xs text-slate-400">
                <p>
                    {{ config('company.name') }} · Devis {{ $quote->number }} · Édité le {{ now()->translatedFormat('d F Y à H:i') }}
                    @if ($quote->creator)
                        par {{ $quote->creator->name }}
                    @endif
                </p>
            </footer>
        </main>
    </body>
</html>
