<?php

use App\Enums\InstallationStatus;
use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('installations.index'))->assertRedirect(route('login'));
});

test('la liste des interventions affiche les chantiers', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $installation = Installation::factory()->forClient($client)->create(['title' => 'Pose 6 caméras']);

    $this->actingAs($user)
        ->get(route('installations.index'))
        ->assertOk()
        ->assertSee($installation->reference)
        ->assertSee('Pose 6 caméras')
        ->assertSee('Groupe Amantek');
});

test('la liste des interventions peut être filtrée par statut', function () {
    $user = User::factory()->create();
    Installation::factory()->create(['title' => 'Chantier planifié']);
    Installation::factory()->terminee()->create(['title' => 'Chantier terminé']);

    Livewire::actingAs($user)
        ->test('installations.index')
        ->set('status', InstallationStatus::Termine->value)
        ->assertSee('Chantier terminé')
        ->assertDontSee('Chantier planifié');
});

test('un utilisateur peut planifier une intervention', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $technician = User::factory()->technician()->create();

    Livewire::actingAs($user)
        ->test('installations.form')
        ->set('clientId', (string) $client->id)
        ->set('technicianId', (string) $technician->id)
        ->set('title', 'Installation 8 caméras + NVR')
        ->set('status', 'planifie')
        ->set('scheduledDate', '2026-11-05')
        ->set('scheduledTime', '08:30')
        ->set('siteAddress', 'Cité 200 logements, bâtiment C')
        ->set('wilaya', 'Alger')
        ->set('items', [
            [
                'designation' => 'Caméra IP dôme 4 MP',
                'serial_number' => 'SN-0001',
                'quantity' => '4',
                'unit' => 'unité',
                'notes' => 'Entrée principale',
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $installation = Installation::query()->firstOrFail();

    expect($installation->title)->toBe('Installation 8 caméras + NVR')
        ->and($installation->status)->toBe(InstallationStatus::Planifie)
        ->and($installation->user_id)->toBe($technician->id)
        ->and($installation->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-05 08:30')
        ->and($installation->created_by)->toBe($user->id)
        ->and($installation->reference)->toStartWith('INT-')
        ->and($installation->items()->count())->toBe(1);
});

test('la création échoue sans client sélectionné', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('installations.form')
        ->set('clientId', '')
        ->call('save')
        ->assertHasErrors(['clientId' => 'required']);

    expect(Installation::query()->count())->toBe(0);
});

test('la fiche intervention affiche le détail du chantier', function () {
    $user = User::factory()->create();
    $installation = Installation::factory()->create([
        'title' => 'Maintenance annuelle',
        'scheduled_at' => now()->setTime(9, 0),
    ]);

    $this->actingAs($user)
        ->get(route('installations.show', $installation))
        ->assertOk()
        ->assertSee($installation->reference)
        ->assertSee('Maintenance annuelle')
        ->assertSee('Matériel posé');
});

test('un technicien non assigné ne peut pas faire avancer le chantier', function () {
    $technician = User::factory()->technician()->create();
    $other = User::factory()->technician()->create();
    $installation = Installation::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($technician)
        ->test('installations.show', ['installation' => $installation])
        ->call('updateStatus', 'en_cours')
        ->assertForbidden();

    expect($installation->refresh()->status)->toBe(InstallationStatus::Planifie);
});

test('un technicien assigné peut démarrer et clôturer le chantier', function () {
    $technician = User::factory()->technician()->create();
    $installation = Installation::factory()->create(['user_id' => $technician->id]);

    Livewire::actingAs($technician)
        ->test('installations.show', ['installation' => $installation])
        ->call('updateStatus', 'en_cours')
        ->assertHasNoErrors();

    expect($installation->refresh()->status)->toBe(InstallationStatus::EnCours)
        ->and($installation->started_at)->not->toBeNull();

    Livewire::actingAs($technician)
        ->test('installations.show', ['installation' => $installation])
        ->call('updateStatus', 'termine')
        ->assertHasNoErrors();

    expect($installation->refresh()->status)->toBe(InstallationStatus::Termine)
        ->and($installation->completed_at)->not->toBeNull();
});

test('un technicien ne peut pas supprimer une intervention', function () {
    $technician = User::factory()->technician()->create();
    $installation = Installation::factory()->create(['user_id' => $technician->id]);

    Livewire::actingAs($technician)
        ->test('installations.show', ['installation' => $installation])
        ->call('destroy')
        ->assertForbidden();

    expect(Installation::query()->count())->toBe(1);
});

test('un administrateur peut supprimer une intervention', function () {
    $admin = User::factory()->create();
    $installation = Installation::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test('installations.show', ['installation' => $installation])
        ->call('destroy')
        ->assertHasNoErrors();

    expect(Installation::query()->count())->toBe(0)
        ->and(Installation::withTrashed()->count())->toBe(1);
});
