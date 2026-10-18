<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case EnPreparation = 'en_preparation';
    case PremierDossierDepose = 'dossier_1_depose';
    case AcquisitionRetenue = 'acquisition_retenue';
    case DeuxiemeDossierDepose = 'dossier_2_depose';
    case AutorisationRetenue = 'autorisation_retenue';
    case Annule = 'annule';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EnPreparation => 'En préparation',
            self::PremierDossierDepose => '1er dossier déposé',
            self::AcquisitionRetenue => 'Acquisition retenue',
            self::DeuxiemeDossierDepose => '2e dossier déposé',
            self::AutorisationRetenue => 'Autorisation retenue',
            self::Annule => 'Annulé',
            self::Refuse => 'Refusé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnPreparation => 'neutral',
            self::PremierDossierDepose => 'info',
            self::AcquisitionRetenue => 'brand',
            self::DeuxiemeDossierDepose => 'info',
            self::AutorisationRetenue => 'success',
            self::Annule => 'danger',
            self::Refuse => 'danger',
        };
    }

    /**
     * Un dossier clos ne peut plus évoluer.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::AutorisationRetenue, self::Annule, self::Refuse], true);
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
