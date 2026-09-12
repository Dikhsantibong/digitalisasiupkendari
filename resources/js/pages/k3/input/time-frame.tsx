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
import timeFrame from '@/routes/k3/input/time-frame';
import type { IdName } from '@/types';

type GridRow = {
    activity_type_id: number;
    activity_name: string;
    pic: string | null;
} & Record<string, number | string | null>;

type Props = {
    filters: { unit_id: number; month: number; year: number; plan_type: string };
    days: number;
    rows: GridRow[];
    options: {
        units: IdName[];
        years: number[];
        plan_types: { value: string; label: string }[];
    };
    can_write: boolean;
};

export default function TimeFrameInput({ filters, days, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${filters.plan_type}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            timeFrame.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'activity_name',
                name: 'Kegiatan',
                width: 220,
                frozen: true,
                headerCellClass: 'rdg-group-header',
                cellClass: 'font-medium',
            },
            {
                key: 'pic',
                name: 'PIC',
                width: 120,
                frozen: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
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

            return { activity_type_id: row.activity_type_id, pic: row.pic, days: daysMap };
        });
        router.post(
            timeFrame.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                plan_type: filters.plan_type,
                rows: payload,
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input K3 — Time Frame" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Time Frame Kinerja K3"
                    description="Matriks rencana vs realisasi kegiatan K3 per hari. Isi tanda (mis. X / ✓) pada tanggal terkait; paste dari Excel didukung."
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
                        Belum ada jenis kegiatan K3. Tambahkan lewat Master K3 &amp; Keamanan.
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: '60vh' }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row.activity_type_id}
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

TimeFrameInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Time Frame K3', href: timeFrame.index() },
    ],
};
