import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { SlaDocument } from '@/components/laporan/sla-document';
import type {
    SlaDocumentBody,
    SlaDocumentState,
} from '@/components/laporan/sla-document';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    downloadSlaCombinedUnitsWorkbook,
    downloadSlaCombinedWorkbook,
} from '@/lib/sla-excel';
import type { SlaCombinedData } from '@/lib/sla-excel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import slaRoutes from '@/routes/sla';
import type { IdName } from '@/types';

type Filters = {
    unit_id: number | 'all';
    month: number;
    year: number;
    tagihan: number | null;
};

type RecapRow = {
    number: string;
    title: string;
    bobot: number;
    percent: number | null;
    denda_count: number;
    measured: number;
    denda_bobot: number;
};

type Props = SlaDocumentBody & {
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    period: string;
    recap: { unit: string; rows: RecapRow[] }[];
    document: SlaDocumentState;
};

/**
 * Laporan SLA gabungan (Project Leader & Super Admin): every divisi's SLA —
 * cover, 1.1.a … 1.2.b and the Rekapitulasi — of one unit or every accessible
 * unit ("Semua unit"), as an editable document (Teks / Excel / Pratinjau PDF,
 * user 2026-11-19) printed in one PDF, plus the Excel of the computed SLA.
 */
export default function SlaGabungan({
    filters,
    options,
    period,
    recap,
    document,
    ...body
}: Props) {
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [tagihanText, setTagihanText] = useState(
        filters.tagihan === null ? '' : String(filters.tagihan),
    );
    const query = (patch: Partial<Filters> = {}) => {
        const merged = { ...filters, ...patch };

        return {
            unit_id: merged.unit_id,
            month: merged.month,
            year: merged.year,
            ...(merged.tagihan === null ? {} : { tagihan: merged.tagihan }),
        };
    };
    const visit = (patch: Partial<Filters>) =>
        router.get(
            slaRoutes.combined.index({ query: query(patch) }).url,
            {},
            { preserveScroll: true, replace: true },
        );
    const all = filters.unit_id === 'all';
    const unitLabel = all
        ? 'Semua_Unit'
        : (options.units.find((u) => u.id === filters.unit_id)?.name ?? 'Unit');
    const fileBase =
        `Laporan_SLA_${unitLabel}_${filters.month}_${filters.year}`.replace(
            /\s+/g,
            '_',
        );

    const exportExcel = async () => {
        setExporting(true);
        setError(null);

        try {
            const response = await fetch(
                slaRoutes.combined.data({ query: query() }).url,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = (await response.json()) as
                SlaCombinedData | { units: SlaCombinedData[] };

            if ('units' in data) {
                await downloadSlaCombinedUnitsWorkbook(
                    data.units,
                    filters.tagihan,
                    `${fileBase}.xlsx`,
                );
            } else {
                await downloadSlaCombinedWorkbook(
                    data,
                    filters.tagihan,
                    `${fileBase}.xlsx`,
                );
            }
        } catch {
            setError('Excel gagal dibuat. Coba lagi beberapa saat.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title="Laporan SLA Gabungan" />
            <div className="flex flex-col gap-3 px-4 pt-4 md:px-6 md:pt-6">
                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({
                                unit_id:
                                    value === 'all' ? 'all' : Number(value),
                            })
                        }
                        className="w-60"
                        options={[
                            ...(options.units.length > 1
                                ? [
                                      {
                                          value: 'all',
                                          label: `Semua unit (${options.units.length})`,
                                      },
                                  ]
                                : []),
                            ...options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            })),
                        ]}
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
                    <form
                        className="flex items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const digits = tagihanText.replace(/[^\d]/g, '');
                            visit({
                                tagihan: digits === '' ? null : Number(digits),
                            });
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
                            Terapkan
                        </Button>
                    </form>
                    <Button
                        variant="outline"
                        onClick={exportExcel}
                        disabled={exporting}
                        className="ml-auto"
                        title="Excel berformat SLA dari hasil hitung otomatis (sampul & sheet per unit)"
                    >
                        {exporting ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <FileSpreadsheet className="size-4" />
                        )}
                        Excel SLA (data)
                    </Button>
                </div>

                {error && (
                    <p className="text-[13px] text-red-700 dark:text-red-300">
                        {error}
                    </p>
                )}

            </div>

            <SlaDocument
                document={document}
                body={body}
                period={period}
                backUrl={dashboard().url}
            />

            <div className="flex flex-col gap-4 px-4 pb-6 md:px-6">
                {recap.map((unit) => (
                    <section
                        key={unit.unit}
                        className="rounded-md border border-border bg-card p-4"
                    >
                        <h2 className="mb-2 text-base font-semibold text-foreground">
                            Rekapitulasi SLA — {unit.unit}
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[13px]">
                                <thead>
                                    <tr>
                                        {[
                                            'Divisi',
                                            'Bobot',
                                            '% Pencapaian',
                                            'Kriteria Denda',
                                            'Bobot Denda',
                                        ].map((column) => (
                                            <th
                                                key={column}
                                                className="border border-border bg-secondary px-2 py-1.5 text-left font-semibold text-foreground"
                                            >
                                                {column}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {unit.rows.map((row) => (
                                        <tr key={row.number}>
                                            <td className="border border-border px-2 py-1 text-foreground">
                                                {row.number} {row.title}
                                            </td>
                                            <td className="border border-border px-2 py-1 text-foreground">
                                                {row.bobot}
                                            </td>
                                            <td
                                                className={cn(
                                                    'border border-border px-2 py-1 font-semibold',
                                                    row.percent !== null &&
                                                        row.percent < 100
                                                        ? 'text-red-700 dark:text-red-300'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {row.percent === null
                                                    ? '—'
                                                    : `${row.percent}%`}
                                            </td>
                                            <td className="border border-border px-2 py-1 text-foreground">
                                                {row.denda_count} /{' '}
                                                {row.measured}
                                            </td>
                                            <td className="border border-border px-2 py-1 text-foreground">
                                                {row.denda_bobot}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

SlaGabungan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan SLA Gabungan', href: slaRoutes.combined.index() },
    ],
};
