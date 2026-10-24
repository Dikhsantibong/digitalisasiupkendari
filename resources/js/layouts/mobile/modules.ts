import { HAR_PENGUSAHAAN_MENUS } from '@/layouts/mobile/menus/har-pengusahaan';
import { HAR_PROJECT_MENUS } from '@/layouts/mobile/menus/har-project';
import { K3_PENGUSAHAAN_MENUS } from '@/layouts/mobile/menus/k3-pengusahaan';
import { K3_PROJECT_MENUS } from '@/layouts/mobile/menus/k3-project';
import { LAPORAN_PROJECT_MENUS } from '@/layouts/mobile/menus/laporan-project';
import { LOGISTIK_MENUS } from '@/layouts/mobile/menus/logistik';
import { OPERASI_MENUS } from '@/layouts/mobile/menus/operasi';
import { OPERASI_PENGUSAHAAN_MENUS } from '@/layouts/mobile/menus/operasi-pengusahaan';
import { OPERASI_PROJECT_MENUS } from '@/layouts/mobile/menus/operasi-project';
import { PDM_MENUS } from '@/layouts/mobile/menus/pdm';
import { PEMELIHARAAN_MENUS } from '@/layouts/mobile/menus/pemeliharaan';
import { UMUM_MENUS } from '@/layouts/mobile/menus/umum';
import type { MobileMenu, MobileModule } from '@/layouts/mobile/types';
import { OPERATOR_PENGUSAHAAN_INPUT } from '@/lib/pengusahaan-menus';
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
/** The Pengusahaan Operasi section of the field roles' phone menu, kept apart from their regular inputs. */
const PENGUSAHAAN_GROUP: Partial<
    Record<MobileMenu['group'], MobileMenu['group']>
> = {
    'op-input': 'opu-input',
    'op-bbm': 'opu-bbm',
    'op-pelumas': 'opu-pelumas',
    'op-kwh': 'opu-kwh',
    'op-rekap': 'opu-rekap',
};

/**
 * The operators' & Project Leader's Operasi cards, in two parts on the phone:
 * - "Input Operasi (Laporan Project)": the field inputs (operasi.lapangan.*);
 * - "Pengusahaan · …": the Pengusahaan Operasi inputs (operasi.pengusahaan.input —
 *   not the Berita Acara nor the Laporan). The Pengusahaan pages that are also
 *   field inputs (Input Harian, Stand Meter, Pemakaian Pelumas) sit here once,
 *   open to either permission.
 */
const OPERATOR_OPERASI_MENUS: MobileMenu[] = (() => {
    const toList = (permission: string | string[]) =>
        Array.isArray(permission) ? permission : [permission];
    const fieldOf = (component: string) =>
        OPERASI_MENUS.find((menu) => menu.component === component);
    const pengusahaan = OPERASI_PENGUSAHAAN_MENUS.filter(
        (menu) => PENGUSAHAAN_GROUP[menu.group] !== undefined,
    ).map((menu): MobileMenu => {
        const field = fieldOf(menu.component);

        return {
            ...menu,
            key: `operator-${menu.key}`,
            group: PENGUSAHAAN_GROUP[menu.group] as MobileMenu['group'],
            permission: [
                ...(field ? toList(field.permission) : []),
                OPERATOR_PENGUSAHAAN_INPUT,
            ],
            // Desktop: a field input page keeps its sidebar place for the roles without the Pengusahaan hub.
            ...(field?.coveredBy ? { coveredBy: field.coveredBy } : {}),
        };
    });
    const shared = new Set(pengusahaan.map((menu) => menu.component));

    return [
        ...OPERASI_MENUS.filter((menu) => !shared.has(menu.component)),
        ...pengusahaan,
    ];
})();

export const MOBILE_MODULES: MobileModule[] = [
    {
        key: 'operator',
        enabled: true,
        title: 'Operator',
        subtitle: 'Menu lapangan sesuai peran Anda',
        roles: ['operator', 'project_leader_operasi', 'harmes', 'harlist'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        // The operators also fill the Pengusahaan Operasi inputs (not the Berita Acara / Laporan Pengusahaan).
        menus: [
            ...UMUM_MENUS,
            ...OPERATOR_OPERASI_MENUS,
            ...PEMELIHARAAN_MENUS,
            ...LAPORAN_PROJECT_MENUS,
        ],
    },
    {
        // Akses 1 — Laporan Project Operasi on a phone: Koordinator Operasi.
        key: 'operasi-project',
        enabled: true,
        title: 'Operasi',
        subtitle: 'Jadwal, input & laporan project operasi',
        roles: ['koordinator_operasi'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: OPERASI_PROJECT_MENUS,
        quick: [
            'opp-jadwal-flm',
            'opp-jadwal-program-5s-5r',
            'opp-operasi-kondisi_abnormal',
            'opp-operasi-permit_to_work',
        ],
    },
    {
        // Akses 2 — Pengusahaan Operasi on a phone: TL & Staf Operasi (Manager UL keeps the full app).
        key: 'operasi-pengusahaan',
        enabled: true,
        title: 'Operasi',
        subtitle: 'Input harian, berita acara & laporan pengusahaan',
        roles: ['tl_operasi', 'staf_operasi'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: OPERASI_PENGUSAHAAN_MENUS,
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
    {
        // Logistik & Gudang on a phone: TL Logistik & Gudang.
        key: 'logistik',
        enabled: true,
        title: 'Logistik & Gudang',
        subtitle: 'Jadwal, input & laporan logistik & gudang',
        roles: ['tl_logistik'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: LOGISTIK_MENUS,
    },
    {
        // PdM & Maturity Level on a phone: TL PdM.
        key: 'pdm',
        enabled: true,
        title: 'PdM & Maturity Level',
        subtitle: 'Jadwal, input & laporan PdM & MATLEV',
        roles: ['tl_pdm'],
        homeComponents: ['dashboard'],
        homeHref: dashboard().url,
        menus: PDM_MENUS,
    },
];

/** Module pages opened to field staff, reused by the desktop sidebar (see MobileMenu.coveredBy). */
export const FIELD_MENUS: MobileMenu[] = MOBILE_MODULES.flatMap(
    (module) => module.menus,
).filter((menu) => menu.coveredBy !== undefined);
