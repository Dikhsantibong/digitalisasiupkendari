import { PersediaanPage } from '@/components/operasi/persediaan-page';
import type { PersediaanPageProps } from '@/components/operasi/persediaan-page';
import persediaan from '@/routes/operasi/pengusahaan/persediaan-pelumas';

export default function PersediaanPelumasIndex(props: PersediaanPageProps) {
    return (
        <PersediaanPage
            {...props}
            title="Persediaan Pelumas"
            description="Persediaan pelumas harian per jenis pelumas, dengan rekap Periode I–III seperti Pemakaian Pelumas"
            itemLabel="pelumas"
            pemakaianSource="Pemakaian Pelumas"
            routes={{
                index: persediaan.index,
                store: persediaan.store,
                pdf: persediaan.pdf,
            }}
        />
    );
}
