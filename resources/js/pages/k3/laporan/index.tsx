import { Head, router } from '@inertiajs/react';
import { Building2, FilePen } from 'lucide-react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/k3/laporan';
import document from '@/routes/k3/laporan/document';
import pengusahaan from '@/routes/k3/laporan/pengusahaan';
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
            <Head title="Laporan K3 Lingkungan Pembangkit" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan K3 Lingkungan Pembangkit"
                    description="Pilih unit & periode, lalu lihat & cetak laporan K3 lingkungan pembangkit, atau buka dokumen untuk diedit."
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
                            <h2 className="text-base font-semibold text-foreground">Laporan K3 Lingkungan Pembangkit</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Tersusun sesuai daftar isi (I–VII) dan terisi otomatis dari input K3 — poin yang belum ada datanya ditandai garis merah. PDF menggabungkan halaman Portrait (formulir) &amp; Landscape (tabel) dengan nomor halaman daftar isi otomatis.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={() => router.get(document.edit(query).url)}>
                                <FilePen className="size-4" />
                                Buka Dokumen (Lihat, Edit &amp; Cetak)
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4">
                        <div>
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-base font-semibold text-foreground">Laporan Pengusahaan Pembangkit</h2>
                            </div>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Laporan pengusahaan K3 &amp; KAM mencakup sampul resmi, evaluasi keselamatan kerja, checklist patroli, APAR, emergency facility, sertifikasi peralatan, dan lampiran foto (gabungan Portrait &amp; Landscape).
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                onClick={() => router.get(pengusahaan.edit(query).url)}
                            >
                                <Building2 className="size-4" />
                                Buka Dokumen (Lihat, Edit &amp; Cetak)
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

K3LaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan K3 Lingkungan Pembangkit', href: laporan.index() },
    ],
};
