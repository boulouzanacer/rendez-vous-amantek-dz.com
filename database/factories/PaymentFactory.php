<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'quote_id' => null,
            'installation_id' => null,
            'amount' => fake()->randomElement([25000, 40000, 50000, 75000, 100000, 150000]),
            'method' => fake()->randomElement([PaymentMethod::Espece, PaymentMethod::Espece, PaymentMethod::Virement, PaymentMethod::Cheque]),
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'reference' => null,
            'notes' => fake()->optional(0.4)->sentence(4),
        ];
    }
}
