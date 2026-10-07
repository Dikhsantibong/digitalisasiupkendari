import { MesinHarianPage } from '@/components/operasi/mesin-harian-page';
import type { MesinHarianPageProps } from '@/components/operasi/mesin-harian-page';
import taraKalor from '@/routes/operasi/pengusahaan/tara-kalor';

export default function TaraKalorIndex(props: MesinHarianPageProps) {
    return (
        <MesinHarianPage
            {...props}
            config={{
                title: 'kWh / kCal (Tara Kalor)',
                description:
                    'Tara kalor (kCal/kWh) per mesin per hari, diisi manual. Total per hari dan JMH per mesin adalah rata-rata nilai yang terisi',
                mode: 'average',
                satuan: 'kCal/kWh',
                routes: {
                    index: taraKalor.index,
                    store: taraKalor.store,
                    pdf: taraKalor.pdf,
                },
            }}
        />
    );
}
