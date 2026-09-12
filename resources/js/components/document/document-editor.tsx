import { router } from '@inertiajs/react';
import { ArrowLeft, Download, FileSpreadsheet, FileText, Save } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { RichTextEditor } from '@/components/rich-text-editor';
import { SpreadsheetEditor } from '@/components/spreadsheet-editor';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { downloadGridAsXlsx } from '@/lib/spreadsheet';
import type { DocumentGrid, GridCell } from '@/lib/spreadsheet';

export type DocumentEditorMode = 'html' | 'grid';

export type DocumentEditorProps = {
    title: string;
    description: string;
    backUrl: string;
    backLabel: string;
    numberLabel: string;
    documentNumber: string;
    content: string;
    contentStyles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: DocumentEditorMode;
    hasSaved: boolean;
    pdfUrl: string;
    canEdit: boolean;
    baseName: string;
    /** Organisation header lines prepended to the .xlsx (a sheet can't hold the logo). */
    xlsxHeaderLines: { text: string; bold?: boolean }[];
    saveUrl: string;
    saveExtra: Record<string, string | number>;
};

/** Scoped styling for the letterhead banner shown above the spreadsheet editor. */
const LETTERHEAD_STYLES = `
.doc-letterhead { background: #fff; color: #000; }
.doc-letterhead img { height: 52px; }
.doc-letterhead .har-org, .doc-letterhead .ba-org { text-align: center; font-weight: bold; line-height: 1.35; font-size: 12px; }
.doc-letterhead .har-org small, .doc-letterhead .ba-org small { font-weight: normal; }
.doc-letterhead .har-meta, .doc-letterhead .ba-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; font-size: 11px; }
.doc-letterhead .har-meta td, .doc-letterhead .ba-meta td { border: 1px solid #000; padding: 3px 6px; }
.doc-letterhead .har-title { text-align: center; font-weight: bold; font-size: 12px; margin-top: 8px; }
.doc-letterhead .har-hr, .doc-letterhead .ba-hr { border: none; border-top: 1px solid #000; margin-top: 8px; }
`;

/**
 * The shared dual-mode document editor: edit as rich text (Word-like, exported
 * to PDF) or as a spreadsheet (Excel-like, exported to .xlsx and PDF). Owns the
 * mode toggle, save state, and export actions; each module supplies its content,
 * letterhead, routes, and labels.
 */
export function DocumentEditor({
    title,
    description,
    backUrl,
    backLabel,
    numberLabel,
    documentNumber,
    content,
    contentStyles,
    letterhead,
    grid,
    format,
    hasSaved,
    pdfUrl,
    canEdit,
    baseName,
    xlsxHeaderLines,
    saveUrl,
    saveExtra,
}: DocumentEditorProps) {
    const [mode, setMode] = useState<DocumentEditorMode>(format);
    const [html, setHtml] = useState(content);
    const [gridState, setGridState] = useState<DocumentGrid>(grid);
    const [saving, setSaving] = useState(false);
    const [savedFormat, setSavedFormat] = useState<DocumentEditorMode | null>(hasSaved ? format : null);

    const save = () => {
        setSaving(true);
        router.post(
            saveUrl,
            {
                ...saveExtra,
                format: mode,
                content_html: mode === 'html' ? html : undefined,
                content_grid: mode === 'grid' ? gridState : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => setSavedFormat(mode),
                onFinish: () => setSaving(false),
            },
        );
    };

    const downloadPdf = () => {
        const separator = pdfUrl.includes('?') ? '&' : '?';
        window.open(`${pdfUrl}${separator}download=1`, '_blank');
    };

    const downloadXlsx = () => {
        const cols = gridState.cols;
        const header: GridCell[][] = [
            ...xlsxHeaderLines.map((line): GridCell[] => [{ t: line.text, b: line.bold, a: 'c' }]),
            [{ t: `${numberLabel}: ${documentNumber}` }],
        ];
        const offset = header.length;
        const shifted = (gridState.merges ?? []).map(
            ([r1, c1, r2, c2]): [number, number, number, number] => [r1 + offset, c1, r2 + offset, c2],
        );
        const headerMerges = header.map(
            (_, i): [number, number, number, number] => [i, 0, i, cols - 1],
        );
        const exportGrid: DocumentGrid = {
            ...gridState,
            rows: [...header, ...gridState.rows],
            merges: [...headerMerges, ...shifted],
        };
        downloadGridAsXlsx(exportGrid, `${baseName}.xlsx`);
    };

    const pdfMismatch = savedFormat !== null && savedFormat !== mode;

    return (
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
            <PageHeader
                title={title}
                description={description}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => router.get(backUrl)}>
                            <ArrowLeft className="size-4" />
                            {backLabel}
                        </Button>
                        {mode === 'grid' && (
                            <Button variant="secondary" onClick={downloadXlsx}>
                                <FileSpreadsheet className="size-4" />
                                Unduh Excel
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            onClick={downloadPdf}
                            disabled={savedFormat === null}
                            title={savedFormat === null ? 'Simpan dulu' : undefined}
                        >
                            <Download className="size-4" />
                            Unduh PDF
                        </Button>
                        {canEdit && (
                            <Button onClick={save} disabled={saving}>
                                <Save className="size-4" />
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-wrap items-center gap-3 rounded-md border border-border bg-card p-3 text-[13px]">
                <div className="flex overflow-hidden rounded-md border border-border">
                    <button
                        type="button"
                        onClick={() => setMode('html')}
                        className={`flex items-center gap-1 px-3 py-1.5 ${mode === 'html' ? 'bg-primary text-primary-foreground' : 'bg-secondary'}`}
                    >
                        <FileText className="size-4" /> Teks (PDF)
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('grid')}
                        className={`flex items-center gap-1 px-3 py-1.5 ${mode === 'grid' ? 'bg-primary text-primary-foreground' : 'bg-secondary'}`}
                    >
                        <FileSpreadsheet className="size-4" /> Excel
                    </button>
                </div>
                <span className="text-muted-foreground">{numberLabel}:</span>
                <span className="font-medium">{documentNumber}</span>
                {savedFormat !== null ? (
                    <StatusBadge tone="success">
                        Tersimpan ({savedFormat === 'grid' ? 'Excel' : 'Teks'})
                    </StatusBadge>
                ) : (
                    <StatusBadge tone="neutral">Belum disimpan</StatusBadge>
                )}
                {pdfMismatch && (
                    <span className="text-amber-600">
                        PDF mengikuti versi tersimpan ({savedFormat === 'grid' ? 'Excel' : 'Teks'}). Simpan mode ini agar PDF ikut berubah.
                    </span>
                )}
            </div>

            {mode === 'grid' && (
                <div className="overflow-x-auto rounded-md border border-border bg-white p-4">
                    <style>{LETTERHEAD_STYLES}</style>
                    <div
                        className="doc-letterhead mx-auto max-w-[1000px]"
                        dangerouslySetInnerHTML={{ __html: letterhead }}
                    />
                    <p className="mx-auto max-w-[1000px] pt-1 text-[12px] text-muted-foreground">
                        Kop surat (logo + header) di atas ikut tercetak di PDF & Excel. Isi tabel diedit di bawah.
                    </p>
                </div>
            )}

            <div className="rounded-md border border-border bg-card p-2">
                {mode === 'html' ? (
                    <RichTextEditor
                        value={html}
                        extraContentStyle={contentStyles}
                        onChange={setHtml}
                        disabled={!canEdit}
                        autoGrow
                    />
                ) : (
                    <SpreadsheetEditor grid={gridState} onChange={setGridState} />
                )}
            </div>
        </div>
    );
}
