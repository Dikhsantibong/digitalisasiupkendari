<?php

namespace App\Enums;

/**
 * The kind of item a physical stock take counts.
 */
enum StockItemType: string
{
    case Fuel = 'fuel';
    case Lubricant = 'lubricant';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => 'Bahan Bakar',
            self::Lubricant => 'Pelumas',
        };
    }
}
