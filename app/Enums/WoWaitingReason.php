<?php

namespace App\Enums;

/**
 * Why a Work Order is on hold (used for the "WO Waiting" report sections).
 */
enum WoWaitingReason: string
{
    case Shutdown = 'shutdown';
    case Material = 'material';
    case Jasa = 'jasa';

    public function label(): string
    {
        return match ($this) {
            self::Shutdown => 'Menunggu Shutdown',
            self::Material => 'Menunggu Material',
            self::Jasa => 'Menunggu Jasa',
        };
    }
}
