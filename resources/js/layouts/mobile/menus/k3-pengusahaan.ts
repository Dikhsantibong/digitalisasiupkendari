import { FileBarChart } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import { PENGUSAHAAN_MENUS, pengusahaanComponent } from '@/lib/pengusahaan-menus';
import k3Laporan from '@/routes/k3/laporan';

const TONES = [
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    'bg-teal-500/10 text-teal-600 dark:text-teal-400',
];

/**
 * Phone menus of Akses 2 — Pengusahaan K3 (TL & Staf K3): every K3 page of the
 * Pengusahaan registry (lib/pengusahaan-menus.ts) plus the Laporan page. A page
 * added to the registry shows up here without further changes.
 */
export const K3_PENGUSAHAAN_MENUS: MobileMenu[] = [
    ...PENGUSAHAAN_MENUS.filter((menu) => menu.module === 'k3' && menu.href !== null).map(
        (menu, index): MobileMenu => ({
            key: `k3-pengusahaan-${pengusahaanComponent(menu)}`,
            group: menu.section === 'formulir' ? 'k3-formulir' : 'k3-input',
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
        key: 'k3-laporan',
        group: 'k3-laporan',
        title: 'Laporan K3 & Keamanan',
        short: 'Laporan',
        description: 'Laporan Pembangkit & Laporan Pengusahaan K3',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: k3Laporan.index().url,
        component: 'k3/laporan/index',
        permission: ['k3.laporan.view', 'k3.pengusahaan.view'],
    },
];
