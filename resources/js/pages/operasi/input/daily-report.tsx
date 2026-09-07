import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column, ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import dailyReport from '@/routes/operasi/input/daily-report';
import type { IdName } from '@/types';

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

/** Excel-like styling for the grouped daily grid. */
const GRID_STYLES = `
.operasi-grid .rdg {
    --rdg-header-row-height: 30px;
    font-size: 12px;
    border: 1px solid var(--rdg-border-color);
    border-radius: 6px;
}
.operasi-grid .rdg-group-header,
.operasi-grid .rdg-sub-header {
    text-align: center;
    justify-content: center;
}
.operasi-grid .rdg-group-header {
    background: #e6eefb;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #1e3a5f;
}
.operasi-grid .rdg-sub-header {
    background: #f4f7fc;
    font-weight: 600;
    color: #334155;
}
.operasi-grid .rdg-derived-header {
    background: #eef0f3;
    color: #64748b;
}
.operasi-grid .rdg-derived-cell {
    background: #f8fafc;
}
`;

type GridRow = {
    day: number;
    report_date: string;
    kwh_produksi_stand_awal: number | null;
    kwh_produksi_stand_akhir: string | null;
    kwh_produksi: number | null;
    kwh_pakai_sendiri_stand_awal: number | null;
    kwh_pakai_sendiri_stand_akhir: string | null;
    kwh_pakai_sendiri: number | null;
    kwh_netto: number | null;
    beban_puncak_pagi_kw: string | null;
    beban_puncak_malam_kw: string | null;
    pemakaian_pelumas_liter: string | null;
    flowmeter_hsd_stand_awal: number | null;
    flowmeter_hsd_stand_akhir: string | null;
    flowmeter_hsd_tambah_liter: string | null;
    pemakaian_hsd: number | null;
    flowmeter_mfo_stand_awal: number | null;
    flowmeter_mfo_stand_akhir: string | null;
    flowmeter_mfo_tambah_liter: string | null;
    pemakaian_mfo: number | null;
    air_pps_stand_akhir: string | null;
    air_softener_stand_akhir: string | null;
    catatan: string | null;
    is_complete: boolean;
};

type Summary = Record<
    string,
    {
        kwh_produksi: number;
        kwh_pakai_sendiri: number;
        kwh_netto: number;
        pemakaian_hsd: number;
        pemakaian_mfo: number;
        pemakaian_pelumas_liter: number;
    }
>;

type Props = {
    filters: {
        unit_id: number;
        engine_id: number | null;
        month: number;
        year: number;
    };
    grid: { rows: GridRow[]; summary: Summary };
    engine: {
        id: number;
        name: string;
        fuel_type: string | null;
        uses_mfo: boolean;
    } | null;
    period: { total_days: number; locked: boolean } | null;
    options: {
        units: IdName[];
        machines: { id: number; name: string; uses_mfo: boolean }[];
        years: number[];
    };
    can_write: boolean;
};

const num = (value: number | null) =>
    value === null ? '' : formatNumber(String(value));

