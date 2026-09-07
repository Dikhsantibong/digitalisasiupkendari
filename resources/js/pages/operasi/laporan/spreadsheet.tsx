import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet, Printer } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { SpreadsheetEditor } from '@/components/spreadsheet-editor';
import { Button } from '@/components/ui/button';
import { downloadGridAsXlsx  } from '@/lib/spreadsheet';
import type {DocumentGrid} from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/operasi/laporan';

type Props = {
    report: { code: string; title: string };
    filters: { unit_id: number; engine_id: number; month: number; year: number };
    grid: DocumentGrid;
    print_url: string;
};

export default function LaporanSpreadsheet({
    report,
    filters,
    grid,
    print_url,
}: Props) {
    const [gridState, setGridState] = useState<DocumentGrid>(grid);

    const downloadXlsx = () =>
        downloadGridAsXlsx(
            gridState,
            `laporan-${filters.engine_id}-${filters.month}-${filters.year}.xlsx`,
        );

    return (
        <>
            <Head title={`Excel — ${report.title}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={report.title}
                    description="Edit sebagai spreadsheet lalu unduh Excel, atau buka versi cetak untuk PDF."
                    actions={
                        <div className="flex gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => router.get(laporan.index().url)}
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button variant="secondary" onClick={() => router.get(print_url)}>
                                <Printer className="size-4" />
                                Cetak / PDF
                            </Button>
                            <Button onClick={downloadXlsx}>
                                <FileSpreadsheet className="size-4" />
                                Unduh Excel
                            </Button>
                        </div>
                    }
                />

                <div className="flex items-center gap-3 rounded-md border border-border bg-white p-3">
                    <img
                        src="/logo/sidebar-logo.png"
                        alt="Logo"
                        className="h-12"
                    />
                    <div className="text-[13px] font-semibold text-slate-900">
                        {report.title}
                    </div>
                </div>

                <div className="rounded-md border border-border bg-card p-2">
                    <SpreadsheetEditor grid={gridState} onChange={setGridState} height={620} />
                </div>
            </div>
        </>
    );
}

LaporanSpreadsheet.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operasi', href: laporan.index() },
        { title: 'Excel', href: laporan.index() },
    ],
};
