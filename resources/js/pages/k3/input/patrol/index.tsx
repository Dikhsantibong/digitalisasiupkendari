import { Head, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column, ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import {
    K3InputExportButtons,
    K3MobilePdfButton,
} from '@/components/k3/input-export-buttons';
import {
    K3MobileFilterCard,
    K3MobileHeader,
} from '@/components/k3/mobile-page-shell';
import { MobileEntryCard, MobileFormScreen } from '@/components/mobile/entry-flow';
import { MobileMatrixForm } from '@/components/mobile/matrix-form';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
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

export default function PatrolInput({
    filters,
    days,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<GridRow[]>(initialRows);
    const compact = useCompactLayout();
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    // Phone (user, 2026-11-27): one location's month on its own screen.
    const [openLocation, setOpenLocation] = useState<number | null>(null);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 53, 620);

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

    const save = (onSaved?: () => void) => {
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
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: payload,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    onSaved?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    if (compact) {
        return (
            <>
                <Head title="Input K3 — Patroli Keamanan" />
                <div
                    className={`flex flex-1 flex-col gap-3 p-3 ${can_write ? 'pb-24' : 'pb-20'}`}
                >
                    <K3MobileHeader
                        title="Patroli Keamanan"
                        backHref={k3Input.index().url}
                    />

                    <K3MobileFilterCard
                        unitId={filters.unit_id}
                        units={options.units}
                        onUnitChange={(v) => visit({ unit_id: v })}
                        month={filters.month}
                        onMonthChange={(v) => visit({ month: v })}
                        year={filters.year}
                        years={options.years}
                        onYearChange={(v) => visit({ year: v })}
                    />

                    {rows.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                            Belum ada lokasi patroli pada master.
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {rows.map((row) => (
                                <MobileEntryCard
                                    key={row.location_id}
                                    title={row.location_name}
                                    subtitle={`Total bulan ini: ${rowTotal(row, days)}`}
                                    onClick={() => setOpenLocation(row.location_id)}
                                />
                            ))}
                        </div>
                    )}

                    {openLocation !== null && (
                        <MobileFormScreen
                            title={rows.find((row) => row.location_id === openLocation)?.location_name ?? 'Lokasi'}
                            subtitle="Patroli Keamanan"
                            onBack={() => setOpenLocation(null)}
                            canWrite={can_write}
                            onSave={() => save(() => setOpenLocation(null))}
                            saving={saving}
                            saveDisabled={!dirty}
                        >
                            <MobileMatrixForm<GridRow>
                                columns={Array.from({ length: days }, (_, i) => ({
                                    key: `day_${i + 1}`,
                                    label: String(i + 1),
                                }))}
                                rows={rows.filter((row) => row.location_id === openLocation)}
                                rowKey={(row) => row.location_id}
                                rowLabel={(row) => row.location_name}
                                rowSub={(row) =>
                                    `Total bulan ini: ${rowTotal(row, days)}`
                                }
                                value={(row, column) =>
                                    row[column] === null ||
                                    row[column] === undefined
                                        ? ''
                                        : String(row[column])
                                }
                                onChange={(index, column, value) => {
                                    setRows((current) =>
                                        current.map((row) =>
                                            row.location_id === openLocation
                                                ? { ...row, [column]: value }
                                                : row,
                                        ),
                                    );
                                    setDirty(true);
                                }}
                                readOnly={!can_write}
                            />
                        </MobileFormScreen>
                    )}

                    <StickyActionBar>
                        <K3MobilePdfButton
                            input="patrols"
                            query={{
                                unit_id: filters.unit_id,
                                month: filters.month,
                                year: filters.year,
                            }}
                        />
                        {can_write && (
                            <Button
                                onClick={() => save()}
                                disabled={saving || rows.length === 0}
                                className="gap-1.5"
                            >
                                <Save className="size-4" />
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        )}
                    </StickyActionBar>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Input K3 — Patroli Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Patroli Keamanan"
                    description="Matriks lokasi patroli (POA…) × tanggal. Isi jumlah scan per hari; total kumulatif per lokasi dihitung otomatis. Paste dari Excel didukung."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <K3InputExportButtons
                                input="patrols"
                                query={{
                                    unit_id: filters.unit_id,
                                    month: filters.month,
                                    year: filters.year,
                                }}
                            />
                            {can_write && (
                                <Button
                                    onClick={() => save()}
                                    disabled={saving || rows.length === 0}
                                >
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
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
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada lokasi patroli. Tambahkan lewat Master K3
                        &amp; Keamanan → Lokasi Patroli.
                    </div>
                ) : compact ? (
                    <MobileMatrixForm<GridRow>
                        columns={Array.from({ length: days }, (_, i) => ({
                            key: `day_${i + 1}`,
                            label: String(i + 1),
                        }))}
                        rows={rows}
                        rowKey={(row) => row.location_id}
                        rowLabel={(row) => row.location_name}
                        rowSub={(row) =>
                            `Total bulan ini: ${rowTotal(row, days)}`
                        }
                        value={(row, column) =>
                            row[column] === null || row[column] === undefined
                                ? ''
                                : String(row[column])
                        }
                        onChange={(index, column, value) => {
                            setRows((current) =>
                                current.map((row, i) =>
                                    i === index
                                        ? { ...row, [column]: value }
                                        : row,
                                ),
                            );
                            setDirty(true);
                        }}
                        readOnly={!can_write}
                    />
                ) : (
                    <div
                        className="operasi-grid overflow-hidden rounded-md border border-border"
                        onPaste={onPaste}
                    >
                        <style>{OPERASI_GRID_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: `${gridHeight}px` }}
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
