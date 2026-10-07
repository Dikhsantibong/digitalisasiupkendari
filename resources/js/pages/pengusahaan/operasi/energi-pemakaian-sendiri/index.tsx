import { EnergiPage } from '@/components/operasi/energi-page';
import type { EnergiPageProps } from '@/components/operasi/energi-page';
import energi from '@/routes/operasi/pengusahaan/energi-pemakaian-sendiri';

export default function EnergiPemakaianSendiriIndex(props: EnergiPageProps) {
    return (
        <EnergiPage
            {...props}
            description="kWh pemakaian sendiri per mesin per hari"
            routes={{ index: energi.index, pdf: energi.pdf }}
        />
    );
}
