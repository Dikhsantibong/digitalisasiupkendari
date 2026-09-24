import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Inventarisasi Tools dan Material Bagian Lainnya — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `inventarisasi`).
 */
export default function JadwalInventarisasiSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalInventarisasiSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'inventarisasi', 'Jadwal Inventarisasi Tools dan Material Bagian Lainnya'),
};
