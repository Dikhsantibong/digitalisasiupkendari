import { CHECK_FIELD, HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import hydrotestRoutes from '@/routes/har/pengusahaan/hydrotest';

export default function HydrotestPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Checklist kebocoran hydrotest per silinder: O-ring liner (OL) dan liner (L)."
            indexUrl={hydrotestRoutes.index().url}
            storeUrl={hydrotestRoutes.store().url}
            tables={[
                {
                    key: 'checklist_items',
                    title: 'Kebocoran per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { oring_liner: 'v', liner: 'v', notes: '' } },
                    columns: [
                        { key: 'oring_liner', label: 'O-Ring Liner (OL)', ...CHECK_FIELD },
                        { key: 'liner', label: 'Liner (L)', ...CHECK_FIELD },
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

HydrotestPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Hydrotest', href: hydrotestRoutes.index() },
    ],
};
