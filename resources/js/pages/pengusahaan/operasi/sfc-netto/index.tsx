import { SfcPage } from '@/components/operasi/sfc-page';
import type { SfcPageProps } from '@/components/operasi/sfc-page';
import sfcNetto from '@/routes/operasi/pengusahaan/sfc-netto';

export default function SfcNettoIndex(props: SfcPageProps) {
    return (
        <SfcPage
            {...props}
            title="SFC Netto"
            basisLabel="kWh Netto"
            decimals={3}
            routes={{ index: sfcNetto.index, pdf: sfcNetto.pdf }}
        />
    );
}
