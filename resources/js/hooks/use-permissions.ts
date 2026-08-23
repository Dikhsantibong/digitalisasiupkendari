import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

/**
 * Reads the permissions shared by the server.
 *
 * This governs what the interface offers, never what the server allows: every
 * route is still checked by a policy on the backend.
 */
export function usePermissions() {
    const auth = usePage().props.auth as Auth | undefined;

    const permissions = auth?.permissions ?? [];
    const isSuperAdmin = auth?.isSuperAdmin ?? false;

    /** True when the user holds the permission, or any one of several. */
    const can = (permission: string | string[]): boolean => {
        if (isSuperAdmin) {
            return true;
        }

        return Array.isArray(permission)
            ? permission.some((name) => permissions.includes(name))
            : permissions.includes(permission);
    };

    return {
        can,
        isSuperAdmin,
        hasGlobalAccess: auth?.hasGlobalAccess ?? false,
        roles: auth?.roles ?? [],
        permissions,
    };
}
