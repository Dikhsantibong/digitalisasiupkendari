import { HarTabelPage, harTabelBreadcrumbs } from '@/components/har/tabel-page';
import type { HarTabelPageProps } from '@/components/har/tabel-page';
import laporanGangguan from '@/routes/har/input/laporan-gangguan';

/**
 * Rekap Laporan Gangguan — definisi: App\Support\HarTabel\RekapGangguanTabel.
 */
export default function RekapLaporanGangguanPage(props: HarTabelPageProps) {
    return <HarTabelPage {...props} />;
}

RekapLaporanGangguanPage.layout = {
    breadcrumbs: harTabelBreadcrumbs('Rekap Laporan Gangguan', laporanGangguan.index().url),
};
