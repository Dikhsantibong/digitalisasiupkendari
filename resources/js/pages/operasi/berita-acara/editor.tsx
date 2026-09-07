import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileSpreadsheet, FileText, Save } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { RichTextEditor } from '@/components/rich-text-editor';
import { SpreadsheetEditor } from '@/components/spreadsheet-editor';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { downloadGridAsXlsx } from '@/lib/spreadsheet';
import type { DocumentGrid, GridCell } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/berita-acara';

type Mode = 'html' | 'grid';

type Props = {
    type: { value: string; label: string };
    filters: { unit_id: number; month: number; year: number };
    document_number: string;
    content: string;
    content_styles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: Mode;
    has_saved: boolean;
    pdf_url: string;
    can_create: boolean;
};

/** Scoped styling for the letterhead banner shown above the spreadsheet editor. */
const LETTERHEAD_STYLES = `
.ba-letterhead { background: #fff; color: #000; }
.ba-letterhead img { height: 52px; }
.ba-letterhead .ba-org { text-align: center; font-weight: bold; line-height: 1.35; font-size: 12px; }
.ba-letterhead .ba-org small { font-weight: normal; }
.ba-letterhead .ba-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; font-size: 11px; }
.ba-letterhead .ba-meta td { border: 1px solid #000; padding: 3px 6px; }
.ba-letterhead .ba-hr { border: none; border-top: 1px solid #000; margin-top: 8px; }
`;

export default function BeritaAcaraEditor({
    type,
    filters,
    document_number,
    content,
    content_styles,
    letterhead,
    grid,
    format,
    has_saved,
    pdf_url,
    can_create,
}: Props) {
    const [mode, setMode] = useState<Mode>(format);
    const [html, setHtml] = useState(content);
    const [gridState, setGridState] = useState<DocumentGrid>(grid);
    const [saving, setSaving] = useState(false);
    const [savedFormat, setSavedFormat] = useState<Mode | null>(has_saved ? format : null);

    const baseName = `BA-${type.value}-${filters.unit_id}-${filters.month}-${filters.year}`;

    const save = () => {
        setSaving(true);
        router.post(
            beritaAcara.store().url,
            {
                unit_id: filters.unit_id,
                type: type.value,
                month: filters.month,
                year: filters.year,
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
        const separator = pdf_url.includes('?') ? '&' : '?';
        window.open(`${pdf_url}${separator}download=1`, '_blank');
    };

    const downloadXlsx = () => {
        // Prepend the organisation header text (a spreadsheet can't hold the
        // logo image) so the .xlsx carries the same header.
        const cols = gridState.cols;
        const header: GridCell[][] = [
            [{ t: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', b: true, a: 'c' }],
            [{ t: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', b: true, a: 'c' }],
            [{ t: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' }],
            [{ t: `No. Surat: ${document_number}` }],
        ];
        const offset = header.length;
        const shifted = (gridState.merges ?? []).map(
            ([r1, c1, r2, c2]): [number, number, number, number] => [
                r1 + offset,
                c1,
                r2 + offset,
                c2,
            ],
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
        <>
            <Head title={`Edit ${type.label}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Edit ${type.label}`}
                    description="Pilih cara edit: teks (seperti Word) atau spreadsheet (seperti Excel). Simpan, lalu unduh PDF atau Excel."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => router.get(beritaAcara.index().url)}
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
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
                            {can_create && (
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
                    <span className="text-muted-foreground">No. Surat:</span>
                    <span className="font-medium">{document_number}</span>
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
                            className="ba-letterhead mx-auto max-w-[1000px]"
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
                            extraContentStyle={content_styles}
                            onChange={setHtml}
                            disabled={!can_create}
                        />
                    ) : (
                        <SpreadsheetEditor grid={gridState} onChange={setGridState} />
                    )}
                </div>
            </div>
        </>
    );
}

BeritaAcaraEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Berita Acara', href: beritaAcara.index() },
        { title: 'Edit', href: beritaAcara.index() },
    ],
};
