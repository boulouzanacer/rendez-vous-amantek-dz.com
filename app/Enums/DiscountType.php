<?php

namespace App\Enums;

enum DiscountType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Montant fixe',
            self::Percent => 'Pourcentage',
        };
    }

    public function suffix(): string
    {
        return match ($this) {
            self::Fixed => config('company.currency_symbol'),
            self::Percent => '%',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
