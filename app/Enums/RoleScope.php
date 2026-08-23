<?php

namespace App\Enums;

/**
 * Determines how far a role's data visibility reaches.
 */
enum RoleScope: string
{
    case Global = 'global';
    case ServiceUnit = 'service_unit';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global (UP Kendari)',
            self::ServiceUnit => 'Unit Layanan (UL)',
            self::Unit => 'Unit Pembangkit',
        };
    }

    /**
     * Whether an assignment for this scope must reference a service unit.
     */
    public function requiresServiceUnit(): bool
    {
        return $this === self::ServiceUnit;
    }

    /**
     * Whether an assignment for this scope must reference a generating unit.
     */
    public function requiresUnit(): bool
    {
        return $this === self::Unit;
    }
}
