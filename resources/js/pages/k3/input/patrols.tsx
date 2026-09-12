import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column, ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import patrol from '@/routes/k3/input/patrol';
import type { IdName } from '@/types';

type GridRow = {
    location_id: number;
    location_name: string;
} & Record<string, number | string | null>;

type Props = {
    filters: { unit_id: number; month: number; year: number };
    days: number;
    rows: GridRow[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const rowTotal = (row: GridRow, days: number): number => {
    let sum = 0;

    for (let d = 1; d <= days; d++) {
        const v = Number(row[`day_${d}`]);

        if (!Number.isNaN(v)) {
            sum += v;
        }
    }

    return sum;
};

export default function PatrolInput({ filters, days, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            patrol.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'location_name',
                name: 'Lokasi Patroli',
                width: 200,
                frozen: true,
                headerCellClass: 'rdg-group-header',
                cellClass: 'font-medium',
            },
            {
                key: 'total',
                name: 'Total',
                width: 70,
                frozen: true,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'text-center font-semibold',
                renderCell: ({ row }) => rowTotal(row, days),
            },
        ];

        for (let d = 1; d <= days; d++) {
            cols.push({
                key: `day_${d}`,
                name: String(d),
                width: 40,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'text-center',
            });
        }

        return cols;
    }, [days, can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(next);
            setDirty(true);
        },
    });

    const save = () => {
        setSaving(true);
        const payload = rows.map((row) => {
            const daysMap: Record<string, string | number | null> = {};

            for (let d = 1; d <= days; d++) {
                daysMap[d] = row[`day_${d}`];
            }

            return { location_id: row.location_id, days: daysMap };
        });
        router.post(
            patrol.store().url,
            { unit_id: filters.unit_id, month: filters.month, year: filters.year, rows: payload },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input K3 — Patroli Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Patroli Keamanan"
                    description="Matriks lokasi patroli (POA…) × tanggal. Isi jumlah scan per hari; total kumulatif per lokasi dihitung otomatis. Paste dari Excel didukung."
                    actions={
                        can_write && (
                            <Button onClick={save} disabled={saving || rows.length === 0}>
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        )
                    }
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
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada lokasi patroli. Tambahkan lewat Master K3 &amp; Keamanan → Lokasi Patroli.
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: '60vh' }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row.location_id}
                            onRowsChange={(next) => {
                                setRows(next);
                                setDirty(true);
                            }}
                            onSelectedCellChange={onSelectedCellChange}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

PatrolInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Patroli Keamanan', href: patrol.index() },
    ],
};
