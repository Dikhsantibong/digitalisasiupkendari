import { Head, router } from '@inertiajs/react';
import { ClipboardList, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type {
    Column,
    ColumnOrColumnGroup,
    RenderEditCellProps,
} from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { toast } from 'sonner';
import { MobileRecordList } from '@/components/mobile/record-list';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
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

type GridRow = RowData & {
    _key: number;
    [key: string]: number | string | null;
};

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

const NONE_VALUE = '__none__';

const hydrate = (rows: RowData[]): GridRow[] =>
    rows.map((row, index) => ({ ...row, _key: index }));

/**
 * A multi-line editor for the description column when pasting or editing directly.
 */
function MultilineEditor({
    row,
    column,
    onRowChange,
    onClose,
}: RenderEditCellProps<GridRow>) {
    const value = (row[column.key] as string | null) ?? '';

    return (
        <textarea
            className="rdg-textarea-editor"
            autoFocus
            rows={4}
            value={value}
            onChange={(event) =>
                onRowChange({ ...row, [column.key]: event.target.value })
            }
            onBlur={() => onClose(true)}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    if (event.ctrlKey || event.metaKey) {
                        onClose(true);

                        return;
                    }

                    event.stopPropagation();
                }
            }}
        />
    );
}

export default function ServiceRequestInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<GridRow[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(Math.max(initialRows.length, 1));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const compact = useCompactLayout();
    const [editingRowKey, setEditingRowKey] = useState<number | null>(null);
    const [formData, setFormData] = useState({
        sr_number: '',
        description: '',
        category_code: '',
        status: 'open',
        engine_name: '',
    });

    const emptyColFilters = {
        category_code: 'all',
        status: 'all',
    };
    const [colFilters, setColFilters] = useState(emptyColFilters);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(Math.max(initialRows.length, 1));
        setColFilters(emptyColFilters);
        setDirty(false);
    }

    const displayRows = useMemo(
        () =>
            rows.filter(
                (row) =>
                    (colFilters.category_code === 'all' ||
                        row.category_code === colFilters.category_code) &&
                    (colFilters.status === 'all' ||
                        row.status === colFilters.status),
            ),
        [rows, colFilters],
    );

    const hasActiveFilter = Object.values(colFilters).some((v) => v !== 'all');

    // Available machine list including any existing custom value in form
    const machineOptions = useMemo(() => {
        const list = [...options.machines];

        if (
            formData.engine_name &&
            !list.some(
                (m) =>
                    m.name.toLowerCase() === formData.engine_name.toLowerCase(),
            )
        ) {
            list.unshift({ id: -1, name: formData.engine_name });
        }

        return list;
    }, [options.machines, formData.engine_name]);

    // Available category list including any custom value in form
    const categoryOptions = useMemo(() => {
        const list = [...options.categories];

        if (
            formData.category_code &&
            !list.some(
                (c) =>
                    c.code.toLowerCase() ===
                    formData.category_code.toLowerCase(),
            )
        ) {
            list.unshift({
                code: formData.category_code,
                name: formData.category_code,
            });
        }

        return list;
    }, [options.categories, formData.category_code]);

    const payload = (row: GridRow): RowData => ({
        sr_number: row.sr_number,
        description: row.description,
        category_code: row.category_code,
        status: row.status,
        engine_name: row.engine_name,
    });

    const saveRows = (
        rowsToSave: GridRow[],
        successMessage = 'Data berhasil disimpan',
    ) => {
        setSaving(true);
        router.post(
            serviceRequest.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rowsToSave.map(payload),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success(successMessage);
                },
                onError: () => {
                    toast.error('Gagal menyimpan ke server');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const openCreateModal = () => {
        setEditingRowKey(null);
        setFormData({
            sr_number: '',
            description: '',
            category_code: options.categories[0]?.code ?? '',
            status: 'open',
            engine_name: '',
        });
        setModalOpen(true);
    };

    const openEditModal = (row: GridRow) => {
        setEditingRowKey(row._key);
        setFormData({
            sr_number: row.sr_number ?? '',
            description: row.description ?? '',
            category_code: row.category_code ?? '',
            status: row.status ?? 'open',
            engine_name: row.engine_name ?? '',
        });
        setModalOpen(true);
    };

    const handleModalSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const srTrimmed = formData.sr_number.trim();

        if (!srTrimmed) {
            toast.error('Nomor SR wajib diisi');

            return;
        }

        // Check duplicate sr_number (except when editing the same row)
        const isDuplicate = rows.some(
            (r) =>
                r.sr_number?.toLowerCase() === srTrimmed.toLowerCase() &&
                r._key !== editingRowKey,
        );

        if (isDuplicate) {
            toast.error(`Nomor SR "${srTrimmed}" sudah ada dalam daftar.`);

            return;
        }

        let nextRows: GridRow[];
        const isEdit = editingRowKey !== null;

        if (isEdit) {
            nextRows = rows.map((r) =>
                r._key === editingRowKey
                    ? {
                          ...r,
                          sr_number: srTrimmed,
                          description: formData.description.trim() || null,
                          category_code: formData.category_code || null,
                          status: formData.status || 'open',
                          engine_name: formData.engine_name || null,
                      }
                    : r,
            );
        } else {
            const newRow: GridRow = {
                _key: nextKey,
                sr_number: srTrimmed,
                description: formData.description.trim() || null,
                category_code: formData.category_code || null,
                status: formData.status || 'open',
                engine_name: formData.engine_name || null,
            };
            setNextKey((k) => k + 1);
            nextRows = [...rows, newRow];
        }

        setRows(nextRows);
        setModalOpen(false);

        // Auto-save directly to backend
        saveRows(
            nextRows,
            isEdit
                ? 'Service Request berhasil diperbarui'
                : 'Service Request berhasil ditambahkan',
        );
    };

    const handleDeleteRow = (row: GridRow) => {
        const label = row.sr_number ? `"${row.sr_number}"` : 'baris ini';

        if (
            !window.confirm(
                `Apakah Anda yakin ingin menghapus Service Request ${label}?`,
            )
        ) {
            return;
        }

        const nextRows = rows.filter((r) => r._key !== row._key);
        setRows(nextRows);
        saveRows(nextRows, 'Service Request berhasil dihapus');
    };

    // Grid edits/paste operate on the (possibly filtered) view; merge results
    // back into the full row set by their stable _key.
    const mergeBack = useCallback((nextDisplay: GridRow[]) => {
        setRows((current) => {
            const patched = new Map(nextDisplay.map((row) => [row._key, row]));

            return current.map((row) => patched.get(row._key) ?? row);
        });
        setDirty(true);
    }, []);

    const ROW_HEIGHT = 56;
    const gridHeight = Math.max(
        Math.min(displayRows.length * ROW_HEIGHT + 44, 680),
        450,
    );

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            serviceRequest.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'sr_number',
                name: 'No. SR',
                width: 170,
                minWidth: 130,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="font-semibold text-foreground">
                        {row.sr_number || '—'}
                    </span>
                ),
            },
            {
                key: 'description',
                name: 'Deskripsi',
                width: 'minmax(280px, 2fr)',
                minWidth: 220,
                resizable: true,
                editable: can_write,
                renderEditCell: MultilineEditor,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'rdg-wrap-cell',
                renderCell: ({ row }) => (
                    <div
                        className="py-1 text-xs leading-snug break-words whitespace-pre-wrap"
                        title={row.description ?? ''}
                    >
                        {row.description || (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                ),
            },
            {
                key: 'category_code',
                name: 'Kategori',
                width: 140,
                minWidth: 110,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => {
                    if (!row.category_code) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    return (
                        <Badge
                            variant="outline"
                            className="bg-muted/60 text-xs font-medium"
                        >
                            {row.category_code}
                        </Badge>
                    );
                },
            },
            {
                key: 'status',
                name: 'Status',
                width: 120,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => {
                    const val = row.status?.toLowerCase();
                    const isClosed = val === 'close' || val === 'closed';

                    return (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                isClosed
                                    ? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400',
                            )}
                        >
                            <span
                                className={cn(
                                    'mr-1.5 size-1.5 rounded-full',
                                    isClosed
                                        ? 'bg-slate-400'
                                        : 'bg-emerald-500',
                                )}
                            />
                            {row.status
                                ? row.status.charAt(0).toUpperCase() +
                                  row.status.slice(1)
                                : 'Open'}
                        </span>
                    );
                },
            },
            {
                key: 'engine_name',
                name: 'Mesin',
                width: 'minmax(160px, 1fr)',
                minWidth: 140,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span>
                        {row.engine_name || (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </span>
                ),
            },
        ];

        if (can_write) {
            cols.push({
                key: 'actions',
                name: 'Aksi',
                width: 86,
                minWidth: 86,
                resizable: false,
                headerCellClass: 'rdg-sub-header text-center',
                cellClass: 'text-center',
                renderCell: ({ row }) => (
                    <div className="flex items-center justify-center gap-1">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                openEditModal(row);
                            }}
                            title="Edit Service Request"
                            aria-label="Edit baris"
                            className="rounded p-1 text-primary transition-colors hover:bg-primary/10"
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                handleDeleteRow(row);
                            }}
                            title="Hapus Service Request"
                            aria-label="Hapus baris"
                            className="rounded p-1 text-destructive transition-colors hover:bg-destructive/10"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    </div>
                ),
            });
        }

        return cols;
    }, [can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows: displayRows,
        onChange: mergeBack,
    });

    return (
        <>
            <Head title="Input HAR — Service Request" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Service Request"
                    description="Kelola daftar Service Request per unit & periode. Gunakan tombol Create untuk menambah data baru atau klik ikon edit/dobel klik baris untuk mengubah."
                    actions={
                        can_write && (
                            <div className="flex items-center gap-2">
                                <Button
                                    onClick={openCreateModal}
                                    className="gap-1.5 shadow-xs"
                                >
                                    <Plus className="size-4" />
                                    Create Service Request
                                </Button>
                                {dirty && (
                                    <Button
                                        onClick={() =>
                                            saveRows(
                                                rows,
                                                'Perubahan berhasil disimpan',
                                            )
                                        }
                                        disabled={saving}
                                        variant="secondary"
                                        className="gap-1.5"
                                    >
                                        <Save className="size-4" />
                                        {saving
                                            ? 'Menyimpan…'
                                            : 'Simpan Perubahan'}
                                    </Button>
                                )}
                            </div>
                        )
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

                    <div className="mx-1 hidden h-9 w-px self-center bg-border lg:block" />

                    <OperasiSelect
                        label="Filter Kategori"
                        value={colFilters.category_code}
                        onChange={(value) =>
                            setColFilters((f) => ({
                                ...f,
                                category_code: value,
                            }))
                        }
                        options={[
                            { value: 'all', label: 'Semua' },
                            ...options.categories.map((c) => ({
                                value: c.code,
                                label: c.code,
                            })),
                        ]}
                    />
                    <OperasiSelect
                        label="Filter Status"
                        value={colFilters.status}
                        onChange={(value) =>
                            setColFilters((f) => ({ ...f, status: value }))
                        }
                        options={[
                            { value: 'all', label: 'Semua' },
                            ...options.statuses,
                        ]}
                    />

                    {hasActiveFilter && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="self-end text-xs text-muted-foreground"
                            onClick={() => setColFilters(emptyColFilters)}
                        >
                            Reset Filter ({displayRows.length}/{rows.length})
                        </Button>
                    )}
                    {dirty && !saving && (
                        <span className="self-end pb-1 text-[13px] text-amber-600">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {displayRows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card/60 p-12 text-center">
                        <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <ClipboardList className="size-6" />
                        </div>
                        <h3 className="text-base font-semibold text-foreground">
                            {hasActiveFilter
                                ? 'Tidak Ada Data yang Cocok'
                                : 'Belum Ada Service Request'}
                        </h3>
                        <p className="mt-1 max-w-md text-sm text-muted-foreground">
                            {hasActiveFilter
                                ? 'Tidak ada Service Request yang sesuai dengan filter kategori atau status yang dipilih.'
                                : 'Belum ada Service Request yang tercatat untuk unit dan periode ini. Silakan buat Service Request baru.'}
                        </p>
                        {can_write && (
                            <div className="mt-4 flex gap-2">
                                {hasActiveFilter ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setColFilters(emptyColFilters)
                                        }
                                    >
                                        Reset Filter
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        onClick={openCreateModal}
                                        className="gap-2"
                                    >
                                        <Plus className="size-4" />
                                        Create Service Request
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                ) : compact ? (
                    <MobileRecordList
                        records={displayRows.map((row) => ({
                            key: row._key,
                            title: row.sr_number || 'SR tanpa nomor',
                            badge: row.status ? (
                                <StatusBadge tone="info">
                                    {row.status}
                                </StatusBadge>
                            ) : undefined,
                            meta: [
                                ['Uraian', row.description],
                                ['Mesin', row.engine_name],
                                ['Kategori', row.category_code],
                            ],
                            onClick: can_write
                                ? () => openEditModal(row)
                                : undefined,
                            actions: can_write ? (
                                <>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => openEditModal(row)}
                                    >
                                        Ubah
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() => handleDeleteRow(row)}
                                    >
                                        Hapus
                                    </Button>
                                </>
                            ) : undefined,
                        }))}
                    />
                ) : (
                    <div
                        className="operasi-grid w-full overflow-hidden rounded-md border border-border"
                        onPaste={onPaste}
                    >
                        <style>{OPERASI_GRID_STYLES}</style>
                        <style>{`
                            .operasi-grid, .operasi-grid .rdg {
                                width: 100% !important;
                            }
                            .rdg-textarea-editor {
                                position: absolute;
                                inset: 0;
                                z-index: 5;
                                width: 100%;
                                min-height: 100%;
                                height: 6.5rem;
                                resize: vertical;
                                padding: 4px 6px;
                                font: inherit;
                                line-height: 1.35;
                                color: inherit;
                                background: var(--popover, #fff);
                                border: 2px solid var(--ring, #6366f1);
                                border-radius: 4px;
                                box-shadow: 0 6px 18px rgb(0 0 0 / 0.18);
                                outline: none;
                            }
                            .rdg-wrap-cell {
                                align-content: start;
                            }
                        `}</style>
                        <DataGrid
                            className="rdg-light w-full"
                            style={{ blockSize: `${gridHeight}px` }}
                            rowHeight={ROW_HEIGHT}
                            columns={columns}
                            rows={displayRows}
                            rowKeyGetter={(row) => row._key}
                            onRowsChange={(next) => mergeBack(next)}
                            onSelectedCellChange={onSelectedCellChange}
                            onCellDoubleClick={({ row }) => {
                                if (can_write) {
                                    openEditModal(row);
                                }
                            }}
                            defaultColumnOptions={{ resizable: true }}
                        />
                    </div>
                )}

                <div className="flex flex-col gap-1 rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                    <p className="font-medium text-foreground">Informasi:</p>
                    {options.categories.length > 0 && (
                        <div>
                            <span className="font-medium">
                                Kategori tersedia:
                            </span>{' '}
                            {options.categories.map((c) => c.code).join(', ')}
                        </div>
                    )}
                    <div>
                        <span className="font-medium">Status:</span>{' '}
                        {options.statuses.map((s) => s.label).join(', ')}
                    </div>
                    <p className="pt-1">
                        Tip: Klik dua kali pada baris tabel atau klik ikon
                        pensil untuk mengubah data.
                    </p>
                </div>
            </div>

            {/* Modal Dialog Form Create / Edit Service Request */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRowKey !== null
                                ? 'Edit Service Request'
                                : 'Create Service Request'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingRowKey !== null
                                ? 'Perbarui informasi Service Request di bawah ini.'
                                : 'Isi formulir berikut untuk membuat Service Request baru.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleModalSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="sr_number">
                                No. SR{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="sr_number"
                                placeholder="Contoh: SR-2026-001"
                                value={formData.sr_number}
                                onChange={(e) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        sr_number: e.target.value,
                                    }))
                                }
                                required
                                autoFocus
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="engine_name">
                                Mesin / Peralatan
                            </Label>
                            {machineOptions.length > 0 ? (
                                <Select
                                    value={formData.engine_name || NONE_VALUE}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            engine_name:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="engine_name">
                                        <SelectValue placeholder="Pilih Mesin (Opsional)" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa Mesin / Umum —
                                        </SelectItem>
                                        {machineOptions.map((m) => (
                                            <SelectItem
                                                key={m.id}
                                                value={m.name}
                                            >
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id="engine_name"
                                    placeholder="Nama mesin (opsional)"
                                    value={formData.engine_name}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            engine_name: e.target.value,
                                        }))
                                    }
                                />
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="category_code">Kategori</Label>
                                <Select
                                    value={formData.category_code || NONE_VALUE}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            category_code:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="category_code">
                                        <SelectValue placeholder="Pilih Kategori" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa Kategori —
                                        </SelectItem>
                                        {categoryOptions.map((cat) => (
                                            <SelectItem
                                                key={cat.code}
                                                value={cat.code}
                                            >
                                                {cat.code}{' '}
                                                {cat.name &&
                                                cat.name !== cat.code
                                                    ? `— ${cat.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    value={formData.status || 'open'}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            status: val,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue placeholder="Pilih Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.statuses.map((st) => (
                                            <SelectItem
                                                key={st.value}
                                                value={st.value}
                                            >
                                                {st.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="description">
                                Deskripsi Pekerjaan / Kendala
                            </Label>
                            <textarea
                                id="description"
                                rows={3}
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Tuliskan deskripsi kendala, temuan, atau perbaikan..."
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        description: e.target.value,
                                    }))
                                }
                            />
                        </div>

                        <DialogFooter className="gap-2 pt-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving
                                    ? 'Menyimpan…'
                                    : editingRowKey !== null
                                      ? 'Perbarui'
                                      : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ServiceRequestInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Service Request', href: serviceRequest.index() },
    ],
};
