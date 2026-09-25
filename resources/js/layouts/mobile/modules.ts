import { ClipboardList, Fingerprint } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/routes';
import logsheet from '@/routes/operator/logsheet';
import presensi from '@/routes/operator/presensi';

/**
 * Registry of modules that get the phone-only shell (no sidebar, no header,
 * a card menu as home). On tablet/desktop these pages render the normal app
 * layout, untouched.
 *
 * - Add a module: append an entry.
 * - Pause a module: set `enabled: false`.
 * - Remove it for good: delete the entry. Nothing else references it.
 */
export type MobileMenu = {
    key: string;
    title: string;
    description: string;
    icon: LucideIcon;
    /** Tailwind classes for the icon tile, e.g. `bg-sky-500/10 text-sky-600`. */
    tone: string;
    href: string;
    /** Page component the menu opens, used to title the shell's top bar. */
    component: string;
    /** Shown only when the user holds one of these permissions. */
    permission: string | string[];
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

export const MOBILE_MODULES: MobileModule[] = [
    {
        key: 'operator',
        enabled: true,
        title: 'Operator',
        subtitle: 'Input lapangan operator pembangkit',
        roles: ['operator', 'project_leader_operasi'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: [
            {
                key: 'logsheet',
                title: 'Input Logsheet',
                description: 'Catat pembacaan parameter mesin per jam',
                icon: ClipboardList,
                tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
                href: logsheet.index().url,
                component: 'operator/logsheet',
                permission: [
                    'operator.logsheet.view',
                    'operator.logsheet.write',
                ],
            },
            {
                key: 'presensi',
                title: 'Absensi',
                description: 'Absen masuk & pulang dalam radius kantor',
                icon: Fingerprint,
                tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
                href: presensi.index().url,
                component: 'operator/presensi',
                permission: 'operator.presensi',
            },
        ],
    },
];
