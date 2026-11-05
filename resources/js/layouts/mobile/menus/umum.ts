import { BarChart3, ClipboardList, Droplets, FilePen, Fingerprint, Fuel, Handshake, Power, Zap } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import logsheet from '@/routes/operator/logsheet';
import mutasi from '@/routes/operator/mutasi';
import opnameBbm from '@/routes/operator/opname-bbm';
import opnameKwh from '@/routes/operator/opname-kwh';
import opnamePelumas from '@/routes/operator/opname-pelumas';
import pengajuan from '@/routes/operator/pengajuan';
import presensi from '@/routes/operator/presensi';
import resume from '@/routes/operator/resume';
import startStop from '@/routes/operator/start-stop';

/** Menus of every field role: attendance, leave & shift-swap requests, the logbook mesin, the shift handover sheet and the personal resume. */
export const UMUM_MENUS: MobileMenu[] = [
    {
        key: 'presensi',
        group: 'umum',
        title: 'Absensi',
        description: 'Absen masuk & pulang dalam radius kantor',
        icon: Fingerprint,
        tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        href: presensi.index().url,
        component: 'operator/presensi/index',
        permission: 'operator.presensi',
    },
    {
        key: 'pengajuan',
        group: 'umum',
        title: 'Pengajuan Absensi',
        short: 'Izin & Tukar Shift',
        description: 'Ajukan izin, sakit, cuti, atau tukar shift dengan rekan',
        icon: FilePen,
        tone: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        href: pengajuan.index().url,
        component: 'operator/pengajuan/index',
        permission: ['operator.presensi', 'operator.absensi.view'],
    },
    {
        key: 'logsheet',
        group: 'umum',
        title: 'Logsheet Mesin',
        short: 'Logsheet Mesin',
        description: 'Catat pembacaan parameter mesin per jam',
        icon: ClipboardList,
        tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        href: logsheet.index().url,
        component: 'operator/logsheet/index',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
    {
        key: 'start-stop',
        group: 'umum',
        title: 'Start Stop Engine',
        short: 'Start Stop',
        description: 'Catat jam start, CBG on/off dan stop mesin',
        icon: Power,
        tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        href: startStop.index().url,
        component: 'operator/start-stop/index',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
    {
        key: 'opname-bbm',
        group: 'umum',
        title: 'Opname BBM',
        short: 'Opname BBM',
        description: 'Opname fisik tangki & administrasi BBM',
        icon: Fuel,
        tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        href: opnameBbm.index().url,
        component: 'operator/opname-bbm/index',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
    {
        key: 'opname-pelumas',
        group: 'umum',
        title: 'Opname Pelumas',
        short: 'Opname Pelumas',
        description: 'Stand awal & akhir setiap jenis pelumas',
        icon: Droplets,
        tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        href: opnamePelumas.index().url,
        component: 'operator/opname-pelumas/index',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
    {
        key: 'opname-kwh',
        group: 'umum',
        title: 'Opname kWh',
        short: 'Opname kWh',
        description: 'Stand kWh produksi, PS & susut trafo',
        icon: Zap,
        tone: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400',
        href: opnameKwh.index().url,
        component: 'operator/opname-kwh/index',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
    {
        key: 'mutasi',
        group: 'umum',
        title: 'Lembar Mutasi Operator',
        short: 'Lembar Mutasi',
        description: 'Serah terima tugas antar regu per shift',
        icon: Handshake,
        tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        href: mutasi.index().url,
        component: 'operator/mutasi/index',
        permission: ['operator.mutasi.view', 'operator.mutasi.write'],
    },
    {
        key: 'resume',
        group: 'umum',
        title: 'Resume Statistik',
        short: 'Resume Saya',
        description: 'Realisasi shift harian & pekerjaan yang sudah dikerjakan',
        icon: BarChart3,
        tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        href: resume.index().url,
        component: 'operator/resume/index',
        permission: ['operator.presensi', 'operator.absensi.view'],
    },
];
