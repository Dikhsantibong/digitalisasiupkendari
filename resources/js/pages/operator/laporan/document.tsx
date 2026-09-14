import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/operator/laporan';
import logsheetDoc from '@/routes/operator/laporan/logsheet';

type Props = {
    filters: { unit_id: number; engine_id: number; log_date: string };
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

export default function OperatorLogsheetDocument({
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
            <Head title="Dokumen Logsheet Operator" />
            <DocumentEditor
                title="Dokumen Logsheet Operator"
                description="Logsheet harian terisi otomatis. Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, lalu unduh PDF atau Excel."
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
                baseName={`Logsheet-${filters.unit_id}-${filters.engine_id}-${filters.log_date}`}
                xlsxHeaderLines={[
                    { text: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' },
                ]}
                saveUrl={logsheetDoc.store().url}
                regenerateUrl={logsheetDoc.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, engine_id: filters.engine_id, log_date: filters.log_date }}
            />
        </>
    );
}

OperatorLogsheetDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Logsheet', href: laporan.index() },
        { title: 'Dokumen', href: laporan.index() },
    ],
};
