import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { ReportWorkflowState } from '@/components/document/report-workflow-panel';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/pdm/laporan';
import document from '@/routes/pdm/laporan/document';

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
    workflow: ReportWorkflowState;
};

export default function PdmLaporanDocument({
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
    workflow,
}: Props) {
    return (
        <>
            <Head title="Dokumen Laporan PdM & Maturity Level Pembangkit" />
            <DocumentEditor
                title="Dokumen Laporan PdM & Maturity Level Pembangkit"
                description="Laporan penuh terisi otomatis dari jadwal & input PdM. Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, lalu unduh PDF atau Excel."
                backUrl={laporan.index({ query: filters }).url}
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
                baseName={`Laporan-PdM-${filters.unit_id}-${filters.month}-${filters.year}`}
                xlsxHeaderLines={[
                    { text: 'JASA PENDUKUNG TEKNIS UP KENDARI 11 & 6 SITE - KIT', bold: true },
                    { text: 'LAPORAN PREDICTIVE DAN MATURITY LEVEL PEMBANGKIT', bold: true },
                ]}
                saveUrl={document.store().url}
                regenerateUrl={document.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }}
                workflow={workflow}
            />
        </>
    );
}

PdmLaporanDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan PdM & Maturity Level', href: laporan.index() },
        { title: 'Dokumen', href: laporan.index() },
    ],
};
