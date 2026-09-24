import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Piket Patrol Check Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `piket-patrol-check`).
 */
export default function JadwalPiketPatrolCheckSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalPiketPatrolCheckSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'piket-patrol-check', 'Jadwal Piket Patrol Check Logistik & Gudang'),
};
