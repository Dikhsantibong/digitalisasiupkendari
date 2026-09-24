import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    ExternalLink,
    FileEdit,
    Printer,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/berita-acara';

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name: string | null;
    };
    type: {
        value: string;
        label: string;
        title: string;
    };
    filters: {
        unit_id: number;
        month: number;
        year: number;
    };
    month_name: string;
    pdf_url: string;
    has_saved: boolean;
    can_create: boolean;
};

export default function BeritaAcaraPreviewPage({
    unit,
    type,
    filters,
    month_name,
    pdf_url,
    has_saved,
    can_create,
}: Props) {
    const [refreshKey, setRefreshKey] = useState(0);

    const handleRefresh = () => {
        setRefreshKey((k) => k + 1);
    };

    const handleDownload = () => {
        window.open(`${pdf_url}&download=1`, '_blank');
    };

    const handleOpenNewTab = () => {
        window.open(pdf_url, '_blank');
    };

    const handleEdit = () => {
        router.get(`/operasi/berita-acara/${type.value}`, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
        });
    };

    const handleBack = () => {
        router.get('/operasi/berita-acara', {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
        });
    };

    const iframeSrc = `${pdf_url}&k=${refreshKey}`;

    return (
        <>
            <Head title={`Pratinjau PDF - ${type.label}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Pratinjau PDF - ${type.label}`}
                    description={`Unit: ${unit.name} • Periode: ${month_name} ${filters.year} • ${type.title}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleBack}
                                className="h-8 gap-1.5 text-xs"
                            >
                                <ArrowLeft className="size-3.5" />
                                Kembali
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleRefresh}
                                className="h-8 gap-1.5 text-xs"
                                title="Segarkan tampilan dokumen"
                            >
                                <RefreshCw className="size-3.5" />
                                Segarkan
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleOpenNewTab}
                                className="h-8 gap-1.5 text-xs"
                                title="Buka di tab browser baru"
                            >
                                <ExternalLink className="size-3.5" />
                                Tab Baru
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleDownload}
                                className="h-8 gap-1.5 text-xs"
                            >
                                <Download className="size-3.5" />
                                Unduh PDF
                            </Button>
                            {can_create && (
                                <Button
                                    size="sm"
                                    onClick={handleEdit}
                                    className="h-8 gap-1.5 text-xs font-semibold"
                                >
                                    <FileEdit className="size-3.5" />
                                    {has_saved ? 'Edit Berita Acara' : 'Buat Berita Acara'}
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Status Bar */}
                <div className="flex items-center justify-between rounded-md border border-border bg-card px-4 py-2 text-xs shadow-xs">
                    <div className="flex items-center gap-2">
                        <Printer className="size-4 text-primary" />
                        <span className="text-muted-foreground">
                            Pratinjau dokumen PDF resmi format A4 standar PT PLN Nusantara Power.
                        </span>
                    </div>
                    <div>
                        <Badge
                            variant="outline"
                            className={
                                has_saved
                                    ? 'border-emerald-600/30 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                    : 'text-[11px] font-medium text-muted-foreground'
                            }
                        >
                            {has_saved ? 'Dokumen Tersimpan' : 'Draft / Default Master'}
                        </Badge>
                    </div>
                </div>

                {/* Main PDF Preview Container */}
                <Card className="flex-1 overflow-hidden border border-border p-0 shadow-xs">
                    <CardContent className="h-full p-0">
                        <PdfPreviewFrame
                            title={`Pratinjau PDF - ${type.label}`}
                            src={iframeSrc}
                            className="h-[calc(100vh-230px)] min-h-[720px] w-full border-0 bg-white"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BeritaAcaraPreviewPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Berita Acara', href: beritaAcara.index() },
        { title: 'Pratinjau PDF', href: '#' },
    ],
};
