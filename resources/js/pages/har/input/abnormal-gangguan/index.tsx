import { KondisiAbnormalInputView } from '@/pages/operasi/input/kondisi-abnormal';
import type { KondisiAbnormalProps } from '@/pages/operasi/input/kondisi-abnormal';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import abnormalGangguanRoutes from '@/routes/har/input/abnormal-gangguan';

/**
 * Laporan Kondisi Abnormal dan Gangguan Pembangkit (Modul Pemeliharaan / HAR):
 * memakai format, tabel desktop, dan form mobile yang sama persis dengan Input
 * Operasi → Kondisi Abnormal & Gangguan, serta berbagi data tabel `kondisi_abnormals`.
 */
export default function AbnormalGangguanPage(props: KondisiAbnormalProps) {
    return (
        <KondisiAbnormalInputView
            {...props}
            routeMap={abnormalGangguanRoutes}
            pageTitle="Kondisi Abnormal & Gangguan"
        />
    );
}

AbnormalGangguanPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        {
            title: 'Kondisi Abnormal & Gangguan',
            href: abnormalGangguanRoutes.index(),
        },
    ],
};
