import {
    Activity,
    CalendarCheck2,
    CalendarClock,
    ClipboardCheck,
    Droplets,
    FileBarChart,
    FileCheck2,
    FlaskConical,
    Gauge,
    HardHat,
    ShieldCheck,
    Sparkles,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import pdmForms from '@/routes/pdm/input/forms';
import kesiapanApd from '@/routes/pdm/input/kesiapan-apd';
import permitToWork from '@/routes/pdm/input/permit-to-work';
import realisasiPrediktif from '@/routes/pdm/input/realisasi-prediktif';
import sampleMonitoring from '@/routes/pdm/input/sample-monitoring';
import pdmJadwal from '@/routes/pdm/jadwal';
import pdmLaporan from '@/routes/pdm/laporan';

const TONES = [
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400',
];

type Entry = [
    key: string,
    title: string,
    short: string,
    icon: LucideIcon,
    href: string,
    component: string,
];

const JADWAL: Entry[] = [
    [
        'harian',
        'Jadwal Kegiatan PdM & MATLEV',
        'Kegiatan',
        Activity,
        pdmJadwal.harian.index().url,
        'pdm/jadwal/harian/index',
    ],
    [
        'patrol-check',
        'Jadwal Piket Patrol Check PdM KIT',
        'Patrol Check',
        ShieldCheck,
        pdmJadwal.patrolCheck.index().url,
        'pdm/jadwal/patrol-check/index',
    ],
    [
        'program-5s-5r',
        'Jadwal Program 5S 5R',
        '5S 5R',
        Sparkles,
        pdmJadwal.program5s5r.index().url,
        'pdm/jadwal/program-5s-5r/index',
    ],
    [
        'meeting',
        'Jadwal Meeting PdM KIT',
        'Meeting',
        CalendarClock,
        pdmJadwal.meeting.index().url,
        'pdm/jadwal/meeting/index',
    ],
];

const INPUT: Entry[] = [
    [
        'kesiapan-apd',
        'Kesiapan APD Bagian PdM Pembangkit',
        'Kesiapan APD',
        HardHat,
        kesiapanApd.index().url,
        'pdm/input/kesiapan-apd/index',
    ],
    [
        'sample-monitoring',
        'Monitoring Pemeriksaan & Pengiriman Sample PdM',
        'Sample PdM',
        FlaskConical,
        sampleMonitoring.index().url,
        'pdm/input/sample-monitoring/index',
    ],
    [
        'permit-to-work',
        'Laporan Permit to Work (PTW) Pembangkit',
        'Permit to Work',
        FileCheck2,
        permitToWork.index().url,
        'pdm/input/permit-to-work/index',
    ],
    [
        'checklist-5s5r',
        'Laporan Inspeksi Checklist 5S5R PdM',
        'Checklist 5S5R',
        Sparkles,
        pdmForms.index('checklist-5s5r').url,
        'pdm/input/checklist-5s5r/index',
    ],
    [
        'air-pendingin',
        'Laporan Pengukuran Kualitas Air Pendingin',
        'Air Pendingin',
        Droplets,
        pdmForms.index('air-pendingin').url,
        'pdm/input/air-pendingin/index',
    ],
    [
        'pelumas',
        'Laporan Pengukuran Kualitas Pelumas',
        'Pelumas',
        Gauge,
        pdmForms.index('pelumas').url,
        'pdm/input/pelumas/index',
    ],
    [
        'vibrasi',
        'Laporan Pengukuran Vibrasi Mesin & Generator',
        'Vibrasi',
        Activity,
        pdmForms.index('vibrasi').url,
        'pdm/input/vibrasi/index',
    ],
    [
        'kontrol-material',
        'Form Kontrol Material, Peralatan & Tools PdM',
        'Kontrol Material',
        Wrench,
        pdmForms.index('kontrol-material').url,
        'pdm/input/kontrol-material/index',
    ],
    [
        'realisasi-prediktif',
        'Realisasi Pemeliharaan Prediktif Bulanan',
        'Realisasi Prediktif',
        CalendarCheck2,
        realisasiPrediktif.index().url,
        'pdm/input/realisasi-prediktif/index',
    ],
    [
        'patrol-check-pdm',
        'Patrol Check Predictive Maintenance (PdM)',
        'Patrol Check PdM',
        ShieldCheck,
        pdmForms.index('patrol-check-pdm').url,
        'pdm/input/patrol-check-pdm/index',
    ],
    [
        'checklist-patrol-check',
        'Laporan Checklist Patrol Check PdM',
        'Checklist Patrol',
        ClipboardCheck,
        pdmForms.index('checklist-patrol-check').url,
        'pdm/input/checklist-patrol-check/index',
    ],
];

/**
 * Phone menus of the PdM & Maturity Level module (TL PdM): Jadwal (opens
 * read-only, "Ubah Jadwal" to edit), Input and Laporan — the same pages as the
 * desktop hubs, each behind the module permission.
 */
export const PDM_MENUS: MobileMenu[] = [
    ...JADWAL.map(
        ([key, title, short, icon, href, component], index): MobileMenu => ({
            key: `pdm-jadwal-${key}`,
            group: 'pdm-jadwal',
            title,
            short,
            description: 'Jadwal PdM & Maturity Level',
            icon,
            tone: TONES[index % TONES.length],
            href,
            component,
            permission: 'pdm.input.view',
        }),
    ),
    ...INPUT.map(
        ([key, title, short, icon, href, component], index): MobileMenu => ({
            key: `pdm-input-${key}`,
            group: 'pdm-input',
            title,
            short,
            description: 'Input PdM & Maturity Level',
            icon,
            tone: TONES[(index + 4) % TONES.length],
            href,
            component,
            permission: 'pdm.input.view',
        }),
    ),
    {
        key: 'pdm-laporan',
        group: 'pdm-laporan',
        title: 'Laporan PdM & Maturity Level',
        short: 'Laporan',
        description: 'Laporan bulanan PdM & Maturity Level',
        icon: FileBarChart,
        tone: 'bg-primary/10 text-primary',
        href: pdmLaporan.index().url,
        component: 'pdm/laporan/index',
        permission: 'pdm.laporan.view',
    },
];
