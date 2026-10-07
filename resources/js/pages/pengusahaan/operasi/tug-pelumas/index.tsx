import { TugPage } from '@/components/operasi/tug-page';
import type { TugPageProps } from '@/components/operasi/tug-page';
import pemakaianPelumas from '@/routes/operasi/pengusahaan/pemakaian-pelumas';
import tugPelumas from '@/routes/operasi/pengusahaan/tug-pelumas';

export default function TugPelumasIndex(props: TugPageProps) {
    return (
        <TugPage
            {...props}
            config={{
                title: 'TUG 9 Pelumas',
                description:
                    'Rekap Bon Pemakaian Energi Primer (Pelumas) per mesin',
                source: {
                    title: 'Pemakaian Pelumas',
                    index: pemakaianPelumas.index,
                },
                routes: {
                    index: tugPelumas.index,
                    store: tugPelumas.store,
                    pdf: tugPelumas.pdf,
                },
                emptyColumns:
                    'Mesin ini belum memakai jenis pelumas apa pun. Atur jenis pelumas unit di Data Master Operasi → Jenis Pelumas, atau pelumas mesin di Master Mesin.',
            }}
        />
    );
}
