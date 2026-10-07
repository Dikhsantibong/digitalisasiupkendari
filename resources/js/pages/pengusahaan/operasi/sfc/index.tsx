import { SfcPage } from '@/components/operasi/sfc-page';
import type { SfcPageProps } from '@/components/operasi/sfc-page';
import sfc from '@/routes/operasi/pengusahaan/sfc';

export default function SfcIndex(props: SfcPageProps) {
    return (
        <SfcPage
            {...props}
            title="SFC"
            basisLabel="kWh"
            decimals={4}
            routes={{ index: sfc.index, pdf: sfc.pdf }}
        />
    );
}
