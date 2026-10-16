<?php

namespace App\Support;

final class Money
{
    /**
     * Formate un montant selon les conventions algériennes : 1 250 000,00 DA.
     */
    public static function format(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format(round((float) $amount, 2), 2, ',', ' ');

        return $withSymbol ? $formatted.' '.self::symbol() : $formatted;
    }

    /**
     * Formate un montant sans décimales inutiles : 1 250 000 DA.
     */
    public static function short(float|int|string|null $amount): string
    {
        return number_format(round((float) $amount), 0, ',', ' ').' '.self::symbol();
    }

    /**
     * Version abrégée pour les indicateurs : 1,25 M DA.
     */
    public static function compact(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return match (true) {
            abs($value) >= 1_000_000 => number_format($value / 1_000_000, 2, ',', ' ').' M '.self::symbol(),
            abs($value) >= 1_000 => number_format($value / 1_000, 1, ',', ' ').' K '.self::symbol(),
            default => self::short($value),
        };
    }

    public static function symbol(): string
    {
        return (string) config('company.currency_symbol', 'DA');
    }
}
