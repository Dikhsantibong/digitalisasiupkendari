import { Head, router } from '@inertiajs/react';
import { Building2, FilePen } from 'lucide-react';
import { LaporanExportButtons } from '@/components/laporan/laporan-export-buttons';
import { ResumeCard } from '@/components/laporan/resume-card';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import laporan from '@/routes/har/laporan';
import document from '@/routes/har/laporan/document';
import pengusahaan from '@/routes/har/laporan/pengusahaan';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

export default function HarLaporanIndex({ filters, options }: Props) {
    const { can } = usePermissions();
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            laporan.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const query = {
        query: {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
        },
    };

    return (
        <>
            <Head title="Laporan Pemeliharaan Pembangkit" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Pemeliharaan Pembangkit"
                    description="Pilih unit & periode, lalu buka dokumen laporan pemeliharaan pembangkit untuk dilihat, diedit & dicetak."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        {can('har.laporan.view') && (
                            <div className="flex flex-col justify-between gap-4 rounded-md border border-border border-l-4 border-l-primary bg-card p-5 shadow-xs">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        Laporan Project Pemeliharaan
                                    </h2>
                                    <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                                        Lembar pengesahan, resume statistik, dan
                                        rekap lengkap seluruh jadwal
                                        pemeliharaan (Harian, P0-P5, Piket On
                                        Call, Patrol Cek, Meeting, Pembuatan
                                        IK).
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        onClick={() =>
                                            router.get(document.edit(query).url)
                                        }
                                        className="max-w-full max-sm:h-auto max-sm:py-2 max-sm:text-left max-sm:whitespace-normal"
                                    >
                                        <FilePen className="size-4" />
                                        Buka Dokumen Pemeliharaan (Lihat, Edit
                                        &amp; Cetak)
                                    </Button>
                                    <LaporanExportButtons
                                        editUrl={document.edit(query).url}
                                        component="har/laporan/document"
                                        pdfUrl={document.pdf(query).url}
                                        baseName={`Laporan-HAR-${filters.unit_id}-${filters.month}-${filters.year}`}
                                    />
                                </div>
                            </div>
                        )}

                        {can('har.pengusahaan.view') && (
                            <div className="flex flex-col justify-between gap-4 rounded-md border border-border border-l-4 border-l-primary bg-card p-5 shadow-xs">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        Laporan Pemeliharaan
                                    </h2>
                                    <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                                        Executive summary, istilah &amp;
                                        definisi, service request, maintenance
                                        summary, rekapitulasi WO task, detail WO
                                        per kategori, dan lampiran.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        onClick={() =>
                                            router.get(
                                                pengusahaan.edit(query).url,
                                            )
                                        }
                                        className="max-w-full max-sm:h-auto max-sm:py-2 max-sm:text-left max-sm:whitespace-normal"
                                    >
                                        <Building2 className="size-4" />
                                        Buka Dokumen Laporan Pemeliharaan (Lihat, Edit
                                        &amp; Cetak)
                                    </Button>
                                    <LaporanExportButtons
                                        editUrl={pengusahaan.edit(query).url}
                                        component="har/laporan/pengusahaan"
                                        pdfUrl={pengusahaan.pdf(query).url}
                                        baseName={`Laporan-Pengusahaan-${filters.unit_id}-${filters.month}-${filters.year}`}
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    <ResumeCard module="pemeliharaan" filters={filters} />
                </div>
            </div>
        </>
    );
}

HarLaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Pemeliharaan Pembangkit', href: laporan.index() },
    ],
};
