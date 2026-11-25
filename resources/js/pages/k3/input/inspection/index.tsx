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
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import inspection from '@/routes/k3/input/inspection';
import type { IdName } from '@/types';

type RowData = {
    item_ref: string;
    kondisi: string | null;
    tindak_lanjut: string | null;
    nilai: string | null;
    catatan: string | null;
};

type GridRow = RowData & {
    _key: number;
    [key: string]: number | string | null;
};

type Header = {
    inspection_date: string | null;
    inspector_team: string | null;
    ketua_tim: string | null;
    keterangan: string | null;
};

type Props = {
    filters: {
        unit_id: number;
        month: number;
        year: number;
        form_code: string;
    };
    header: Header;
    rows: RowData[];
    options: { units: IdName[]; years: number[]; form_codes: string[] };
    can_write: boolean;
};

export default function InspectionInput({
    filters,
    header: initialHeader,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const hydrate = (rows: RowData[]): GridRow[] =>
        rows.map((row, index) => ({ ...row, _key: index }));

    const [rows, setRows] = useState<GridRow[]>(() => hydrate(initialRows));
    const compact = useCompactLayout();
    const [header, setHeader] = useState<Header>(initialHeader);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${filters.form_code}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setHeader(initialHeader);
        setDirty(false);
    }

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 520);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            inspection.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const setHeaderField = (key: keyof Header, value: string) => {
        setHeader((current) => ({ ...current, [key]: value }));
        setDirty(true);
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (
            key: keyof RowData,
            name: string,
            width = 150,
        ): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
        });

        return [
            {
                key: 'item_ref',
                name: 'Item Pemeriksaan',
                minWidth: 260,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'font-medium',
            },
            editable('kondisi', 'Kondisi', 120),
            editable('tindak_lanjut', 'Tindak Lanjut', 180),
            editable('nilai', 'Nilai', 90),
            editable('catatan', 'Catatan', 180),
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
            inspection.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                form_code: filters.form_code,
                header,
                rows: rows.map(
                    ({ item_ref, kondisi, tindak_lanjut, nilai, catatan }) => ({
                        item_ref,
                        kondisi,
                        tindak_lanjut,
                        nilai,
                        catatan,
                    }),
                ),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const mobileFields: RowField<GridRow>[] = [
        {
            key: 'kondisi',
            label: 'Kondisi',
            type: 'dropdown',
            options: ['Baik', 'Tidak Baik'],
            placeholder: 'Pilih Kondisi',
            span: 1,
        },
        {
            key: 'nilai',
            label: 'Nilai / Skor',
            placeholder: 'Contoh: 100%',
            span: 1,
        },
        { key: 'tindak_lanjut', label: 'Tindak Lanjut', span: 2 },
        { key: 'catatan', label: 'Catatan', type: 'textarea', span: 2 },
    ];

    if (compact) {
        return (
            <>
                <Head title="Input K3 — Inspeksi" />
                <div
                    className={`flex flex-1 flex-col gap-3 p-3 ${can_write ? 'pb-24' : 'pb-20'}`}
                >
                    <K3MobileHeader
                        title="Inspeksi Checklist"
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
                        extra={
                            options.form_codes.length > 0 ? (
                                <OperasiSelect
                                    label="Form"
                                    value={filters.form_code}
                                    onChange={(value) =>
                                        visit({ form_code: value })
                                    }
                                    options={options.form_codes.map((code) => ({
                                        value: code,
                                        label: code,
                                    }))}
                                    className="w-full"
                                />
                            ) : undefined
                        }
                    />

                    {options.form_codes.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                            Belum ada item checklist. Tambahkan lewat Master K3
                            → Item Checklist Inspeksi.
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-2 gap-2.5 rounded-xl border border-border bg-card p-3">
                                <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                    Tanggal Inspeksi
                                    <Input
                                        type="date"
                                        value={header.inspection_date ?? ''}
                                        onChange={(e) =>
                                            setHeaderField(
                                                'inspection_date',
                                                e.target.value,
                                            )
                                        }
                                        disabled={!can_write}
                                        className="h-9 text-xs"
                                    />
                                </label>
                                <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                    Ketua Tim
                                    <Input
                                        value={header.ketua_tim ?? ''}
                                        onChange={(e) =>
                                            setHeaderField(
                                                'ketua_tim',
                                                e.target.value,
                                            )
                                        }
                                        disabled={!can_write}
                                        className="h-9 text-xs"
                                    />
                                </label>
                                <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                    Tim Inspeksi
                                    <Input
                                        value={header.inspector_team ?? ''}
                                        onChange={(e) =>
                                            setHeaderField(
                                                'inspector_team',
                                                e.target.value,
                                            )
                                        }
                                        disabled={!can_write}
                                        className="h-9 text-xs"
                                    />
                                </label>
                                <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                    Keterangan
                                    <Input
                                        value={header.keterangan ?? ''}
                                        onChange={(e) =>
                                            setHeaderField(
                                                'keterangan',
                                                e.target.value,
                                            )
                                        }
                                        disabled={!can_write}
                                        className="h-9 text-xs"
                                    />
                                </label>
                            </div>

                            <MobileRowEditor<GridRow>
                                rows={rows}
                                rowKey={(row) => row._key}
                                title={(row) => row.item_ref}
                                subtitle={(row) =>
                                    `${row.kondisi || 'Belum diisi'}${row.nilai ? ` · Nilai ${row.nilai}` : ''}`
                                }
                                canWrite={can_write}
                                fields={mobileFields}
                                onChange={(index, key, value) => {
                                    setRows((current) =>
                                        current.map((row, i) =>
                                            i === index
                                                ? {
                                                      ...row,
                                                      [key]: String(
                                                          value ?? '',
                                                      ),
                                                  }
                                                : row,
                                        ),
                                    );
                                    setDirty(true);
                                }}
                            />
                        </>
                    )}

                    <StickyActionBar>
                        <K3MobilePdfButton
                            input="inspections"
                            query={{
                                unit_id: filters.unit_id,
                                month: filters.month,
                                year: filters.year,
                                form_code: filters.form_code,
                            }}
                        />
                        {can_write && (
                            <Button
                                onClick={save}
                                disabled={saving || filters.form_code === ''}
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
            <Head title="Input K3 — Inspeksi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Inspeksi Checklist"
                    description="Checklist inspeksi per form (tempat kerja, rambu, fire alarm, dll.). Item diambil dari Master K3; isi kondisi & tindak lanjut."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <K3InputExportButtons
                                input="inspections"
                                query={{
                                    unit_id: filters.unit_id,
                                    month: filters.month,
                                    year: filters.year,
                                    form_code: filters.form_code,
                                }}
                            />
                            {can_write && (
                                <Button
                                    onClick={save}
                                    disabled={
                                        saving || filters.form_code === ''
                                    }
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
                    {options.form_codes.length > 0 && (
                        <OperasiSelect
                            label="Form"
                            value={filters.form_code}
                            onChange={(value) => visit({ form_code: value })}
                            options={options.form_codes.map((code) => ({
                                value: code,
                                label: code,
                            }))}
                        />
                    )}
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {options.form_codes.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada item checklist. Tambahkan lewat Master K3
                        &amp; Keamanan → Item Checklist Inspeksi.
                    </div>
                ) : (
                    <>
                        <div className="grid gap-3 rounded-md border border-border bg-card p-3 md:grid-cols-4">
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Tanggal Inspeksi
                                </span>
                                <Input
                                    type="date"
                                    value={header.inspection_date ?? ''}
                                    onChange={(e) =>
                                        setHeaderField(
                                            'inspection_date',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!can_write}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Tim Inspeksi
                                </span>
                                <Input
                                    value={header.inspector_team ?? ''}
                                    onChange={(e) =>
                                        setHeaderField(
                                            'inspector_team',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!can_write}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Ketua Tim
                                </span>
                                <Input
                                    value={header.ketua_tim ?? ''}
                                    onChange={(e) =>
                                        setHeaderField(
                                            'ketua_tim',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!can_write}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Keterangan
                                </span>
                                <Input
                                    value={header.keterangan ?? ''}
                                    onChange={(e) =>
                                        setHeaderField(
                                            'keterangan',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!can_write}
                                />
                            </label>
                        </div>

                        {compact ? (
                            <MobileRowEditor<GridRow>
                                rows={rows}
                                rowKey={(row) => row._key}
                                title={(row) => row.item_ref}
                                subtitle={(row) =>
                                    `${row.kondisi || 'Belum diisi'}${row.nilai ? ` · Nilai ${row.nilai}` : ''}`
                                }
                                canWrite={can_write}
                                fields={mobileFields}
                                onChange={(index, key, value) => {
                                    setRows((current) =>
                                        current.map((row, i) =>
                                            i === index
                                                ? {
                                                      ...row,
                                                      [key]: String(
                                                          value ?? '',
                                                      ),
                                                  }
                                                : row,
                                        ),
                                    );
                                    setDirty(true);
                                }}
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
                                    rowKeyGetter={(row) => row._key}
                                    onRowsChange={(next) => {
                                        setRows(next);
                                        setDirty(true);
                                    }}
                                    onSelectedCellChange={onSelectedCellChange}
                                />
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

InspectionInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inspeksi K3', href: inspection.index() },
    ],
};
