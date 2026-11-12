import { Link } from '@inertiajs/react';
import { ChartNoAxesCombined, Download, FileSpreadsheet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import resume from '@/routes/laporan/resume';

type Props = {
    /** ModuleResume::MODULES key: operasi | pemeliharaan | k3 | logistik | pdm */
    module: string;
    filters: { unit_id: number; month: number; year: number };
};

/**
 * The "Resume Bulanan" card of a module's Laporan page: grafik, akumulasi &
 * arah data of the unit and month, shown on its own page with the PDF and
 * Excel downloads (both landscape).
 */
export function ResumeCard({ module, filters }: Props) {
    const query = { query: filters };

    return (
        <div className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4">
            <div>
                <h2 className="text-base font-semibold text-foreground">
                    Resume Bulanan (Grafik &amp; Akumulasi)
                </h2>
                <p className="mt-1 text-[13px] text-muted-foreground">
                    Ringkasan satu halaman per modul: indikator utama dengan
                    arah naik/turun dibanding bulan lalu, ringkasan arah data,
                    grafik lengkap (batang, garis, donat, gauge, akumulasi,
                    dll.) dan tabel rinciannya — siap diunduh PDF atau Excel
                    (landscape).
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button asChild>
                    <Link href={resume.show(module, query).url}>
                        <ChartNoAxesCombined className="size-4" />
                        Buka Resume
                    </Link>
                </Button>
                <Button variant="outline" asChild>
                    <a
                        href={
                            resume.pdf(module, {
                                query: { ...filters, download: 1 },
                            }).url
                        }
                    >
                        <Download className="size-4" />
                        PDF
                    </a>
                </Button>
                <Button variant="outline" asChild>
                    {/* Opens the resume page, which downloads its Excel (charts are drawn in the browser). */}
                    <Link
                        href={
                            resume.show(module, {
                                query: { ...filters, export: 'excel' },
                            }).url
                        }
                    >
                        <FileSpreadsheet className="size-4" />
                        Excel
                    </Link>
                </Button>
            </div>
        </div>
    );
}
