<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AuthorizationStatus;
use App\Enums\InstallationStatus;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Authorization;
use App\Models\Camera;
use App\Models\Client;
use App\Models\Installation;
use App\Models\InstallationItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoSeeder extends Seeder
{
    /**
     * Jeu de données de démonstration réaliste (activité de vidéosurveillance).
     */
    public function run(): void
    {
        $technicians = User::factory()->count(2)->technician()->create();
        $manager = User::query()->where('role', 'admin')->first();

        /** @var Collection<int, Client> $clients */
        $clients = Client::factory()->count(9)->create()
            ->merge(Client::factory()->count(4)->entreprise()->create())
            ->merge(Client::factory()->count(3)->administration()->create())
            ->values();

        $this->seedAgenda($clients, $technicians, $manager);
        $this->seedCommercialActivity($clients, $technicians, $manager);
        $this->seedCameras();
        $this->seedAuthorizations($clients, $manager);
    }

    /**
     * Catalogue de références de caméras.
     */
    private function seedCameras(): void
    {
        $catalogue = [
            ['reference' => 'DS-2CD1023G0-I', 'brand' => 'Hikvision'],
            ['reference' => 'DS-2CD2143G2-I', 'brand' => 'Hikvision'],
            ['reference' => 'DS-2CD2T47G2-L', 'brand' => 'Hikvision'],
            ['reference' => 'IPC-HDW2230T-AS', 'brand' => 'Dahua'],
            ['reference' => 'IPC-HFW2431T-ZS', 'brand' => 'Dahua'],
            ['reference' => 'C3TN', 'brand' => 'EZVIZ'],
            ['reference' => 'IPC2124LE-ADF28KM', 'brand' => 'Uniview'],
            ['reference' => 'VIGI C340', 'brand' => 'TP-Link'],
        ];

        foreach ($catalogue as $camera) {
            Camera::query()->firstOrCreate(
                ['reference' => $camera['reference']],
                ['brand' => $camera['brand'], 'is_active' => true],
            );
        }
    }

    /**
     * Dossiers d'autorisation rattachés aux clients.
     *
     * @param  Collection<int, Client>  $clients
     */
    private function seedAuthorizations(Collection $clients, ?User $manager): void
    {
        $references = Camera::query()->pluck('reference')->all();

        if ($references === []) {
            return;
        }

        $statuses = [
            AuthorizationStatus::EnPreparation,
            AuthorizationStatus::PremierDossierDepose,
            AuthorizationStatus::AcquisitionRetenue,
            AuthorizationStatus::DeuxiemeDossierDepose,
            AuthorizationStatus::AutorisationRetenue,
            AuthorizationStatus::Refuse,
        ];

        foreach ($clients->take(8)->values() as $index => $client) {
            $hasInside = $index % 3 !== 2;
            $hasOutside = $index % 2 === 0;

            Authorization::factory()
                ->forClient($client)
                ->create([
                    'status' => $statuses[$index % count($statuses)],
                    'has_inside_cameras' => $hasInside,
                    'has_outside_cameras' => $hasOutside,
                    'inside_cameras' => $hasInside
                        ? [['reference' => $references[$index % count($references)], 'quantity' => random_int(2, 8)]]
                        : null,
                    'outside_cameras' => $hasOutside
                        ? [['reference' => $references[($index + 3) % count($references)], 'quantity' => random_int(1, 4)]]
                        : null,
                    'notes' => $index % 4 === 0 ? 'Dossier déposé auprès de la sûreté de wilaya.' : null,
                    'created_by' => $manager?->id,
                ]);
        }
    }

    /**
     * Rendez-vous du jour, à venir et passés.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, User>  $technicians
     */
    private function seedAgenda(Collection $clients, Collection $technicians, ?User $manager): void
    {
        $todayTypes = [
            [AppointmentType::Installation, AppointmentStatus::Termine, '08:30', 180],
            [AppointmentType::Visite, AppointmentStatus::Confirme, '11:00', 60],
            [AppointmentType::Devis, AppointmentStatus::Confirme, '14:00', 90],
            [AppointmentType::Sav, AppointmentStatus::Planifie, '15:30', 60],
            [AppointmentType::Maintenance, AppointmentStatus::Planifie, '17:00', 60],
        ];

        foreach ($todayTypes as $index => [$type, $status, $time, $duration]) {
            Appointment::factory()
                ->forClient($clients[$index])
                ->assignedTo($technicians[$index % $technicians->count()])
                ->create([
                    'type' => $type,
                    'status' => $status,
                    'priority' => $type === AppointmentType::Sav ? Priority::Urgente : Priority::Normale,
                    'scheduled_at' => now()->setTimeFromTimeString($time),
                    'duration_minutes' => $duration,
                    'created_by' => $manager?->id,
                ]);
        }

        foreach (range(1, 14) as $index) {
            Appointment::factory()
                ->forClient($clients[$index % $clients->count()])
                ->assignedTo($technicians[$index % $technicians->count()])
                ->upcoming()
                ->create([
                    'status' => $index % 3 === 0 ? AppointmentStatus::Confirme : AppointmentStatus::Planifie,
                    'created_by' => $manager?->id,
                ]);
        }

        foreach (range(1, 18) as $index) {
            $status = match ($index % 6) {
                0 => AppointmentStatus::Absent,
                1 => AppointmentStatus::Annule,
                default => AppointmentStatus::Termine,
            };

            Appointment::factory()
                ->forClient($clients[$index % $clients->count()])
                ->assignedTo($technicians[$index % $technicians->count()])
                ->past()
                ->withStatus($status)
                ->create([
                    'created_by' => $manager?->id,
                    'completed_at' => $status === AppointmentStatus::Termine ? now()->subDays(random_int(1, 25)) : null,
                    'cancelled_reason' => $status === AppointmentStatus::Annule ? 'Report demandé par le client.' : null,
                ]);
        }
    }

    /**
     * Devis, interventions et encaissements.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, User>  $technicians
     */
    private function seedCommercialActivity(Collection $clients, Collection $technicians, ?User $manager): void
    {
        $statuses = [
            QuoteStatus::Accepte, QuoteStatus::Accepte, QuoteStatus::Accepte,
            QuoteStatus::Envoye, QuoteStatus::Envoye,
            QuoteStatus::Brouillon, QuoteStatus::Refuse,
        ];

        foreach ($clients as $index => $client) {
            $status = $statuses[$index % count($statuses)];
            $issuedAt = now()->subDays(random_int(5, 50));

            $quote = Quote::factory()->create([
                'client_id' => $client->id,
                'status' => $status,
                'issued_at' => $issuedAt,
                'valid_until' => $issuedAt->copy()->addDays(30),
                'discount_type' => $index % 4 === 0 ? 'percent' : null,
                'discount_value' => $index % 4 === 0 ? 5 : 0,
                'notes' => $index % 3 === 0 ? 'Prix valables pour une fourniture de matériel garantie 24 mois.' : null,
                'created_by' => $manager?->id,
            ]);

            QuoteItem::factory()
                ->count(random_int(3, 6))
                ->create(['quote_id' => $quote->id])
                ->each(fn (QuoteItem $item, int $position) => $item->update(['position' => $position + 1]));

            $quote->recalculateTotals();

            if ($status !== QuoteStatus::Accepte) {
                continue;
            }

            $technician = $technicians[$index % $technicians->count()];
            $completed = $index % 3 !== 2;

            $installation = Installation::factory()
                ->forClient($client)
                ->create([
                    'quote_id' => $quote->id,
                    'user_id' => $technician->id,
                    'title' => 'Installation vidéosurveillance — '.$client->name,
                    'status' => $completed ? InstallationStatus::Termine : InstallationStatus::EnCours,
                    'scheduled_at' => now()->subDays(random_int(2, 30)),
                    'started_at' => now()->subDays(random_int(2, 30))->setTime(9, 0),
                    'completed_at' => $completed ? now()->subDays(random_int(1, 20))->setTime(16, 30) : null,
                    'created_by' => $manager?->id,
                ]);

            InstallationItem::factory()->count(random_int(2, 4))->create([
                'installation_id' => $installation->id,
            ]);

            Payment::factory()->create([
                'client_id' => $client->id,
                'quote_id' => $quote->id,
                'installation_id' => $installation->id,
                'amount' => round((float) $quote->total * 0.5, 2),
                'method' => $index % 3 === 0 ? PaymentMethod::Virement : PaymentMethod::Espece,
                'paid_at' => now()->subDays(random_int(1, 25)),
                'reference' => $index % 3 === 0 ? 'VIR-'.random_int(100000, 999999) : null,
                'recorded_by' => $manager?->id,
            ]);

            if ($completed && $index % 2 === 0) {
                Payment::factory()->create([
                    'client_id' => $client->id,
                    'quote_id' => $quote->id,
                    'installation_id' => $installation->id,
                    'amount' => round((float) $quote->total * 0.5, 2),
                    'method' => PaymentMethod::Espece,
                    'paid_at' => now()->subDays(random_int(1, 10)),
                    'recorded_by' => $manager?->id,
                ]);
            }
        }
    }
}
