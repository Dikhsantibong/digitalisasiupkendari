import { HarTabelPage, harTabelBreadcrumbs } from '@/components/har/tabel-page';
import type { HarTabelPageProps } from '@/components/har/tabel-page';
import abnormalGangguan from '@/routes/har/input/abnormal-gangguan';

/**
 * Laporan Kondisi Abnormal dan Gangguan Pembangkit — definisi: App\Support\HarTabel\AbnormalGangguanTabel.
 */
export default function AbnormalGangguanPage(props: HarTabelPageProps) {
    return <HarTabelPage {...props} />;
}

AbnormalGangguanPage.layout = {
    breadcrumbs: harTabelBreadcrumbs('Laporan Kondisi Abnormal dan Gangguan', abnormalGangguan.index().url),
};
