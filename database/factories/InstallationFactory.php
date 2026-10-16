<?php

namespace Database\Factories;

use App\Enums\InstallationStatus;
use App\Models\Client;
use App\Models\Installation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'quote_id' => null,
            'user_id' => null,
            'title' => 'Installation de vidéosurveillance',
            'status' => InstallationStatus::Planifie,
            'scheduled_at' => fake()->dateTimeBetween('-15 days', '+20 days'),
            'started_at' => null,
            'completed_at' => null,
            'site_address' => null,
            'commune' => null,
            'wilaya' => null,
            'description' => fake()->optional(0.8)->sentence(),
            'notes' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->id,
            'site_address' => $client->address,
            'commune' => $client->commune,
            'wilaya' => $client->wilaya,
        ]);
    }

    public function enCours(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InstallationStatus::EnCours,
            'started_at' => now()->subHours(3),
        ]);
    }

    public function terminee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InstallationStatus::Termine,
            'started_at' => now()->subDays(2)->setTime(9, 0),
            'completed_at' => now()->subDays(2)->setTime(16, 30),
        ]);
    }
}
