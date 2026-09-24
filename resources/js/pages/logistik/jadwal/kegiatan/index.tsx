import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Jadwal Kegiatan Logistik & Gudang — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `kegiatan`).
 */
export default function JadwalKegiatanSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

JadwalKegiatanSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('jadwal', 'kegiatan', 'Jadwal Kegiatan Logistik & Gudang'),
};
