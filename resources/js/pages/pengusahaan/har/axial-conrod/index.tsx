import { CONDITION_FIELD, HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import axialConrodRoutes from '@/routes/har/pengusahaan/axial-conrod';

export default function AxialConrodPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pemeriksaan axial connecting rod dan pengencangan baut conrod per silinder."
            indexUrl={axialConrodRoutes.index().url}
            storeUrl={axialConrodRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Pemeriksaan per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { axial_check: 'baik', bolt_tightening: 'baik', notes: '' } },
                    columns: [
                        { key: 'axial_check', label: 'Pengecekan Axial Conrod', ...CONDITION_FIELD },
                        { key: 'bolt_tightening', label: 'Pengencangan Baut', ...CONDITION_FIELD },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'torque_standard', label: 'Torsi Standar' },
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'notes', label: 'Catatan', type: 'textarea', wide: true },
            ]}
        />
    );
}

AxialConrodPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Axial Conrod', href: axialConrodRoutes.index() },
    ],
};
