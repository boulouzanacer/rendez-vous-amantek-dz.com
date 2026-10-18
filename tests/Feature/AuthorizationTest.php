<?php

use App\Enums\AuthorizationStatus;
use App\Models\Authorization;
use App\Models\Camera;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('authorizations.index'))->assertRedirect(route('login'));
});

test('la liste des autorisations affiche les dossiers', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $authorization = Authorization::factory()->forClient($client)->create();

    $this->actingAs($user)
        ->get(route('authorizations.index'))
        ->assertOk()
        ->assertSee($authorization->reference)
        ->assertSee('Groupe Amantek');
});

test('la liste peut être filtrée par statut', function () {
    $user = User::factory()->create();
    Authorization::factory()->create();
    Authorization::factory()->autorisee()->create();

    Livewire::actingAs($user)
        ->test('authorizations.index')
        ->set('status', AuthorizationStatus::AutorisationRetenue->value)
        ->assertViewHas('authorizations', fn ($authorizations) => $authorizations->total() === 1);
});

test('un utilisateur peut créer un dossier d\'autorisation', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Camera::factory()->create(['reference' => 'DS-2CD2143G2-I', 'brand' => 'Hikvision']);

    Livewire::actingAs($user)
        ->test('authorizations.form')
        ->set('clientId', (string) $client->id)
        ->set('status', 'dossier_1_depose')
        ->set('wilaya', 'Alger')
        ->set('commune', 'Bab Ezzouar')
        ->set('address', 'Cité 200 logements')
        ->set('hasInside', true)
        ->set('insideItems', [['reference' => 'DS-2CD2143G2-I', 'quantity' => '4']])
        ->set('hasOutside', true)
        ->set('outsideItems', [['reference' => 'DS-2CD2143G2-I', 'quantity' => '2']])
        ->call('save')
        ->assertHasNoErrors();

    $authorization = Authorization::query()->firstOrFail();

    expect($authorization->status)->toBe(AuthorizationStatus::PremierDossierDepose)
        ->and($authorization->client_id)->toBe($client->id)
        ->and($authorization->created_by)->toBe($user->id)
        ->and($authorization->reference)->toStartWith('AUT-')
        ->and($authorization->insideList())->toHaveCount(1)
        ->and($authorization->outsideList())->toHaveCount(1)
        ->and($authorization->cameraCount())->toBe(6);
});

test('la création échoue si aucun type de caméra n\'est coché', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('authorizations.form')
        ->set('clientId', (string) $client->id)
        ->call('save')
        ->assertHasErrors(['hasInside']);

    expect(Authorization::query()->count())->toBe(0);
});

test('la création échoue si un type est coché sans référence de caméra', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('authorizations.form')
        ->set('clientId', (string) $client->id)
        ->set('hasInside', true)
        ->set('insideItems', [['reference' => '', 'quantity' => '3']])
        ->call('save')
        ->assertHasErrors(['insideItems']);

    expect(Authorization::query()->count())->toBe(0);
});

test('la fiche autorisation affiche le détail des caméras', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Clinique Es-Salam']);
    $authorization = Authorization::factory()
        ->forClient($client)
        ->interieures(4, 'DS-2CD1043G0-I')
        ->create();

    $this->actingAs($user)
        ->get(route('authorizations.show', $authorization))
        ->assertOk()
        ->assertSee($authorization->reference)
        ->assertSee('Clinique Es-Salam')
        ->assertSee('Caméras intérieures')
        ->assertSee('DS-2CD1043G0-I');
});

test('le statut du dossier peut être mis à jour', function () {
    $user = User::factory()->create();
    $authorization = Authorization::factory()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('authorizations.show', ['authorization' => $authorization])
        ->call('updateStatus', AuthorizationStatus::AutorisationRetenue->value)
        ->assertHasNoErrors();

    expect($authorization->refresh()->status)->toBe(AuthorizationStatus::AutorisationRetenue);
});

test('un technicien non responsable ne peut pas modifier le dossier', function () {
    $technician = User::factory()->technician()->create();
    $other = User::factory()->technician()->create();
    $authorization = Authorization::factory()->create(['created_by' => $other->id]);

    Livewire::actingAs($technician)
        ->test('authorizations.show', ['authorization' => $authorization])
        ->call('updateStatus', AuthorizationStatus::AutorisationRetenue->value)
        ->assertForbidden();
});

test('un technicien ne peut pas supprimer un dossier', function () {
    $technician = User::factory()->technician()->create();
    $authorization = Authorization::factory()->create(['created_by' => $technician->id]);

    Livewire::actingAs($technician)
        ->test('authorizations.show', ['authorization' => $authorization])
        ->call('destroy')
        ->assertForbidden();

    expect(Authorization::query()->count())->toBe(1);
});

test('un administrateur peut supprimer un dossier', function () {
    $admin = User::factory()->create();
    $authorization = Authorization::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test('authorizations.show', ['authorization' => $authorization])
        ->call('destroy')
        ->assertHasNoErrors();

    expect(Authorization::query()->count())->toBe(0)
        ->and(Authorization::withTrashed()->count())->toBe(1);
});

test('un administrateur peut ajouter une référence au catalogue des caméras', function () {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('cameras.index')
        ->set('reference', 'DS-2CD2143G2-I')
        ->set('brand', 'Hikvision')
        ->call('save')
        ->assertHasNoErrors();

    expect(Camera::query()->where('reference', 'DS-2CD2143G2-I')->exists())->toBeTrue();
});

test('la référence du catalogue doit être unique', function () {
    $admin = User::factory()->create();
    Camera::factory()->create(['reference' => 'DS-2CD2143G2-I']);

    Livewire::actingAs($admin)
        ->test('cameras.index')
        ->set('reference', 'DS-2CD2143G2-I')
        ->call('save')
        ->assertHasErrors(['reference' => 'unique']);
});

test('un technicien ne peut pas modifier le catalogue des caméras', function () {
    $technician = User::factory()->technician()->create();

    Livewire::actingAs($technician)
        ->test('cameras.index')
        ->set('reference', 'CAM-X')
        ->call('save')
        ->assertForbidden();

    expect(Camera::query()->count())->toBe(0);
});

test('un administrateur peut supprimer une référence du catalogue', function () {
    $admin = User::factory()->create();
    $camera = Camera::factory()->create();

    Livewire::actingAs($admin)
        ->test('cameras.index')
        ->call('destroy', $camera->id)
        ->assertHasNoErrors();

    expect(Camera::query()->count())->toBe(0);
});
