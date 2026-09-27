import { FileBarChart } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import harLaporan from '@/routes/har/laporan';
import k3Laporan from '@/routes/k3/laporan';
import logistikLaporan from '@/routes/logistik/laporan';
import operasiLaporan from '@/routes/operasi/laporan';
import pdmLaporan from '@/routes/pdm/laporan';

/**
 * Akses 1 — Laporan Project on a phone: the Laporan page of every module,
 * shown only to holders of that module's `*.laporan.view` (the Project
 * Leader), never to the operators / Harmes / Harlist of the same shell.
 */
export const LAPORAN_PROJECT_MENUS: MobileMenu[] = [
    { key: 'laporan-operasi', title: 'Laporan Operasi Pembangkit', short: 'Operasi', tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400', href: operasiLaporan.index().url, component: 'operasi/laporan/index', permission: 'operasi.laporan.view' },
    { key: 'laporan-har', title: 'Laporan Pemeliharaan Pembangkit', short: 'Pemeliharaan', tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', href: harLaporan.index().url, component: 'har/laporan/index', permission: 'har.laporan.view' },
    { key: 'laporan-k3', title: 'Laporan K3 & Keamanan', short: 'K3 & Keamanan', tone: 'bg-rose-500/10 text-rose-600 dark:text-rose-400', href: k3Laporan.index().url, component: 'k3/laporan/index', permission: 'k3.laporan.view' },
    { key: 'laporan-logistik', title: 'Laporan Logistik & Gudang', short: 'Logistik', tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', href: logistikLaporan.index().url, component: 'logistik/laporan/index', permission: 'logistik.laporan.view' },
    { key: 'laporan-pdm', title: 'Laporan PdM & Maturity Level', short: 'PdM', tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400', href: pdmLaporan.index().url, component: 'pdm/laporan/index', permission: 'pdm.laporan.view' },
].map((menu) => ({ ...menu, group: 'laporan-project', description: 'Laporan Pembangkit (Akses 1 — Laporan Project)', icon: FileBarChart }));
