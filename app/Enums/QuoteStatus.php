<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Brouillon = 'brouillon';
    case Envoye = 'envoye';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
    case Expire = 'expire';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Envoye => 'Envoyé',
            self::Accepte => 'Accepté',
            self::Refuse => 'Refusé',
            self::Expire => 'Expiré',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Brouillon => 'neutral',
            self::Envoye => 'info',
            self::Accepte => 'success',
            self::Refuse => 'danger',
            self::Expire => 'warning',
        };
    }

    /**
     * Le devis est encore modifiable par son auteur.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Brouillon, self::Envoye], true);
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
