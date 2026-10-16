<?php

namespace Database\Factories;

use App\Models\Installation;
use App\Models\InstallationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationItem>
 */
class InstallationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installation_id' => Installation::factory(),
            'designation' => fake()->randomElement([
                'Caméra IP dôme 4 MP',
                'Caméra IP bullet 2 MP',
                'Enregistreur NVR 8 canaux PoE',
                'Disque dur surveillance 2 To',
                'Switch PoE 8 ports Gigabit',
                'Onduleur 1000 VA',
            ]),
            'serial_number' => mb_strtoupper(fake()->bothify('??####-####')),
            'quantity' => fake()->numberBetween(1, 8),
            'unit' => 'unité',
            'notes' => null,
        ];
    }
}
