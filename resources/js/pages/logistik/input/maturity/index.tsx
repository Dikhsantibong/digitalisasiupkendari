import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Maturity Level Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `maturity`).
 */
export default function MaturitySheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

MaturitySheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('input', 'maturity', 'Maturity Level Logistik & Gudang'),
};
