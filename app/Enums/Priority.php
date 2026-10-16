<?php

namespace App\Enums;

enum Priority: string
{
    case Basse = 'basse';
    case Normale = 'normale';
    case Haute = 'haute';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Basse => 'Basse',
            self::Normale => 'Normale',
            self::Haute => 'Haute',
            self::Urgente => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Basse => 'neutral',
            self::Normale => 'info',
            self::Haute => 'warning',
            self::Urgente => 'danger',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Basse => 1,
            self::Normale => 2,
            self::Haute => 3,
            self::Urgente => 4,
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
