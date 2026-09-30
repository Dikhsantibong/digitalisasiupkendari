import {
    Activity,
    Boxes,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    FileBarChart,
    FileSignature,
    FileText,
    FolderOpen,
    Gauge,
    Lightbulb,
    MonitorSmartphone,
    Package,
    PhoneCall,
    ShieldCheck,
    Sparkles,
    TriangleAlert,
    Users,
    Warehouse,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import formRoutes from '@/routes/logistik/input/form';
import rekomendasi from '@/routes/logistik/input/rekomendasi';
import inputSheet from '@/routes/logistik/input/sheet';
import jadwalSheet from '@/routes/logistik/jadwal/sheet';
import logistikLaporan from '@/routes/logistik/laporan';

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

type Entry = [key: string, title: string, short: string, icon: LucideIcon];

/** Jadwal sheets: pages/logistik/jadwal/{key}/index (App\Support\LogistikJadwal). */
const JADWAL: Entry[] = [
    ['kegiatan', 'Jadwal Kegiatan Logistik & Gudang', 'Kegiatan', Activity],
    [
        'shift',
        'Jadwal Shift Operator Logistik & Gudang',
        'Shift Operator',
        Users,
    ],
    [
        'piket',
        'Jadwal Piket Patrol Check (On Call)',
        'Piket On Call',
        PhoneCall,
    ],
    ['5s5r', 'Jadwal Pelaksanaan 5S5R Logistik & Gudang', '5S5R', Sparkles],
    ['meeting', 'Jadwal Meeting Logistik & Gudang', 'Meeting', CalendarClock],
    [
        'inventarisasi',
        'Jadwal Inventarisasi Tools dan Material',
        'Inventarisasi',
        ClipboardList,
    ],
    ['ik', 'Jadwal Pembuatan Instruksi Kerja (IK)', 'Pembuatan IK', FileText],
    [
        'pemeliharaan',
        'Jadwal Pemeliharaan Logistik dan Gudang',
        'Pemeliharaan',
        Wrench,
    ],
    [
        'piket-patrol-check',
        'Jadwal Piket Patrol Check Logistik & Gudang',
        'Piket Patrol',
        ShieldCheck,
    ],
];

/** Grid input sheets: pages/logistik/input/{key}/index (App\Support\LogistikJadwal). */
const INPUT_SHEETS: Entry[] = [
    [
        'patrol-check',
        'Laporan Kegiatan Patrol Check Logistik & Gudang',
        'Patrol Check',
        ClipboardCheck,
    ],
    [
        'inspeksi-5s5r',
        'Laporan Inspeksi Checklist 5S5R Logistik & Gudang',
        'Inspeksi 5S5R',
        Sparkles,
    ],
    [
        'input-aplikasi',
        'Laporan Input Data Aplikasi Pembangkit',
        'Input Aplikasi',
        MonitorSmartphone,
    ],
    ['maturity', 'Maturity Level Logistik & Gudang', 'Maturity Level', Gauge],
];

/** Form inputs: pages/logistik/input/{key}/index (App\Support\LogistikForms). */
const INPUT_FORMS: Entry[] = [
    [
        'inventaris-lainnya',
        'Laporan Inventaris Lainnya Logistik & Gudang',
        'Inventaris Lainnya',
        Boxes,
    ],
    [
        'peralatan',
        'Laporan Peralatan, Material dan Tools',
        'Peralatan & Tools',
        Package,
    ],
    [
        'kondisi-stok',
        'Laporan Kondisi Stok Tools dan Material',
        'Kondisi Stok',
        Warehouse,
    ],
    [
        'unsafe',
        'Laporan Unsafe Action dan Unsafe Condition',
        'Unsafe',
        TriangleAlert,
    ],
    [
        'pendukung',
        'Laporan Pendukung Logistik & Gudang',
        'Pendukung',
        FolderOpen,
    ],
    [
        'permit-to-work',
        'Laporan Permit To Work Pembangkit',
        'Permit To Work',
        FileSignature,
    ],
];

const menu = (
    key: string,
    group: MobileMenu['group'],
    title: string,
    short: string,
    icon: LucideIcon,
    index: number,
    href: string,
    component: string,
    description: string,
    permission: string,
): MobileMenu => ({
    key: `log-${key}`,
    group,
    title,
    short,
    description,
    icon,
    tone: TONES[index % TONES.length],
    href,
    component,
    permission,
});

/**
 * Phone menus of the Logistik & Gudang module (TL Logistik & Gudang): Jadwal
 * (opens read-only, "Ubah Jadwal" to edit), Input and Laporan — the same pages
 * as the desktop hubs, each behind the module permission.
 */
export const LOGISTIK_MENUS: MobileMenu[] = [
    ...JADWAL.map(([key, title, short, icon], index) =>
        menu(
            `jadwal-${key}`,
            'log-jadwal',
            title,
            short,
            icon,
            index,
            jadwalSheet.index(key).url,
            `logistik/jadwal/${key}/index`,
            'Jadwal Logistik & Gudang',
            'logistik.input.view',
        ),
    ),
    menu(
        'rekomendasi',
        'log-input',
        'Rekomendasi Logistik & Gudang',
        'Rekomendasi',
        Lightbulb,
        0,
        rekomendasi.index().url,
        'logistik/input/rekomendasi/index',
        'Input Logistik & Gudang',
        'logistik.input.view',
    ),
    ...INPUT_SHEETS.map(([key, title, short, icon], index) =>
        menu(
            `input-${key}`,
            'log-input',
            title,
            short,
            icon,
            index + 1,
            inputSheet.index(key).url,
            `logistik/input/${key}/index`,
            'Input Logistik & Gudang',
            'logistik.input.view',
        ),
    ),
    ...INPUT_FORMS.map(([key, title, short, icon], index) =>
        menu(
            `form-${key}`,
            'log-input',
            title,
            short,
            icon,
            index + 5,
            formRoutes.index(key).url,
            `logistik/input/${key}/index`,
            'Input Logistik & Gudang',
            'logistik.input.view',
        ),
    ),
    menu(
        'laporan',
        'log-laporan',
        'Laporan Logistik & Gudang',
        'Laporan',
        FileBarChart,
        1,
        logistikLaporan.index().url,
        'logistik/laporan/index',
        'Laporan bulanan Logistik & Gudang',
        'logistik.laporan.view',
    ),
];
