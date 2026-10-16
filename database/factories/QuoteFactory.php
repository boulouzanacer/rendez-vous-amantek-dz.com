<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = Carbon::instance(fake()->dateTimeBetween('-2 months', 'now'));

        return [
            'client_id' => Client::factory(),
            'appointment_id' => null,
            'issued_at' => $issuedAt,
            'valid_until' => $issuedAt->copy()->addDays(30),
            'status' => QuoteStatus::Brouillon,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => config('company.tax_rate'),
            'notes' => null,
            'terms' => 'Devis valable 30 jours. Acompte de 50 % à la commande, solde à la réception.',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => QuoteStatus::Brouillon]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => QuoteStatus::Envoye]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => QuoteStatus::Accepte]);
    }

    public function refused(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => QuoteStatus::Refuse]);
    }

    public function discounted(DiscountType $type = DiscountType::Percent, float $value = 5): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => $type,
            'discount_value' => $value,
        ]);
    }
}
