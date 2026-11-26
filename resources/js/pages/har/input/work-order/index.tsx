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
import { MobileFormScreen } from '@/components/mobile/entry-flow';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
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
import workOrder from '@/routes/har/input/work-order';
import type { IdName } from '@/types';

type Code = { code: string; name: string };

/** One material / jasa line of a waiting WO (WO Waiting Material dan Jasa, A17). */
type Material = {
    description: string | null;
    stockcode: string | null;
    amount: string | null;
};

type RowData = {
    wonum: string | null;
    description: string | null;
    type_code: string | null;
    engine_name: string | null;
    assetnum: string | null;
    work_group_code: string | null;
    owner_group: string | null;
    status_code: string | null;
    cycle_code: string | null;
    report_date: string | null;
    sched_start: string | null;
    sched_finish: string | null;
    waiting_reason: string | null;
    priority_text: string | null;
    materials: Material[];
};

type GridRow = RowData & {
    _key: number;
    [key: string]: number | string | null | Material[];
};

type GroupKey = 'all' | 'PM' | 'PDM' | 'CM' | 'ENJI' | 'shutdown' | 'material';

/**
 * WO groups of the Laporan Pengusahaan: by jenis (PM/PdM/CM/ENJI) or by
 * waiting reason. One WO can sit in two groups (e.g. a CM waiting material),
 * so they are views over the same list, not separate inputs.
 */
const WO_GROUPS: { key: GroupKey; label: string; doc: string | null }[] = [
    { key: 'all', label: 'Semua WO', doc: null },
    { key: 'PM', label: 'Preventive (PM)', doc: 'A12' },
    { key: 'PDM', label: 'Predictive (PdM)', doc: 'A13' },
    { key: 'CM', label: 'Corrective (CM)', doc: 'A14' },
    { key: 'ENJI', label: 'Rekomendasi Enjiniring', doc: 'A15' },
    { key: 'shutdown', label: 'Waiting Shutdown', doc: 'A16' },
    { key: 'material', label: 'Waiting Material & Jasa', doc: 'A17' },
];

const inGroup = (row: RowData, group: GroupKey): boolean => {
    switch (group) {
        case 'all':
            return true;
        case 'shutdown':
            return row.waiting_reason === 'shutdown';
        case 'material':
            return (
                row.waiting_reason === 'material' ||
                row.waiting_reason === 'jasa'
            );
        default:
            return (row.type_code ?? '').toUpperCase() === group;
    }
};

const blankMaterial = (): Material => ({
    description: null,
    stockcode: null,
    amount: null,
});

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: RowData[];
    options: {
        units: IdName[];
        years: number[];
        maintenance_types: Code[];
        work_groups: Code[];
        statuses: Code[];
        cycles: Code[];
        machines: IdName[];
        waiting_reasons: { value: string; label: string }[];
    };
    can_write: boolean;
};

const NONE_VALUE = '__none__';

const hydrate = (rows: RowData[]): GridRow[] =>
    rows.map((row, index) => ({
        ...row,
        materials: row.materials ?? [],
        _key: index,
    }));

