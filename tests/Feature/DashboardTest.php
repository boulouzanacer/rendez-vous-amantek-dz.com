<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('un utilisateur authentifié accède à son tableau de bord', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Programme du jour')
        ->assertSee('Votre programme et votre activité en temps réel');
});

test('le tableau de bord affiche les rendez-vous du jour', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'SARL Horizon Sécurité']);
    $technician = User::factory()->technician()->create();

    Appointment::factory()
        ->forClient($client)
        ->assignedTo($technician)
        ->create([
            'scheduled_at' => now()->setTime(9, 30),
            'created_by' => $user->id,
        ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('SARL Horizon Sécurité')
        ->assertSee('09:30');
});

test('un administrateur peut clôturer un rendez-vous du jour', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'scheduled_at' => now()->setTime(10, 0),
        'created_by' => $user->id,
    ]);

    Livewire\Livewire::actingAs($user)
        ->test('dashboard')
        ->call('complete', $appointment->id)
        ->assertHasNoErrors();

    expect($appointment->refresh()->status->value)->toBe('termine');
    expect($appointment->completed_at)->not->toBeNull();
});
