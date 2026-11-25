<?php

namespace App\Enums;

enum PrintMethod: string
{
    case Dtf = 'dtf';
    case Embroidery = 'embroidery';
    case Engraving = 'engraving';

    public function label(): string
    {
        return match ($this) {
            self::Dtf => 'DTF print',
            self::Embroidery => 'Embroidery',
            self::Engraving => 'Engraving',
        };
    }
}
