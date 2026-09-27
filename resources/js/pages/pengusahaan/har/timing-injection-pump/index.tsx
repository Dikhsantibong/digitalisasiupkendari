import { CHECK_FIELD, HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import timingInjectionPumpRoutes from '@/routes/har/pengusahaan/timing-injection-pump';

export default function TimingInjectionPumpPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Checklist timing pembakaran injection pump per silinder, sebelum dan sesudah penyetelan."
            indexUrl={timingInjectionPumpRoutes.index().url}
            storeUrl={timingInjectionPumpRoutes.store().url}
            tables={[
                {
                    key: 'checklist_items',
                    title: 'Timing Pembakaran per Silinder',
                    rowHeader: 'Silinder',
                    rowLabel: (row) => `Silinder ${row.cylinder}`,
                    cylinders: { numberKey: 'cylinder', blank: { timing_before: 'v', timing_after: 'v', notes: '' } },
                    columns: [
                        { key: 'timing_before', label: 'Sebelum', ...CHECK_FIELD },
                        { key: 'timing_after', label: 'Sesudah', ...CHECK_FIELD },
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

TimingInjectionPumpPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Timing Injection Pump', href: timingInjectionPumpRoutes.index() },
    ],
};
