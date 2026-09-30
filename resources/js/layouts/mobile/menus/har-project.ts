import { Activity, BatteryCharging, Boxes, CalendarClock, FileBarChart, FileText, PhoneCall, ShieldCheck, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PEMELIHARAAN_MENUS } from '@/layouts/mobile/menus/pemeliharaan';
import type { MobileMenu } from '@/layouts/mobile/types';
import harInstruksiKerja from '@/routes/har/input/instruksi-kerja';
import harJadwal from '@/routes/har/jadwal';
import harJadwalLembar from '@/routes/har/jadwal/lembar';
import harLaporan from '@/routes/har/laporan';

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
    ['harian', 'Jadwal Kegiatan Harian Pemeliharaan', 'Harian', Activity, harJadwal.harian.index().url, 'har/jadwal/harian/index'],
    ['p0-p5', 'Jadwal Kegiatan Pemeliharaan P0 - P5', 'P0 - P5', Wrench, harJadwal.p0P5.index().url, 'har/jadwal/p0-p5/index'],
    ['piket-on-call', 'Jadwal Piket Pemeliharaan (On Call)', 'Piket On Call', PhoneCall, harJadwal.piketOnCall.index().url, 'har/jadwal/piket-on-call/index'],
    ['patrol-check', 'Jadwal Piket Patrol Check Harian', 'Patrol Check', ShieldCheck, harJadwal.patrolCheck.index().url, 'har/jadwal/patrol-check/index'],
    ['meeting', 'Jadwal Meeting Pemeliharaan', 'Meeting', CalendarClock, harJadwal.meetingPemeliharaan.index().url, 'har/jadwal/meeting-pemeliharaan/index'],
    ['pembuatan-ik', 'Jadwal Pembuatan IK Pemeliharaan', 'Pembuatan IK', FileText, harJadwal.pembuatanIk.index().url, 'har/jadwal/pembuatan-ik/index'],
    ['inventarisasi-tools', 'Jadwal Inventarisasi Tools & Material', 'Inventarisasi', Boxes, harJadwalLembar.index('inventarisasi-tools').url, 'har/jadwal/inventarisasi-tools/index'],
    ['pemeriksaan-blackstart', 'Jadwal Pemeriksaan Instalasi Blackstart', 'Blackstart', BatteryCharging, harJadwalLembar.index('pemeriksaan-blackstart').url, 'har/jadwal/pemeriksaan-blackstart/index'],
];

/**
 * Phone menus of Akses 1 — Laporan Project Pemeliharaan (Koordinator & Office
 * Pemeliharaan): Jadwal (opens read-only, "Ubah Jadwal" to edit), Input,
 * Formulir (Daily Meeting, Logbook Mutasi, LH-05) and Laporan — the same pages
 * as the desktop hubs, each behind an Akses 1 permission.
 */
export const HAR_PROJECT_MENUS: MobileMenu[] = [
    ...JADWAL.map(([key, title, short, icon, href, component], index): MobileMenu => ({
        key: `harp-${key}`,
        group: 'har-jadwal',
        title,
        short,
        description: 'Jadwal Pemeliharaan',
        icon,
        tone: TONES[index % TONES.length],
        href,
        component,
        permission: 'har.input.view',
    })),
    // The Akses 1 input & formulir pages of the field menus, opened by the Koordinator's own permission.
    ...PEMELIHARAAN_MENUS.filter((menu) => menu.coveredBy === 'har.input.view').map(
        (menu): MobileMenu => ({ ...menu, key: `harp-${menu.key}`, permission: 'har.input.view', coveredBy: undefined }),
    ),
    {
        key: 'harp-instruksi-kerja',
        group: 'har-input',
        title: 'Instruksi Kerja (IK) Pemeliharaan',
        short: 'Instruksi Kerja',
        description: 'Buat & cetak IK dari template',
        icon: FileText,
        tone: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        href: harInstruksiKerja.index().url,
        component: 'har/input/instruksi-kerja/index',
        permission: 'har.input.view',
    },
    {
        key: 'harp-laporan',
        group: 'har-laporan',
        title: 'Laporan Pemeliharaan',
        short: 'Laporan',
        description: 'Laporan Pemeliharaan Pembangkit (Akses 1)',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: harLaporan.index().url,
        component: 'har/laporan/index',
        permission: 'har.laporan.view',
    },
];
