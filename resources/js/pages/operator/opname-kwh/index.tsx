import { OpnameSheetPage } from '@/components/operator/opname-sheet';
import type { OpnamePageProps } from '@/components/operator/opname-sheet';
import { dashboard } from '@/routes';
import opnameKwh from '@/routes/operator/opname-kwh';

/** Opname kWh: kWh produksi, kWh PS and susut trafo, with kWh netto & SFC. */
export default function OpnameKwhIndex(props: OpnamePageProps) {
    return (
        <OpnameSheetPage
            key={`${props.filters.unit_id}-${props.filters.tanggal}-${props.saved?.at ?? ''}`}
            title="Opname kWh"
            description="Stand kWh produksi, kWh PS dan susut trafo, dengan kWh netto & SFC"
            indexUrl={opnameKwh.index().url}
            storeUrl={opnameKwh.store().url}
            props={props}
        />
    );
}

OpnameKwhIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Opname kWh', href: opnameKwh.index() },
    ],
};
