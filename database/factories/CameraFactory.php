<?php

namespace Database\Factories;

use App\Models\Camera;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Camera>
 */
class CameraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => strtoupper(fake()->unique()->bothify('CAM-####??')),
            'brand' => fake()->randomElement(['Hikvision', 'Dahua', 'EZVIZ', 'Uniview', 'TP-Link']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
