import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Pelaksanaan 5S5R Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `5s5r`).
 */
export default function Jadwal5s5rSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

Jadwal5s5rSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', '5s5r', 'Jadwal Pelaksanaan 5S5R Logistik & Gudang'),
};
