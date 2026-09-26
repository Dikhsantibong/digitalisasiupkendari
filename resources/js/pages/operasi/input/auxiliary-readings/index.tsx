import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { textEditor   } from 'react-data-grid';
import type {Column, ColumnOrColumnGroup} from 'react-data-grid';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OperasiGrid } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import auxiliary from '@/routes/operasi/input/auxiliary';
import type { IdName } from '@/types';

type GridRow = { day: number; report_date: string; [key: string]: number | string | null };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    sources: IdName[];
    rows: GridRow[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

export default function AuxiliaryReadings({
    filters,
    sources,
    rows: initialRows,
    options,
    can_write,
}: Props) {
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
            auxiliary.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: string, name: string): Column<GridRow> => ({
            key,
            name,
            width: 110,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
            cellClass: 'text-right tabular-nums',
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
        ];

        for (const source of sources) {
            cols.push({
                name: source.name,
                headerCellClass: 'rdg-group-header',
                children: [
                    editable(`kwh_${source.id}`, 'Stand kWh'),
                    editable(`bbm_${source.id}`, 'Stand BBM'),
                ],
            });
        }

        return cols;
    }, [sources, can_write]);

    const save = () => {
        setSaving(true);
        const readings = rows.flatMap((row) =>
            sources.map((source) => ({
                auxiliary_source_id: source.id,
                day: row.day,
                stand_kwh_akhir: row[`kwh_${source.id}`] ?? null,
                stand_bbm_akhir: row[`bbm_${source.id}`] ?? null,
            })),
        );
        router.post(
            auxiliary.store().url,
            { unit_id: filters.unit_id, month: filters.month, year: filters.year, readings },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input Operasi — Pasokan Cadangan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Pembacaan Pasokan Cadangan"
                    description="Stand kWh & BBM tiap sumber pasokan cadangan per tanggal."
                    actions={
                        can_write && (
                            <Button onClick={save} disabled={saving || sources.length === 0}>
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
                        <span className="text-[13px] text-amber-600">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {sources.length === 0 ? (
                    <EmptyState
                        title="Belum ada pasokan cadangan"
                        description="Unit ini belum punya master pasokan cadangan aktif."
                    />
                ) : (
                    <OperasiGrid
                        columns={columns}
                        rows={rows}
                        rowKey={(row) => row.day}
                        onRowsChange={(next) => {
                            setRows(next);
                            setDirty(true);
                        }}
                    />
                )}
            </div>
        </>
    );
}

AuxiliaryReadings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pasokan Cadangan', href: auxiliary.index() },
    ],
};
