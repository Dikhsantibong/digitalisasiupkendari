import { Head, router } from '@inertiajs/react';
import { Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column, ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import accident from '@/routes/k3/input/accident';
import type { IdName } from '@/types';

type RowData = {
    category: string | null;
    incident_date: string | null;
    fungsi: string | null;
    lokasi: string | null;
    luka_ringan: string | number | null;
    luka_berat: string | number | null;
    meninggal: string | number | null;
    kerugian_material: string | number | null;
    is_nihil: string | number | boolean | null;
    keterangan: string | null;
};

type GridRow = RowData & { _key: number; [key: string]: number | string | boolean | null };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: RowData[];
    options: {
        units: IdName[];
        years: number[];
        categories: { value: string; label: string }[];
    };
    can_write: boolean;
};

const asNihil = (v: RowData['is_nihil']): string =>
    v === true || v === 1 || v === '1' || v === 'ya' ? 'ya' : v ? String(v) : 'tidak';

const blankRow = (key: number, nihil = false): GridRow => ({
    _key: key,
    category: 'pak',
    incident_date: null,
    fungsi: null,
    lokasi: null,
    luka_ringan: 0,
    luka_berat: 0,
    meninggal: 0,
    kerugian_material: null,
    is_nihil: nihil ? 'ya' : 'tidak',
    keterangan: null,
});

const hydrate = (rows: RowData[]): GridRow[] =>
    rows.length
        ? rows.map((row, index) => ({ ...row, is_nihil: asNihil(row.is_nihil), _key: index }))
        : [blankRow(0)];

export default function AccidentInput({ filters, rows: initialRows, options, can_write }: Props) {
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

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 520);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            accident.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const removeRow = (key: number) => {
        setRows((current) => current.filter((row) => row._key !== key));
        setDirty(true);
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof RowData, name: string, width = 120): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
        });

        const cols: Column<GridRow>[] = [
            editable('category', 'Kategori', 110),
            editable('incident_date', 'Tanggal', 110),
            editable('fungsi', 'Fungsi', 130),
            editable('lokasi', 'Lokasi', 130),
            editable('luka_ringan', 'Luka Ringan', 90),
            editable('luka_berat', 'Luka Berat', 90),
            editable('meninggal', 'Meninggal', 90),
            editable('kerugian_material', 'Kerugian (Rp)', 120),
            editable('is_nihil', 'NIHIL (ya/tidak)', 110),
            {
                key: 'keterangan',
                name: 'Keterangan',
                minWidth: 200,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
            },
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

    const payload = (row: GridRow) => ({
        category: row.category,
        incident_date: row.incident_date || null,
        fungsi: row.fungsi,
        lokasi: row.lokasi,
        luka_ringan: Number(row.luka_ringan) || 0,
        luka_berat: Number(row.luka_berat) || 0,
        meninggal: Number(row.meninggal) || 0,
        kerugian_material: row.kerugian_material === '' || row.kerugian_material === null ? null : Number(row.kerugian_material),
        is_nihil: asNihil(row.is_nihil) === 'ya',
        keterangan: row.keterangan,
    });

    const persist = (nextRows: GridRow[]) => {
        setSaving(true);
        router.post(
            accident.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: nextRows.map(payload),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const markNihil = () => {
        const nihilRows = [blankRow(0, true)];
        setRows(nihilRows);
        setNextKey(1);
        persist(nihilRows);
    };

    return (
        <>
            <Head title="Input K3 — Laporan Kecelakaan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Kecelakaan (PAK/PAHK)"
                    description="Isi kejadian kecelakaan/penyakit akibat kerja per periode. Bila tidak ada kejadian, gunakan tombol Tandai NIHIL."
                    actions={
                        can_write && (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="secondary" onClick={markNihil} disabled={saving}>
                                    <ShieldCheck className="size-4" />
                                    Tandai NIHIL
                                </Button>
                                <Button variant="secondary" onClick={addRow}>
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button onClick={() => persist(rows)} disabled={saving}>
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

                <div className="rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                    <span className="font-medium">Kategori valid:</span>{' '}
                    {options.categories.map((c) => `${c.value} (${c.label})`).join(' · ')}
                </div>
            </div>
        </>
    );
}

AccidentInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Kecelakaan', href: accident.index() },
    ],
};
