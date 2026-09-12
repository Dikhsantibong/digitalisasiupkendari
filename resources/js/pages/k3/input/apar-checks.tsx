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
import aparCheck from '@/routes/k3/input/apar-check';
import type { IdName } from '@/types';

type RowData = {
    extinguisher_id: number;
    label: string;
    tgl_periksa: string | null;
    kondisi_tabung: string | null;
    kondisi_nozzle: string | null;
    indikator_tekanan: string | null;
    kondisi_pin_segel: string | null;
    exp_date: string | null;
    keterangan: string | null;
};

type GridRow = RowData & { [key: string]: number | string | null };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: RowData[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

export default function AparCheckInput({ filters, rows: initialRows, options, can_write }: Props) {
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

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 560);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            aparCheck.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof RowData, name: string, width = 130): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
        });

        return [
            { key: 'label', name: 'APAR/APAB', minWidth: 200, frozen: true, headerCellClass: 'rdg-group-header', cellClass: 'font-medium' },
            editable('tgl_periksa', 'Tgl Periksa', 110),
            editable('kondisi_tabung', 'Tabung', 110),
            editable('kondisi_nozzle', 'Nozzle', 110),
            editable('indikator_tekanan', 'Tekanan', 110),
            editable('kondisi_pin_segel', 'Pin/Segel', 110),
            editable('exp_date', 'Exp Date', 110),
            editable('keterangan', 'Keterangan', 180),
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
            aparCheck.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((row) => ({
                    extinguisher_id: row.extinguisher_id,
                    tgl_periksa: row.tgl_periksa || null,
                    kondisi_tabung: row.kondisi_tabung,
                    kondisi_nozzle: row.kondisi_nozzle,
                    indikator_tekanan: row.indikator_tekanan,
                    kondisi_pin_segel: row.kondisi_pin_segel,
                    exp_date: row.exp_date || null,
                    keterangan: row.keterangan,
                })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input K3 — Inspeksi APAR/APAB" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Inspeksi APAR/APAB"
                    description="Kondisi tiap tabung APAR/APAB per periode. Daftar tabung diambil dari Master K3. Paste dari Excel didukung."
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
                        Belum ada APAR/APAB. Tambahkan lewat Master K3 &amp; Keamanan → APAR/APAB.
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: `${gridHeight}px` }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row.extinguisher_id}
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

AparCheckInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inspeksi APAR/APAB', href: aparCheck.index() },
    ],
};
