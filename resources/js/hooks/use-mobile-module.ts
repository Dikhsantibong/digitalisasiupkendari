import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { createContext, useContext } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { MOBILE_MODULES } from '@/layouts/mobile/modules';
import type { MobileMenu, MobileModule } from '@/layouts/mobile/modules';
import type { Auth } from '@/types';

/** Account pages every shell user reaches (from the bell), shown inside the shell rather than the desktop layout. */
const SHELL_PAGES: MobileMenu[] = [
    {
        key: 'notifikasi',
        group: 'umum',
        title: 'Notifikasi',
        description: 'Pengingat jadwal & absen',
        icon: Bell,
        tone: 'bg-primary/10 text-primary',
        href: '/notifikasi',
        component: 'notifications/index',
        permission: [],
    },
];

export type ResolvedMobileModule = {
    module: MobileModule;
    /** Menus the user may open. */
    menus: MobileMenu[];
    /** The current page is the module's home (render the card menu). */
    isHome: boolean;
    /** The menu matching the current page — or, on a sub-page, the menu it was opened from. */
    current: MobileMenu | null;
    /** Top bar title: the menu's title, or the sub-page's own (e.g. "Laporan Pengusahaan"). */
    title: string;
    /** Where the top bar's back arrow goes: the module home, or the parent menu of a sub-page. */
    backHref: string;
};

/**
 * Titles of pages opened from a menu page in the same folder (document
 * editors, previews …), shown in the shell's top bar.
 */
const SUB_PAGE_TITLES: Record<string, string> = {
    document: 'Dokumen Laporan',
    pengusahaan: 'Laporan Pengusahaan',
    monthly: 'Laporan Bulanan',
    spreadsheet: 'Spreadsheet Laporan',
    editor: 'Isi Berita Acara',
    preview: 'Pratinjau Berita Acara',
};

/**
 * The menu a sub-page belongs to: the menu whose page sits in the same folder
 * as `{folder}/index` (e.g. operasi/laporan/pengusahaan → operasi/laporan/index,
 * pengusahaan/operasi/berita-acara/editor → …/berita-acara/index).
 */
function parentMenuOf(
    component: string,
    menus: MobileMenu[],
): MobileMenu | null {
    const slash = component.lastIndexOf('/');

    if (slash < 0 || component.endsWith('/index')) {
        return null;
    }

    const index = `${component.slice(0, slash)}/index`;

    return menus.find((menu) => menu.component === index) ?? null;
}

/**
 * The mobile module whose shell would wrap the current page on a phone: for a
 * user holding one of the module's roles, on the module's home or one of its
 * menu pages. The viewport is not considered here (unknown while rendering on
 * the server).
 */
export function useMobileModuleCandidate(): ResolvedMobileModule | null {
    const page = usePage();
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
        const menu =
            menus.find((item) => item.component === page.component) ??
            SHELL_PAGES.find((item) => item.component === page.component) ??
            null;
        // A page opened from a menu page (document editor, preview …) stays in the shell; back returns to that menu.
        const parent = menu ? null : parentMenuOf(page.component, menus);
        const current = menu ?? parent;
        const isHome = module.homeComponents.includes(page.component);

        if (isHome || current) {
            const subPage = page.component.slice(
                page.component.lastIndexOf('/') + 1,
            );

            return {
                module,
                menus,
                isHome,
                current,
                title: parent
                    ? (SUB_PAGE_TITLES[subPage] ?? parent.title)
                    : (current?.title ?? module.title),
                backHref: parent ? parent.href : module.homeHref,
            };
        }
    }

    return null;
}

/**
 * Resolves which mobile module shell (if any) wraps the current page: only on a
 * phone-sized viewport. Returns null everywhere else so the normal layout
 * renders.
 */
export function useMobileModule(): ResolvedMobileModule | null {
    const candidate = useMobileModuleCandidate();
    const isMobile = useIsMobile();

    return isMobile ? candidate : null;
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
