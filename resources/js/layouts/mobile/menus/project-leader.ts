import { FileBarChart, Target } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import laporan from '@/routes/laporan';
import sla from '@/routes/sla';

/**
 * The Project Leader's "Laporan & SLA" cards on the phone — like its desktop
 * sidebar (user, 2026-11-18/19): one Laporan page with every report by module
 * (laporan.hub) and the Laporan SLA gabungan (sla.gabungan), instead of a
 * Laporan per module.
 */
export const PROJECT_LEADER_LAPORAN_MENUS: MobileMenu[] = [
    {
        key: 'pl-laporan-hub',
        group: 'pl-laporan',
        title: 'Laporan (Semua Modul)',
        short: 'Laporan',
        description: 'Seluruh laporan per modul: status, dokumen & PDF',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: laporan.hub().url,
        component: 'laporan/index',
        permission: 'laporan.hub',
    },
    {
        key: 'pl-sla-gabungan',
        group: 'pl-laporan',
        title: 'Laporan SLA Gabungan',
        short: 'SLA',
        description: 'SLA seluruh divisi — lihat, edit & cetak',
        icon: Target,
        tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        href: sla.combined.index().url,
        component: 'laporan/sla-gabungan',
        permission: 'sla.gabungan',
    },
];

/** The per-module Laporan pages: the Project Leader opens them from the Laporan page. */
export const MODULE_LAPORAN_PAGES = new Set([
    'operasi/laporan/index',
    'har/laporan/index',
    'k3/laporan/index',
    'logistik/laporan/index',
    'pdm/laporan/index',
]);

/**
 * Each page once (the first entry wins): the Koordinator menus and the field
 * menus open several of the same pages.
 */
export function uniqueMenus(menus: MobileMenu[]): MobileMenu[] {
    const seen = new Set<string>();

    return menus.filter((menu) => {
        const id = `${menu.component}|${menu.href.split('?')[0]}`;

        if (seen.has(id)) {
            return false;
        }

        seen.add(id);

        return true;
    });
}
