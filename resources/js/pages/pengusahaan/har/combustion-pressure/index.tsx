import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import combustionPressureRoutes from '@/routes/har/pengusahaan/combustion-pressure';

export default function CombustionPressurePage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran tekanan pembakaran, temperatur gas buang dan rack injection pump per silinder."
            indexUrl={combustionPressureRoutes.index().url}
            storeUrl={combustionPressureRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Pengukuran per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { combustion_pressure: '', exhaust_temp: '', rack_position: '' } },
                    columns: [
                        { key: 'combustion_pressure', label: 'Tekanan Pembakaran (kg/cm²)', type: 'number' },
                        { key: 'exhaust_temp', label: 'Temperatur Gas Buang (°C)', type: 'number' },
                        { key: 'rack_position', label: 'Rack Injection Pump', type: 'number' },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'cylinder_notes', label: 'Keterangan Silinder', type: 'textarea', wide: true },
                { key: 'visual_inspection', label: 'Hasil Pemeriksaan Visual', type: 'textarea', wide: true },
            ]}
        />
    );
}

CombustionPressurePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Tekanan Pembakaran', href: combustionPressureRoutes.index() },
    ],
};
