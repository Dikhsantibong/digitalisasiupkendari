<?php

namespace App\Enums;

/**
 * The contract package a generating unit belongs to: the 11 KIT or the 6 KIT
 * contract. Set in Master Unit; meant to split responsibilities per contract.
 */
enum UnitContract: string
{
    case Kit11 = 'kit_11';
    case Kit6 = 'kit_6';

    public function label(): string
    {
        return match ($this) {
            self::Kit11 => '11 KIT',
            self::Kit6 => '6 KIT',
        };
    }
}
