import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import vibrationRoutes from '@/routes/har/pengusahaan/vibration';

export default function VibrationPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran vibrasi vertikal & horizontal (max / min) per titik pengukuran. Rata-rata dihitung otomatis saat disimpan."
            indexUrl={vibrationRoutes.index().url}
            storeUrl={vibrationRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Hasil Pengukuran per Titik',
                    rowHeader: 'Titik',
                    rowLabel: (row, index) => `${index + 1}. ${row.point || 'Titik'}`,
                    addable: { label: 'Tambah Titik Pengukuran', blank: (rows) => ({ pos: rows.length + 1, point: '', v_max: '', v_min: '', v_avg: '', h_max: '', h_min: '', h_avg: '', notes: '' }) },
                    columns: [
                        { key: 'point', label: 'Titik Pengukuran' },
                        { key: 'v_max', label: 'Vertikal Max', type: 'number' },
                        { key: 'v_min', label: 'Vertikal Min', type: 'number' },
                        { key: 'h_max', label: 'Horizontal Max', type: 'number' },
                        { key: 'h_min', label: 'Horizontal Min', type: 'number' },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_text', label: 'Standar', type: 'textarea', wide: true },
                { key: 'max_text', label: 'Batas Maksimum', type: 'textarea', wide: true },
                { key: 'conclusion_text', label: 'Kesimpulan', type: 'textarea', wide: true },
            ]}
        />
    );
}

VibrationPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Tekanan Vibrasi', href: vibrationRoutes.index() },
    ],
};
