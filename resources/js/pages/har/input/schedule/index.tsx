import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor   } from 'react-data-grid';
import type {Column, ColumnOrColumnGroup} from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import schedule from '@/routes/har/input/schedule';
import type { IdName } from '@/types';

type GridRow = {
    engine_id: number;
    engine_name: string;
} & Record<string, number | string | null>;

type Props = {
    filters: { unit_id: number; month: number; year: number; scope: string; plan_type: string };
    days: number;
    rows: GridRow[];
    options: {
        units: IdName[];
        years: number[];
        scopes: { value: string; label: string }[];
        plan_types: { value: string; label: string }[];
    };
    can_write: boolean;
};

export default function SchedulesInput({ filters, days, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${filters.scope}-${filters.plan_type}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            schedule.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'engine_name',
                name: 'Mesin',
                width: 150,
                frozen: true,
                headerCellClass: 'rdg-group-header',
                cellClass: 'font-medium',
            },
        ];

        for (let d = 1; d <= days; d++) {
            cols.push({
                key: `day_${d}`,
                name: String(d),
                width: 44,
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

            return { engine_id: row.engine_id, days: daysMap };
        });
        router.post(
            schedule.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                scope: filters.scope,
                plan_type: filters.plan_type,
                rows: payload,
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input HAR — Rencana vs Realisasi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Rencana vs Realisasi"
                    description="Matriks jadwal pemeliharaan per mesin × tanggal. Isi kode siklus (mis. P1/P2) pada hari terkait; paste dari Excel didukung."
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
                    <OperasiSelect
                        label="Lingkup"
                        value={filters.scope}
                        onChange={(value) => visit({ scope: value })}
                        options={options.scopes.map((s) => ({ value: s.value, label: s.label }))}
                    />
                    <OperasiSelect
                        label="Jenis"
                        value={filters.plan_type}
                        onChange={(value) => visit({ plan_type: value })}
                        options={options.plan_types.map((p) => ({ value: p.value, label: p.label }))}
                    />
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Unit ini belum punya mesin aktif.
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: '60vh' }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row.engine_id}
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

SchedulesInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Rencana vs Realisasi', href: schedule.index() },
    ],
};
