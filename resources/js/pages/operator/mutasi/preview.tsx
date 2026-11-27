import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    ExternalLink,
    FileText,
    Maximize2,
    Minimize2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { Button } from '@/components/ui/button';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import mutasiRoutes from '@/routes/operator/mutasi';

type Props = {
    unit: { id: number; name: string };
    mutasi: {
        id: number;
        tanggal: string;
        shift: string;
        shift_label: string;
        hari_tanggal: string;
        status: string;
        regu_penyerah: string | null;
        regu_penerima: string | null;
    };
    html: string;
    pdf_url: string;
    download_url: string;
    back_url: string;
};

/** A4 portrait pixel dimensions at 96 DPI (210mm × 297mm). */
const A4_WIDTH = 794;
const A4_HEIGHT = 1123;

/**
 * Halaman pratinjau render PDF Lembar Mutasi Operator:
 * - Di HP (`compact`): merender dokumen A4 secara responsif (skala muat layar
 *   atau 100%) sehingga selalu tampil jelas di browser HP / PWA, lengkap dengan
 *   tombol Unduh PDF dan Buka PDF.
 * - Di desktop: menampilkan pratinjau PDF server langsung (`PdfPreviewFrame`).
 */
export default function OperatorMutasiPreviewPage({
    unit,
    mutasi,
    html,
    pdf_url,
    download_url,
    back_url,
}: Props) {
    const compact = useCompactLayout();
    const containerRef = useRef<HTMLDivElement>(null);
    const [fitWidth, setFitWidth] = useState(true);
    const [scale, setScale] = useState(1);

    useEffect(() => {
        if (!compact) {
            return;
        }

        const update = () => {
            const available = containerRef.current?.clientWidth ?? 0;
            if (available > 0) {
                setScale(Math.min(1, available / A4_WIDTH));
            }
        };

        update();

        const observer = new ResizeObserver(update);
        if (containerRef.current) {
            observer.observe(containerRef.current);
        }

        return () => observer.disconnect();
    }, [compact]);

    const previewHtml = html.includes('</head>')
        ? html.replace(
              '</head>',
              '<style>html,body{background:#fff;padding:18px 20px;box-sizing:border-box;width:794px;min-height:1123px;overflow-x:hidden;}</style></head>',
          )
        : html;

    return (
        <>
            <Head
                title={`Pratinjau PDF Mutasi ${mutasi.hari_tanggal} - ${unit.name}`}
            />

            <div className="flex flex-1 flex-col gap-3 p-4 md:p-6">
                <PageHeader
                    title="Pratinjau PDF Lembar Mutasi"
                    description={`${unit.name} · ${mutasi.hari_tanggal} · Shift ${mutasi.shift_label}`}
                    actions={
                        compact ? (
                            <div className="flex flex-col gap-2">
                                <div className="flex gap-2">
                                    <Button
                                        size="lg"
                                        asChild
                                        className="flex-1 gap-1.5"
                                    >
                                        <a href={download_url} download>
                                            <Download className="size-4" />
                                            Unduh PDF
                                        </a>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        asChild
                                        className="gap-1.5"
                                    >
                                        <a
                                            href={pdf_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink className="size-4" />
                                            Buka
                                        </a>
                                    </Button>
                                </div>
                                <div className="flex items-center justify-between gap-2 rounded-xl border border-border bg-card px-3 py-2 text-xs">
                                    <span className="flex min-w-0 items-center gap-1.5 truncate text-muted-foreground">
                                        <FileText className="size-3.5 shrink-0 text-rose-600" />
                                        <span className="truncate">
                                            {mutasi.hari_tanggal} · Shift{' '}
                                            {mutasi.shift_label}
                                        </span>
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setFitWidth((prev) => !prev)
                                        }
                                        className="h-7 shrink-0 gap-1 px-2 text-xs"
                                    >
                                        {fitWidth ? (
                                            <>
                                                <Maximize2 className="size-3.5" />
                                                100%
                                            </>
                                        ) : (
                                            <>
                                                <Minimize2 className="size-3.5" />
                                                Muat Layar
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(back_url)}
                                    className="gap-1.5"
                                >
                                    <ArrowLeft className="size-4" />
                                    Kembali
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="gap-1.5"
                                >
                                    <a
                                        href={pdf_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink className="size-4" />
                                        Tab Baru
                                    </a>
                                </Button>
                                <Button size="sm" asChild className="gap-1.5">
                                    <a href={download_url} download>
                                        <Download className="size-4" />
                                        Unduh PDF
                                    </a>
                                </Button>
                            </div>
                        )
                    }
                />

                {compact ? (
                    <div
                        ref={containerRef}
                        className="w-full overflow-x-auto rounded-xl border border-border bg-muted/40 shadow-xs"
                    >
                        {fitWidth ? (
                            <div
                                className="mx-auto overflow-hidden bg-white shadow-sm"
                                style={{
                                    width: `${Math.round(A4_WIDTH * scale)}px`,
                                    height: `${Math.round(A4_HEIGHT * scale)}px`,
                                }}
                            >
                                <iframe
                                    title={`Pratinjau PDF Lembar Mutasi ${mutasi.hari_tanggal}`}
                                    srcDoc={previewHtml}
                                    sandbox="allow-same-origin"
                                    className="origin-top-left border-0 bg-white"
                                    style={{
                                        width: `${A4_WIDTH}px`,
                                        height: `${A4_HEIGHT}px`,
                                        transform: `scale(${scale})`,
                                    }}
                                />
                            </div>
                        ) : (
                            <iframe
                                title={`Pratinjau PDF Lembar Mutasi ${mutasi.hari_tanggal}`}
                                srcDoc={previewHtml}
                                sandbox="allow-same-origin"
                                className="block border-0 bg-white"
                                style={{
                                    width: `${A4_WIDTH}px`,
                                    height: `${A4_HEIGHT}px`,
                                }}
                            />
                        )}
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                        <PdfPreviewFrame
                            title={`Pratinjau PDF Lembar Mutasi ${mutasi.hari_tanggal}`}
                            src={pdf_url}
                            className="h-[calc(100vh-210px)] min-h-[720px] w-full border-0 bg-white"
                        />
                    </div>
                )}

                {compact && (
                    <div className="pt-1">
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={() => router.get(back_url)}
                            className="w-full gap-1.5"
                        >
                            <ArrowLeft className="size-4" />
                            Kembali ke Lembar Mutasi
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

OperatorMutasiPreviewPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lembar Mutasi Operator', href: mutasiRoutes.index() },
        { title: 'Pratinjau PDF', href: '#' },
    ],
};
