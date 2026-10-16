<?php

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('payments.index'))->assertRedirect(route('login'));
});

test('la liste des paiements affiche les encaissements', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Clinique Es-Salam']);
    Payment::factory()->create([
        'client_id' => $client->id,
        'amount' => 120000,
        'paid_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertSee('Clinique Es-Salam')
        ->assertSee('120 000,00');
});

test('la liste des paiements peut être filtrée par moyen de paiement', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Client Virement']);
    Payment::factory()->create(['client_id' => $client->id, 'method' => PaymentMethod::Virement, 'paid_at' => now()]);
    Payment::factory()->create(['client_id' => $client->id, 'method' => PaymentMethod::Espece, 'paid_at' => now()]);

    Livewire::actingAs($user)
        ->test('payments.index')
        ->set('method', PaymentMethod::Virement->value)
        ->set('period', 'tout')
        ->assertSee('Virement bancaire')
        ->assertSee('1 paiement(s)');
});

test('un utilisateur peut enregistrer un paiement', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('payments.form')
        ->set('clientId', (string) $client->id)
        ->set('amount', '75000')
        ->set('method', 'virement')
        ->set('paidAt', '2026-10-20')
        ->set('reference', 'BRD-2026-014')
        ->call('save')
        ->assertHasNoErrors();

    $payment = Payment::query()->firstOrFail();

    expect($payment->client_id)->toBe($client->id)
        ->and((float) $payment->amount)->toBe(75000.0)
        ->and($payment->method)->toBe(PaymentMethod::Virement)
        ->and($payment->paid_at->toDateString())->toBe('2026-10-20')
        ->and($payment->reference)->toBe('BRD-2026-014')
        ->and($payment->recorded_by)->toBe($user->id);
});

test('l\'enregistrement échoue sans montant valide', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('payments.form')
        ->set('clientId', (string) $client->id)
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['amount' => 'min']);

    expect(Payment::query()->count())->toBe(0);
});

test('un technicien ne peut pas modifier un paiement', function () {
    $technician = User::factory()->technician()->create();
    $payment = Payment::factory()->create(['amount' => 50000, 'paid_at' => now()]);

    $this->actingAs($technician)
        ->get(route('payments.edit', $payment))
        ->assertForbidden();

    expect((float) $payment->refresh()->amount)->toBe(50000.0);
});

test('un technicien ne peut pas supprimer un paiement', function () {
    $technician = User::factory()->technician()->create();
    $payment = Payment::factory()->create(['paid_at' => now()]);

    Livewire::actingAs($technician)
        ->test('payments.index')
        ->call('destroy', $payment->id)
        ->assertForbidden();

    expect(Payment::query()->count())->toBe(1);
});

test('un administrateur peut supprimer un paiement', function () {
    $admin = User::factory()->create();
    $payment = Payment::factory()->create(['paid_at' => now()]);

    Livewire::actingAs($admin)
        ->test('payments.index')
        ->call('destroy', $payment->id)
        ->assertHasNoErrors();

    expect(Payment::query()->count())->toBe(0);
});
