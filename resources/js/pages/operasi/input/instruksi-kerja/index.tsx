import { InstruksiKerjaEditorPage } from '@/components/instruksi-kerja/editor-page';
import type { InstruksiKerjaPageProps } from '@/components/instruksi-kerja/editor-page';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import instruksiKerja from '@/routes/operasi/input/instruksi-kerja';

/**
 * Input Instruksi Kerja (IK) Operasi: the shared IK input
 * (components/instruksi-kerja/editor-page.tsx) with the Operasi routes —
 * the same input as the IK Pemeliharaan.
 */
export default function OperasiInstruksiKerjaPage(
    props: InstruksiKerjaPageProps,
) {
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
                headTitle: 'Instruksi Kerja Operasi',
                title: 'Instruksi Kerja (IK) Operasi',
                description:
                    'Buat IK dari template (mis. start / stop mesin PLTD), sesuaikan langkah-langkahnya, lalu cetak PDF sesuai format MKP.',
                judulPlaceholder:
                    'PENGOPERASIAN START MESIN\nPEMBANGKIT LISTRIK TENAGA DIESEL (PLTD)',
                mesinPlaceholder: 'mis. Mirrlees #1',
            }}
        />
    );
}

OperasiInstruksiKerjaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Instruksi Kerja', href: instruksiKerja.index() },
    ],
};
