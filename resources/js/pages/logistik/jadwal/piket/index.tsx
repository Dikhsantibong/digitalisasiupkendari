import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Piket Patrol Check Logistik & Gudang (On Call) — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `piket`).
 */
export default function JadwalPiketSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalPiketSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'piket', 'Jadwal Piket Patrol Check Logistik & Gudang (On Call)'),
};
