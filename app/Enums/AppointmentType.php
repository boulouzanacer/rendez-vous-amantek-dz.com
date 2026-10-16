<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Visite = 'visite';
    case Devis = 'devis';
    case Installation = 'installation';
    case Maintenance = 'maintenance';
    case Sav = 'sav';

    public function label(): string
    {
        return match ($this) {
            self::Visite => 'Visite technique',
            self::Devis => 'Établissement de devis',
            self::Installation => 'Installation',
            self::Maintenance => 'Maintenance',
            self::Sav => 'SAV / Dépannage',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Visite => 'info',
            self::Devis => 'brand',
            self::Installation => 'success',
            self::Maintenance => 'warning',
            self::Sav => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Visite => 'search',
            self::Devis => 'file-text',
            self::Installation => 'wrench',
            self::Maintenance => 'refresh',
            self::Sav => 'alert',
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
