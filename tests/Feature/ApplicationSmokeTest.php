<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;

test('toutes les pages de l\'application se chargent pour un administrateur', function () {
    $admin = User::factory()->create();

    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $appointment = Appointment::factory()->forClient($client)->create(['created_by' => $admin->id]);
    $quote = Quote::factory()->for($client)->create(['created_by' => $admin->id]);
    $quote->items()->create([
        'position' => 1,
        'designation' => 'Caméra IP dôme 4 MP',
        'unit' => 'unité',
        'quantity' => 4,
        'unit_price' => 18500,
        'total' => 74000,
    ]);
    $quote->recalculateTotals();

    $installation = Installation::factory()->forClient($client)->create(['quote_id' => $quote->id]);
    $payment = Payment::factory()->create(['client_id' => $client->id, 'quote_id' => $quote->id, 'paid_at' => now()]);

    $pages = [
        route('dashboard'),
        route('agenda'),
        route('appointments.index'),
        route('appointments.create'),
        route('appointments.show', $appointment),
        route('appointments.edit', $appointment),
        route('clients.index'),
        route('clients.create'),
        route('clients.show', $client),
        route('clients.edit', $client),
        route('quotes.index'),
        route('quotes.create'),
        route('quotes.show', $quote),
        route('quotes.edit', $quote),
        route('quotes.print', $quote),
        route('installations.index'),
        route('installations.create'),
        route('installations.show', $installation),
        route('installations.edit', $installation),
        route('payments.index'),
        route('payments.create'),
        route('payments.edit', $payment),
        route('reports.index'),
        route('settings'),
        route('profile'),
    ];

    foreach ($pages as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

test('la page rapports synthétise l\'activité', function () {
    $admin = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Clinique Es-Salam']);
    $quote = Quote::factory()->accepted()->for($client)->create(['created_by' => $admin->id]);
    $quote->items()->create([
        'position' => 1,
        'designation' => 'NVR 8 canaux PoE',
        'unit' => 'unité',
        'quantity' => 1,
        'unit_price' => 42000,
        'total' => 42000,
    ]);
    $quote->recalculateTotals();

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Affaires signées')
        ->assertSee('Taux d\'acceptation')
        ->assertSee('Meilleurs clients')
        ->assertSee('Clinique Es-Salam');
});

test('la page paramètres affiche la configuration de la société', function () {
    $admin = User::factory()->create(['name' => 'Nacer Boulouza']);

    $this->actingAs($admin)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee(config('company.name'))
        ->assertSee('Nacer Boulouza')
        ->assertSee('Données enregistrées');
});
