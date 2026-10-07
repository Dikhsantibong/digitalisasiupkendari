import { TugPage } from '@/components/operasi/tug-page';
import type { TugPageProps } from '@/components/operasi/tug-page';
import pemakaianBbm from '@/routes/operasi/pengusahaan/pemakaian-bbm';
import tugBbm from '@/routes/operasi/pengusahaan/tug-bbm';

export default function TugBbmIndex(props: TugPageProps) {
    return (
        <TugPage
            {...props}
            config={{
                title: 'TUG 9 BBM',
                description:
                    'Rekap Bon Pemakaian Energi Primer (HSD/MFO/Batubara) per mesin',
                source: {
                    title: 'Pemakaian Bahan Bakar',
                    index: pemakaianBbm.index,
                },
                routes: {
                    index: tugBbm.index,
                    store: tugBbm.store,
                    pdf: tugBbm.pdf,
                },
                emptyColumns:
                    'Mesin ini tidak memakai jenis BBM unit. Jenis BBM diambil dari Tangki BBM aktif unit dan BBM mesin di Master Mesin.',
            }}
        />
    );
}
