import { Head, router } from '@inertiajs/react';
import {
    BookCopy,
    Download,
    FileSpreadsheet,
    FileText,
    LoaderCircle,
} from 'lucide-react';
import { useState } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    downloadSlaCombinedWorkbook,
    downloadSlaWorkbook,
    formatRupiah,
    formatSlaNumber,
    slaPenalty,
} from '@/lib/sla-excel';
import type { SlaCombinedData, SlaData } from '@/lib/sla-excel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import slaRoutes from '@/routes/sla';
import type { IdName } from '@/types';

type Filters = {
    unit_id: number;
    month: number;
    year: number;
    tagihan: number | null;
};

type Props = {
    sla: SlaData;
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    divisions: { key: string; number: string; label: string }[];
    /** May download the Laporan SLA gabungan (every divisi in one file). */
    can_combined: boolean;
};

const TH =
    'border border-slate-400 bg-[#1f3b64] px-1.5 py-1 text-center text-[11px] font-semibold text-white';
const TD = 'border border-slate-300 px-1.5 py-1 text-center align-middle';

/**
 * SLA bulanan per divisi (App\Services\Reports\SlaReport): the SLA sheet's
 * table scored from the unit's inputs, with the data sources of each
 * criterion, the Rp denda from an optional tagihan bulan berjalan, and the
 * PDF / Excel downloads (landscape).
 */
