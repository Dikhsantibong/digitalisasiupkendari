import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    Search,
    SlidersHorizontal,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type {
    Column,
    ColumnOrColumnGroup,
    RenderEditCellProps,
} from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { toast } from 'sonner';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
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
    const [searchQuery, setSearchQuery] = useState('');
    const [mobileFilterOpen, setMobileFilterOpen] = useState(false);
    const [mobilePage, setMobilePage] = useState(1);
    const MOBILE_PAGE_SIZE = 15;

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(Math.max(initialRows.length, 1));
        setColFilters(emptyColFilters);
        setSearchQuery('');
        setMobilePage(1);
        setDirty(false);
    }

    const displayRows = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();

        return rows.filter((row) => {
            if (
                (colFilters.category_code !== 'all' &&
                    row.category_code !== colFilters.category_code) ||
                (colFilters.status !== 'all' &&
                    row.status !== colFilters.status)
            ) {
                return false;
            }

            if (!q) {
                return true;
            }

            return [
                row.sr_number,
                row.description,
                row.engine_name,
                row.category_code,
                row.status,
            ].some((val) => (val ?? '').toLowerCase().includes(q));
        });
    }, [rows, colFilters, searchQuery]);

    const activeFilterCount = Object.values(colFilters).filter(
        (v) => v !== 'all',
    ).length;
    const hasActiveFilter =
        activeFilterCount > 0 || searchQuery.trim().length > 0;

    const totalMobilePages = Math.max(
        1,
        Math.ceil(displayRows.length / MOBILE_PAGE_SIZE),
    );
    const safeMobilePage = Math.min(mobilePage, totalMobilePages);
    const mobilePagedRows = useMemo(() => {
        const start = (safeMobilePage - 1) * MOBILE_PAGE_SIZE;

        return displayRows.slice(start, start + MOBILE_PAGE_SIZE);
    }, [displayRows, safeMobilePage]);

    const resetAllFilters = () => {
        setColFilters(emptyColFilters);
        setSearchQuery('');
        setMobilePage(1);
    };

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

    const renderStatusPill = (status: string | null) => {
        const val = status?.toLowerCase();
        const isClosed = val === 'close' || val === 'closed';
        const label = status
            ? status.charAt(0).toUpperCase() + status.slice(1)
            : 'Open';

        return (
            <span
                className={cn(
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                    isClosed
                        ? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400',
                )}
            >
                <span
                    className={cn(
                        'mr-1 size-1.5 rounded-full',
                        isClosed ? 'bg-slate-400' : 'bg-emerald-500',
                    )}
                />
                {label}
            </span>
        );
    };

    const statusTabs = useMemo(
        () => [
            { value: 'all', label: 'Semua SR', count: rows.length },
            ...options.statuses.map((st) => ({
                value: st.value,
                label: st.label,
                count: rows.filter(
                    (r) =>
                        (r.status ?? 'open').toLowerCase() ===
                        st.value.toLowerCase(),
                ).length,
            })),
        ],
        [rows, options.statuses],
    );

    return (
        <>
            <Head title="Input HAR — Service Request" />
            <div className="flex flex-1 flex-col gap-3 p-3 md:gap-4 md:p-6">
                {!compact && (
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
                )}

                {compact ? (
                    /* Mobile Compact Top Toolbar: Search + Collapsible Filter + Create Button */
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-1.5">
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) => {
                                        setSearchQuery(e.target.value);
                                        setMobilePage(1);
                                    }}
                                    placeholder="Cari No. SR, uraian, mesin…"
                                    className="h-9 pr-7 pl-8 text-xs"
                                />
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchQuery('');
                                            setMobilePage(1);
                                        }}
                                        aria-label="Bersihkan pencarian"
                                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>
                            <Button
                                type="button"
                                variant={
                                    mobileFilterOpen || activeFilterCount > 0
                                        ? 'secondary'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    setMobileFilterOpen((open) => !open)
                                }
                                className="h-9 shrink-0 gap-1 px-2.5 text-xs"
                            >
                                <SlidersHorizontal className="size-3.5" />
                                Filter
                                {activeFilterCount > 0 && (
                                    <span className="inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </Button>
                            {can_write && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={openCreateModal}
                                    className="h-9 shrink-0 gap-1 px-2.5 text-xs shadow-xs"
                                >
                                    <Plus className="size-3.5" />
                                    SR Baru
                                </Button>
                            )}
                            {can_write && dirty && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    disabled={saving}
                                    onClick={() =>
                                        saveRows(
                                            rows,
                                            'Perubahan berhasil disimpan',
                                        )
                                    }
                                    className="h-9 shrink-0 px-2.5 text-xs"
                                >
                                    <Save className="size-3.5" />
                                </Button>
                            )}
                        </div>

                        {/* Single-row horizontal scrollable Status / Category Quick Chips on Mobile */}
                        <div
                            className="-mx-1 flex [scrollbar-width:none] items-center gap-1.5 overflow-x-auto px-1 pb-0.5 [&::-webkit-scrollbar]:hidden"
                            role="tablist"
                            aria-label="Filter Cepat Service Request"
                        >
                            {statusTabs.map((item) => {
                                const active =
                                    colFilters.status === item.value &&
                                    (item.value !== 'all' ||
                                        colFilters.category_code === 'all');

                                return (
                                    <button
                                        key={`status-${item.value}`}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => {
                                            setColFilters((f) => ({
                                                ...f,
                                                status: item.value,
                                                ...(item.value === 'all'
                                                    ? { category_code: 'all' }
                                                    : {}),
                                            }));
                                            setMobilePage(1);
                                        }}
                                        className={cn(
                                            'flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium whitespace-nowrap transition-colors',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-card text-foreground hover:border-primary/50',
                                        )}
                                    >
                                        {item.label}
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 text-[10px] font-semibold',
                                                active
                                                    ? 'bg-primary-foreground/20'
                                                    : 'bg-muted',
                                            )}
                                        >
                                            {item.count}
                                        </span>
                                    </button>
                                );
                            })}
                            {options.categories.map((cat) => {
                                const count = rows.filter(
                                    (r) => r.category_code === cat.code,
                                ).length;
                                const active =
                                    colFilters.category_code === cat.code;

                                return (
                                    <button
                                        key={`cat-${cat.code}`}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => {
                                            setColFilters((f) => ({
                                                ...f,
                                                category_code: active
                                                    ? 'all'
                                                    : cat.code,
                                            }));
                                            setMobilePage(1);
                                        }}
                                        className={cn(
                                            'flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium whitespace-nowrap transition-colors',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-card text-foreground hover:border-primary/50',
                                        )}
                                    >
                                        {cat.code}
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 text-[10px] font-semibold',
                                                active
                                                    ? 'bg-primary-foreground/20'
                                                    : 'bg-muted',
                                            )}
                                        >
                                            {count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Collapsible Compact 2-Column Mobile Filter Drawer */}
                        {mobileFilterOpen && (
                            <div className="rounded-lg border border-border bg-card p-2.5 shadow-xs">
                                <div className="mb-2 flex items-center justify-between">
                                    <span className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        Filter Service Request
                                    </span>
                                    {activeFilterCount > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setColFilters(emptyColFilters);
                                                setMobilePage(1);
                                            }}
                                            className="inline-flex items-center gap-1 text-[11px] font-medium text-primary"
                                        >
                                            <RotateCcw className="size-3" />
                                            Reset
                                        </button>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Kategori
                                        </span>
                                        <Select
                                            value={colFilters.category_code}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    category_code: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Kategori
                                                </SelectItem>
                                                {options.categories.map((c) => (
                                                    <SelectItem
                                                        key={c.code}
                                                        value={c.code}
                                                    >
                                                        {c.code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Status
                                        </span>
                                        <Select
                                            value={colFilters.status}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    status: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Status
                                                </SelectItem>
                                                {options.statuses.map((s) => (
                                                    <SelectItem
                                                        key={s.value}
                                                        value={s.value}
                                                    >
                                                        {s.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Compact Mobile Status & Counter Bar */}
                        <div className="flex items-center justify-between px-0.5 text-[11px] text-muted-foreground">
                            <span>
                                Menampilkan{' '}
                                <strong className="text-foreground">
                                    {displayRows.length === 0
                                        ? 0
                                        : (safeMobilePage - 1) *
                                              MOBILE_PAGE_SIZE +
                                          1}
                                    –
                                    {Math.min(
                                        safeMobilePage * MOBILE_PAGE_SIZE,
                                        displayRows.length,
                                    )}
                                </strong>{' '}
                                dari{' '}
                                <strong className="text-foreground">
                                    {displayRows.length}
                                </strong>{' '}
                                SR
                                {displayRows.length !== rows.length &&
                                    ` (total ${rows.length})`}
                            </span>
                            {hasActiveFilter && (
                                <button
                                    type="button"
                                    onClick={resetAllFilters}
                                    className="font-medium text-primary hover:underline"
                                >
                                    Reset filter
                                </button>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(value) =>
                                visit({ unit_id: Number(value) })
                            }
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                        />
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            onChange={(value) =>
                                visit({ month: Number(value) })
                            }
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

                        <div className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">
                                Cari Cepat
                            </span>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                    placeholder="No. SR / uraian / mesin…"
                                    className="h-9 w-48 pr-7 pl-8 text-xs"
                                />
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery('')}
                                        className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>

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
                                onClick={resetAllFilters}
                            >
                                Reset Filter ({displayRows.length}/{rows.length}
                                )
                            </Button>
                        )}
                        {dirty && !saving && (
                            <span className="self-end pb-1 text-[13px] text-amber-600">
                                Ada perubahan belum disimpan.
                            </span>
                        )}
                    </div>
                )}

                {displayRows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card/60 p-8 text-center md:p-12">
                        <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <ClipboardList className="size-5" />
                        </div>
                        <h3 className="text-sm font-semibold text-foreground md:text-base">
                            {hasActiveFilter
                                ? 'Tidak Ada Data yang Cocok'
                                : 'Belum Ada Service Request'}
                        </h3>
                        <p className="mt-1 max-w-md text-xs text-muted-foreground md:text-sm">
                            {hasActiveFilter
                                ? 'Tidak ada Service Request yang sesuai dengan pencarian, filter kategori, atau status yang dipilih.'
                                : 'Belum ada Service Request yang tercatat untuk unit dan periode ini. Silakan buat Service Request baru.'}
                        </p>
                        {can_write && (
                            <div className="mt-4 flex gap-2">
                                {hasActiveFilter ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={resetAllFilters}
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
                    /* High-Density Mobile Card List + Pagination for Large Datasets */
                    <div className="flex flex-col gap-2">
                        <ul className="flex flex-col gap-2">
                            {mobilePagedRows.map((row) => (
                                <li
                                    key={row._key}
                                    className="flex flex-col gap-1.5 rounded-lg border border-border bg-card p-2.5 shadow-2xs transition-colors hover:border-primary/40"
                                >
                                    {/* Line 1: No. SR, Category Badge, Status Pill & Quick Action Icons */}
                                    <div className="flex items-center justify-between gap-1.5">
                                        <button
                                            type="button"
                                            onClick={
                                                can_write
                                                    ? () => openEditModal(row)
                                                    : undefined
                                            }
                                            className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 text-left"
                                        >
                                            <span className="font-mono text-xs font-bold text-foreground">
                                                {row.sr_number ||
                                                    'SR tanpa nomor'}
                                            </span>
                                            {row.category_code && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-5 bg-muted/60 px-1.5 text-[10px] font-semibold"
                                                >
                                                    {row.category_code}
                                                </Badge>
                                            )}
                                        </button>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {renderStatusPill(row.status)}
                                            {can_write && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditModal(row)
                                                        }
                                                        aria-label={`Ubah ${row.sr_number ?? 'Service Request'}`}
                                                        className="inline-flex size-7 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDeleteRow(row)
                                                        }
                                                        aria-label={`Hapus ${row.sr_number ?? 'Service Request'}`}
                                                        className="inline-flex size-7 items-center justify-center rounded-md text-destructive hover:bg-destructive/10"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {/* Line 2: Description */}
                                    {row.description && (
                                        <button
                                            type="button"
                                            onClick={
                                                can_write
                                                    ? () => openEditModal(row)
                                                    : undefined
                                            }
                                            className="line-clamp-2 text-left text-xs leading-snug text-foreground/90"
                                        >
                                            {row.description}
                                        </button>
                                    )}

                                    {/* Line 3: Compact Metadata Strip */}
                                    {row.engine_name && (
                                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                            <span className="inline-flex items-center gap-1 font-medium text-foreground/80">
                                                <Wrench className="size-3 text-muted-foreground" />
                                                {row.engine_name}
                                            </span>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {/* Mobile Pagination Controls when rows > MOBILE_PAGE_SIZE */}
                        {totalMobilePages > 1 && (
                            <div className="mt-1 flex items-center justify-between rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={safeMobilePage <= 1}
                                    onClick={() =>
                                        setMobilePage((p) => Math.max(1, p - 1))
                                    }
                                    className="h-7 gap-1 px-2 text-xs"
                                >
                                    <ChevronLeft className="size-3.5" />
                                    Sebelumnya
                                </Button>
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    Hal{' '}
                                    <strong className="text-foreground">
                                        {safeMobilePage}
                                    </strong>{' '}
                                    / {totalMobilePages}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={
                                        safeMobilePage >= totalMobilePages
                                    }
                                    onClick={() =>
                                        setMobilePage((p) =>
                                            Math.min(totalMobilePages, p + 1),
                                        )
                                    }
                                    className="h-7 gap-1 px-2 text-xs"
                                >
                                    Berikutnya
                                    <ChevronRight className="size-3.5" />
                                </Button>
                            </div>
                        )}
                    </div>
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

                {!compact && (
                    <div className="flex flex-col gap-1 rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                        <p className="font-medium text-foreground">
                            Informasi:
                        </p>
                        {options.categories.length > 0 && (
                            <div>
                                <span className="font-medium">
                                    Kategori tersedia:
                                </span>{' '}
                                {options.categories
                                    .map((c) => c.code)
                                    .join(', ')}
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
                )}
            </div>

            {/* Modal Dialog Form Create / Edit Service Request (Compact & Screen-Efficient) */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-h-[92vh] p-4 sm:max-w-lg sm:p-6">
                    <DialogHeader className="space-y-1">
                        <DialogTitle className="text-base sm:text-lg">
                            {editingRowKey !== null
                                ? 'Edit Service Request'
                                : 'Create Service Request'}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {editingRowKey !== null
                                ? 'Perbarui informasi Service Request di bawah ini.'
                                : 'Isi formulir berikut untuk membuat Service Request baru.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleModalSubmit}
                        className="space-y-3 py-1"
                    >
                        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="sr_number" className="text-xs">
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
                                    className="h-9 text-xs sm:text-sm"
                                />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="engine_name"
                                    className="text-xs"
                                >
                                    Mesin / Peralatan
                                </Label>
                                {machineOptions.length > 0 ? (
                                    <Select
                                        value={
                                            formData.engine_name || NONE_VALUE
                                        }
                                        onValueChange={(val) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                engine_name:
                                                    val === NONE_VALUE
                                                        ? ''
                                                        : val,
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id="engine_name"
                                            className="h-9 text-xs sm:text-sm"
                                        >
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
                                        className="h-9 text-xs sm:text-sm"
                                    />
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2.5">
                            <div className="space-y-1">
                                <Label
                                    htmlFor="category_code"
                                    className="text-xs"
                                >
                                    Kategori
                                </Label>
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
                                    <SelectTrigger
                                        id="category_code"
                                        className="h-9 text-xs sm:text-sm"
                                    >
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

                            <div className="space-y-1">
                                <Label htmlFor="status" className="text-xs">
                                    Status
                                </Label>
                                <Select
                                    value={formData.status || 'open'}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            status: val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="h-9 text-xs sm:text-sm"
                                    >
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

                        <div className="space-y-1">
                            <Label htmlFor="description" className="text-xs">
                                Deskripsi Pekerjaan / Kendala
                            </Label>
                            <textarea
                                id="description"
                                rows={3}
                                className="flex min-h-[70px] w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
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
                                size="sm"
                                onClick={() => setModalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" size="sm" disabled={saving}>
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
