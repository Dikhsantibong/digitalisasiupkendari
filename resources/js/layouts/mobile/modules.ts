import { HAR_PENGUSAHAAN_MENUS } from '@/layouts/mobile/menus/har-pengusahaan';
import { HAR_PROJECT_MENUS } from '@/layouts/mobile/menus/har-project';
import { K3_PENGUSAHAAN_MENUS } from '@/layouts/mobile/menus/k3-pengusahaan';
import { K3_PROJECT_MENUS } from '@/layouts/mobile/menus/k3-project';
import { LAPORAN_PROJECT_MENUS } from '@/layouts/mobile/menus/laporan-project';
import { OPERASI_MENUS } from '@/layouts/mobile/menus/operasi';
import { PEMELIHARAAN_MENUS } from '@/layouts/mobile/menus/pemeliharaan';
import { UMUM_MENUS } from '@/layouts/mobile/menus/umum';
import type { MobileMenu, MobileModule } from '@/layouts/mobile/types';
import { dashboard } from '@/routes';

export type { MobileMenu, MobileModule } from '@/layouts/mobile/types';

/**
 * Registry of modules that get the phone-only shell (no sidebar, no header,
 * a card menu as home). On tablet/desktop these pages render the normal app
 * layout, untouched.
 *
 * - Add a module: append an entry.
 * - Pause a module: set `enabled: false`.
 * - Remove it for good: delete the entry. Nothing else references it.
 * - Add a menu: append it to a list in `layouts/mobile/menus/`. Each menu is
 *   shown only to users holding its permission, which a Super Admin grants or
 *   withdraws per role in Role & Akses.
 */
export const MOBILE_MODULES: MobileModule[] = [
    {
        key: 'operator',
        enabled: true,
        title: 'Operator',
        subtitle: 'Menu lapangan sesuai peran Anda',
        roles: ['operator', 'project_leader_operasi', 'harmes', 'harlist'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: [...UMUM_MENUS, ...OPERASI_MENUS, ...PEMELIHARAAN_MENUS, ...LAPORAN_PROJECT_MENUS],
    },
    {
        // Akses 1 — Laporan Project Pemeliharaan on a phone: Koordinator (& Office) Pemeliharaan.
        key: 'har-project',
        enabled: true,
        title: 'Pemeliharaan',
        subtitle: 'Jadwal, input & laporan project pemeliharaan',
        roles: ['koordinator_pemeliharaan'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: HAR_PROJECT_MENUS,
    },
    {
        // Akses 2 — Pengusahaan Pemeliharaan on a phone: TL & Staf Pemeliharaan.
        key: 'har-pengusahaan',
        enabled: true,
        title: 'Pemeliharaan',
        subtitle: 'Work order, service request & formulir pengusahaan',
        roles: ['tl_pemeliharaan', 'staf_pemeliharaan'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: HAR_PENGUSAHAAN_MENUS,
    },
    {
        // Akses 1 — Laporan Project K3 on a phone: Koordinator (& Office) K3.
        key: 'k3-project',
        enabled: true,
        title: 'K3 & Keamanan',
        subtitle: 'Jadwal, input & laporan project K3',
        roles: ['koordinator_k3'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: K3_PROJECT_MENUS,
    },
    {
        // Akses 2 — Pengusahaan K3 on a phone: TL K3 & Staf K3.
        key: 'k3-pengusahaan',
        enabled: true,
        title: 'K3 & Keamanan',
        subtitle: 'Input pengusahaan K3 sesuai peran Anda',
        roles: ['tl_k3', 'staf_k3'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: K3_PENGUSAHAAN_MENUS,
    },
];

/** Module pages opened to field staff, reused by the desktop sidebar (see MobileMenu.coveredBy). */
export const FIELD_MENUS: MobileMenu[] = MOBILE_MODULES.flatMap(
    (module) => module.menus,
).filter((menu) => menu.coveredBy !== undefined);
