import { InstruksiKerjaEditorPage } from '@/components/instruksi-kerja/editor-page';
import type { InstruksiKerjaPageProps } from '@/components/instruksi-kerja/editor-page';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import instruksiKerja from '@/routes/har/input/instruksi-kerja';

/**
 * Input Instruksi Kerja (IK) Pemeliharaan (Akses 1): the shared IK input
 * (components/instruksi-kerja/editor-page.tsx) with the Pemeliharaan routes.
 */
export default function InstruksiKerjaPage(props: InstruksiKerjaPageProps) {
    return (
        <InstruksiKerjaEditorPage
            {...props}
            routes={{
                index: instruksiKerja.index().url,
                store: instruksiKerja.store().url,
                pdf: (query) => instruksiKerja.pdf({ query }).url,
                destroy: (id) => instruksiKerja.destroy(id).url,
            }}
            copy={{
                headTitle: 'Instruksi Kerja Pemeliharaan',
                title: 'Instruksi Kerja (IK) Pemeliharaan',
                description:
                    'Buat IK dari template (mis. PM 1500 jam Cummins KTA 50), sesuaikan langkah-langkahnya, lalu cetak PDF sesuai format MKP.',
                judulPlaceholder:
                    'PEKERJAAN PREVENTIVE MAINTENANCE PM 1500 JAM\nMESIN CUMMINS KTA 50',
                mesinPlaceholder: 'mis. Cummins KTA 50',
            }}
        />
    );
}

InstruksiKerjaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Instruksi Kerja', href: instruksiKerja.index() },
    ],
};
