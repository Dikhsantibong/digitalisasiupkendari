import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Laporan Inspeksi Checklist 5S5R Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `inspeksi-5s5r`).
 */
export default function Inspeksi5s5rSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

Inspeksi5s5rSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('input', 'inspeksi-5s5r', 'Laporan Inspeksi Checklist 5S5R Logistik & Gudang'),
};
