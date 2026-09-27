import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import injectorPressureRoutes from '@/routes/har/pengusahaan/injector-pressure';

export default function InjectorPressurePage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran tekanan pengabutan injektor per silinder, sebelum dan sesudah kalibrasi."
            indexUrl={injectorPressureRoutes.index().url}
            storeUrl={injectorPressureRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Tekanan Pengabutan per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { pressure_before: '', pressure_after: '', nozzle_holes: '', notes: '' } },
                    columns: [
                        { key: 'pressure_before', label: 'Tekanan Sebelum (kg/cm²)', type: 'number' },
                        { key: 'pressure_after', label: 'Tekanan Sesudah (kg/cm²)', type: 'number' },
                        { key: 'nozzle_holes', label: 'Lubang Nozzle (BH)' },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'visual_inspection', label: 'Hasil Pemeriksaan Visual', type: 'textarea', wide: true },
            ]}
        />
    );
}

InjectorPressurePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Tekanan Pengabutan Injektor', href: injectorPressureRoutes.index() },
    ],
};
