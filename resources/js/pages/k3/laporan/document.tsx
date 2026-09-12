import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/k3/laporan';
import document from '@/routes/k3/laporan/document';

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

export default function K3LaporanDocument({
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
            <Head title="Dokumen Laporan K3" />
            <DocumentEditor
                title="Dokumen Laporan K3 & Keamanan"
                description="Laporan penuh terisi otomatis. Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, lalu unduh PDF atau Excel."
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
                baseName={`Laporan-K3-${filters.unit_id}-${filters.month}-${filters.year}`}
                xlsxHeaderLines={[
                    { text: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' },
                ]}
                saveUrl={document.store().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }}
            />
        </>
    );
}

K3LaporanDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan K3', href: laporan.index() },
        { title: 'Dokumen', href: laporan.index() },
    ],
};
