<?php

namespace App\Enums;

enum TierScope: string
{
    case ProductDesign = 'product_design';
    case Design = 'design';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::ProductDesign => 'Per product and design',
            self::Design => 'Per design across products',
            self::Order => 'Whole order',
        };
    }
}
