import { ClipboardList, Fingerprint } from 'lucide-react';
import type { MobileMenu } from '@/layouts/mobile/types';
import logsheet from '@/routes/operator/logsheet';
import presensi from '@/routes/operator/presensi';

/** Menus of every field role: attendance and the operators' logbook mesin. */
export const UMUM_MENUS: MobileMenu[] = [
    {
        key: 'presensi',
        group: 'umum',
        title: 'Absensi',
        description: 'Absen masuk & pulang dalam radius kantor',
        icon: Fingerprint,
        tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        href: presensi.index().url,
        component: 'operator/presensi',
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
        component: 'operator/logsheet',
        permission: ['operator.logsheet.view', 'operator.logsheet.write'],
    },
];
