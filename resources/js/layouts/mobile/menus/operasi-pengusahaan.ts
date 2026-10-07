import { FileBarChart } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import { PENGUSAHAAN_MENUS, pengusahaanComponent } from '@/lib/pengusahaan-menus';
import type { PengusahaanMenuGroup } from '@/lib/pengusahaan-menus';
import operasiLaporan from '@/routes/operasi/laporan';

/** The phone section of each hub sub-heading. */
const GROUPS: Record<PengusahaanMenuGroup, MobileMenu['group']> = {
    'Bahan Bakar': 'op-bbm',
    Pelumas: 'op-pelumas',
    kWh: 'op-kwh',
    Rekap: 'op-rekap',
};

const TONES = [
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
];

/**
 * Phone menus of Akses 2 — Pengusahaan Operasi (TL & Staf Operasi): every
 * Operasi page of the Pengusahaan registry (the daily inputs and Berita Acara)
 * plus the Laporan page. A page added to the registry shows up here without
 * further changes.
 */
export const OPERASI_PENGUSAHAAN_MENUS: MobileMenu[] = [
    ...PENGUSAHAAN_MENUS.filter((menu) => menu.module === 'operasi' && menu.href !== null).map(
        (menu, index): MobileMenu => ({
            key: `operasi-pengusahaan-${pengusahaanComponent(menu)}`,
            group: menu.section === 'formulir' ? 'op-formulir' : menu.group ? GROUPS[menu.group] : 'op-input',
            title: menu.title,
            description: menu.description,
            icon: menu.icon,
            tone: TONES[index % TONES.length],
            href: menu.href as string,
            component: pengusahaanComponent(menu),
            permission: menu.permission,
        }),
    ),
    {
        key: 'operasi-pengusahaan-laporan',
        group: 'op-laporan',
        title: 'Laporan Operasi',
        short: 'Laporan',
        description: 'Laporan Pembangkit & Laporan Pengusahaan Operasi',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: operasiLaporan.index().url,
        component: 'operasi/laporan/index',
        permission: ['operasi.laporan.view', 'operasi.pengusahaan.view'],
    },
];
