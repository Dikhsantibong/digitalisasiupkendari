import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/k3/laporan';
import pengusahaan from '@/routes/k3/laporan/pengusahaan';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    document_number: string;
    content: string;
    content_styles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: DocumentEditorMode;
    has_saved: boolean;
    pdf_url: string;
    can_write: boolean;
};

export default function K3LaporanPengusahaan({
    filters,
    document_number,
    content,
    content_styles,
    letterhead,
    grid,
    format,
    has_saved,
    pdf_url,
    can_write,
}: Props) {
    return (
        <>
            <Head title="Dokumen Laporan Pengusahaan Pembangkit K3" />
            <DocumentEditor
                title="Laporan Pengusahaan Pembangkit (K3 & KAM)"
                description="Laporan kinerja K3 & KAM terisi otomatis dari seluruh data input. Edit sebagai teks atau spreadsheet, simpan, lalu unduh PDF multi-orientasi (Portrait & Landscape)."
                backUrl={laporan.index().url}
                backLabel="Kembali"
                numberLabel="No. Dokumen"
                documentNumber={document_number}
                content={content}
                contentStyles={content_styles}
                letterhead={letterhead}
                grid={grid}
                format={format}
                hasSaved={has_saved}
                pdfUrl={pdf_url}
                canEdit={can_write}
                baseName={`Laporan-Pengusahaan-K3-${filters.unit_id}-${filters.month}-${filters.year}`}
                xlsxHeaderLines={[
                    { text: 'PT PLN NUSANTARA POWER', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'LAPORAN KINERJA K3 & KEAMANAN' },
                ]}
                saveUrl={pengusahaan.store().url}
                regenerateUrl={pengusahaan.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }}
            />
        </>
    );
}

K3LaporanPengusahaan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan K3 Lingkungan Pembangkit', href: laporan.index() },
        { title: 'Laporan Pengusahaan Pembangkit', href: laporan.index() },
    ],
};
