import {
    Activity,
    AlertTriangle,
    BatteryCharging,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Cog,
    Droplet,
    FileText,
    Flame,
    Gauge,
    Image,
    MessageSquare,
    NotebookPen,
    Ruler,
    ShieldAlert,
    Siren,
    Sparkles,
    Timer,
    Vibrate,
    Waves,
    Wrench,
    Zap,
} from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import dailyMeeting from '@/routes/har/formulir/daily-meeting';
import formulirLaporanGangguan from '@/routes/har/formulir/laporan-gangguan';
import logbookMutasi from '@/routes/har/formulir/logbook-mutasi';
import abnormalGangguan from '@/routes/har/input/abnormal-gangguan';
import activity from '@/routes/har/input/activity';
import attachment from '@/routes/har/input/attachment';
import rekapGangguan from '@/routes/har/input/laporan-gangguan';
import harLembar from '@/routes/har/input/lembar';
import patrolCheckParameter from '@/routes/har/input/patrol-check-parameter';
import program5s5r from '@/routes/har/input/program-5s5r';
import serviceRequest from '@/routes/har/input/service-request';
import unsafeCondition from '@/routes/har/input/unsafe-condition';
import workOrder from '@/routes/har/input/work-order';
import axialConrod from '@/routes/har/pengusahaan/axial-conrod';
import batteryVoltage from '@/routes/har/pengusahaan/battery-voltage';
import clearanceValve from '@/routes/har/pengusahaan/clearance-valve';
import combustionPressure from '@/routes/har/pengusahaan/combustion-pressure';
import counterWeight from '@/routes/har/pengusahaan/counter-weight';
import crankshaftDeflection from '@/routes/har/pengusahaan/crankshaft-deflection';
import hydrotest from '@/routes/har/pengusahaan/hydrotest';
import injectorPressure from '@/routes/har/pengusahaan/injector-pressure';
import lubeQuality from '@/routes/har/pengusahaan/lube-quality';
import motorCurrent from '@/routes/har/pengusahaan/motor-current';
import prelubeTest from '@/routes/har/pengusahaan/prelube-test';
import rencanaRealisasi from '@/routes/har/pengusahaan/rencana-realisasi';
import timingInjectionPump from '@/routes/har/pengusahaan/timing-injection-pump';
import vibration from '@/routes/har/pengusahaan/vibration';

type Entry = [
    key: string,
    title: string,
    short: string,
    description: string,
    icon: MobileMenu['icon'],
    tone: string,
    href: string,
    component: string,
];

const INPUT: Entry[] = [
    [
        'work_order',
        'Work Order',
        'Work Order',
        'WO pemeliharaan & progresnya',
        Wrench,
        'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        workOrder.index().url,
        'har/input/work-order/index',
    ],
    [
        'service_request',
        'Service Request',
        'Service Request',
        'Permintaan perbaikan dari operasi',
        ClipboardList,
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        serviceRequest.index().url,
        'har/input/service-request/index',
    ],
    [
        'activity',
        'Log Kegiatan',
        'Log Kegiatan',
        'Kegiatan pemeliharaan harian',
        Activity,
        'bg-teal-500/10 text-teal-600 dark:text-teal-400',
        activity.index().url,
        'har/input/activity/index',
    ],
    [
        'attachment',
        'Lampiran Foto',
        'Foto',
        'Foto dokumentasi pemeliharaan',
        Image,
        'bg-pink-500/10 text-pink-600 dark:text-pink-400',
        attachment.index().url,
        'har/input/attachment/index',
    ],
    [
        'unsafe_condition',
        'Unsafe Action & Condition',
        'Unsafe',
        'Temuan tindakan & kondisi tidak aman',
        ShieldAlert,
        'bg-red-500/10 text-red-600 dark:text-red-400',
        unsafeCondition.index().url,
        'har/input/unsafe-condition/index',
    ],
    [
        'rekap_gangguan',
        'Rekap Laporan Gangguan',
        'Rekap Gangguan',
        'Rekap gangguan, tindakan & kWh loss',
        FileText,
        'bg-slate-500/10 text-slate-600 dark:text-slate-300',
        rekapGangguan.index().url,
        'har/input/laporan-gangguan/index',
    ],
    [
        'abnormal_gangguan',
        'Abnormal & Gangguan',
        'Abnormal',
        'Laporan kondisi abnormal & gangguan pembangkit',
        Siren,
        'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        abnormalGangguan.index().url,
        'har/input/abnormal-gangguan/index',
    ],
    [
        'program_5s5r',
        'Program 5S 5R Pemeliharaan',
        '5S 5R',
        'Ringkas, Rapi, Resik, Rawat, Rajin per minggu',
        Sparkles,
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        program5s5r.index().url,
        'har/input/program-5s5r/index',
    ],
    [
        'patrol_check_pemeliharaan',
        'Patrol Check Pemeliharaan',
        'Patrol Check',
        'Pemeriksaan harian peralatan: N / T',
        ClipboardCheck,
        'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        harLembar.index('patrol-check-pemeliharaan').url,
        'har/input/patrol-check-pemeliharaan/index',
    ],
    [
        'patrol_check_parameter',
        'Patrol Check Parameter Mesin',
        'Parameter',
        'Pembacaan harian engine, generator, trafo & baterai',
        Gauge,
        'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        patrolCheckParameter.index().url,
        'har/input/patrol-check-parameter/index',
    ],
];

/** Input pemeliharaan — Akses 2 Pengusahaan (pages/pengusahaan/har). */
const INPUT_PENGUSAHAAN: Entry[] = [
    [
        'schedule',
        'Rencana & Realisasi',
        'Rencana',
        'Jadwal pemeliharaan & realisasinya',
        CalendarRange,
        'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        rencanaRealisasi.index().url,
        'pengusahaan/har/rencana-realisasi/index',
    ],
];

