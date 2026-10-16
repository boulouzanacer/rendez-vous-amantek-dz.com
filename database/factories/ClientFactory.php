<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $wilaya = fake()->randomElement(array_keys(config('algeria.communes')));
        $communes = config("algeria.communes.{$wilaya}");

        return [
            'name' => fake()->name(),
            'type' => ClientType::Particulier,
            'phone' => '0'.fake()->randomElement(['5', '6', '7']).fake()->numerify('########'),
            'phone_alt' => null,
            'email' => null,
            'address' => fake()->streetAddress(),
            'commune' => fake()->randomElement($communes),
            'wilaya' => $wilaya,
            'notes' => null,
        ];
    }

    public function entreprise(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => fake()->company(),
            'type' => ClientType::Entreprise,
            'email' => fake()->unique()->companyEmail(),
        ]);
    }

    public function administration(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Direction '.fake()->randomElement(['de la Santé', 'de l\'Éducation', 'des Impôts', 'de l\'Urbanisme']).' '.fake()->randomElement(config('algeria.wilayas')),
            'type' => ClientType::Administration,
            'phone' => '0'.fake()->numerify('2########'),
        ]);
    }
}
