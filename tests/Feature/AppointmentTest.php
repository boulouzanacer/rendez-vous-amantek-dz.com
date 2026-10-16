<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('appointments.index'))->assertRedirect(route('login'));
});

test('la liste affiche les rendez-vous avec le client et la référence', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Groupe Amantek']);
    $appointment = Appointment::factory()->forClient($client)->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('Groupe Amantek')
        ->assertSee($appointment->reference);
});

test('la fiche rendez-vous affiche le détail du rendez-vous', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'created_by' => $user->id,
        'scheduled_at' => now()->setTime(9, 30),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($user)
        ->get(route('appointments.show', $appointment))
        ->assertOk()
        ->assertSee($appointment->reference)
        ->assertSee('09:30 – 10:30');
});

test('un utilisateur peut créer un rendez-vous', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    Livewire::actingAs($user)
        ->test('appointments.form')
        ->set('clientId', (string) $client->id)
        ->set('type', 'installation')
        ->set('status', 'confirme')
        ->set('priority', 'haute')
        ->set('scheduledDate', '2026-11-03')
        ->set('scheduledTime', '14:30')
        ->set('durationMinutes', 90)
        ->set('address', 'Cité 200 logements')
        ->set('wilaya', 'Alger')
        ->call('save')
        ->assertHasNoErrors();

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->client_id)->toBe($client->id)
        ->and($appointment->type->value)->toBe('installation')
        ->and($appointment->status->value)->toBe('confirme')
        ->and($appointment->priority->value)->toBe('haute')
        ->and($appointment->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-03 14:30')
        ->and($appointment->duration_minutes)->toBe(90)
        ->and($appointment->created_by)->toBe($user->id)
        ->and($appointment->reference)->toStartWith('RVD-');
});

test('la création échoue sans client sélectionné', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('appointments.form')
        ->set('clientId', '')
        ->set('scheduledDate', '2026-11-03')
        ->set('scheduledTime', '09:00')
        ->call('save')
        ->assertHasErrors(['clientId' => 'required']);

    expect(Appointment::query()->count())->toBe(0);
});

test('un administrateur peut modifier un rendez-vous existant', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('appointments.form', ['appointment' => $appointment])
        ->set('status', 'en_cours')
        ->set('durationMinutes', 120)
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::EnCours)
        ->and($appointment->duration_minutes)->toBe(120);
});

test('un technicien ne peut pas modifier le rendez-vous d\'un autre', function () {
    $technician = User::factory()->technician()->create();
    $other = User::factory()->technician()->create();
    $appointment = Appointment::factory()->assignedTo($other)->create();

    Livewire::actingAs($technician)
        ->test('appointments.show', ['appointment' => $appointment])
        ->call('setStatus', 'termine')
        ->assertForbidden();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Planifie);
});

test('un technicien peut clôturer le rendez-vous qui lui est assigné', function () {
    $technician = User::factory()->technician()->create();
    $appointment = Appointment::factory()->assignedTo($technician)->create();

    Livewire::actingAs($technician)
        ->test('appointments.show', ['appointment' => $appointment])
        ->call('setStatus', 'termine')
        ->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Termine)
        ->and($appointment->completed_at)->not->toBeNull();
});

test('l\'annulation exige un motif', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create(['created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('appointments.show', ['appointment' => $appointment])
        ->call('cancel')
        ->assertHasErrors(['cancelledReason' => 'required']);

    Livewire::actingAs($user)
        ->test('appointments.show', ['appointment' => $appointment])
        ->set('cancelledReason', 'Client injoignable')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Annule)
        ->and($appointment->cancelled_reason)->toBe('Client injoignable');
});

test('un technicien ne peut pas supprimer un rendez-vous', function () {
    $technician = User::factory()->technician()->create();
    $appointment = Appointment::factory()->assignedTo($technician)->create();

    Livewire::actingAs($technician)
        ->test('appointments.show', ['appointment' => $appointment])
        ->call('destroy')
        ->assertForbidden();

    expect(Appointment::query()->count())->toBe(1);
});

test('un administrateur peut supprimer un rendez-vous', function () {
    $admin = User::factory()->create();
    $appointment = Appointment::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test('appointments.show', ['appointment' => $appointment])
        ->call('destroy')
        ->assertHasNoErrors();

    expect(Appointment::query()->count())->toBe(0)
        ->and(Appointment::withTrashed()->count())->toBe(1);
});
