import type { LucideIcon } from 'lucide-react';

/** The sections of the phone card menu, in display order. */
export const MOBILE_MENU_GROUPS = [
    { key: 'umum', label: 'Umum' },
    { key: 'operasi', label: 'Input Operasi' },
    { key: 'op-jadwal', label: 'Jadwal Operasi' },
    { key: 'op-input', label: 'Input Operasi' },
    { key: 'op-formulir', label: 'Formulir Operasi' },
    { key: 'op-laporan', label: 'Laporan Operasi' },
    { key: 'har-jadwal', label: 'Jadwal Pemeliharaan' },
    { key: 'har-input', label: 'Input Pemeliharaan' },
    { key: 'har-formulir', label: 'Formulir Pemeliharaan' },
    { key: 'har-laporan', label: 'Laporan Pemeliharaan' },
    { key: 'laporan-project', label: 'Laporan Project' },
    { key: 'k3p-jadwal', label: 'Jadwal K3 & Lingkungan' },
    { key: 'k3p-input', label: 'Input K3 & Keamanan' },
    { key: 'k3p-formulir', label: 'Formulir K3 & Keamanan' },
    { key: 'k3p-laporan', label: 'Monitoring & Laporan K3' },
    { key: 'k3-input', label: 'Input K3 & Keamanan' },
    { key: 'k3-formulir', label: 'Formulir K3 & Keamanan' },
    { key: 'k3-laporan', label: 'Laporan K3 & Keamanan' },
    { key: 'log-jadwal', label: 'Jadwal Logistik & Gudang' },
    { key: 'log-input', label: 'Input Logistik & Gudang' },
    { key: 'log-laporan', label: 'Laporan Logistik & Gudang' },
    { key: 'pdm-jadwal', label: 'Jadwal PdM & MATLEV' },
    { key: 'pdm-input', label: 'Input PdM & MATLEV' },
    { key: 'pdm-laporan', label: 'Laporan PdM & MATLEV' },
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
