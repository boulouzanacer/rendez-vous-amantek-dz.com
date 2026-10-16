<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Planifie = 'planifie';
    case Confirme = 'confirme';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Reporte = 'reporte';
    case Absent = 'absent';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Planifie => 'Planifié',
            self::Confirme => 'Confirmé',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::Reporte => 'Reporté',
            self::Absent => 'Client absent',
            self::Annule => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planifie => 'neutral',
            self::Confirme => 'brand',
            self::EnCours => 'warning',
            self::Termine => 'success',
            self::Reporte => 'info',
            self::Absent => 'danger',
            self::Annule => 'danger',
        };
    }

    /**
     * Un rendez-vous encore actif peut être modifié et apparaît dans la charge de travail.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planifie, self::Confirme, self::EnCours, self::Reporte], true);
    }

    /**
     * Le rendez-vous n'occupe plus le planning.
     */
    public function isClosed(): bool
    {
        return ! $this->isOpen();
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

    /**
     * @return array<int, self>
     */
    public static function openCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->isOpen()));
    }
}
