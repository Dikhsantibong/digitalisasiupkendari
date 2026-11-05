import { OpnameSheetPage } from '@/components/operator/opname-sheet';
import type { OpnamePageProps } from '@/components/operator/opname-sheet';
import { dashboard } from '@/routes';
import opnameBbm from '@/routes/operator/opname-bbm';

/** Opname BBM: Opname Fisik (storage & setlink) and Opname Administrasi (service & flowmeter BBM). */
export default function OpnameBbmIndex(props: OpnamePageProps) {
    return (
        <OpnameSheetPage
            key={`${props.filters.unit_id}-${props.filters.tanggal}-${props.saved?.at ?? ''}`}
            title="Opname BBM"
            description="Opname fisik tangki storage & setlink, opname administrasi tangki service & flowmeter BBM"
            indexUrl={opnameBbm.index().url}
            storeUrl={opnameBbm.store().url}
            props={props}
        />
    );
}

OpnameBbmIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Opname BBM', href: opnameBbm.index() },
    ],
};
