<?php

namespace App\Enums;

enum ProductType: string
{
    case TShirt = 'tshirt';
    case Polo = 'polo';
    case Hoodie = 'hoodie';
    case Cap = 'cap';
    case Bracelet = 'bracelet';

    public function label(): string
    {
        return match ($this) {
            self::TShirt => 'T-shirt',
            self::Polo => 'Polo',
            self::Hoodie => 'Hoodie',
            self::Cap => 'Cap',
            self::Bracelet => 'Bracelet',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::TShirt => 'T-shirts',
            self::Polo => 'Polos',
            self::Hoodie => 'Hoodies',
            self::Cap => 'Caps',
            self::Bracelet => 'Bracelets',
        };
    }

    /** Bracelets use a text field and style picker instead of the full canvas. */
    public function isTextOnly(): bool
    {
        return $this === self::Bracelet;
    }
}
