<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\Priority;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = fake()->dateTimeBetween('-1 month', '+1 month');
        $status = AppointmentStatus::Planifie;

        return [
            'client_id' => Client::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(AppointmentType::cases()),
            'status' => $status,
            'priority' => fake()->randomElement([Priority::Normale, Priority::Normale, Priority::Haute, Priority::Urgente, Priority::Basse]),
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120, 180]),
            'time_not_fixed' => false,
            'open_ended' => false,
            'technician_pending' => false,
            'address' => null,
            'commune' => null,
            'wilaya' => null,
            'description' => fake()->optional(0.7)->sentence(),
            'internal_notes' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->id,
            'address' => $client->address,
            'commune' => $client->commune,
            'wilaya' => $client->wilaya,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->id]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => fake()->dateTimeBetween('today 08:00', 'today 18:00'),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+21 days'),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function withStatus(AppointmentStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }

    public function withTechnicians(User ...$technicians): static
    {
        return $this->afterCreating(function (Appointment $appointment) use ($technicians): void {
            $appointment->technicians()->sync(collect($technicians)->pluck('id')->all());
        });
    }

    /**
     * Rendez-vous dont la date et l'heure restent à définir.
     */
    public function timeNotFixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'time_not_fixed' => true,
            'scheduled_at' => null,
        ]);
    }

    public function openEnded(): static
    {
        return $this->state(fn (array $attributes): array => ['open_ended' => true]);
    }

    public function technicianPending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'technician_pending' => true,
            'user_id' => null,
        ]);
    }
}
