import { Head, router } from '@inertiajs/react';
import { Building2, FilePenLine } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import laporan from '@/routes/operasi/laporan';
import document from '@/routes/operasi/laporan/document';
import pengusahaan from '@/routes/operasi/laporan/pengusahaan';
import type { IdName } from '@/types';

type ReportDef = {
    code: string;
    title: string;
    description: string;
    requires_engine: boolean;
};

type Filters = {
    unit_id: number;
    month: number;
    year: number;
};

type Props = {
    filters: Filters;
    reports: ReportDef[];
    options: { units: IdName[]; machines?: IdName[]; years: number[] };
};

export default function LaporanIndex({ filters, reports, options }: Props) {
    const { can } = usePermissions();
    const visit = (patch: Partial<Filters>) => {
        router.get(
            laporan.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reportQuery = () => ({
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    });

    const openDocument = (report: ReportDef) => {
        router.get(document.edit(report.code, { query: reportQuery() }).url);
    };

    return (
        <>
            <Head title="Laporan Operasi Pembangkit" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Operasi Pembangkit"
                    description="Pilih unit dan periode, lalu satu klik untuk melihat & mencetak."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({ unit_id: Number(value) })
                        }
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                {reports.length === 0 ? (
                    <EmptyState title="Belum ada laporan" description="Belum ada laporan terdaftar." />
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {can('operasi.laporan.view') && reports.map((report) => (
                            <div
                                key={report.code}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4"
                            >
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {report.title}
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground">
                                        {report.description}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button onClick={() => openDocument(report)} className="max-w-full max-sm:h-auto max-sm:py-2 max-sm:text-left max-sm:whitespace-normal">
                                        <FilePenLine className="size-4" />
                                        Buka Dokumen Operasi (Lihat, Edit &amp; Cetak)
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {/* Laporan Pengusahaan Pembangkit (Akses 2 — data dari input Pengusahaan Operasi) */}
                        {can('operasi.pengusahaan.view') && (
                            <div className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        Laporan Pengusahaan Pembangkit
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground">
                                        Ringkasan kinerja, produksi &amp; pemakaian sendiri, BBM &amp; pelumas, feeder, pasokan cadangan, star-stop, resource pembangkit dan status berita acara — terisi otomatis dari input Pengusahaan Operasi (gabungan Portrait &amp; Landscape).
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button onClick={() => router.get(pengusahaan.edit({ query: reportQuery() }).url)} className="max-w-full max-sm:h-auto max-sm:py-2 max-sm:text-left max-sm:whitespace-normal">
                                        <Building2 className="size-4" />
                                        Buka Dokumen Pengusahaan (Lihat, Edit &amp; Cetak)
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

LaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operasi Pembangkit', href: laporan.index() },
    ],
};