export default function DailyReportInput({
    filters,
    grid,
    engine,
    period,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<GridRow[]>(grid.rows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Reset the grid when the server sends a different unit/engine/period, using
    // the "adjust state during render" pattern rather than an effect.
    const signature = `${filters.unit_id}-${filters.engine_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(grid.rows);
        setDirty(false);
    }

    const usesMfo = engine?.uses_mfo ?? false;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            dailyReport.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof GridRow, name: string): Column<GridRow> => ({
            key,
            name,
            width: 92,
            editable: can_write && !(period?.locked ?? false),
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
            cellClass: 'rdg-editable-cell text-right tabular-nums',
        });
        const derived = (
            key: keyof GridRow,
            name: string,
            width = 96,
        ): Column<GridRow> => ({
            key,
            name,
            width,
            headerCellClass: 'rdg-sub-header rdg-derived-header',
            cellClass: 'rdg-derived-cell text-right tabular-nums text-muted-foreground',
            renderCell: ({ row }) => num(row[key] as number | null),
        });

        const group = (
            name: string,
            children: Column<GridRow>[],
        ): ColumnOrColumnGroup<GridRow> => ({
            name,
            headerCellClass: 'rdg-group-header',
            children,
        });

        const cols: ColumnOrColumnGroup<GridRow>[] = [
            {
                key: 'day',
                name: 'Tgl',
                width: 52,
                frozen: true,
                headerCellClass: 'rdg-group-header',
                cellClass: 'text-center font-semibold',
            },
            group('KWH PRODUKSI', [
                derived('kwh_produksi_stand_awal', 'Stand Awal'),
                editable('kwh_produksi_stand_akhir', 'Stand Akhir'),
                derived('kwh_produksi', 'Produksi'),
            ]),
            group('PEMAKAIAN SENDIRI', [
                derived('kwh_pakai_sendiri_stand_awal', 'Stand Awal'),
                editable('kwh_pakai_sendiri_stand_akhir', 'Stand Akhir'),
                derived('kwh_netto', 'kWh Netto'),
            ]),
            group('BEBAN PUNCAK', [
                editable('beban_puncak_pagi_kw', 'Pagi (kW)'),
                editable('beban_puncak_malam_kw', 'Malam (kW)'),
            ]),
            group('PELUMAS', [
                editable('pemakaian_pelumas_liter', 'Pakai (L)'),
            ]),
            group('BBM — HSD', [
                derived('flowmeter_hsd_stand_awal', 'Stand Awal'),
                editable('flowmeter_hsd_stand_akhir', 'Stand Akhir'),
                editable('flowmeter_hsd_tambah_liter', 'Tambah (L)'),
                derived('pemakaian_hsd', 'Pakai (L)'),
            ]),
        ];

        if (usesMfo) {
            cols.push(
                group('BBM — MFO', [
                    derived('flowmeter_mfo_stand_awal', 'Stand Awal'),
                    editable('flowmeter_mfo_stand_akhir', 'Stand Akhir'),
                    editable('flowmeter_mfo_tambah_liter', 'Tambah (L)'),
                    derived('pemakaian_mfo', 'Pakai (L)'),
                ]),
            );
        }

        cols.push(
            group('AIR', [
                editable('air_pps_stand_akhir', 'PPS'),
                editable('air_softener_stand_akhir', 'Softener'),
            ]),
            group('KETERANGAN', [{ ...editable('catatan', 'Catatan'), width: 170 }]),
        );

        return cols;
    }, [usesMfo, can_write, period?.locked]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(next);
            setDirty(true);
        },
    });

    const save = () => {
        if (!engine) {
            return;
        }

        setSaving(true);
        router.post(
            dailyReport.store().url,
            {
                unit_id: filters.unit_id,
                engine_id: engine.id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((row) => ({
                    day: row.day,
                    kwh_produksi_stand_akhir: row.kwh_produksi_stand_akhir,
                    kwh_pakai_sendiri_stand_akhir: row.kwh_pakai_sendiri_stand_akhir,
                    beban_puncak_pagi_kw: row.beban_puncak_pagi_kw,
                    beban_puncak_malam_kw: row.beban_puncak_malam_kw,
                    pemakaian_pelumas_liter: row.pemakaian_pelumas_liter,
                    flowmeter_hsd_stand_akhir: row.flowmeter_hsd_stand_akhir,
                    flowmeter_hsd_tambah_liter: row.flowmeter_hsd_tambah_liter,
                    flowmeter_mfo_stand_akhir: row.flowmeter_mfo_stand_akhir,
                    flowmeter_mfo_tambah_liter: row.flowmeter_mfo_tambah_liter,
                    air_pps_stand_akhir: row.air_pps_stand_akhir,
                    air_softener_stand_akhir: row.air_softener_stand_akhir,
                    catatan: row.catatan,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const totals = grid.summary.total;

    return (
        <>
            <Head title="Input Operasi — Laporan Harian" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input Operasi Harian"
                    description="Grid pembacaan meter harian per mesin. Stand awal dan kolom hasil dihitung otomatis."
                    actions={
                        can_write && (
                            <Button onClick={save} disabled={saving || !engine}>
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <Selector
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({ unit_id: Number(value), engine_id: null })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <Selector
                        label="Mesin"
                        value={engine ? String(engine.id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({
                            value: String(m.id),
                            label: m.name,
                        }))}
                    />
                    <Selector
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <Selector
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    {period?.locked && (
                        <span className="rounded-md bg-amber-100 px-2 py-1 text-[13px] text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            Periode terkunci — hanya baca.
                        </span>
                    )}
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {engine === null ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Unit ini belum punya mesin aktif. Tambahkan mesin dulu di
                        Master Mesin.
                    </div>
                ) : (
                    <>
                        <style>{GRID_STYLES}</style>
                        <div
                            className="operasi-grid overflow-hidden rounded-md border border-border"
                            onPaste={onPaste}
                        >
                            <DataGrid
                                className="rdg-light"
                                style={{ blockSize: '62vh' }}
                                columns={columns}
                                rows={rows}
                                rowKeyGetter={(row) => row.day}
                                onRowsChange={(next) => {
                                    setRows(next);
                                    setDirty(true);
                                }}
                                onSelectedCellChange={onSelectedCellChange}
                                rowClass={(row) =>
                                    row.is_complete
                                        ? 'bg-emerald-50/60'
                                        : undefined
                                }
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <SummaryCard label="kWh Produksi" value={totals?.kwh_produksi} />
                            <SummaryCard label="kWh Netto" value={totals?.kwh_netto} />
                            <SummaryCard label="Pemakaian HSD (L)" value={totals?.pemakaian_hsd} />
                            {usesMfo ? (
                                <SummaryCard label="Pemakaian MFO (L)" value={totals?.pemakaian_mfo} />
                            ) : (
                                <SummaryCard label="Pemakaian Pelumas (L)" value={totals?.pemakaian_pelumas_liter} />
                            )}
                        </div>
                        <p className="text-[13px] text-muted-foreground">
                            Kolom hasil (kWh Prod, Netto, Pakai HSD/MFO) dihitung
                            ulang oleh server setelah disimpan. Tip: salin satu blok
                            dari Excel, klik sel awal, lalu tempel (Ctrl+V) untuk
                            mengisi banyak sel sekaligus.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

function Selector({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <label className="flex flex-col gap-1 text-[13px]">
            <span className="text-muted-foreground">{label}</span>
            <Select value={value || undefined} onValueChange={onChange}>
                <SelectTrigger className="h-9 w-44">
                    <SelectValue placeholder={`Pilih ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </label>
    );
}

function SummaryCard({ label, value }: { label: string; value?: number }) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <p className="text-[13px] text-muted-foreground">{label}</p>
            <p className="text-lg font-semibold tabular-nums">
                {value === undefined ? '—' : formatNumber(String(value))}
            </p>
        </div>
    );
}

DailyReportInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: dailyReport.index() },
    ],
};
