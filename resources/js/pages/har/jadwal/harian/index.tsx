import { KegiatanJadwalPage } from '@/components/jadwal/kegiatan-jadwal-page';
import type { KegiatanJadwalProps } from '@/components/jadwal/kegiatan-jadwal-page';
import jadwal from '@/routes/har/jadwal';
import harian from '@/routes/har/jadwal/harian';

/** Jadwal Kegiatan Pemeliharaan: harian, mingguan, bulanan & non rutin (2026-11-25). */
export default function HarJadwalHarianPage(props: KegiatanJadwalProps) {
    return (
        <KegiatanJadwalPage
            {...props}
            config={{
                title: 'Jadwal Kegiatan Pemeliharaan',
                longTitle:
                    'Jadwal Kegiatan Harian, Mingguan, Bulanan & Non Rutin Pemeliharaan',
                kopTitle: 'JADWAL KEGIATAN PEMELIHARAAN',
                subject: 'Pemeliharaan',
                placeholder: 'Contoh: Pengecekan kebocoran oli mesin',
                routes: {
                    index: harian.index().url,
                    store: harian.store().url,
                    pdf: harian.pdf().url,
                    back: jadwal.index().url,
                },
            }}
        />
    );
}
