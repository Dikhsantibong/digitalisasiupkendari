import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import crankshaftDeflectionRoutes from '@/routes/har/pengusahaan/crankshaft-deflection';

export default function CrankshaftDeflectionPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran defleksi crankshaft per silinder pada posisi A – E."
            indexUrl={crankshaftDeflectionRoutes.index().url}
            storeUrl={crankshaftDeflectionRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Defleksi per Silinder (mm)',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { pos_a: '0', pos_b: '0', pos_c: '0', pos_d: '0', pos_e: '0' } },
                    columns: [
                        { key: 'pos_a', label: 'Posisi A', type: 'number' },
                        { key: 'pos_b', label: 'Posisi B', type: 'number' },
                        { key: 'pos_c', label: 'Posisi C', type: 'number' },
                        { key: 'pos_d', label: 'Posisi D', type: 'number' },
                        { key: 'pos_e', label: 'Posisi E', type: 'number' },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_min', label: 'Standar Minimum' },
                { key: 'standard_max', label: 'Standar Maksimum' },
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'cylinder_notes', label: 'Keterangan Silinder', type: 'textarea', wide: true },
                { key: 'visual_inspection', label: 'Hasil Pemeriksaan Visual', type: 'textarea', wide: true },
            ]}
        />
    );
}

CrankshaftDeflectionPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Defleksi Crankshaft', href: crankshaftDeflectionRoutes.index() },
    ],
};
