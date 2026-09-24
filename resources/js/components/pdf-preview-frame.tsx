import { FileText, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';

/**
 * Iframe pratinjau PDF dengan animasi pemuatan: dokumen dibuat di server
 * (dompdf) sehingga butuh beberapa detik — overlay tampil sampai iframe
 * selesai memuat, dan muncul lagi setiap kali `src` berubah (segarkan / simpan).
 */
export function PdfPreviewFrame({
    src,
    title,
    className = 'h-[750px] w-full rounded-md border border-border bg-white',
    style,
}: {
    src: string;
    title: string;
    className?: string;
    style?: CSSProperties;
}) {
    const [loadedSrc, setLoadedSrc] = useState<string | null>(null);
    const [elapsed, setElapsed] = useState<number>(0);
    const loading = loadedSrc !== src;

    useEffect(() => {
        if (!loading) {
            return;
        }

        const startedAt = Date.now();
        const timer = window.setInterval(() => setElapsed(Math.floor((Date.now() - startedAt) / 1000)), 1000);

        return () => {
            window.clearInterval(timer);
            setElapsed(0);
        };
    }, [loading, src]);

    return (
        <div className="relative w-full" aria-busy={loading}>
            <iframe
                key={src}
                title={title}
                src={src}
                className={className}
                style={style}
                onLoad={() => setLoadedSrc(src)}
            />

            {loading && (
                <div
                    role="status"
                    aria-live="polite"
                    className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-5 overflow-hidden rounded-md border border-border bg-muted/60 backdrop-blur-[2px]"
                >
                    <div className="absolute inset-x-0 top-0 h-1 overflow-hidden bg-primary/15">
                        <div className="h-full w-1/4 animate-pdf-progress rounded-full bg-primary" />
                    </div>

                    <div className="w-[min(300px,80%)] animate-pulse rounded-md border border-border bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <FileText className="size-5" />
                            </div>
                            <div className="flex-1 space-y-2">
                                <div className="h-2.5 w-3/4 rounded bg-muted-foreground/20" />
                                <div className="h-2 w-1/2 rounded bg-muted-foreground/15" />
                            </div>
                        </div>
                        <div className="space-y-2">
                            {[100, 92, 96, 70, 88, 60].map((width, index) => (
                                <div key={index} className="h-2 rounded bg-muted-foreground/15" style={{ width: `${width}%` }} />
                            ))}
                        </div>
                        <div className="mt-4 grid grid-cols-4 gap-1.5">
                            {Array.from({ length: 8 }, (_, index) => (
                                <div key={index} className="h-3 rounded-sm bg-muted-foreground/10" />
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-col items-center gap-1 px-4 text-center">
                        <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                            <Loader2 className="size-4 animate-spin text-primary" />
                            Menyiapkan pratinjau PDF…
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {elapsed < 4
                                ? 'Dokumen sedang dibuat di server, mohon tunggu sebentar.'
                                : elapsed < 12
                                  ? `Menyusun halaman dokumen… (${elapsed} detik)`
                                  : `Dokumen cukup besar, hampir selesai… (${elapsed} detik)`}
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
