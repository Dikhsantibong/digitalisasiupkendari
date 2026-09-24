import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Shift Operator Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `shift`).
 */
export default function JadwalShiftSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalShiftSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'shift', 'Jadwal Shift Operator Logistik & Gudang'),
};
