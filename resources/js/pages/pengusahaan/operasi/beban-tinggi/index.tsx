import { MesinHarianPage } from '@/components/operasi/mesin-harian-page';
import type { MesinHarianPageProps } from '@/components/operasi/mesin-harian-page';
import bebanTinggi from '@/routes/operasi/pengusahaan/beban-tinggi';

export default function BebanTinggiIndex(props: MesinHarianPageProps) {
    return (
        <MesinHarianPage
            {...props}
            config={{
                title: 'Beban Tertinggi',
                description:
                    'Rekap beban harian tertinggi (kW) per mesin terhadap daya mampu',
                mode: 'max',
                satuan: 'kW',
                routes: {
                    index: bebanTinggi.index,
                    store: bebanTinggi.store,
                    pdf: bebanTinggi.pdf,
                },
            }}
        />
    );
}
