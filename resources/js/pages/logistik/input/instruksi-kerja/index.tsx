import { InstruksiKerjaEditorPage } from '@/components/instruksi-kerja/editor-page';
import type { InstruksiKerjaPageProps } from '@/components/instruksi-kerja/editor-page';
import { dashboard } from '@/routes';
import logistikInput from '@/routes/logistik/input';
import instruksiKerja from '@/routes/logistik/input/instruksi-kerja';

/**
 * Input Instruksi Kerja (IK) Logistik & Gudang: the shared visual IK editor
 * (components/instruksi-kerja/editor-page.tsx) with the Logistik & Gudang routes —
 * the same input as the IK Pemeliharaan & Operasi.
 */
export default function LogistikInstruksiKerjaPage(
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
                preview: instruksiKerja.preview().url,
            }}
            copy={{
                headTitle: 'Instruksi Kerja Logistik & Gudang',
                title: 'Instruksi Kerja (IK) Logistik & Gudang',
                description:
                    'Buat IK dari template (mis. penerimaan material, stok opname gudang), ketik langsung pada dokumen, lalu cetak PDF sesuai format MKP.',
                judulPlaceholder: 'PENERIMAAN MATERIAL\nGUDANG PEMBANGKIT',
                mesinPlaceholder: 'mis. Gudang Material',
            }}
        />
    );
}

LogistikInstruksiKerjaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Logistik & Gudang', href: logistikInput.index() },
        { title: 'Instruksi Kerja', href: instruksiKerja.index() },
    ],
};
