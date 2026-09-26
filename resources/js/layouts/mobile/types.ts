import type { LucideIcon } from 'lucide-react';

/** The sections of the phone card menu, in display order. */
export const MOBILE_MENU_GROUPS = [
    { key: 'umum', label: 'Umum' },
    { key: 'operasi', label: 'Input Operasi' },
    { key: 'har-input', label: 'Input Pemeliharaan' },
    { key: 'har-formulir', label: 'Formulir Pemeliharaan' },
] as const;

export type MobileMenuGroup = (typeof MOBILE_MENU_GROUPS)[number]['key'];

export type MobileMenu = {
    key: string;
    group: MobileMenuGroup;
    title: string;
    /** Short label for the menu tile; falls back to the title. */
    short?: string;
    description: string;
    icon: LucideIcon;
    /** Tailwind classes for the icon tile, e.g. `bg-sky-500/10 text-sky-600`. */
    tone: string;
    href: string;
    /** Page component the menu opens, used to title the shell's top bar. */
    component: string;
    /** Shown only when the user holds one of these permissions (set per role in Role & Akses). */
    permission: string | string[];
    /**
     * For a module page opened to field staff: the permission of the roles
     * that already reach it from their own module menu. The desktop sidebar
     * lists the page as a field menu only for users without it.
     */
    coveredBy?: string;
};

export type MobileModule = {
    key: string;
    enabled: boolean;
    title: string;
    subtitle: string;
    /** Role names that get this shell on a phone (presentation only; the server still authorises every route). */
    roles: string[];
    /** Pages that are replaced by the card menu on a phone, e.g. the post-login dashboard. */
    homeComponents: string[];
    /** Home URL the shell's back button returns to. */
    homeHref: string;
    menus: MobileMenu[];
};
