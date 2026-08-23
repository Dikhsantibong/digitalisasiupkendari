<?php

namespace App\Enums;

/**
 * The audited events recorded to the activity log.
 */
enum ActivityEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionsUpdated = 'permissions_updated';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Dibuat',
            self::Updated => 'Diubah',
            self::Deleted => 'Dihapus',
            self::Restored => 'Dipulihkan',
            self::LoggedIn => 'Masuk',
            self::LoggedOut => 'Keluar',
            self::RoleAssigned => 'Role Ditugaskan',
            self::RoleRevoked => 'Role Dicabut',
            self::PermissionsUpdated => 'Permission Diubah',
            self::Exported => 'Diekspor',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Created, self::Restored, self::RoleAssigned => 'success',
            self::Deleted, self::RoleRevoked => 'danger',
            self::Updated, self::PermissionsUpdated => 'warning',
            default => 'neutral',
        };
    }
}
