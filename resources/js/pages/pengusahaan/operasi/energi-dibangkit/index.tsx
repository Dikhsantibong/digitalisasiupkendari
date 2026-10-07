import { EnergiPage } from '@/components/operasi/energi-page';
import type { EnergiPageProps } from '@/components/operasi/energi-page';
import energi from '@/routes/operasi/pengusahaan/energi-dibangkit';

export default function EnergiDibangkitIndex(props: EnergiPageProps) {
    return (
        <EnergiPage
            {...props}
            description="kWh nett (produksi dikurangi pemakaian sendiri) per mesin per hari"
            routes={{ index: energi.index, pdf: energi.pdf }}
        />
    );
}
