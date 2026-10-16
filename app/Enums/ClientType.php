<?php

namespace App\Enums;

enum ClientType: string
{
    case Particulier = 'particulier';
    case Entreprise = 'entreprise';
    case Administration = 'administration';

    public function label(): string
    {
        return match ($this) {
            self::Particulier => 'Particulier',
            self::Entreprise => 'Entreprise',
            self::Administration => 'Administration',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Particulier => 'neutral',
            self::Entreprise => 'brand',
            self::Administration => 'info',
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
