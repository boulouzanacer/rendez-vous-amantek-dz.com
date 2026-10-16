<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Espece = 'espece';
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Espece => 'Espèces',
            self::Virement => 'Virement bancaire',
            self::Cheque => 'Chèque',
            self::Autre => 'Autre',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Espece => 'success',
            self::Virement => 'info',
            self::Cheque => 'brand',
            self::Autre => 'neutral',
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