export default function SlaPage({
    sla,
    filters,
    options,
    divisions,
    can_combined,
}: Props) {
    const [exporting, setExporting] = useState(false);
    const [exportingCombined, setExportingCombined] = useState(false);
    const [tagihanText, setTagihanText] = useState(
        filters.tagihan === null ? '' : String(filters.tagihan),
    );
    const tagihan = filters.tagihan;
    const query = (patch: Partial<Filters> = {}) => {
        const merged = { ...filters, ...patch };

        return {
            unit_id: merged.unit_id,
            month: merged.month,
            year: merged.year,
            ...(merged.tagihan === null ? {} : { tagihan: merged.tagihan }),
        };
    };
    const visit = (divisi: string, patch: Partial<Filters> = {}) => {
        router.get(
            slaRoutes.show(divisi, { query: query(patch) }).url,
            {},
            { preserveScroll: true },
        );
    };
    const applyTagihan = () => {
        const digits = tagihanText.replace(/[^\d]/g, '');
        visit(sla.divisi, { tagihan: digits === '' ? null : Number(digits) });
    };

    const dendaTotal = sla.rows.reduce(
        (sum, row) => sum + slaPenalty(row, tagihan),
        0,
    );
    const fileBase =
        `SLA_${sla.divisi}_${sla.unit.name}_${sla.period.month}_${sla.period.year}`.replace(
            /\s+/g,
            '_',
        );

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadSlaWorkbook(sla, tagihan, `${fileBase}.xlsx`);
        } finally {
            setExporting(false);
        }
    };

    const exportCombinedExcel = async () => {
        setExportingCombined(true);

        try {
            const response = await fetch(
                slaRoutes.combined.data({ query: query() }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = (await response.json()) as SlaCombinedData;
            await downloadSlaCombinedWorkbook(
                data,
                tagihan,
                `Laporan_SLA_${sla.unit.name}_${sla.period.month}_${sla.period.year}.xlsx`.replace(
                    /\s+/g,
                    '_',
                ),
            );
        } finally {
            setExportingCombined(false);
        }
    };

    return (
        <>
            <Head title={`SLA ${sla.title} - ${sla.unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`SLA — ${sla.title}`}
                    description={`${sla.number} · ${sla.unit.name} · periode ${sla.period.label} — dihitung otomatis dari inputan aplikasi.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        slaRoutes.pdf(sla.divisi, {
                                            query: query(),
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText className="size-4" /> Lihat PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        slaRoutes.pdf(sla.divisi, {
                                            query: { ...query(), download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh PDF
                                </a>
                            </Button>
                            <Button onClick={exportExcel} disabled={exporting}>
                                {exporting ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <FileSpreadsheet className="size-4" />
                                )}
                                Unduh Excel
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    {divisions.length > 1 && (
                        <OperasiSelect
                            label="Divisi"
                            value={sla.divisi}
                            onChange={(value) => visit(value)}
                            className="w-72"
                            options={divisions.map((d) => ({
                                value: d.key,
                                label: `${d.number} ${d.label}`,
                            }))}
                        />
                    )}
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit(sla.divisi, { unit_id: Number(value) })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) =>
                            visit(sla.divisi, { month: Number(value) })
                        }
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) =>
                            visit(sla.divisi, { year: Number(value) })
                        }
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <form
                        className="flex items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyTagihan();
                        }}
                    >
                        <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                            Tagihan bulan berjalan (Rp, opsional)
                            <Input
                                inputMode="numeric"
                                value={tagihanText}
                                onChange={(event) =>
                                    setTagihanText(event.target.value)
                                }
                                placeholder="mis. 150000000"
                                className="w-48 bg-card"
                            />
                        </label>
                        <Button type="submit" variant="outline">
                            Hitung Denda
                        </Button>
                    </form>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        label="Pencapaian Kinerja"
                        value={
                            sla.total.percent === null
                                ? '—'
                                : `${sla.total.percent}%`
                        }
                        hint={`${formatSlaNumber(sla.total.achieved)} dari target ${formatSlaNumber(sla.total.target)}`}
                    />
                    <SummaryCard
                        label="Kriteria Denda"
                        value={`${sla.total.denda_count} / ${sla.total.measured}`}
                        hint={`${sla.rows.length - sla.total.measured} kriteria belum terukur`}
                    />
                    <SummaryCard
                        label="Bobot Denda"
                        value={`${sla.total.denda_bobot} / ${sla.total.bobot}`}
                        hint={`${formatSlaNumber(sla.total.denda_bobot * 0.9)}% dari tagihan bulan berjalan`}
                    />
                    <SummaryCard
                        label="Nilai Denda"
                        value={
                            tagihan === null ? '—' : formatRupiah(dendaTotal)
                        }
                        hint={
                            tagihan === null
                                ? 'Isi tagihan bulan berjalan untuk menghitung Rp'
                                : `Tagihan ${formatRupiah(tagihan)}`
                        }
                    />
                </div>

                {can_combined && (
                    <div className="flex flex-col justify-between gap-3 rounded-md border border-l-4 border-border border-l-primary bg-card p-4 sm:flex-row sm:items-center">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-foreground">
                                Laporan SLA Gabungan
                            </h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Satu file berisi sampul, SLA seluruh divisi
                                (1.1.a – 1.2.b) dan rekapitulasi denda —{' '}
                                {sla.unit.name}, periode {sla.period.label}.
                            </p>
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        slaRoutes.combined.pdf({
                                            query: query(),
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <BookCopy className="size-4" /> Lihat PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        slaRoutes.combined.pdf({
                                            query: { ...query(), download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh PDF
                                </a>
                            </Button>
                            <Button
                                onClick={exportCombinedExcel}
                                disabled={exportingCombined}
                            >
                                {exportingCombined ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <FileSpreadsheet className="size-4" />
                                )}
                                Unduh Excel
                            </Button>
                        </div>
                    </div>
                )}

                {sla.period.state !== 'past' && (
                    <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-[13px] text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                        {sla.period.state === 'future'
                            ? 'Periode belum berjalan — SLA belum dapat dihitung.'
                            : 'Periode berjalan — nilai pencapaian dapat berubah hingga akhir bulan.'}
                    </p>
                )}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <div className="border-b border-border px-4 py-3 text-center">
                        <p className="text-sm font-bold italic">
                            SLA (SERVICE LEVEL AGREEMENT)
                        </p>
                        <p className="text-xs font-semibold">{sla.contract}</p>
                        <p className="text-xs font-semibold">
                            {sla.contract_number} · {sla.unit.name} · PERIODE{' '}
                            {sla.period.label}
                        </p>
                    </div>
                    <table className="w-full min-w-[1400px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <td
                                    colSpan={tagihan === null ? 14 : 15}
                                    className="py-1.5 text-center text-[13px] font-bold text-[#1f3b64] underline dark:text-sky-300"
                                >
                                    {sla.number} {sla.title}
                                </td>
                            </tr>
                            <tr>
                                <th rowSpan={3} className={TH}>
                                    No.
                                </th>
                                <th rowSpan={3} className={cn(TH, 'w-56')}>
                                    Kriteria
                                </th>
                                <th rowSpan={3} className={cn(TH, 'w-56')}>
                                    Target Kinerja
                                </th>
                                <th rowSpan={3} className={cn(TH, 'w-48')}>
                                    Eviden
                                </th>
                                <th rowSpan={3} className={TH}>
                                    Jenis SLA
                                </th>
                                <th rowSpan={3} className={TH}>
                                    Bobot
                                </th>
                                <th rowSpan={3} className={cn(TH, 'w-40')}>
                                    Perhitungan Konsekuensi / Denda
                                </th>
                                <th colSpan={4} className={TH}>
                                    Perhitungan Kinerja
                                </th>
                                <th
                                    colSpan={tagihan === null ? 2 : 3}
                                    className={TH}
                                >
                                    Status Consequence
                                </th>
                                <th rowSpan={3} className={cn(TH, 'w-56')}>
                                    Sumber Data (Aplikasi)
                                </th>
                            </tr>
                            <tr>
                                <th colSpan={2} className={TH}>
                                    Target Kinerja
                                </th>
                                <th colSpan={2} className={TH}>
                                    Pencapaian Kinerja
                                </th>
                                <th rowSpan={2} className={TH}>
                                    Denda
                                </th>
                                <th rowSpan={2} className={TH}>
                                    Tidak Denda
                                </th>
                                {tagihan !== null && (
                                    <th rowSpan={2} className={TH}>
                                        Nilai Denda
                                    </th>
                                )}
                            </tr>
                            <tr>
                                <th className={TH}>Nilai Target Kinerja</th>
                                <th className={TH}>% Target Kinerja</th>
                                <th className={TH}>Nilai Pencapaian Kinerja</th>
                                <th className={TH}>
                                    % Pencapaian Target Kinerja
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sla.rows.map((row) => {
                                const fill =
                                    row.target > 1
                                        ? 'bg-lime-300/70 dark:bg-lime-700/50'
                                        : 'bg-yellow-200/80 dark:bg-yellow-700/40';
                                const amount = slaPenalty(row, tagihan);

                                return (
                                    <tr key={row.no}>
                                        <td className={TD}>{row.no}</td>
                                        <td className={TD}>{row.kriteria}</td>
                                        <td className={TD}>
                                            {row.target_kinerja}
                                        </td>
                                        <td className={TD}>{row.eviden}</td>
                                        <td className={TD}>{row.jenis}</td>
                                        <td className={TD}>{row.bobot}</td>
                                        <td className={TD}>{row.formula}</td>
                                        <td className={cn(TD, fill)}>
                                            {formatSlaNumber(row.target)}
                                        </td>
                                        <td className={TD}>
                                            {row.target_percent}%
                                        </td>
                                        <td
                                            className={cn(
                                                TD,
                                                row.denda
                                                    ? 'bg-red-200 dark:bg-red-900/60'
                                                    : fill,
                                            )}
                                        >
                                            {formatSlaNumber(row.achieved)}
                                        </td>
                                        <td
                                            className={cn(
                                                TD,
                                                'font-semibold',
                                                row.denda &&
                                                    'text-red-700 dark:text-red-300',
                                            )}
                                        >
                                            {row.percent === null
                                                ? 'Belum terukur'
                                                : `${row.percent}%`}
                                        </td>
                                        <td
                                            className={cn(
                                                TD,
                                                'text-red-700 dark:text-red-300',
                                            )}
                                        >
                                            {row.denda === true ? '✓' : ''}
                                        </td>
                                        <td
                                            className={cn(
                                                TD,
                                                'text-emerald-700 dark:text-emerald-300',
                                            )}
                                        >
                                            {row.denda === false ? '✓' : ''}
                                        </td>
                                        {tagihan !== null && (
                                            <td className={TD}>
                                                {amount > 0
                                                    ? formatRupiah(amount)
                                                    : '—'}
                                            </td>
                                        )}
                                        <td
                                            className={cn(
                                                TD,
                                                'text-left text-[11px] text-muted-foreground',
                                            )}
                                        >
                                            {row.sources.length === 0 ? (
                                                <span className="italic">
                                                    Belum ada data di aplikasi —
                                                    diverifikasi manual
                                                </span>
                                            ) : (
                                                <ul className="space-y-0.5">
                                                    {row.sources.map(
                                                        (source) => (
                                                            <li
                                                                key={
                                                                    source.label
                                                                }
                                                            >
                                                                {source.label}:{' '}
                                                                {row.mode ===
                                                                'count' ? (
                                                                    <span className="font-semibold text-foreground">
                                                                        {
                                                                            source.filled
                                                                        }{' '}
                                                                        dokumen
                                                                    </span>
                                                                ) : (
                                                                    <span
                                                                        className={cn(
                                                                            'font-semibold',
                                                                            source.percent !==
                                                                                null &&
                                                                                source.percent <
                                                                                    100
                                                                                ? 'text-red-700 dark:text-red-300'
                                                                                : 'text-foreground',
                                                                        )}
                                                                    >
                                                                        {source.percent ===
                                                                        null
                                                                            ? '—'
                                                                            : `${source.percent}%`}
                                                                    </span>
                                                                )}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr className="font-bold">
                                <td colSpan={5} className={TD}>
                                    TOTAL
                                </td>
                                <td className={TD}>{sla.total.bobot}</td>
                                <td className={TD} />
                                <td className={TD}>
                                    {formatSlaNumber(sla.total.target)}
                                </td>
                                <td className={TD}>100%</td>
                                <td className={TD}>
                                    {formatSlaNumber(sla.total.achieved)}
                                </td>
                                <td className={TD}>
                                    {sla.total.percent === null
                                        ? '—'
                                        : `${sla.total.percent}%`}
                                </td>
                                <td className={TD}>{sla.total.denda_count}</td>
                                <td className={TD}>
                                    {sla.total.measured - sla.total.denda_count}
                                </td>
                                {tagihan !== null && (
                                    <td className={TD}>
                                        {formatRupiah(dendaTotal)}
                                    </td>
                                )}
                                <td className={TD} />
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p className="text-xs text-muted-foreground">
                    Nilai pencapaian = rata-rata kelengkapan inputan sumber
                    datanya pada periode ini (untuk IK/dokumen: jumlah dokumen
                    yang diinput). Laporan dihitung terpenuhi setelah diajukan.
                    % Pencapaian di bawah 100% = Denda (bobot/10 × 9% × tagihan
                    bulan berjalan).
                </p>
            </div>
        </>
    );
}

SlaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'SLA', href: '#' },
    ],
};
