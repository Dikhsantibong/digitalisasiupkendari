import { Download, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { downloadK3InputWorkbook } from '@/lib/k3-input-excel';
import type { K3InputExport } from '@/lib/k3-input-excel';
import exportRoutes from '@/routes/k3/input/export';

type Query = Record<string, string | number | null | undefined>;

/**
 * Excel & PDF (landscape) export buttons for a K3 input page. Both exports are
 * built on the server from the saved data (App\Services\K3\K3InputTables), so
 * unsaved edits on the page are not included.
 */
export function K3InputExportButtons({
    input,
    query,
    pdfUrl,
}: {
    /** Export key, e.g. "rambu" (see K3InputTables::INPUTS). */
    input: string;
    query: Query;
    /** Optional page-specific PDF (defaults to the generic landscape export). */
    pdfUrl?: string;
}) {
    const [exporting, setExporting] = useState(false);
    const cleanQuery = Object.fromEntries(
        Object.entries(query).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number>;

    const openPdf = () => {
        window.open(
            pdfUrl ?? exportRoutes.pdf(input, { query: cleanQuery }).url,
            '_blank',
        );
    };

    const downloadExcel = async () => {
        setExporting(true);

        try {
            const response = await fetch(
                exportRoutes.data(input, { query: cleanQuery }).url,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            await downloadK3InputWorkbook(
                (await response.json()) as K3InputExport,
            );
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Button
                variant="outline"
                onClick={downloadExcel}
                disabled={exporting}
                className="gap-1.5"
            >
                <FileSpreadsheet className="size-4 text-emerald-600" />
                {exporting ? 'Menyiapkan…' : 'Excel'}
            </Button>
            <Button variant="outline" onClick={openPdf} className="gap-1.5">
                <Download className="size-4 text-rose-600" />
                PDF
            </Button>
        </>
    );
}
