import { OpnameSheetPage } from '@/components/operator/opname-sheet';
import type { OpnamePageProps } from '@/components/operator/opname-sheet';
import { dashboard } from '@/routes';
import opnamePelumas from '@/routes/operator/opname-pelumas';

/** Opname Pelumas: stand awal, stand akhir and sub total of each jenis pelumas. */
export default function OpnamePelumasIndex(props: OpnamePageProps) {
    return (
        <OpnameSheetPage
            key={`${props.filters.unit_id}-${props.filters.tanggal}-${props.saved?.at ?? ''}`}
            title="Opname Pelumas"
            description="Stand awal, stand akhir dan sub total setiap jenis pelumas"
            indexUrl={opnamePelumas.index().url}
            storeUrl={opnamePelumas.store().url}
            props={props}
        />
    );
}

OpnamePelumasIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Opname Pelumas', href: opnamePelumas.index() },
    ],
};
