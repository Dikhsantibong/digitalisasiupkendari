import { usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import type { DocumentGrid } from '@/lib/spreadsheet';

type Props = {
    /** URL of the document page ("Buka Dokumen …"), e.g. operasi.laporan.document.edit. */
    editUrl: string;
    /** Inertia component of that page, e.g. "operasi/laporan/document". */
    component: string;
    /** The document's PDF route (the existing PDF output). */
    pdfUrl: string;
    /** File name without extension, the same as the document page's "Unduh Excel". */
    baseName: string;
};

type DocumentProps = {
    content?: string;
    content_styles?: string;
    grid?: DocumentGrid;
    format?: 'html' | 'grid';
    has_saved?: boolean;
};

/**
 * PDF & Excel of a laporan straight from the Laporan page, beside "Buka
 * Dokumen …" (user, 2026-11-25). Excel is the same book as the document
 * page's "Unduh Excel": the PDF document (saved or generated) laid out on the
 * sheets exactly as it prints — Sampul, then one sheet per lembar (see
 * report-layout-workbook.ts); a saved Excel version is written as edited.
 */
export function LaporanExportButtons({
    editUrl,
    component,
    pdfUrl,
    baseName,
}: Props) {
    const { version } = usePage();
    const [exporting, setExporting] = useState(false);

    const downloadPdf = () => {
        const separator = pdfUrl.includes('?') ? '&' : '?';
        window.open(`${pdfUrl}${separator}download=1`, '_blank');
    };

    const downloadExcel = async () => {
        setExporting(true);

        try {
            // The document body is a deferred prop of the document page: ask for just that.
            const response = await fetch(editUrl, {
                headers: {
                    Accept: 'text/html, application/xhtml+xml',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Inertia': 'true',
                    'X-Inertia-Version': String(version ?? ''),
                    'X-Inertia-Partial-Component': component,
                    'X-Inertia-Partial-Data':
                        'content,content_styles,grid,format,has_saved',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const props = ((await response.json()).props ??
                {}) as DocumentProps;

            if (props.has_saved && props.format === 'grid' && props.grid) {
                // A saved Excel version: the sheets as they were edited.
                const { downloadReportWorkbook } =
                    await import('@/lib/report-workbook');
                await downloadReportWorkbook(props.grid, `${baseName}.xlsx`);
            } else {
                // The PDF document, laid out on the sheets as it prints.
                const { downloadReportLayoutWorkbook } =
                    await import('@/lib/report-layout-workbook');
                await downloadReportLayoutWorkbook(
                    props.content ?? '',
                    props.content_styles ?? '',
                    `${baseName}.xlsx`,
                );
            }
        } catch {
            toast.error('Excel laporan gagal dibuat. Coba lagi.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Button
                type="button"
                onClick={downloadPdf}
                className="bg-rose-600 text-white hover:bg-rose-700"
            >
                <Download className="size-4" />
                PDF
            </Button>
            <Button
                type="button"
                onClick={downloadExcel}
                disabled={exporting}
                className="bg-emerald-600 text-white hover:bg-emerald-700"
            >
                {exporting ? (
                    <Loader2 className="size-4 animate-spin" />
                ) : (
                    <FileSpreadsheet className="size-4" />
                )}
                {exporting ? 'Menyiapkan…' : 'Excel'}
            </Button>
        </>
    );
}
