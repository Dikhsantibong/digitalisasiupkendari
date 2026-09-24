import { Head, router } from '@inertiajs/react';
import { Building2, CheckCircle2, CircleDashed, FilePen } from 'lucide-react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/pdm/laporan';
import document from '@/routes/pdm/laporan/document';
import type { IdName } from '@/types';

type ReportContent = {
    group: 'jadwal' | 'input';
    key: string;
    title: string;
    orientation: 'portrait' | 'landscape';
    saved: boolean;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    contents: ReportContent[];
};

const GROUP_LABELS: Record<ReportContent['group'], string> = {
    jadwal: 'Jadwal PdM',
    input: 'Input PdM',
};

export default function PdmLaporanIndex({ filters, options, contents }: Props) {
    const savedCount = contents.filter((item) => item.saved).length;
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

                <section className="rounded-md border border-border bg-card" aria-labelledby="isi-laporan">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-4">
                        <div>
                            <h2 id="isi-laporan" className="text-base font-semibold text-foreground">
                                Isi Laporan PdM — {OPERASI_MONTHS[filters.month - 1]} {filters.year}
                            </h2>
                            <p className="mt-0.5 text-[13px] text-muted-foreground">
                                Urutan tabel di dokumen laporan. Tabel yang belum disimpan tetap tampil dengan isian bawaan halamannya.
                            </p>
                        </div>
                        <StatusBadge tone={savedCount === contents.length ? 'success' : 'info'}>
                            {savedCount} dari {contents.length} tabel tersimpan
                        </StatusBadge>
                    </div>

                    <div className="grid gap-4 p-4 md:grid-cols-2">
                        {(Object.keys(GROUP_LABELS) as ReportContent['group'][]).map((group) => (
                            <div key={group}>
                                <h3 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">{GROUP_LABELS[group]}</h3>
                                <ol className="divide-y divide-border rounded-md border border-border">
                                    {contents
                                        .filter((item) => item.group === group)
                                        .map((item) => (
                                            <li key={item.key} className="flex items-center justify-between gap-3 px-3 py-2 text-[13px]">
                                                <span className="flex min-w-0 items-center gap-2">
                                                    {item.saved ? (
                                                        <CheckCircle2 className="size-4 shrink-0 text-emerald-600" aria-hidden />
                                                    ) : (
                                                        <CircleDashed className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                                                    )}
                                                    <span className="truncate text-foreground" title={item.title}>{item.title}</span>
                                                </span>
                                                <span className="flex shrink-0 items-center gap-2">
                                                    <span className="text-[11px] text-muted-foreground">{item.orientation === 'portrait' ? 'Portrait' : 'Landscape'}</span>
                                                    <StatusBadge tone={item.saved ? 'success' : 'neutral'}>{item.saved ? 'Tersimpan' : 'Isian bawaan'}</StatusBadge>
                                                </span>
                                            </li>
                                        ))}
                                </ol>
                            </div>
                        ))}
                    </div>
                </section>
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
