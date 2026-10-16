<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Technicien = 'technicien';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Technicien => 'Technicien',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'brand',
            self::Technicien => 'info',
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
