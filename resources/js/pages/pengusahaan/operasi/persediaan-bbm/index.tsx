import { PersediaanPage } from '@/components/operasi/persediaan-page';
import type { PersediaanPageProps } from '@/components/operasi/persediaan-page';
import persediaan from '@/routes/operasi/pengusahaan/persediaan-bbm';

export default function PersediaanBbmIndex(props: PersediaanPageProps) {
    return (
        <PersediaanPage
            {...props}
            title="Persediaan Bahan Bakar"
            description="Persediaan BBM harian per jenis BBM (liter), dengan rekap Periode I–IV"
            itemLabel="BBM"
            pemakaianSource="Pemakaian Bahan Bakar"
            routes={{
                index: persediaan.index,
                store: persediaan.store,
                pdf: persediaan.pdf,
            }}
        />
    );
}
