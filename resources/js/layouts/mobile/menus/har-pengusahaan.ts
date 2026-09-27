import { FileBarChart } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import { PENGUSAHAAN_MENUS, pengusahaanComponent } from '@/lib/pengusahaan-menus';
import harLaporan from '@/routes/har/laporan';

const TONES = [
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
];

/**
 * Phone menus of Akses 2 — Pengusahaan Pemeliharaan (TL & Staf Pemeliharaan):
 * every HAR page of the Pengusahaan registry (Work Order, Service Request and
 * the technical formulir) plus the Laporan page. A page added to the registry
 * shows up here without further changes.
 */
export const HAR_PENGUSAHAAN_MENUS: MobileMenu[] = [
    ...PENGUSAHAAN_MENUS.filter((menu) => menu.module === 'har' && menu.href !== null).map(
        (menu, index): MobileMenu => ({
            key: `har-pengusahaan-${pengusahaanComponent(menu)}`,
            group: menu.section === 'formulir' ? 'har-formulir' : 'har-input',
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
        key: 'har-pengusahaan-laporan',
        group: 'har-laporan',
        title: 'Laporan Pemeliharaan',
        short: 'Laporan',
        description: 'Laporan Pembangkit & Laporan Pengusahaan Pemeliharaan',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: harLaporan.index().url,
        component: 'har/laporan/index',
        permission: ['har.laporan.view', 'har.pengusahaan.view'],
    },
];
