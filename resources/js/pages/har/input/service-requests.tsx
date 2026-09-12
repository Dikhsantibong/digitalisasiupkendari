import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
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
import serviceRequest from '@/routes/har/input/service-request';
import type { IdName } from '@/types';

type Code = { code: string; name: string };

type RowData = {
    sr_number: string | null;
    description: string | null;
    category_code: string | null;
    status: string | null;
    engine_name: string | null;
};

type GridRow = RowData & { _key: number; [key: string]: number | string | null };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: RowData[];
    options: {
        units: IdName[];
        years: number[];
        categories: Code[];
        machines: IdName[];
        statuses: { value: string; label: string }[];
    };
    can_write: boolean;
};

const blankRow = (key: number): GridRow => ({
    _key: key,
    sr_number: '',
    description: null,
    category_code: null,
    status: 'open',
    engine_name: null,
});

const hydrate = (rows: RowData[]): GridRow[] =>
    rows.length ? rows.map((row, index) => ({ ...row, _key: index })) : [blankRow(0)];

export default function ServiceRequestInput({ filters, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(Math.max(initialRows.length, 1));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(Math.max(initialRows.length, 1));
        setDirty(false);
    }

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 560);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            serviceRequest.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const removeRow = (key: number) => {
        setRows((current) => current.filter((row) => row._key !== key));
        setDirty(true);
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof GridRow, name: string, width = 150): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
        });

        const cols: Column<GridRow>[] = [
            editable('sr_number', 'No. SR', 130),
            {
                key: 'description',
                name: 'Deskripsi',
                minWidth: 240,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
            },
            editable('category_code', 'Kategori', 110),
            editable('status', 'Status', 100),
            editable('engine_name', 'Mesin', 160),
        ];

        if (can_write) {
            cols.push({
                key: 'actions',
                name: 'Aksi',
                width: 64,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'text-center',
                renderCell: ({ row }) => (
                    <button
                        type="button"
                        onClick={() => removeRow(row._key)}
                        aria-label="Hapus baris"
                        className="text-destructive"
                    >
                        <Trash2 className="mx-auto size-4" />
                    </button>
                ),
            });
        }

        return cols;
    }, [can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(next);
            setDirty(true);
        },
    });

    const addRow = () => {
        setRows((current) => [...current, blankRow(nextKey)]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const payload = (row: GridRow): RowData => ({
        sr_number: row.sr_number,
        description: row.description,
        category_code: row.category_code,
        status: row.status,
        engine_name: row.engine_name,
    });

    const save = () => {
        setSaving(true);
        router.post(
            serviceRequest.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map(payload),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Input HAR — Service Request" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Service Request"
                    description="Daftar Service Request per unit & periode. Isi kode kategori & nama mesin; paste dari Excel didukung."
                    actions={
                        can_write && (
                            <div className="flex gap-2">
                                <Button variant="secondary" onClick={addRow}>
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button onClick={save} disabled={saving}>
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
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

                <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                    <style>{OPERASI_GRID_STYLES}</style>
                    <DataGrid
                        className="rdg-light"
                        style={{ blockSize: `${gridHeight}px` }}
                        columns={columns}
                        rows={rows}
                        rowKeyGetter={(row) => row._key}
                        onRowsChange={(next) => {
                            setRows(next);
                            setDirty(true);
                        }}
                        onSelectedCellChange={onSelectedCellChange}
                    />
                </div>

                <div className="flex flex-col gap-1 rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                    <p className="font-medium text-foreground">Kode yang valid:</p>
                    {options.categories.length > 0 && (
                        <div>
                            <span className="font-medium">Kategori:</span>{' '}
                            {options.categories.map((c) => c.code).join(', ')}
                        </div>
                    )}
                    <div>
                        <span className="font-medium">Status:</span>{' '}
                        {options.statuses.map((s) => s.value).join(', ')}
                    </div>
                    <p className="pt-1">Kode/mesin yang tak dikenali akan dikosongkan saat simpan.</p>
                </div>
            </div>
        </>
    );
}

ServiceRequestInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Service Request', href: serviceRequest.index() },
    ],
};
