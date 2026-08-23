<?php

namespace App\Enums;

/**
 * Operational condition of a generating unit.
 */
enum UnitStatus: string
{
    case Operating = 'operating';
    case Standby = 'standby';
    case Maintenance = 'maintenance';
    case Outage = 'outage';

    public function label(): string
    {
        return match ($this) {
            self::Operating => 'Beroperasi',
            self::Standby => 'Siaga',
            self::Maintenance => 'Pemeliharaan',
            self::Outage => 'Gangguan',
        };
    }

    /**
     * Semantic tone used by the interface, per the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Operating => 'success',
            self::Standby => 'info',
            self::Maintenance => 'warning',
            self::Outage => 'danger',
        };
    }
}
