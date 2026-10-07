import { MesinHarianPage } from '@/components/operasi/mesin-harian-page';
import type { MesinHarianPageProps } from '@/components/operasi/mesin-harian-page';
import kaliGangguan from '@/routes/operasi/pengusahaan/kali-gangguan';

export default function KaliGangguanIndex(props: MesinHarianPageProps) {
    return (
        <MesinHarianPage
            {...props}
            config={{
                title: 'Jumlah Kali Gangguan',
                description:
                    'Jumlah kejadian gangguan per mesin per hari, bisa diambil dari Star-Stop',
                mode: 'sum',
                satuan: 'kali',
                integer: true,
                routes: {
                    index: kaliGangguan.index,
                    store: kaliGangguan.store,
                    pdf: kaliGangguan.pdf,
                },
            }}
        />
    );
}
