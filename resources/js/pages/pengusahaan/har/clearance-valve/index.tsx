import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import clearanceValveRoutes from '@/routes/har/pengusahaan/clearance-valve';

export default function ClearanceValvePage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran clearance katup buang (exhaust) dan hisap (inlet), kanan & kiri, sebelum dan sesudah penyetelan."
            indexUrl={clearanceValveRoutes.index().url}
            storeUrl={clearanceValveRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Clearance per Silinder (mm)',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { ex_before_r: '', ex_before_l: '', ex_after_r: '', ex_after_l: '', in_before_r: '', in_before_l: '', in_after_r: '', in_after_l: '' } },
                    columns: [
                        { key: 'ex_before_r', label: 'Exhaust Sebelum R', type: 'number' },
                        { key: 'ex_before_l', label: 'Exhaust Sebelum L', type: 'number' },
                        { key: 'ex_after_r', label: 'Exhaust Sesudah R', type: 'number' },
                        { key: 'ex_after_l', label: 'Exhaust Sesudah L', type: 'number' },
                        { key: 'in_before_r', label: 'Inlet Sebelum R', type: 'number' },
                        { key: 'in_before_l', label: 'Inlet Sebelum L', type: 'number' },
                        { key: 'in_after_r', label: 'Inlet Sesudah R', type: 'number' },
                        { key: 'in_after_l', label: 'Inlet Sesudah L', type: 'number' },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_ex', label: 'Standar Exhaust' },
                { key: 'standard_in', label: 'Standar Inlet' },
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'cylinder_notes', label: 'Keterangan Silinder', type: 'textarea', wide: true },
                { key: 'visual_inspection', label: 'Hasil Pemeriksaan Visual', type: 'textarea', wide: true },
            ]}
        />
    );
}

ClearanceValvePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Clearance Valve', href: clearanceValveRoutes.index() },
    ],
};
