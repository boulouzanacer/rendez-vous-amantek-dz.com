<?php

namespace App\Enums;

enum InstallationStatus: string
{
    case Planifie = 'planifie';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Suspendu = 'suspendu';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Planifie => 'Planifié',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::Suspendu => 'Suspendu',
            self::Annule => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planifie => 'neutral',
            self::EnCours => 'brand',
            self::Termine => 'success',
            self::Suspendu => 'warning',
            self::Annule => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Planifie, self::EnCours, self::Suspendu], true);
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
