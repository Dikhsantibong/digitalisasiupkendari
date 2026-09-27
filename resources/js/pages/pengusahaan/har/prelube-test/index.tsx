import { CHECK_FIELD, HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import prelubeTestRoutes from '@/routes/har/pengusahaan/prelube-test';

export default function PrelubeTestPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Checklist sistem pelumasan awal per silinder: camshaft, conrod, piston dan rocker arm."
            indexUrl={prelubeTestRoutes.index().url}
            storeUrl={prelubeTestRoutes.store().url}
            tables={[
                {
                    key: 'checklist_items',
                    title: 'Checklist per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { camshaft: 'v', conrod: 'v', piston: 'v', rocker_arm: 'v', notes: '' } },
                    columns: [
                        { key: 'camshaft', label: 'Camshaft', ...CHECK_FIELD },
                        { key: 'conrod', label: 'Conrod', ...CHECK_FIELD },
                        { key: 'piston', label: 'Piston', ...CHECK_FIELD },
                        { key: 'rocker_arm', label: 'Rocker Arm', ...CHECK_FIELD },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'notes', label: 'Catatan', type: 'textarea', wide: true },
            ]}
        />
    );
}

PrelubeTestPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Prelube Test', href: prelubeTestRoutes.index() },
    ],
};
