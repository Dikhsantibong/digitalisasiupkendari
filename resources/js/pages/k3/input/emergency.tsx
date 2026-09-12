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
import emergency from '@/routes/k3/input/emergency';
import type { IdName } from '@/types';

type RowData = {
    equipment_id: number;
    equipment_name: string;
    jml_total: string | number | null;
    jml_ready: string | number | null;
    jml_not_ready: string | number | null;
    persen_kesiapan: string;
    kendala: string | null;
    tindak_lanjut: string | null;
};

type GridRow = RowData & { [key: string]: number | string | null };

type Props = {
    filters: { unit_id: number; month: number; year: number; week: string };
    rows: RowData[];
    options: {
        units: IdName[];
        years: number[];
        weeks: { value: string; label: string }[];
    };
    can_write: boolean;
};

const readiness = (row: GridRow): string => {
    const total = Number(row.jml_total);
    const ready = Number(row.jml_ready);

    if (!Number.isFinite(total) || total <= 0) {
        return '—';
    }

    return `${Math.round((ready / total) * 1000) / 10}%`;
};

export default function EmergencyInput({ filters, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${filters.week}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 560);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            emergency.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof RowData, name: string, width = 100): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
            cellClass: 'text-center',
        });

        return [
            { key: 'equipment_name', name: 'Fasilitas Darurat', minWidth: 220, frozen: true, headerCellClass: 'rdg-group-header', cellClass: 'font-medium' },
            editable('jml_total', 'Total', 80),
            editable('jml_ready', 'Ready', 80),
            editable('jml_not_ready', 'Not Ready', 90),
            {
                key: 'persen_kesiapan',
                name: '% Kesiapan',
                width: 100,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'text-center font-semibold',
                renderCell: ({ row }) => readiness(row),
            },
            { key: 'kendala', name: 'Kendala', width: 180, editable: can_write, renderEditCell: textEditor, headerCellClass: 'rdg-sub-header' },
            { key: 'tindak_lanjut', name: 'Tindak Lanjut', width: 180, editable: can_write, renderEditCell: textEditor, headerCellClass: 'rdg-sub-header' },
        ];
    }, [can_write]);

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
        router.post(
            emergency.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                week: filters.week,
                rows: rows.map((row) => ({
                    equipment_id: row.equipment_id,
                    jml_total: Number(row.jml_total) || 0,
                    jml_ready: Number(row.jml_ready) || 0,
                    jml_not_ready: Number(row.jml_not_ready) || 0,
                    kendala: row.kendala,
                    tindak_lanjut: row.tindak_lanjut,
                })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input K3 — Kesiapan Fasilitas Darurat" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Kesiapan Fasilitas Darurat"
                    description="Ready/Not Ready tiap fasilitas darurat per periode (bulanan atau mingguan). % kesiapan dihitung otomatis dari Ready/Total."
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
                        label="Periode"
                        value={filters.week}
                        onChange={(value) => visit({ week: value })}
                        options={options.weeks}
                    />
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada fasilitas darurat. Tambahkan lewat Master K3 &amp; Keamanan → Fasilitas Darurat.
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: `${gridHeight}px` }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row.equipment_id}
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

EmergencyInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Kesiapan Fasilitas Darurat', href: emergency.index() },
    ],
};
