import {
    AlertOctagon,
    ClipboardCheck,
    Cog,
    Droplet,
    FileCheck,
    Fuel,
    Gauge,
    Package,
    Plug,
    ShieldAlert,
    Sparkles,
    TimerReset,
    Wrench,
    Zap,
} from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import auxiliary from '@/routes/operasi/input/auxiliary';
import checklistCommissioningMesin from '@/routes/operasi/input/checklist-commissioning-mesin';
import dailyReport from '@/routes/operasi/input/daily-report';
import feeder from '@/routes/operasi/input/feeder';
import flmMonitoring from '@/routes/operasi/input/flm-monitoring';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
import kondisiAbnormal from '@/routes/operasi/input/kondisi-abnormal';
import materialPeralatan from '@/routes/operasi/input/material-peralatan';
import patrolCheckMesin from '@/routes/operasi/input/patrol-check-mesin';
import permitToWork from '@/routes/operasi/input/permit-to-work';
import program5s5r from '@/routes/operasi/input/program-5s5r';
import resourcePembangkit from '@/routes/operasi/input/resource-pembangkit';
import starStop from '@/routes/operasi/input/star-stop';
import unsafeCondition from '@/routes/operasi/input/unsafe-condition';

type Entry = [
    key: string,
    title: string,
    short: string,
    description: string,
    icon: MobileMenu['icon'],
    tone: string,
    href: string,
    page: string,
];

/** Input Operasi pages (resources/js/pages/operasi/input/{page}) with their per-page permission operasi.lapangan.{key}. */
const ENTRIES: Entry[] = [
    [
        'daily_report',
        'Input Harian',
        'Input Harian',
        'Produksi kWh, pemakaian sendiri, beban puncak & BBM',
        Zap,
        'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        dailyReport.index().url,
        'daily-report',
    ],
    [
        'star_stop',
        'Star-Stop Mesin',
        'Star-Stop',
        'Riwayat start, stop & gangguan mesin',
        TimerReset,
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        starStop.index().url,
        'star-stop',
    ],
    [
        'feeder',
        'Feeder',
        'Feeder',
        'Beban, tegangan & arus per feeder',
        Plug,
        'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        feeder.index().url,
        'feeder-readings',
    ],
    [
        'auxiliary',
        'Pasokan Cadangan',
        'Pasokan Cadangan',
        'kWh pasokan cadangan & pemakaian internal',
        Gauge,
        'bg-teal-500/10 text-teal-600 dark:text-teal-400',
        auxiliary.index().url,
        'auxiliary-readings',
    ],
    [
        'fuel_receipt',
        'Penerimaan BBM',
        'Terima BBM',
        'Penerimaan BBM/pelumas & sounding tangki',
        Fuel,
        'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        fuelReceipt.index().url,
        'fuel-receipts',
    ],
    [
        'kondisi_abnormal',
        'Kondisi Abnormal & Gangguan',
        'Abnormal',
        'Kejadian abnormal & gangguan mesin',
        AlertOctagon,
        'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        kondisiAbnormal.index().url,
        'kondisi-abnormal',
    ],
    [
        'resource_pembangkit',
        'Resource Pembangkit',
        'Resource',
        'Stok, pemakaian & penerimaan BBM harian',
        Droplet,
        'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        resourcePembangkit.index().url,
        'resource-pembangkit',
    ],
    [
        'material_peralatan',
        'Material & Peralatan',
        'Material',
        'Stok material, masuk, keluar & reorder',
        Package,
        'bg-lime-500/10 text-lime-700 dark:text-lime-400',
        materialPeralatan.index().url,
        'material-peralatan',
    ],
    [
        'permit_to_work',
        'Permit to Work (PTW)',
        'PTW',
        'Izin kerja open & close',
        FileCheck,
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        permitToWork.index().url,
        'permit-to-work',
    ],
    [
        'flm_monitoring',
        'Monitoring FLM',
        'FLM',
        'Temuan first line maintenance & tindakan awal',
        Wrench,
        'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        flmMonitoring.index().url,
        'flm-monitoring',
    ],
    [
        'patrol_check_mesin',
        'Patrol Check Mesin',
        'Patrol Check',
        'Pemeriksaan harian mesin per sistem: N / T',
        Cog,
        'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        patrolCheckMesin.index().url,
        'patrol-check-mesin',
    ],
    [
        'checklist_commissioning',
        'Checklist Commissioning Test Mesin',
        'Commissioning',
        'Kesiapan peralatan saat commissioning test',
        ClipboardCheck,
        'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400',
        checklistCommissioningMesin.index().url,
        'checklist-commissioning-mesin',
    ],
    [
        'unsafe_condition',
        'Unsafe Action & Condition',
        'Unsafe',
        'Temuan tindakan & kondisi tidak aman',
        ShieldAlert,
        'bg-red-500/10 text-red-600 dark:text-red-400',
        unsafeCondition.index().url,
        'unsafe-condition',
    ],
    [
        'program_5s5r',
        'Program 5S 5R Pengoperasian KIT',
        '5S 5R',
        'Ringkas, Rapi, Resik, Rawat, Rajin per minggu',
        Sparkles,
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        program5s5r.index().url,
        'program-5s5r',
    ],
];

export const OPERASI_MENUS: MobileMenu[] = ENTRIES.map(
    ([key, title, short, description, icon, tone, href, page]) => ({
        key: `operasi-${key}`,
        group: 'operasi',
        title,
        short,
        description,
        icon,
        tone,
        href,
        component: `operasi/input/${page}/index`,
        permission: `operasi.lapangan.${key}`,
        coveredBy: 'operasi.input.view',
    }),
);
