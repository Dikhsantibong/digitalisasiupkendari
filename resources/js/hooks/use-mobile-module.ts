import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { MOBILE_MODULES } from '@/layouts/mobile/modules';
import type { MobileMenu, MobileModule } from '@/layouts/mobile/modules';
import type { Auth } from '@/types';

export type ResolvedMobileModule = {
    module: MobileModule;
    /** Menus the user may open. */
    menus: MobileMenu[];
    /** The current page is the module's home (render the card menu). */
    isHome: boolean;
    /** The menu matching the current page, when there is one. */
    current: MobileMenu | null;
};

/**
 * Resolves which mobile module shell (if any) wraps the current page: only on a
 * phone-sized viewport, for a user holding one of the module's roles, on the
 * module's home or one of its menu pages. Returns null everywhere else so the
 * normal layout renders.
 */
export function useMobileModule(): ResolvedMobileModule | null {
    const page = usePage();
    const isMobile = useIsMobile();

    if (!isMobile) {
        return null;
    }

    const auth = page.props.auth as Auth | undefined;
    const roleNames = (auth?.roles ?? []).map((role) => role.name);
    const permissions = auth?.permissions ?? [];
    const allowed = (permission: string | string[]) =>
        (auth?.isSuperAdmin ?? false) ||
        (Array.isArray(permission) ? permission : [permission]).some((name) =>
            permissions.includes(name),
        );

    for (const module of MOBILE_MODULES) {
        if (
            !module.enabled ||
            !module.roles.some((role) => roleNames.includes(role))
        ) {
            continue;
        }

        const menus = module.menus.filter((menu) => allowed(menu.permission));
        const current =
            menus.find((menu) => menu.component === page.component) ?? null;
        const isHome = module.homeComponents.includes(page.component);

        if (isHome || current) {
            return { module, menus, isHome, current };
        }
    }

    return null;
}

export const MobileShellContext = createContext(false);

/** True when the page is rendered inside a mobile module shell, so it can adapt its layout. */
export function useInMobileShell(): boolean {
    return useContext(MobileShellContext);
}

/**
 * True on a phone-sized viewport or inside a mobile module shell: input pages
 * then swap their tables for touch-friendly cards. Tablets and desktops keep
 * the full table layout.
 */
export function useCompactLayout(): boolean {
    const inMobileShell = useInMobileShell();
    const isMobile = useIsMobile();

    return inMobileShell || isMobile;
}