/** Formulir teknis pemeliharaan — Akses 2 Pengusahaan (pages/pengusahaan/har). */
const FORMULIR_PENGUSAHAAN: Entry[] = [
    [
        'prelube_test',
        'Checklist Prelube Test',
        'Prelube',
        'Sistem pelumasan awal & tekanan oli',
        Droplet,
        'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        prelubeTest.index().url,
        'pengusahaan/har/prelube-test/index',
    ],
    [
        'hydrotest',
        'Checklist Hydrotest',
        'Hydrotest',
        'Uji tekanan hidrolik pipa & bejana',
        Waves,
        'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        hydrotest.index().url,
        'pengusahaan/har/hydrotest/index',
    ],
    [
        'timing_injection_pump',
        'Timing Injection Pump',
        'Timing Pump',
        'Pengukuran timing injection pump',
        Timer,
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        timingInjectionPump.index().url,
        'pengusahaan/har/timing-injection-pump/index',
    ],
    [
        'crankshaft_deflection',
        'Defleksi Crankshaft',
        'Crankshaft',
        'Pengukuran defleksi crankshaft',
        Ruler,
        'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        crankshaftDeflection.index().url,
        'pengusahaan/har/crankshaft-deflection/index',
    ],
    [
        'counter_weight',
        'Baut Counter Weight',
        'Counter Weight',
        'Kekencangan baut counter weight',
        Cog,
        'bg-slate-500/10 text-slate-600 dark:text-slate-300',
        counterWeight.index().url,
        'pengusahaan/har/counter-weight/index',
    ],
    [
        'axial_conrod',
        'Axial Conrod & Baut Conrod',
        'Axial Conrod',
        'Pemeriksaan axial & baut conrod',
        Wrench,
        'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        axialConrod.index().url,
        'pengusahaan/har/axial-conrod/index',
    ],
    [
        'clearance_valve',
        'Clearance Valve',
        'Clearance',
        'Pengukuran clearance valve',
        Ruler,
        'bg-teal-500/10 text-teal-600 dark:text-teal-400',
        clearanceValve.index().url,
        'pengusahaan/har/clearance-valve/index',
    ],
    [
        'combustion_pressure',
        'Tekanan Pembakaran',
        'Pembakaran',
        'Pengukuran tekanan pembakaran',
        Flame,
        'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        combustionPressure.index().url,
        'pengusahaan/har/combustion-pressure/index',
    ],
    [
        'injector_pressure',
        'Tekanan Pengabutan Injektor',
        'Injektor',
        'Tekanan pengabutan injektor',
        Gauge,
        'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        injectorPressure.index().url,
        'pengusahaan/har/injector-pressure/index',
    ],
    [
        'motor_current',
        'Arus Kerja Elektro Motor',
        'Arus Motor',
        'Data arus kerja elektro motor',
        Zap,
        'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400',
        motorCurrent.index().url,
        'pengusahaan/har/motor-current/index',
    ],
    [
        'vibration',
        'Tekanan Vibrasi',
        'Vibrasi',
        'Pengukuran vibrasi',
        Vibrate,
        'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400',
        vibration.index().url,
        'pengusahaan/har/vibration/index',
    ],
    [
        'lube_quality',
        'Kualitas Pelumas',
        'Pelumas',
        'Pengukuran kualitas pelumas',
        Droplet,
        'bg-lime-500/10 text-lime-700 dark:text-lime-400',
        lubeQuality.index().url,
        'pengusahaan/har/lube-quality/index',
    ],
    [
        'battery_voltage',
        'Tegangan Baterai',
        'Baterai',
        'Pengukuran tegangan baterai',
        BatteryCharging,
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        batteryVoltage.index().url,
        'pengusahaan/har/battery-voltage/index',
    ],
];

const FORMULIR: Entry[] = [
    [
        'laporan_gangguan',
        'Laporan Gangguan (LH-05)',
        'LH-05',
        'Formulir laporan gangguan',
        AlertTriangle,
        'bg-red-500/10 text-red-600 dark:text-red-400',
        formulirLaporanGangguan.index().url,
        'har/formulir/laporan-gangguan/index',
    ],
    [
        'daily_meeting',
        'Daily Meeting',
        'Daily Meeting',
        'Buat meeting & QR absensi peserta',
        MessageSquare,
        'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        dailyMeeting.index().url,
        'har/formulir/daily-meeting/index',
    ],
    [
        'logbook_mutasi',
        'Logbook Mutasi Harian',
        'Logbook Mutasi',
        'Absensi, APD, job harian & kondisi K3',
        NotebookPen,
        'bg-purple-500/10 text-purple-600 dark:text-purple-400',
        logbookMutasi.index().url,
        'har/formulir/logbook-mutasi/index',
    ],
];

const toMenus = (entries: Entry[], group: MobileMenu['group'], coveredBy = 'har.input.view'): MobileMenu[] =>
    entries.map(
        ([key, title, short, description, icon, tone, href, component]) => ({
            key: `har-${key}`,
            group,
            title,
            short,
            description,
            icon,
            tone,
            href,
            component,
            permission: `har.lapangan.${key}`,
            coveredBy,
        }),
    );

/** Input Pemeliharaan & Formulir Pemeliharaan pages with their per-page permission har.lapangan.{key}. */
export const PEMELIHARAAN_MENUS: MobileMenu[] = [
    ...toMenus(INPUT, 'har-input'),
    ...toMenus(FORMULIR, 'har-formulir'),
    ...toMenus(INPUT_PENGUSAHAAN, 'har-input', 'har.pengusahaan.view'),
    ...toMenus(FORMULIR_PENGUSAHAAN, 'har-formulir', 'har.pengusahaan.view'),
];
