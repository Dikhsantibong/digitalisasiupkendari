import { KegiatanJadwalPage } from '@/components/jadwal/kegiatan-jadwal-page';
import type { KegiatanJadwalProps } from '@/components/jadwal/kegiatan-jadwal-page';
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import kegiatan from '@/routes/operasi/jadwal/kegiatan';

/** Jadwal Kegiatan Operasi: harian, mingguan, bulanan & non rutin (2026-11-25). */
export default function OperasiJadwalKegiatanPage(props: KegiatanJadwalProps) {
    return (
        <KegiatanJadwalPage
            {...props}
            config={{
                title: 'Jadwal Kegiatan Operasi',
                longTitle:
                    'Jadwal Kegiatan Harian, Mingguan, Bulanan & Non Rutin Operasi',
                kopTitle: 'JADWAL KEGIATAN OPERASI',
                subject: 'Operasi',
                placeholder: 'Contoh: Pengecekan level BBM daily tank',
                routes: {
                    index: kegiatan.index().url,
                    store: kegiatan.store().url,
                    pdf: kegiatan.pdf().url,
                    back: jadwal.index().url,
                },
            }}
        />
    );
}

OperasiJadwalKegiatanPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Jadwal Kegiatan Operasi', href: kegiatan.index() },
    ],
};
