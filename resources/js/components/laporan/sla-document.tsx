import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';

/** The SLA document of the page (SlaController::documentProps). */
export type SlaDocumentState = {
    format: DocumentEditorMode;
    has_saved: boolean;
    pdf_url: string;
    can_write: boolean;
    save_url: string;
    regenerate_url: string;
    save_extra: Record<string, string | number>;
    title: string;
    unit_label: string;
};

/** The document body, deferred on the server (built from the inputs or the saved document). */
export type SlaDocumentBody = {
    document_number?: string;
    content?: string;
    content_styles?: string;
    letterhead?: string;
    grid?: DocumentGrid;
};

/**
 * The SLA (one divisi, or the Laporan SLA gabungan) as an editable document:
 * Teks (visual editor) / Excel / Pratinjau PDF, Simpan & Muat Ulang dari Data.
 * The saved document is what the PDF prints; the cover of the gabungan stays
 * automatic.
 */
export function SlaDocument({
    document,
    body,
    period,
    backUrl,
}: {
    document: SlaDocumentState;
    body: SlaDocumentBody;
    period: string;
    backUrl: string;
}) {
    return (
        <DocumentEditor
            title={`${document.title} — ${document.unit_label}`}
            description={`Periode ${period}. Nilai SLA terisi otomatis dari inputan aplikasi dan dapat diedit langsung (Teks seperti Word, atau Excel); simpan agar PDF ikut memakai nilai yang diedit. "Muat Ulang dari Data" mengembalikan ke hasil hitung otomatis.`}
            backUrl={backUrl}
            backLabel="Kembali"
            numberLabel="No. Dokumen"
            documentNumber={body.document_number}
            content={body.content}
            contentStyles={body.content_styles}
            letterhead={body.letterhead}
            grid={body.grid}
            format={document.format}
            hasSaved={document.has_saved}
            pdfUrl={document.pdf_url}
            canEdit={document.can_write}
            baseName={`${document.title}-${document.unit_label}-${period}`.replace(
                /\s+/g,
                '-',
            )}
            xlsxHeaderLines={[
                { text: 'SLA (SERVICE LEVEL AGREEMENT)', bold: true },
                {
                    text: `${document.title.toUpperCase()} — ${document.unit_label.toUpperCase()}`,
                    bold: true,
                },
                { text: `PERIODE ${period.toUpperCase()}` },
            ]}
            saveUrl={document.save_url}
            regenerateUrl={document.regenerate_url}
            saveExtra={document.save_extra}
        />
    );
}
