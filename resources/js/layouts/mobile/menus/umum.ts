import { ClipboardList, Fingerprint, Handshake } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import logsheet from '@/routes/operator/logsheet';
import mutasi from '@/routes/operator/mutasi';
import presensi from '@/routes/operator/presensi';

/** Menus of every field role: attendance, the operators' logbook mesin and the shift handover sheet. */
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
];
