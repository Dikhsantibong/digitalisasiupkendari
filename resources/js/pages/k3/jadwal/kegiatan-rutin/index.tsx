import { KegiatanJadwalPage } from '@/components/jadwal/kegiatan-jadwal-page';
import type { KegiatanJadwalProps } from '@/components/jadwal/kegiatan-jadwal-page';
import { dashboard } from '@/routes';
import jadwal from '@/routes/k3/jadwal';
import kegiatanRutin from '@/routes/k3/jadwal/kegiatan-rutin';

/** Jadwal Kegiatan Rutin K3L KIT: harian, mingguan, bulanan & non rutin. */
export default function K3KegiatanRutinPage(props: KegiatanJadwalProps) {
    return (
        <KegiatanJadwalPage
            {...props}
            config={{
                title: 'Jadwal Kegiatan Rutin K3L',
                longTitle:
                    'Jadwal Kegiatan Harian, Mingguan, Bulanan & Non Rutin K3L KIT',
                kopTitle: 'JADWAL KEGIATAN K3L',
                subject: 'K3L',
                placeholder: 'Contoh: Inspeksi Rambu K3L',
                routes: {
                    index: kegiatanRutin.index().url,
                    store: kegiatanRutin.store().url,
                    pdf: kegiatanRutin.pdf().url,
                    back: jadwal.index().url,
                },
            }}
        />
    );
}

K3KegiatanRutinPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3 & Lingkungan', href: jadwal.index() },
        { title: 'Kegiatan Rutin K3L KIT', href: kegiatanRutin.index() },
    ],
};
