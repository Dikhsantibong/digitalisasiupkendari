import { Head, router } from '@inertiajs/react';
import { FilePen } from 'lucide-react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/k3/laporan';
import document from '@/routes/k3/laporan/document';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

export default function K3LaporanIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            laporan.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const query = { query: { unit_id: filters.unit_id, month: filters.month, year: filters.year } };

    return (
        <>
            <Head title="Laporan K3 & Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan K3 & Keamanan"
                    description="Pilih unit & periode, lalu buka dokumen laporan penuh untuk diedit & dicetak."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
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

                <div className="grid gap-3 md:grid-cols-2">
                    <div className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">Dokumen Laporan K3 Penuh</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Gabungan semua form (Time Frame, kecelakaan, APAR, kesiapan darurat, patroli, sertifikat, inspeksi, lampiran) terisi otomatis — bisa diedit teks/Excel lalu cetak PDF.
                            </p>
                        </div>
                        <Button onClick={() => router.get(document.edit(query).url)}>
                            <FilePen className="size-4" />
                            Lihat &amp; Edit Dokumen
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

K3LaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan K3', href: laporan.index() },
    ],
};
