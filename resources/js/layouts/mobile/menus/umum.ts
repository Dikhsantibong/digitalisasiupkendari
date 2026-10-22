import { BarChart3, ClipboardList, FilePen, Fingerprint, Handshake } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import logsheet from '@/routes/operator/logsheet';
import mutasi from '@/routes/operator/mutasi';
import pengajuan from '@/routes/operator/pengajuan';
import presensi from '@/routes/operator/presensi';
import resume from '@/routes/operator/resume';

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
        title: 'Logbook Mesin (Logsheet)',
        short: 'Logbook Mesin',
        description: 'Catat pembacaan parameter mesin per jam',
        icon: ClipboardList,
        tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        href: logsheet.index().url,
        component: 'operator/logsheet/index',
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
