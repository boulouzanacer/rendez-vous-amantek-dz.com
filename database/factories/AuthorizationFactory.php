<?php

namespace Database\Factories;

use App\Enums\AuthorizationStatus;
use App\Models\Authorization;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Authorization>
 */
class AuthorizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'status' => AuthorizationStatus::EnPreparation,
            'wilaya' => null,
            'commune' => null,
            'address' => null,
            'has_inside_cameras' => false,
            'has_outside_cameras' => false,
            'inside_cameras' => null,
            'outside_cameras' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->id,
            'wilaya' => $client->wilaya,
            'commune' => $client->commune,
            'address' => $client->address,
        ]);
    }

    public function interieures(int $quantity = 4, string $reference = 'CAM-INT-01'): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_inside_cameras' => true,
            'inside_cameras' => [['reference' => $reference, 'quantity' => $quantity]],
        ]);
    }

    public function exterieures(int $quantity = 2, string $reference = 'CAM-EXT-01'): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_outside_cameras' => true,
            'outside_cameras' => [['reference' => $reference, 'quantity' => $quantity]],
        ]);
    }

    public function autorisee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuthorizationStatus::AutorisationRetenue,
        ]);
    }
}
