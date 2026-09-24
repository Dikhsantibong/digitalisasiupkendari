import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Pemeliharaan Logistik dan Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `pemeliharaan`).
 */
export default function JadwalPemeliharaanSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalPemeliharaanSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'pemeliharaan', 'Jadwal Pemeliharaan Logistik dan Gudang'),
};
