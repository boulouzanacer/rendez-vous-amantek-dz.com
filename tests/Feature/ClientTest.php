<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

test('la liste des clients affiche le portefeuille', function () {
    $user = User::factory()->create();
    Client::factory()->create(['name' => 'SARL Amantek Solutions', 'phone' => '0555112233']);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('SARL Amantek Solutions')
        ->assertSee('0555112233');
});

test('la liste des clients peut être filtrée par recherche', function () {
    $user = User::factory()->create();
    Client::factory()->create(['name' => 'Boulangerie du Coin']);
    Client::factory()->create(['name' => 'Clinique Es-Salam']);

    Livewire::actingAs($user)
        ->test('clients.index')
        ->set('search', 'Clinique')
        ->assertSee('Clinique Es-Salam')
        ->assertDontSee('Boulangerie du Coin');
});

test('un utilisateur peut créer un client', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('clients.form')
        ->set('name', 'EURL Vision Sécurité')
        ->set('type', 'entreprise')
        ->set('phone', '0770123456')
        ->set('email', 'contact@vision.dz')
        ->set('wilaya', 'Oran')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::query()->firstOrFail();

    expect($client->name)->toBe('EURL Vision Sécurité')
        ->and($client->type->value)->toBe('entreprise')
        ->and($client->phone)->toBe('0770123456')
        ->and($client->wilaya)->toBe('Oran')
        ->and($client->created_by)->toBe($user->id);
});

test('la création échoue sans nom ni téléphone', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('clients.form')
        ->set('name', '')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'phone' => 'required']);

    expect(Client::query()->count())->toBe(0);
});

test('un utilisateur peut modifier une fiche client', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Ancien nom']);

    Livewire::actingAs($user)
        ->test('clients.form', ['client' => $client])
        ->set('name', 'Nouveau nom')
        ->call('save')
        ->assertHasNoErrors();

    expect($client->refresh()->name)->toBe('Nouveau nom');
});

test('la fiche client affiche l\'historique', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $appointment = Appointment::factory()->forClient($client)->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Groupe Amantek')
        ->assertSee($appointment->reference)
        ->assertSee('Reste à encaisser');
});

test('un technicien ne peut pas supprimer un client', function () {
    $technician = User::factory()->technician()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($technician)
        ->test('clients.show', ['client' => $client])
        ->call('destroy')
        ->assertForbidden();

    expect(Client::query()->count())->toBe(1);
});

test('un administrateur peut supprimer un client', function () {
    $admin = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test('clients.show', ['client' => $client])
        ->call('destroy')
        ->assertHasNoErrors();

    expect(Client::query()->count())->toBe(0)
        ->and(Client::withTrashed()->count())->toBe(1);
});
