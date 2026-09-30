import { Activity, CalendarClock, FileBarChart, FileText, PackageCheck, Sliders, Sparkles, Wrench, ZapOff } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { OPERASI_MENUS } from '@/layouts/mobile/menus/operasi';
import type { MobileMenu } from '@/layouts/mobile/types';
import operasiInstruksiKerja from '@/routes/operasi/input/instruksi-kerja';
import operasiJadwal from '@/routes/operasi/jadwal';
import operasiLaporan from '@/routes/operasi/laporan';

const TONES = [
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
];

type Entry = [key: string, title: string, short: string, icon: LucideIcon, href: string, component: string];

const JADWAL: Entry[] = [
    ['flm', 'Jadwal FLM', 'FLM', Wrench, operasiJadwal.flm.index().url, 'operasi/jadwal/flm/index'],
    ['program-5s-5r', 'Jadwal Program 5S 5R', '5S 5R', Sparkles, operasiJadwal.program5s5r.index().url, 'operasi/jadwal/program-5s-5r/index'],
    ['meeting-shift', 'Jadwal Meeting Shift', 'Meeting Shift', CalendarClock, operasiJadwal.meetingShift.index().url, 'operasi/jadwal/meeting-shift/index'],
    ['inventarisasi-tools', 'Jadwal Inventarisasi Tools & Material Operasi', 'Inventarisasi', PackageCheck, operasiJadwal.inventarisasiTools.index().url, 'operasi/jadwal/inventarisasi-tools/index'],
    ['pembuatan-ik', 'Jadwal Pembuatan IK', 'Pembuatan IK', FileText, operasiJadwal.pembuatanIk.index().url, 'operasi/jadwal/pembuatan-ik/index'],
    ['pembuatan-data-teknis', 'Jadwal Pembuatan Data Teknis Pembangkit', 'Data Teknis', FileText, operasiJadwal.pembuatanDataTeknis.index().url, 'operasi/jadwal/pembuatan-data-teknis/index'],
    ['blackstart', 'Jadwal Pemeriksaan Instalasi Blackstart', 'Blackstart', ZapOff, operasiJadwal.blackstart.index().url, 'operasi/jadwal/blackstart/index'],
    ['commissioning-test', 'Jadwal Commissioning Test Mesin', 'Commissioning Mesin', Activity, operasiJadwal.commissioningTest.index().url, 'operasi/jadwal/commissioning-test/index'],
    ['commissioning-test-peralatan', 'Jadwal Commissioning Test Peralatan Non Mesin', 'Commissioning Peralatan', Sliders, operasiJadwal.commissioningTestPeralatan.index().url, 'operasi/jadwal/commissioning-test-peralatan/index'],
    ['performance-test', 'Jadwal Pelaksanaan Performance Test Mesin', 'Performance Test', Activity, operasiJadwal.performanceTest.index().url, 'operasi/jadwal/performance-test/index'],
];

/**
 * Phone menus of Akses 1 — Laporan Project Operasi (Koordinator Operasi):
 * Jadwal, Input (the Akses 1 pages of the field menus plus Instruksi Kerja)
 * and Laporan — the same pages as the desktop hubs, each behind an Akses 1
 * permission. The Pengusahaan pages are never listed here.
 */
export const OPERASI_PROJECT_MENUS: MobileMenu[] = [
    ...JADWAL.map(([key, title, short, icon, href, component], index): MobileMenu => ({
        key: `opp-jadwal-${key}`,
        group: 'op-jadwal',
        title,
        short,
        description: 'Jadwal Operasi',
        icon,
        tone: TONES[index % TONES.length],
        href,
        component,
        permission: 'operasi.input.view',
    })),
    ...OPERASI_MENUS.filter((menu) => menu.coveredBy === 'operasi.input.view').map(
        (menu): MobileMenu => ({ ...menu, key: `opp-${menu.key}`, group: 'op-input', permission: 'operasi.input.view', coveredBy: undefined }),
    ),
    {
        key: 'opp-instruksi-kerja',
        group: 'op-input',
        title: 'Instruksi Kerja (IK) Operasi',
        short: 'Instruksi Kerja',
        description: 'Buat & cetak IK dari template',
        icon: FileText,
        tone: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        href: operasiInstruksiKerja.index().url,
        component: 'operasi/input/instruksi-kerja/index',
        permission: 'operasi.input.view',
    },
    {
        key: 'opp-laporan',
        group: 'op-laporan',
        title: 'Laporan Operasi',
        short: 'Laporan',
        description: 'Laporan Operasi Pembangkit (Akses 1)',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: operasiLaporan.index().url,
        component: 'operasi/laporan/index',
        permission: 'operasi.laporan.view',
    },
];
