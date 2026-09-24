import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Laporan Patrol Checklist Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `patrol-check`).
 */
export default function PatrolCheckSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

PatrolCheckSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('input', 'patrol-check', 'Laporan Patrol Checklist Logistik & Gudang'),
};
