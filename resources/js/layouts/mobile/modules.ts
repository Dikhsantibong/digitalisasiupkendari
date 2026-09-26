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
        menus: [...UMUM_MENUS, ...OPERASI_MENUS, ...PEMELIHARAAN_MENUS],
    },
];

/** Module pages opened to field staff, reused by the desktop sidebar (see MobileMenu.coveredBy). */
export const FIELD_MENUS: MobileMenu[] = MOBILE_MODULES.flatMap(
    (module) => module.menus,
).filter((menu) => menu.coveredBy !== undefined);
