import { InstruksiKerjaEditorPage } from '@/components/instruksi-kerja/editor-page';
import type { InstruksiKerjaPageProps } from '@/components/instruksi-kerja/editor-page';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import instruksiKerja from '@/routes/pdm/input/instruksi-kerja';

/**
 * Input Instruksi Kerja (IK) PdM: the shared visual IK editor
 * (components/instruksi-kerja/editor-page.tsx) with the PdM routes —
 * the same input as the IK Pemeliharaan & Operasi.
 */
export default function PdmInstruksiKerjaPage(props: InstruksiKerjaPageProps) {
    return (
        <InstruksiKerjaEditorPage
            {...props}
            routes={{
                index: instruksiKerja.index().url,
                store: instruksiKerja.store().url,
                pdf: (query) => instruksiKerja.pdf({ query }).url,
                destroy: (id) => instruksiKerja.destroy(id).url,
                preview: instruksiKerja.preview().url,
            }}
            copy={{
                headTitle: 'Instruksi Kerja PdM',
                title: 'Instruksi Kerja (IK) PdM',
                description:
                    'Buat IK dari template (mis. pengukuran vibrasi, pengambilan sampel pelumas), ketik langsung pada dokumen, lalu cetak PDF sesuai format MKP.',
                judulPlaceholder: 'PENGUKURAN VIBRASI\nMESIN & GENERATOR PLTD',
                mesinPlaceholder: 'mis. Mirrlees #1',
            }}
        />
    );
}

PdmInstruksiKerjaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title: 'Instruksi Kerja', href: instruksiKerja.index() },
    ],
};
