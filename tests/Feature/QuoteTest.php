<?php

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('quotes.index'))->assertRedirect(route('login'));
});

test('la liste des devis affiche le numéro et le client', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $quote = Quote::factory()->for($client)->create();

    $this->actingAs($user)
        ->get(route('quotes.index'))
        ->assertOk()
        ->assertSee($quote->number)
        ->assertSee('Groupe Amantek');
});

test('la liste des devis peut être filtrée par recherche', function () {
    $user = User::factory()->create();
    $clinique = Client::factory()->create(['name' => 'Clinique Es-Salam']);
    $boulangerie = Client::factory()->create(['name' => 'Boulangerie du Coin']);
    Quote::factory()->for($clinique)->create();
    Quote::factory()->for($boulangerie)->create();

    Livewire::actingAs($user)
        ->test('quotes.index')
        ->set('search', 'Clinique')
        ->assertSee('Clinique Es-Salam')
        ->assertDontSee('Boulangerie du Coin');
});

test('un utilisateur peut créer un devis avec ses lignes et ses totaux', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('quotes.form')
        ->set('clientId', (string) $client->id)
        ->set('items', [
            [
                'designation' => 'Caméra IP dôme 4 MP',
                'description' => 'Vision nocturne 30 m',
                'unit' => 'unité',
                'quantity' => '2',
                'unit_price' => '18500',
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $quote = Quote::query()->firstOrFail();

    expect($quote->number)->toStartWith('DEV-')
        ->and($quote->client_id)->toBe($client->id)
        ->and($quote->created_by)->toBe($user->id)
        ->and($quote->items()->count())->toBe(1)
        ->and((float) $quote->subtotal)->toBe(37000.0)
        ->and((float) $quote->tax_amount)->toBe(7030.0)
        ->and((float) $quote->total)->toBe(44030.0);
});

test('la création échoue sans client sélectionné', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('quotes.form')
        ->set('clientId', '')
        ->call('save')
        ->assertHasErrors(['clientId' => 'required']);

    expect(Quote::query()->count())->toBe(0);
});

test('la modification recalcule les totaux à partir des lignes', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('quotes.form', ['quote' => $quote])
        ->set('items', [
            [
                'designation' => 'NVR 8 canaux PoE',
                'description' => '',
                'unit' => 'unité',
                'quantity' => '3',
                'unit_price' => '10000',
            ],
            [
                'designation' => 'Main d\'œuvre',
                'description' => '',
                'unit' => 'forfait',
                'quantity' => '1',
                'unit_price' => '5000',
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($quote->refresh()->items()->count())->toBe(2)
        ->and((float) $quote->subtotal)->toBe(35000.0)
        ->and((float) $quote->tax_amount)->toBe(6650.0)
        ->and((float) $quote->total)->toBe(41650.0);
});

test('la fiche devis affiche les lignes et le total', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['created_by' => $user->id]);
    $quote->items()->create([
        'position' => 1,
        'designation' => 'Switch PoE 8 ports',
        'unit' => 'unité',
        'quantity' => 2,
        'unit_price' => 21500,
        'total' => 43000,
    ]);
    $quote->recalculateTotals();

    $this->actingAs($user)
        ->get(route('quotes.show', $quote))
        ->assertOk()
        ->assertSee($quote->number)
        ->assertSee('Switch PoE 8 ports')
        ->assertSee('Montant total');
});

test('la vue d\'impression du devis est accessible', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['created_by' => $user->id]);
    $quote->items()->create([
        'position' => 1,
        'designation' => 'Onduleur 1000 VA',
        'unit' => 'unité',
        'quantity' => 1,
        'unit_price' => 23000,
        'total' => 23000,
    ]);
    $quote->recalculateTotals();

    $this->actingAs($user)
        ->get(route('quotes.print', $quote))
        ->assertOk()
        ->assertSee($quote->number)
        ->assertSee('Onduleur 1000 VA')
        ->assertSee('Total à payer');
});

test('un devis accepté n\'est plus modifiable', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->accepted()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('quotes.show', ['quote' => $quote])
        ->call('updateStatus', 'brouillon')
        ->assertForbidden();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Accepte);
});

test('un devis envoyé peut être marqué comme accepté', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->sent()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('quotes.show', ['quote' => $quote])
        ->call('updateStatus', 'accepte')
        ->assertHasNoErrors();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Accepte);
});

test('un technicien ne peut pas supprimer un devis', function () {
    $technician = User::factory()->technician()->create();
    $quote = Quote::factory()->create();

    Livewire::actingAs($technician)
        ->test('quotes.show', ['quote' => $quote])
        ->call('destroy')
        ->assertForbidden();

    expect(Quote::query()->count())->toBe(1);
});

test('un administrateur peut supprimer un devis', function () {
    $admin = User::factory()->create();
    $quote = Quote::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test('quotes.show', ['quote' => $quote])
        ->call('destroy')
        ->assertHasNoErrors();

    expect(Quote::query()->count())->toBe(0)
        ->and(Quote::withTrashed()->count())->toBe(1);
});
