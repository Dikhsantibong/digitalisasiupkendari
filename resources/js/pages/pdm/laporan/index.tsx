import { Head, router } from '@inertiajs/react';
import { Building2, FilePen } from 'lucide-react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/pdm/laporan';
import document from '@/routes/pdm/laporan/document';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

export default function PdmLaporanIndex({ filters, options }: Props) {
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
            <Head title="Laporan PdM & Maturity Level" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan PdM & Maturity Level"
                    description="Pilih unit & periode, lalu lihat & cetak laporan PdM & maturity level pembangkit, atau buka dokumen untuk diedit."
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
                            <h2 className="text-base font-semibold text-foreground">Laporan PdM &amp; Maturity Level Pembangkit</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Tersusun Sampul, Daftar Isi, Lembar Pengesahan, lalu seluruh tabel jadwal &amp; input PdM — terisi otomatis dari data tersimpan (atau isian bawaan halaman bila belum disimpan). PDF menggabungkan halaman Portrait &amp; Landscape dengan nomor halaman daftar isi otomatis.
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
                            <h2 className="text-base font-semibold text-foreground">Laporan Pengusahaan Pembangkit</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Laporan pengusahaan dan kinerja unit pembangkit terpadu (ketersediaan daya, efisiensi, pemeliharaan, serta evaluasi operasional).
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button disabled title="Laporan pengusahaan PdM sedang disiapkan">
                                <Building2 className="size-4" />
                                Buka Dokumen (Segera Hadir)
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

PdmLaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan PdM & Maturity Level', href: laporan.index() },
    ],
};
