import {
    AlertTriangle,
    BellRing,
    Boxes,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    Cctv,
    ClipboardCheck,
    ClipboardList,
    Droplets,
    FileBarChart,
    FileCheck2,
    FileText,
    Flame,
    Gauge,
    HardHat,
    Image,
    Leaf,
    PhoneCall,
    ScrollText,
    ShieldCheck,
    SignpostBig,
    Siren,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { MobileMenu, MobileMenuGroup } from '@/layouts/mobile/types';
import k3Accident from '@/routes/k3/input/accident';
import k3AirLimbah from '@/routes/k3/input/air-limbah';
import k3AparCheck from '@/routes/k3/input/apar-check';
import k3ApdInventory from '@/routes/k3/input/apd-inventory';
import k3Attachment from '@/routes/k3/input/attachment';
import k3Cctv from '@/routes/k3/input/cctv';
import k3Certificate from '@/routes/k3/input/certificate';
import k3Emergency from '@/routes/k3/input/emergency';
import k3EmergencyFacility from '@/routes/k3/input/emergency-facility';
import k3FireAlarm from '@/routes/k3/input/fire-alarm';
import k3Hydrant from '@/routes/k3/input/hydrant';
import k3Inspection from '@/routes/k3/input/inspection';
import k3KesiapanApd from '@/routes/k3/input/kesiapan-apd';
import k3KondisiK3 from '@/routes/k3/input/kondisi-k3';
import k3Patrol from '@/routes/k3/input/patrol';
import k3Rambu from '@/routes/k3/input/rambu';
import k3TimeFrame from '@/routes/k3/input/time-frame';
import k3Laporan from '@/routes/k3/laporan';
import k3Monitoring from '@/routes/k3/monitoring';

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

const section = (group: MobileMenuGroup, permission: string, description: string, entries: Entry[]): MobileMenu[] =>
    entries.map(([key, title, short, icon, href, component], index) => ({
        key: `k3p-${key}`,
        group,
        title,
        short,
        description,
        icon,
        tone: TONES[index % TONES.length],
        href,
        component,
        permission,
    }));

/**
 * Phone menus of Akses 1 — Laporan Project K3 (Koordinator & Office K3):
 * Jadwal, Input, Formulir, Monitoring and Laporan — the same pages as the
 * desktop hubs, each behind its Akses 1 permission (never a Pengusahaan one).
 */
export const K3_PROJECT_MENUS: MobileMenu[] = [
    ...section('k3p-jadwal', 'k3.input.view', 'Jadwal K3 & Lingkungan', [
        ['on-call', 'Jadwal On Call K3L', 'On Call', PhoneCall, '/k3/jadwal/on-call', 'k3/jadwal/on-call/index'],
        ['kegiatan-rutin', 'Jadwal Kegiatan Rutin K3L KIT', 'Kegiatan Rutin', CalendarDays, '/k3/jadwal/kegiatan-rutin', 'k3/jadwal/kegiatan-rutin/index'],
        ['pekerjaan-rutin', 'Jadwal Pekerjaan Rutin K3L & Lingkungan', 'Pekerjaan Rutin', Leaf, '/k3/jadwal/pekerjaan-rutin', 'k3/jadwal/pekerjaan-rutin/index'],
        ['jadwal-patrol', 'Jadwal Patrol Check Harian K3L', 'Patrol Check', ShieldCheck, '/k3/jadwal/patrol-check', 'k3/jadwal/patrol-check/index'],
        ['instruksi-kerja', 'Jadwal Instruksi Kerja K3L', 'Instruksi Kerja', FileText, '/k3/jadwal/instruksi-kerja', 'k3/jadwal/instruksi-kerja/index'],
    ]),
    ...section('k3p-input', 'k3.input.view', 'Input K3 & Keamanan', [
        ['time-frame', 'Time Frame', 'Time Frame', CalendarRange, k3TimeFrame.index().url, 'k3/input/time-frame'],
        ['accidents', 'Laporan Kecelakaan', 'Kecelakaan', ClipboardCheck, k3Accident.index().url, 'k3/input/accidents'],
        ['inspections', 'Inspeksi Checklist', 'Inspeksi', ClipboardList, k3Inspection.index().url, 'k3/input/inspections'],
        ['apar-checks', 'Inspeksi APAR/APAB', 'APAR/APAB', Flame, k3AparCheck.index().url, 'k3/input/apar-checks'],
        ['hydrant', 'Inspeksi Hydrant', 'Hydrant', Droplets, k3Hydrant.index().url, 'k3/input/hydrant'],
        ['cctv', 'Daftar CCTV', 'CCTV', Cctv, k3Cctv.index().url, 'k3/input/cctv'],
        ['fire-alarm', 'Inspeksi Fire Alarm', 'Fire Alarm', BellRing, k3FireAlarm.index().url, 'k3/input/fire-alarm'],
        ['rambu', 'Inspeksi Rambu-Rambu K3 & B3', 'Rambu', SignpostBig, k3Rambu.index().url, 'k3/input/rambu'],
        ['emergency-facility', 'Pemeriksaan Emergency Facility', 'Emergency Facility', Siren, k3EmergencyFacility.index().url, 'k3/input/emergency-facility'],
        ['kesiapan-apd', 'Kesiapan APD', 'Kesiapan APD', HardHat, k3KesiapanApd.index().url, 'k3/input/kesiapan-apd'],
        ['apd-inventory', 'Daftar Inventaris APD', 'Inventaris APD', HardHat, k3ApdInventory.index().url, 'k3/input/apd-inventory'],
        ['air-limbah', 'Logbook Pemantauan Air Limbah', 'Air Limbah', Droplets, k3AirLimbah.index().url, 'k3/input/air-limbah'],
        ['emergency', 'Fasilitas Darurat', 'Fasilitas Darurat', Siren, k3Emergency.index().url, 'k3/input/emergency'],
        ['patrols', 'Patroli Keamanan', 'Patroli', ShieldCheck, k3Patrol.index().url, 'k3/input/patrols'],
        ['certificates', 'Sertifikasi Peralatan', 'Sertifikasi', ScrollText, k3Certificate.index().url, 'k3/input/certificates'],
        ['attachments', 'Lampiran K3', 'Lampiran', Image, k3Attachment.index().url, 'k3/input/attachments'],
        ['kondisi-k3', 'Kondisi K3 (Unsafe Action & Condition)', 'Kondisi K3', AlertTriangle, k3KondisiK3.index().url, 'k3/input/kondisi-k3'],
    ]),
    ...section('k3p-formulir', 'k3.input.view', 'Formulir K3 & Keamanan', [
        ['sarana-prasarana', 'Formulir Atribut, Peralatan, Administrasi & Sarana Prasarana', 'Sarana Prasarana', ShieldCheck, '/k3/formulir/sarana-prasarana', 'k3/formulir/sarana-prasarana/index'],
        ['kontrol-mingguan', 'Form Kontrol K3 Mingguan', 'Kontrol Mingguan', CalendarCheck, '/k3/formulir/kontrol-mingguan', 'k3/formulir/kontrol-mingguan/index'],
        ['metode-pengujian', 'Formulir Metode Pengujian Peralatan', 'Metode Pengujian', FileCheck2, '/k3/formulir/metode-pengujian', 'k3/formulir/metode-pengujian/index'],
        ['tps-lb3', 'Formulir Pemeliharaan TPS LB3', 'TPS LB3', Boxes, '/k3/formulir/pemeliharaan-tps-lb3', 'k3/formulir/pemeliharaan-tps-lb3/index'],
        ['oil-trap', 'Formulir Pemeliharaan Oil Trap', 'Oil Trap', Droplets, '/k3/formulir/pemeliharaan-oil-trap', 'k3/formulir/pemeliharaan-oil-trap/index'],
    ]),
    ...section('k3p-laporan', 'k3.monitoring.view', 'Monitoring status sertifikat & APAR', [['monitoring', 'Monitoring K3', 'Monitoring', Gauge, k3Monitoring.index().url, 'k3/monitoring/index']]),
    ...section('k3p-laporan', 'k3.laporan.view', 'Laporan Pembangkit K3 (Akses 1)', [['laporan', 'Laporan K3 & Keamanan', 'Laporan', FileBarChart, k3Laporan.index().url, 'k3/laporan/index']]),
];
