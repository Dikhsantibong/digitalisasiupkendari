import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Meeting Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `meeting`).
 */
export default function JadwalMeetingSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalMeetingSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'meeting', 'Jadwal Meeting Logistik & Gudang'),
};