/**
 * A multi-line editor for the description column when editing directly in grid.
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

export default function WorkOrderInput({
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
        wonum: '',
        description: '',
        type_code: '',
        engine_name: '',
        assetnum: '',
        work_group_code: '',
        owner_group: '',
        status_code: '',
        cycle_code: '',
        report_date: '',
        sched_start: '',
        sched_finish: '',
        waiting_reason: '',
        priority_text: '',
    });
    const [formMaterials, setFormMaterials] = useState<Material[]>([]);
    const [group, setGroup] = useState<GroupKey>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [mobileFilterOpen, setMobileFilterOpen] = useState(false);
    const [mobilePage, setMobilePage] = useState(1);
    const MOBILE_PAGE_SIZE = 15;

    const emptyColFilters = {
        type_code: 'all',
        work_group_code: 'all',
        status_code: 'all',
        cycle_code: 'all',
        waiting_reason: 'all',
    };
    const [colFilters, setColFilters] = useState(emptyColFilters);

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
            if (!inGroup(row, group)) {
                return false;
            }

            if (
                (colFilters.type_code !== 'all' &&
                    row.type_code !== colFilters.type_code) ||
                (colFilters.work_group_code !== 'all' &&
                    row.work_group_code !== colFilters.work_group_code) ||
                (colFilters.status_code !== 'all' &&
                    row.status_code !== colFilters.status_code) ||
                (colFilters.cycle_code !== 'all' &&
                    row.cycle_code !== colFilters.cycle_code) ||
                (colFilters.waiting_reason !== 'all' &&
                    row.waiting_reason !== colFilters.waiting_reason)
            ) {
                return false;
            }

            if (!q) {
                return true;
            }

            return [
                row.wonum,
                row.description,
                row.engine_name,
                row.assetnum,
                row.type_code,
                row.work_group_code,
                row.owner_group,
                row.status_code,
                row.cycle_code,
                row.waiting_reason,
                row.priority_text,
            ].some((val) => (val ?? '').toLowerCase().includes(q));
        });
    }, [rows, colFilters, group, searchQuery]);

    const showMaterials =
        formData.waiting_reason === 'material' ||
        formData.waiting_reason === 'jasa';

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

    // Dynamic option fallbacks in case loaded row has custom values
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

    const typeOptions = useMemo(() => {
        const list = [...options.maintenance_types];

        if (
            formData.type_code &&
            !list.some(
                (t) =>
                    t.code.toLowerCase() === formData.type_code.toLowerCase(),
            )
        ) {
            list.unshift({
                code: formData.type_code,
                name: formData.type_code,
            });
        }

        return list;
    }, [options.maintenance_types, formData.type_code]);

    const workGroupOptions = useMemo(() => {
        const list = [...options.work_groups];

        if (
            formData.work_group_code &&
            !list.some(
                (w) =>
                    w.code.toLowerCase() ===
                    formData.work_group_code.toLowerCase(),
            )
        ) {
            list.unshift({
                code: formData.work_group_code,
                name: formData.work_group_code,
            });
        }

        return list;
    }, [options.work_groups, formData.work_group_code]);

    const statusOptions = useMemo(() => {
        const list = [...options.statuses];

        if (
            formData.status_code &&
            !list.some(
                (s) =>
                    s.code.toLowerCase() === formData.status_code.toLowerCase(),
            )
        ) {
            list.unshift({
                code: formData.status_code,
                name: formData.status_code,
            });
        }

        return list;
    }, [options.statuses, formData.status_code]);

    const cycleOptions = useMemo(() => {
        const list = [...options.cycles];

        if (
            formData.cycle_code &&
            !list.some(
                (c) =>
                    c.code.toLowerCase() === formData.cycle_code.toLowerCase(),
            )
        ) {
            list.unshift({
                code: formData.cycle_code,
                name: formData.cycle_code,
            });
        }

        return list;
    }, [options.cycles, formData.cycle_code]);

    const waitingReasonOptions = useMemo(() => {
        const list = [...options.waiting_reasons];

        if (
            formData.waiting_reason &&
            !list.some(
                (w) =>
                    w.value.toLowerCase() ===
                    formData.waiting_reason.toLowerCase(),
            )
        ) {
            list.unshift({
                value: formData.waiting_reason,
                label: formData.waiting_reason,
            });
        }

        return list;
    }, [options.waiting_reasons, formData.waiting_reason]);

    const payload = (row: GridRow): RowData => ({
        wonum: row.wonum,
        description: row.description,
        type_code: row.type_code,
        engine_name: row.engine_name,
        assetnum: row.assetnum,
        work_group_code: row.work_group_code,
        owner_group: row.owner_group,
        status_code: row.status_code,
        cycle_code: row.cycle_code,
        report_date: row.report_date,
        sched_start: row.sched_start,
        sched_finish: row.sched_finish,
        waiting_reason: row.waiting_reason,
        priority_text: row.priority_text,
        materials: row.materials ?? [],
    });

    const saveRows = (
        rowsToSave: GridRow[],
        successMessage = 'Data berhasil disimpan',
    ) => {
        setSaving(true);
        router.post(
            workOrder.store().url,
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
        const typeFromGroup = options.maintenance_types.find(
            (t) => t.code.toUpperCase() === group,
        )?.code;
        const waitingFromGroup =
            group === 'shutdown' || group === 'material' ? group : '';

        setEditingRowKey(null);
        setFormData({
            wonum: '',
            description: '',
            type_code:
                typeFromGroup ?? options.maintenance_types[0]?.code ?? '',
            engine_name: '',
            assetnum: '',
            work_group_code: options.work_groups[0]?.code ?? '',
            owner_group: '',
            status_code: options.statuses[0]?.code ?? '',
            cycle_code: options.cycles[0]?.code ?? '',
            report_date: '',
            sched_start: '',
            sched_finish: '',
            waiting_reason: waitingFromGroup,
            priority_text: '',
        });
        setFormMaterials(
            waitingFromGroup === 'material' ? [blankMaterial()] : [],
        );
        setModalOpen(true);
    };

    const openEditModal = (row: GridRow) => {
        setEditingRowKey(row._key);
        setFormData({
            wonum: row.wonum ?? '',
            description: row.description ?? '',
            type_code: row.type_code ?? '',
            engine_name: row.engine_name ?? '',
            assetnum: row.assetnum ?? '',
            work_group_code: row.work_group_code ?? '',
            owner_group: row.owner_group ?? '',
            status_code: row.status_code ?? '',
            cycle_code: row.cycle_code ?? '',
            report_date: row.report_date ?? '',
            sched_start: row.sched_start ?? '',
            sched_finish: row.sched_finish ?? '',
            waiting_reason: row.waiting_reason ?? '',
            priority_text: row.priority_text ?? '',
        });
        setFormMaterials(row.materials ?? []);
        setModalOpen(true);
    };

    const handleModalSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const wonumTrimmed = formData.wonum.trim();

        if (!wonumTrimmed) {
            toast.error('WONUM wajib diisi');

            return;
        }

        const isDuplicate = rows.some(
            (r) =>
                r.wonum?.toLowerCase() === wonumTrimmed.toLowerCase() &&
                r._key !== editingRowKey,
        );

        if (isDuplicate) {
            toast.error(`WONUM "${wonumTrimmed}" sudah ada dalam daftar.`);

            return;
        }

        let nextRows: GridRow[];
        const isEdit = editingRowKey !== null;
        const extra = {
            assetnum: formData.assetnum.trim() || null,
            owner_group: formData.owner_group.trim() || null,
            priority_text: formData.priority_text.trim() || null,
            materials: showMaterials
                ? formMaterials.filter((m) =>
                      [m.description, m.stockcode, m.amount].some(
                          (v) => (v ?? '').trim() !== '',
                      ),
                  )
                : [],
        };

        if (isEdit) {
            nextRows = rows.map((r) =>
                r._key === editingRowKey
                    ? {
                          ...r,
                          wonum: wonumTrimmed,
                          description: formData.description.trim() || null,
                          type_code: formData.type_code || null,
                          engine_name: formData.engine_name || null,
                          work_group_code: formData.work_group_code || null,
                          status_code: formData.status_code || null,
                          cycle_code: formData.cycle_code || null,
                          report_date: formData.report_date || null,
                          sched_start: formData.sched_start || null,
                          sched_finish: formData.sched_finish || null,
                          waiting_reason: formData.waiting_reason || null,
                          ...extra,
                      }
                    : r,
            );
        } else {
            const newRow: GridRow = {
                _key: nextKey,
                wonum: wonumTrimmed,
                description: formData.description.trim() || null,
                type_code: formData.type_code || null,
                engine_name: formData.engine_name || null,
                work_group_code: formData.work_group_code || null,
                status_code: formData.status_code || null,
                cycle_code: formData.cycle_code || null,
                report_date: formData.report_date || null,
                sched_start: formData.sched_start || null,
                sched_finish: formData.sched_finish || null,
                waiting_reason: formData.waiting_reason || null,
                ...extra,
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
                ? 'Work Order berhasil diperbarui'
                : 'Work Order berhasil ditambahkan',
        );
    };

    const handleDeleteRow = (row: GridRow) => {
        const label = row.wonum ? `"${row.wonum}"` : 'baris ini';

        if (
            !window.confirm(
                `Apakah Anda yakin ingin menghapus Work Order ${label}?`,
            )
        ) {
            return;
        }

        const nextRows = rows.filter((r) => r._key !== row._key);
        setRows(nextRows);
        saveRows(nextRows, 'Work Order berhasil dihapus');
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
            workOrder.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'wonum',
                name: 'WONUM',
                width: 140,
                minWidth: 110,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="font-semibold text-foreground">
                        {row.wonum || '—'}
                    </span>
                ),
            },
            {
                key: 'description',
                name: 'Deskripsi',
                width: 'minmax(260px, 2fr)',
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
                key: 'type_code',
                name: 'Jenis',
                width: 110,
                minWidth: 90,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) =>
                    row.type_code ? (
                        <Badge
                            variant="outline"
                            className="bg-muted/60 text-xs font-medium"
                        >
                            {row.type_code}
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'engine_name',
                name: 'Mesin',
                width: 'minmax(150px, 1fr)',
                minWidth: 130,
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
            {
                key: 'assetnum',
                name: 'Assetnum',
                width: 150,
                minWidth: 110,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="text-xs">{row.assetnum || '—'}</span>
                ),
            },
            {
                key: 'work_group_code',
                name: 'Work Group',
                width: 130,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) =>
                    row.work_group_code ? (
                        <Badge
                            variant="secondary"
                            className="text-xs font-normal"
                        >
                            {row.work_group_code}
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'owner_group',
                name: 'Owner Group',
                width: 110,
                minWidth: 90,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="text-xs">{row.owner_group || '—'}</span>
                ),
            },
            {
                key: 'status_code',
                name: 'Status',
                width: 120,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => {
                    if (!row.status_code) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    const val = row.status_code.toUpperCase();
                    const isComp =
                        val === 'COMP' || val === 'CLOSE' || val === 'CLOSED';
                    const isInprg = val === 'INPRG' || val === 'APPR';

                    return (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                isComp
                                    ? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                    : isInprg
                                      ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400'
                                      : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-400',
                            )}
                        >
                            <span
                                className={cn(
                                    'mr-1.5 size-1.5 rounded-full',
                                    isComp
                                        ? 'bg-slate-400'
                                        : isInprg
                                          ? 'bg-emerald-500'
                                          : 'bg-amber-500',
                                )}
                            />
                            {row.status_code}
                        </span>
                    );
                },
            },
            {
                key: 'cycle_code',
                name: 'Siklus',
                width: 100,
                minWidth: 80,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) =>
                    row.cycle_code ? (
                        <Badge variant="outline" className="text-xs">
                            {row.cycle_code}
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'report_date',
                name: 'Report Date',
                width: 110,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => <span>{row.report_date || '—'}</span>,
            },
            {
                key: 'sched_start',
                name: 'Sched Start',
                width: 110,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => <span>{row.sched_start || '—'}</span>,
            },
            {
                key: 'sched_finish',
                name: 'Sched Finish',
                width: 110,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => <span>{row.sched_finish || '—'}</span>,
            },
            {
                key: 'waiting_reason',
                name: 'Waiting',
                width: 130,
                minWidth: 110,
                resizable: true,
                editable: can_write,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                        {row.waiting_reason || (
                            <span className="font-normal text-muted-foreground">
                                —
                            </span>
                        )}
                    </span>
                ),
            },
            {
                key: 'priority_text',
                name: 'WO Prior Text',
                width: 120,
                minWidth: 100,
                resizable: true,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) => (
                    <span className="text-xs">{row.priority_text || '—'}</span>
                ),
            },
            {
                key: 'materials',
                name: 'Material/Jasa',
                width: 130,
                minWidth: 110,
                resizable: true,
                editable: false,
                headerCellClass: 'rdg-sub-header',
                renderCell: ({ row }) =>
                    row.materials.length > 0 ? (
                        <span
                            className="text-xs"
                            title={row.materials
                                .map((m) =>
                                    [m.description, m.stockcode, m.amount]
                                        .filter(Boolean)
                                        .join(' · '),
                                )
                                .join('\n')}
                        >
                            {row.materials.length} item
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
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
                            title="Edit Work Order"
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
                            title="Hapus Work Order"
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

    const codeList = (label: string, items: Code[]) =>
        items.length > 0 && (
            <div>
                <span className="font-medium">{label}:</span>{' '}
                {items.map((i) => i.code).join(', ')}
            </div>
        );

    const renderStatusPill = (statusCode: string | null) => {
        if (!statusCode) {
            return null;
        }

        const val = statusCode.toUpperCase();
        const isComp = val === 'COMP' || val === 'CLOSE' || val === 'CLOSED';
        const isInprg = val === 'INPRG' || val === 'APPR';

        return (
            <span
                className={cn(
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                    isComp
                        ? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        : isInprg
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400'
                          : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-400',
                )}
            >
                <span
                    className={cn(
                        'mr-1 size-1.5 rounded-full',
                        isComp
                            ? 'bg-slate-400'
                            : isInprg
                              ? 'bg-emerald-500'
                              : 'bg-amber-500',
                    )}
                />
                {statusCode}
            </span>
        );
    };

    const modalForm = (
                    <form
                        id="wo-form"
                        onSubmit={handleModalSubmit}
                        className="space-y-3 py-1"
                    >
                        {/* Baris 1: WONUM & Mesin */}
                        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="wonum" className="text-xs">
                                    WONUM{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="wonum"
                                    placeholder="Contoh: WO-2026-001"
                                    value={formData.wonum}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            wonum: e.target.value,
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

                        {/* Baris 2: Deskripsi */}
                        <div className="space-y-1">
                            <Label htmlFor="description" className="text-xs">
                                Deskripsi Pekerjaan
                            </Label>
                            <textarea
                                id="description"
                                rows={2}
                                className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
                                placeholder="Tuliskan uraian pekerjaan atau kendala Work Order..."
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        description: e.target.value,
                                    }))
                                }
                            />
                        </div>

                        {/* Baris 3: Jenis, Work Group, Status, Siklus (2x2 di HP, 4 kolom di Desktop) */}
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            <div className="space-y-1">
                                <Label htmlFor="type_code" className="text-xs">
                                    Jenis
                                </Label>
                                <Select
                                    value={formData.type_code || NONE_VALUE}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            type_code:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="type_code"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Pilih Jenis" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa Jenis —
                                        </SelectItem>
                                        {typeOptions.map((t) => (
                                            <SelectItem
                                                key={t.code}
                                                value={t.code}
                                            >
                                                {t.code}{' '}
                                                {t.name && t.name !== t.code
                                                    ? `— ${t.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="work_group_code"
                                    className="text-xs"
                                >
                                    Work Group
                                </Label>
                                <Select
                                    value={
                                        formData.work_group_code || NONE_VALUE
                                    }
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            work_group_code:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="work_group_code"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Pilih WG" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa WG —
                                        </SelectItem>
                                        {workGroupOptions.map((w) => (
                                            <SelectItem
                                                key={w.code}
                                                value={w.code}
                                            >
                                                {w.code}{' '}
                                                {w.name && w.name !== w.code
                                                    ? `— ${w.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="status_code"
                                    className="text-xs"
                                >
                                    Status
                                </Label>
                                <Select
                                    value={formData.status_code || NONE_VALUE}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            status_code:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="status_code"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Pilih Status" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa Status —
                                        </SelectItem>
                                        {statusOptions.map((s) => (
                                            <SelectItem
                                                key={s.code}
                                                value={s.code}
                                            >
                                                {s.code}{' '}
                                                {s.name && s.name !== s.code
                                                    ? `— ${s.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="cycle_code" className="text-xs">
                                    Siklus
                                </Label>
                                <Select
                                    value={formData.cycle_code || NONE_VALUE}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            cycle_code:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="cycle_code"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Pilih Siklus" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tanpa Siklus —
                                        </SelectItem>
                                        {cycleOptions.map((c) => (
                                            <SelectItem
                                                key={c.code}
                                                value={c.code}
                                            >
                                                {c.code}{' '}
                                                {c.name && c.name !== c.code
                                                    ? `— ${c.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Baris 4: Tanggal (Report Date, Sched Start, Sched Finish) */}
                        <div className="grid grid-cols-3 gap-2">
                            <div className="space-y-1">
                                <Label
                                    htmlFor="report_date"
                                    className="text-xs"
                                >
                                    Report Date
                                </Label>
                                <Input
                                    id="report_date"
                                    type="date"
                                    value={formData.report_date}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            report_date: e.target.value,
                                        }))
                                    }
                                    className="h-9 px-2 text-xs"
                                />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="sched_start"
                                    className="text-xs"
                                >
                                    Sched Start
                                </Label>
                                <Input
                                    id="sched_start"
                                    type="date"
                                    value={formData.sched_start}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            sched_start: e.target.value,
                                        }))
                                    }
                                    className="h-9 px-2 text-xs"
                                />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="sched_finish"
                                    className="text-xs"
                                >
                                    Sched Finish
                                </Label>
                                <Input
                                    id="sched_finish"
                                    type="date"
                                    value={formData.sched_finish}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            sched_finish: e.target.value,
                                        }))
                                    }
                                    className="h-9 px-2 text-xs"
                                />
                            </div>
                        </div>

                        {/* Baris 5: Waiting Reason, Assetnum, Owner Group, WO Prior Text (2x2 di HP, 4 kolom di Desktop) */}
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            <div className="space-y-1">
                                <Label
                                    htmlFor="waiting_reason"
                                    className="text-xs"
                                >
                                    Waiting Reason
                                </Label>
                                <Select
                                    value={
                                        formData.waiting_reason || NONE_VALUE
                                    }
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            waiting_reason:
                                                val === NONE_VALUE ? '' : val,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="waiting_reason"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Pilih Alasan..." />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        <SelectItem
                                            value={NONE_VALUE}
                                            className="text-muted-foreground"
                                        >
                                            — Tidak Ada Waiting —
                                        </SelectItem>
                                        {waitingReasonOptions.map((w) => (
                                            <SelectItem
                                                key={w.value}
                                                value={w.value}
                                            >
                                                {w.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {(
                                [
                                    [
                                        'assetnum',
                                        'Assetnum',
                                        'WUAWTD002MJA10AV001',
                                    ],
                                    [
                                        'owner_group',
                                        'Owner Group',
                                        'MECHD (= WG)',
                                    ],
                                    [
                                        'priority_text',
                                        'WO Prior Text',
                                        'Urgent',
                                    ],
                                ] as const
                            ).map(([key, label, placeholder]) => (
                                <div key={key} className="space-y-1">
                                    <Label htmlFor={key} className="text-xs">
                                        {label}
                                    </Label>
                                    <Input
                                        id={key}
                                        placeholder={placeholder}
                                        value={formData[key]}
                                        onChange={(e) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                [key]: e.target.value,
                                            }))
                                        }
                                        className="h-9 text-xs"
                                    />
                                </div>
                            ))}
                        </div>

                        {showMaterials && (
                            <div className="space-y-2 rounded-md border border-border bg-muted/20 p-2.5">
                                <div className="flex items-center justify-between">
                                    <Label className="text-xs font-semibold">
                                        Rincian Material / Jasa yang ditunggu
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1 text-xs"
                                        onClick={() =>
                                            setFormMaterials((list) => [
                                                ...list,
                                                blankMaterial(),
                                            ])
                                        }
                                    >
                                        <Plus className="size-3.5" />
                                        Tambah Item
                                    </Button>
                                </div>
                                {formMaterials.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Belum ada item — WO tetap tampil satu
                                        baris di laporan.
                                    </p>
                                )}
                                {formMaterials.map((material, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-1 gap-1.5 rounded border border-border/60 bg-card p-2 sm:grid-cols-[1fr_1fr_90px_auto] sm:items-center sm:border-0 sm:bg-transparent sm:p-0"
                                    >
                                        <Input
                                            placeholder="Deskripsi (mis. Piston Ring 1)"
                                            aria-label={`Deskripsi (mis. Piston Ring 1) item ${index + 1}`}
                                            value={material.description ?? ''}
                                            onChange={(e) =>
                                                setFormMaterials((list) =>
                                                    list.map((m, i) =>
                                                        i === index
                                                            ? {
                                                                  ...m,
                                                                  description:
                                                                      e.target
                                                                          .value,
                                                              }
                                                            : m,
                                                    ),
                                                )
                                            }
                                            className="h-8 text-xs"
                                        />
                                        <div className="grid grid-cols-[1fr_80px_auto] items-center gap-1.5 sm:contents">
                                            <Input
                                                placeholder="No Stockcode / Part"
                                                aria-label={`No Stockcode / Part item ${index + 1}`}
                                                value={material.stockcode ?? ''}
                                                onChange={(e) =>
                                                    setFormMaterials((list) =>
                                                        list.map((m, i) =>
                                                            i === index
                                                                ? {
                                                                      ...m,
                                                                      stockcode:
                                                                          e
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : m,
                                                        ),
                                                    )
                                                }
                                                className="h-8 text-xs"
                                            />
                                            <Input
                                                placeholder="Jumlah"
                                                aria-label={`Jumlah item ${index + 1}`}
                                                value={material.amount ?? ''}
                                                onChange={(e) =>
                                                    setFormMaterials((list) =>
                                                        list.map((m, i) =>
                                                            i === index
                                                                ? {
                                                                      ...m,
                                                                      amount: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : m,
                                                        ),
                                                    )
                                                }
                                                className="h-8 text-xs"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setFormMaterials((list) =>
                                                        list.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                                className="inline-flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                aria-label={`Hapus item ${index + 1}`}
                                            >
                                                <X className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                                <p className="text-[11px] text-muted-foreground">
                                    Tiap item jadi satu baris di tabel WO
                                    Waiting Material dan Jasa (A17),
                                    dikelompokkan per Bidang dari Work Group
                                    (MECHD Mekanik, ELECD Listrik, INSTD Kontrol
                                    &amp; Instrumen, CIVD Sipil).
                                </p>
                            </div>
                        )}

                        {!compact && (
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
                        )}
                    </form>
    );

    return (
        <>
            <Head title="Input HAR — Work Order" />
            <div className={cn('flex flex-1 flex-col gap-3 p-3 md:gap-4 md:p-6', compact && can_write && 'pb-28')}>
                {!compact && (
                    <PageHeader
                        title="Work Order"
                        description="Kelola daftar Work Order per unit & periode. Gunakan tombol Create untuk menambah data baru atau klik ikon edit/dobel klik baris untuk mengubah."
                        actions={
                            can_write && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        onClick={openCreateModal}
                                        className="gap-1.5 shadow-xs"
                                    >
                                        <Plus className="size-4" />
                                        Create Work Order
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
                                    placeholder="Cari WONUM, uraian, mesin…"
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
                            {/* Phone: Tambah sits in the bottom bar. */}
                            {!compact && can_write && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={openCreateModal}
                                    className="h-9 shrink-0 gap-1 px-2.5 text-xs shadow-xs"
                                >
                                    <Plus className="size-3.5" />
                                    WO Baru
                                </Button>
                            )}
                            {/* Phone: Simpan sits in the bottom bar. */}
                            {!compact && can_write && dirty && (
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

                        {/* Single-row horizontal scrollable WO Group Tabs on Mobile */}
                        <div
                            className="-mx-1 flex [scrollbar-width:none] items-center gap-1.5 overflow-x-auto px-1 pb-0.5 [&::-webkit-scrollbar]:hidden"
                            role="tablist"
                            aria-label="Kelompok Work Order"
                        >
                            {WO_GROUPS.map((item) => {
                                const count = rows.filter((row) =>
                                    inGroup(row, item.key),
                                ).length;
                                const active = group === item.key;

                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => {
                                            setGroup(item.key);
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
                                        {item.doc && (
                                            <span
                                                className={cn(
                                                    'text-[10px]',
                                                    active
                                                        ? 'text-primary-foreground/80'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {item.doc}
                                            </span>
                                        )}
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
                                        Filter Work Order
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
                                            Jenis
                                        </span>
                                        <Select
                                            value={colFilters.type_code}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    type_code: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Jenis
                                                </SelectItem>
                                                {options.maintenance_types.map(
                                                    (i) => (
                                                        <SelectItem
                                                            key={i.code}
                                                            value={i.code}
                                                        >
                                                            {i.code}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Work Group
                                        </span>
                                        <Select
                                            value={colFilters.work_group_code}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    work_group_code: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua WG
                                                </SelectItem>
                                                {options.work_groups.map(
                                                    (i) => (
                                                        <SelectItem
                                                            key={i.code}
                                                            value={i.code}
                                                        >
                                                            {i.code}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Status
                                        </span>
                                        <Select
                                            value={colFilters.status_code}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    status_code: value,
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
                                                {options.statuses.map((i) => (
                                                    <SelectItem
                                                        key={i.code}
                                                        value={i.code}
                                                    >
                                                        {i.code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Siklus
                                        </span>
                                        <Select
                                            value={colFilters.cycle_code}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    cycle_code: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Siklus
                                                </SelectItem>
                                                {options.cycles.map((i) => (
                                                    <SelectItem
                                                        key={i.code}
                                                        value={i.code}
                                                    >
                                                        {i.code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-2 flex flex-col gap-0.5">
                                        <span className="text-[11px] text-muted-foreground">
                                            Waiting Reason
                                        </span>
                                        <Select
                                            value={colFilters.waiting_reason}
                                            onValueChange={(value) => {
                                                setColFilters((f) => ({
                                                    ...f,
                                                    waiting_reason: value,
                                                }));
                                                setMobilePage(1);
                                            }}
                                        >
                                            <SelectTrigger className="h-8 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Waiting
                                                </SelectItem>
                                                {options.waiting_reasons.map(
                                                    (w) => (
                                                        <SelectItem
                                                            key={w.value}
                                                            value={w.value}
                                                        >
                                                            {w.label}
                                                        </SelectItem>
                                                    ),
                                                )}
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
                                WO
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
                    <>
                        <div
                            className="flex flex-wrap gap-2"
                            role="tablist"
                            aria-label="Kelompok Work Order"
                        >
                            {WO_GROUPS.map((item) => {
                                const count = rows.filter((row) =>
                                    inGroup(row, item.key),
                                ).length;
                                const active = group === item.key;

                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => setGroup(item.key)}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-card text-foreground hover:border-primary/50',
                                        )}
                                    >
                                        {item.label}
                                        {item.doc && (
                                            <span
                                                className={cn(
                                                    'text-[10px]',
                                                    active
                                                        ? 'text-primary-foreground/80'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {item.doc}
                                            </span>
                                        )}
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 text-[10px]',
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
                                onChange={(value) =>
                                    visit({ year: Number(value) })
                                }
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
                                        placeholder="WONUM / uraian / mesin…"
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
                                label="Filter Jenis"
                                value={colFilters.type_code}
                                onChange={(value) =>
                                    setColFilters((f) => ({
                                        ...f,
                                        type_code: value,
                                    }))
                                }
                                options={[
                                    { value: 'all', label: 'Semua' },
                                    ...options.maintenance_types.map((i) => ({
                                        value: i.code,
                                        label: i.code,
                                    })),
                                ]}
                            />
                            <OperasiSelect
                                label="Filter Work Group"
                                value={colFilters.work_group_code}
                                onChange={(value) =>
                                    setColFilters((f) => ({
                                        ...f,
                                        work_group_code: value,
                                    }))
                                }
                                options={[
                                    { value: 'all', label: 'Semua' },
                                    ...options.work_groups.map((i) => ({
                                        value: i.code,
                                        label: i.code,
                                    })),
                                ]}
                            />
                            <OperasiSelect
                                label="Filter Status"
                                value={colFilters.status_code}
                                onChange={(value) =>
                                    setColFilters((f) => ({
                                        ...f,
                                        status_code: value,
                                    }))
                                }
                                options={[
                                    { value: 'all', label: 'Semua' },
                                    ...options.statuses.map((i) => ({
                                        value: i.code,
                                        label: i.code,
                                    })),
                                ]}
                            />
                            <OperasiSelect
                                label="Filter Siklus"
                                value={colFilters.cycle_code}
                                onChange={(value) =>
                                    setColFilters((f) => ({
                                        ...f,
                                        cycle_code: value,
                                    }))
                                }
                                options={[
                                    { value: 'all', label: 'Semua' },
                                    ...options.cycles.map((i) => ({
                                        value: i.code,
                                        label: i.code,
                                    })),
                                ]}
                            />
                            <OperasiSelect
                                label="Filter Waiting"
                                value={colFilters.waiting_reason}
                                onChange={(value) =>
                                    setColFilters((f) => ({
                                        ...f,
                                        waiting_reason: value,
                                    }))
                                }
                                options={[
                                    { value: 'all', label: 'Semua' },
                                    ...options.waiting_reasons,
                                ]}
                            />

                            {hasActiveFilter && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="self-end text-xs text-muted-foreground"
                                    onClick={resetAllFilters}
                                >
                                    Reset Filter ({displayRows.length}/
                                    {rows.length})
                                </Button>
                            )}
                            {dirty && !saving && (
                                <span className="self-end pb-1 text-[13px] text-amber-600">
                                    Ada perubahan belum disimpan.
                                </span>
                            )}
                        </div>
                    </>
                )}

                {displayRows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card/60 p-8 text-center md:p-12">
                        <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <ClipboardList className="size-5" />
                        </div>
                        <h3 className="text-sm font-semibold text-foreground md:text-base">
                            {hasActiveFilter
                                ? 'Tidak Ada Data yang Cocok'
                                : 'Belum Ada Work Order'}
                        </h3>
                        <p className="mt-1 max-w-md text-xs text-muted-foreground md:text-sm">
                            {hasActiveFilter
                                ? 'Tidak ada Work Order yang sesuai dengan kriteria pencarian atau filter yang dipilih.'
                                : 'Belum ada Work Order yang tercatat untuk unit dan periode ini. Silakan buat Work Order baru.'}
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
                                        Create Work Order
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
                                    {/* Line 1: WONUM, Badges & Quick Action Icons */}
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
                                                {row.wonum || 'WO tanpa nomor'}
                                            </span>
                                            {row.type_code && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-5 bg-muted/60 px-1.5 text-[10px] font-semibold"
                                                >
                                                    {row.type_code}
                                                </Badge>
                                            )}
                                            {row.work_group_code && (
                                                <Badge
                                                    variant="secondary"
                                                    className="h-5 px-1.5 text-[10px] font-normal"
                                                >
                                                    {row.work_group_code}
                                                </Badge>
                                            )}
                                            {row.cycle_code && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-5 px-1.5 text-[10px]"
                                                >
                                                    {row.cycle_code}
                                                </Badge>
                                            )}
                                        </button>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {renderStatusPill(row.status_code)}
                                            {can_write && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditModal(row)
                                                        }
                                                        aria-label={`Ubah ${row.wonum ?? 'Work Order'}`}
                                                        className="inline-flex size-7 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDeleteRow(row)
                                                        }
                                                        aria-label={`Hapus ${row.wonum ?? 'Work Order'}`}
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
                                    <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                        {row.engine_name && (
                                            <span className="inline-flex items-center gap-1 font-medium text-foreground/80">
                                                <Wrench className="size-3 text-muted-foreground" />
                                                {row.engine_name}
                                            </span>
                                        )}
                                        {row.assetnum && (
                                            <span className="truncate font-mono text-[10.5px]">
                                                #{row.assetnum}
                                            </span>
                                        )}
                                        {row.report_date && (
                                            <span>{row.report_date}</span>
                                        )}
                                        {row.waiting_reason && (
                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                Waiting: {row.waiting_reason}
                                            </span>
                                        )}
                                        {row.materials.length > 0 && (
                                            <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                                                {row.materials.length} item
                                                material/jasa
                                            </span>
                                        )}
                                    </div>
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
                            Kode yang valid:
                        </p>
                        {codeList('Jenis', options.maintenance_types)}
                        {codeList('Work Group', options.work_groups)}
                        {codeList('Status', options.statuses)}
                        {codeList('Siklus', options.cycles)}
                        <div>
                            <span className="font-medium">Waiting:</span>{' '}
                            {options.waiting_reasons
                                .map((r) => r.value)
                                .join(', ')}
                        </div>
                        <p className="pt-1">
                            Tip: Klik dua kali pada baris tabel atau klik ikon
                            pensil untuk mengubah data.
                        </p>
                    </div>
                )}
            </div>

            {/* Phone: Tambah (left) & Simpan (right); the form opens on its own screen (user, 2026-11-27). */}
            {compact && can_write && !modalOpen && (
                <StickyActionBar>
                    <Button size="lg" variant="outline" onClick={openCreateModal}>
                        <Plus className="size-4" />
                        Tambah
                    </Button>
                    <Button
                        size="lg"
                        onClick={() => saveRows(rows, 'Perubahan berhasil disimpan')}
                        disabled={saving || !dirty}
                    >
                        <Save className="size-4" />
                        {saving ? 'Menyimpan…' : dirty ? 'Simpan' : 'Tersimpan'}
                    </Button>
                </StickyActionBar>
            )}

            {compact ? (
                modalOpen && (
                    <MobileFormScreen
                        title={editingRowKey !== null ? 'Edit Work Order' : 'Work Order baru'}
                        subtitle="Work Order"
                        onBack={() => setModalOpen(false)}
                        formId="wo-form"
                        saving={saving}
                        canWrite={can_write}
                        onDelete={
                            editingRowKey !== null
                                ? () => {
                                      const row = rows.find((r) => r._key === editingRowKey);

                                      if (row) {
                                          setModalOpen(false);
                                          handleDeleteRow(row);
                                      }
                                  }
                                : undefined
                        }
                        deleteLabel="Hapus Work Order"
                    >
                        {modalForm}
                    </MobileFormScreen>
                )
            ) : (
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-h-[92vh] max-w-2xl overflow-y-auto p-4 sm:p-6">
                    <DialogHeader className="space-y-1">
                        <DialogTitle className="text-base sm:text-lg">
                            {editingRowKey !== null
                                ? 'Edit Work Order'
                                : 'Create Work Order'}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {editingRowKey !== null
                                ? 'Perbarui rincian data Work Order di bawah ini.'
                                : 'Isi formulir berikut untuk menambahkan Work Order baru.'}
                        </DialogDescription>
                    </DialogHeader>

                    {modalForm}
                </DialogContent>
            </Dialog>
            )}
        </>
    );
}

WorkOrderInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Work Order', href: workOrder.index() },
    ],
};
