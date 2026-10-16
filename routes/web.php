<?php

use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('agenda', 'agenda')->name('agenda');

    // Rendez-vous
    Volt::route('rendez-vous', 'appointments.index')->name('appointments.index');
    Volt::route('rendez-vous/nouveau', 'appointments.form')->name('appointments.create');
    Volt::route('rendez-vous/{appointment}', 'appointments.show')->name('appointments.show');
    Volt::route('rendez-vous/{appointment}/modifier', 'appointments.form')->name('appointments.edit');

    // Clients
    Volt::route('clients', 'clients.index')->name('clients.index');
    Volt::route('clients/nouveau', 'clients.form')->name('clients.create');
    Volt::route('clients/{client}', 'clients.show')->name('clients.show');
    Volt::route('clients/{client}/modifier', 'clients.form')->name('clients.edit');

    // Devis
    Volt::route('devis', 'quotes.index')->name('quotes.index');
    Volt::route('devis/nouveau', 'quotes.form')->name('quotes.create');
    Volt::route('devis/{quote}', 'quotes.show')->name('quotes.show');
    Volt::route('devis/{quote}/modifier', 'quotes.form')->name('quotes.edit');
    Route::get('devis/{quote}/impression', function (Quote $quote) {
        Gate::authorize('view', $quote);

        return view('quotes.print', ['quote' => $quote->load('client', 'items', 'creator')]);
    })->name('quotes.print');

    // Interventions
    Volt::route('interventions', 'installations.index')->name('installations.index');
    Volt::route('interventions/nouvelle', 'installations.form')->name('installations.create');
    Volt::route('interventions/{installation}', 'installations.show')->name('installations.show');
    Volt::route('interventions/{installation}/modifier', 'installations.form')->name('installations.edit');

    // Paiements
    Volt::route('paiements', 'payments.index')->name('payments.index');
    Volt::route('paiements/nouveau', 'payments.form')->name('payments.create');
    Volt::route('paiements/{payment}/modifier', 'payments.form')->name('payments.edit');

    // Analyse et configuration
    Volt::route('rapports', 'reports')->name('reports.index');
    Volt::route('parametres', 'settings')->name('settings');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
