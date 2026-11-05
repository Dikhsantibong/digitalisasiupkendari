<?php

namespace App\Enums;

/**
 * The role of a fuel tank in the unit's fuel line, used by the Opname BBM:
 * storage & setlink are counted in the Opname Fisik, service in the Opname
 * Administrasi; transfer tanks are not opnamed.
 */
enum TankJenis: string
{
    case Storage = 'storage';
    case Setlink = 'setlink';
    case Transfer = 'transfer';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Storage => 'Storage',
            self::Setlink => 'Setlink',
            self::Transfer => 'Transfer',
            self::Service => 'Service',
        };
    }
}
