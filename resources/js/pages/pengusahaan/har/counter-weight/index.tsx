import { CONDITION_FIELD, HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import counterWeightRoutes from '@/routes/har/pengusahaan/counter-weight';

export default function CounterWeightPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pemeriksaan kondisi kekencangan baut counter weight per silinder."
            indexUrl={counterWeightRoutes.index().url}
            storeUrl={counterWeightRoutes.store().url}
            tables={[
                {
                    key: 'measurements',
                    title: 'Kondisi Baut per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { condition: 'baik', notes: '' } },
                    columns: [
                        { key: 'condition', label: 'Kondisi Baut Counter Weight', ...CONDITION_FIELD },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'standard_allowed', label: 'Standar yang Diizinkan' },
                { key: 'notes', label: 'Catatan', type: 'textarea', wide: true },
            ]}
        />
    );
}

CounterWeightPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Baut Counter Weight', href: counterWeightRoutes.index() },
    ],
};
