<?php

use App\Models\Appointment;
use App\Models\Camera;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Paramètres', 'subtitle' => 'Configuration et exploitation'])] class extends Component
{
    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = auth()->user();

        return [
            'company' => [
                'name' => config('company.name'),
                'phone' => config('company.phone'),
                'email' => config('company.email'),
                'address' => config('company.address'),
                'currency' => config('company.currency'),
                'currency_symbol' => Money::symbol(),
                'tax_rate' => (float) config('company.tax_rate'),
            ],
            'users' => $user->isAdmin()
                ? User::query()->orderBy('name')->get()
                : User::query()->whereKey($user->id)->get(),
            'canManageUsers' => $user->isAdmin(),
            'cameraCount' => Camera::query()->count(),
            'counts' => [
                'clients' => Client::query()->count(),
                'appointments' => Appointment::query()->count(),
                'quotes' => Quote::query()->count(),
                'installations' => Installation::query()->count(),
                'payments' => Payment::query()->count(),
            ],
            'environment' => [
                'framework' => 'Laravel '.\Illuminate\Foundation\Application::VERSION,
                'php' => 'PHP '.PHP_VERSION,
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'environment' => app()->environment(),
            ],
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-4">
    {{-- Société --}}
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Société</h3>
            <span class="badge-neutral">Via variables d'environnement</span>
        </div>
        <div class="card-body">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Raison sociale</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-white">{{ $company['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Téléphone</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $company['phone'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $company['email'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Adresse</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $company['address'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Devise</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $company['currency'] }} ({{ $company['currency_symbol'] }})</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Taux de TVA par défaut</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $company['tax_rate'] }} %</dd>
                </div>
            </dl>

            <p class="mt-4 flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                Ces informations alimentent l'en-tête de l'application et les devis imprimés. Elles se modifient depuis le fichier <code class="rounded bg-white px-1 py-0.5 font-mono text-[11px] dark:bg-slate-900">.env</code> (variables <code class="rounded bg-white px-1 py-0.5 font-mono text-[11px] dark:bg-slate-900">COMPANY_*</code>).
            </p>
        </div>
    </div>

    {{-- Utilisateurs --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="section-title">Utilisateurs</h3>
            @unless ($canManageUsers)
                <span class="badge-neutral">Votre compte</span>
            @endunless
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $member)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-slate-800 dark:text-slate-100">{{ $member->name }}</td>
                            <td class="text-slate-600 dark:text-slate-300">{{ $member->email }}</td>
                            <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $member->phone ?: '—' }}</td>
                            <td><span class="badge-brand">{{ $member->role->label() }}</span></td>
                            <td>
                                <span class="badge-{{ $member->is_active ? 'success' : 'neutral' }}">
                                    {{ $member->is_active ? 'Actif' : 'Désactivé' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Données --}}
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Données enregistrées</h3>
        </div>
        <div class="card-body grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([
                ['label' => 'Clients', 'value' => $counts['clients'], 'icon' => 'users'],
                ['label' => 'Rendez-vous', 'value' => $counts['appointments'], 'icon' => 'calendar-check'],
                ['label' => 'Devis', 'value' => $counts['quotes'], 'icon' => 'file-text'],
                ['label' => 'Interventions', 'value' => $counts['installations'], 'icon' => 'wrench'],
                ['label' => 'Paiements', 'value' => $counts['payments'], 'icon' => 'wallet'],
            ] as $item)
                <div class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-800">
                    <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                    </span>
                    <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ $item['value'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Catalogue --}}
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Catalogue</h3>
        </div>
        <div class="card-body">
            <a
                href="{{ route('settings.cameras') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-800 dark:hover:border-brand-800 dark:hover:bg-brand-950/30"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <x-icon name="camera" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Caméras</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $cameraCount }} référence(s) au catalogue</span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
            </a>
        </div>
    </div>

    {{-- Application --}}
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Application</h3>
        </div>
        <div class="card-body">
            <dl class="grid gap-4 sm:grid-cols-3">
                @foreach ($environment as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</div>
