import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Pembuatan Instruksi Kerja (IK) Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `ik`).
 */
export default function JadwalIkSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalIkSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'ik', 'Jadwal Pembuatan Instruksi Kerja (IK) Logistik & Gudang'),
};
