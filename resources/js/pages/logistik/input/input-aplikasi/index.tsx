import { LogistikJadwalSheetPage, logistikSheetBreadcrumbs } from '@/components/logistik/jadwal-sheet-page';
import type { LogistikJadwalSheetPageProps } from '@/components/logistik/jadwal-sheet-page';

/**
 * Laporan Input Data Aplikasi Pembangkit — definisi sheet: App\Support\LogistikJadwal::SHEETS (key `input-aplikasi`).
 */
export default function InputAplikasiSheet(props: LogistikJadwalSheetPageProps) {
    return <LogistikJadwalSheetPage {...props} />;
}

InputAplikasiSheet.layout = {
    breadcrumbs: logistikSheetBreadcrumbs('input', 'input-aplikasi', 'Laporan Input Data Aplikasi Pembangkit'),
};
