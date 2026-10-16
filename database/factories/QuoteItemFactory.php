<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * Équipements et prestations courants en vidéosurveillance et réseaux.
     *
     * @var array<int, array{0: string, 1: string, 2: float}>
     */
    private const CATALOG = [
        ['Caméra IP dôme 4 MP, vision nocturne 30 m', 'unité', 18500],
        ['Caméra IP bullet 2 MP, IP66', 'unité', 12500],
        ['Caméra PTZ 4 MP, zoom 20x', 'unité', 89000],
        ['Enregistreur NVR 8 canaux PoE', 'unité', 42000],
        ['Enregistreur NVR 16 canaux PoE', 'unité', 68000],
        ['Disque dur surveillance 1 To', 'unité', 12500],
        ['Disque dur surveillance 2 To', 'unité', 19000],
        ['Switch PoE 8 ports Gigabit', 'unité', 21500],
        ['Câble réseau UTP Cat6 extérieur', 'mètre', 145],
        ['Gaine de protection TPC Ø 25', 'mètre', 95],
        ['Connecteurs RJ45 blindés', 'boîte', 2500],
        ['Alimentation stabilisée 12V 5A', 'unité', 4200],
        ['Boîtier de raccordement étanche', 'unité', 3500],
        ['Support de fixation murale', 'unité', 1800],
        ['Onduleur 1000 VA', 'unité', 23000],
        ['Main d\'œuvre pose et câblage', 'forfait', 35000],
        ['Configuration réseau, VLAN et accès distant', 'forfait', 15000],
        ['Formation utilisateur à l\'exploitation', 'forfait', 8000],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$designation, $unit, $unitPrice] = fake()->randomElement(self::CATALOG);
        $quantity = $unit === 'mètre' ? fake()->numberBetween(40, 300)
            : ($unit === 'forfait' ? 1 : fake()->numberBetween(1, 12));

        return [
            'quote_id' => Quote::factory(),
            'position' => fake()->numberBetween(1, 20),
            'designation' => $designation,
            'description' => null,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }
}
