import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCheck,
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    Plus,
    Printer,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { EvidencePhotoField } from '@/components/operasi/evidence-photo-field';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import {
    buildDocumentHeader,
    createSheet,
    downloadWorkbook,
    mergeCells,
    paintSheet,
    setColWidths,
    SPECS,
    XLSX_COLORS,
} from '@/lib/jadwal-excel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import patrolCheckMesin from '@/routes/operasi/input/patrol-check-mesin';
import patrolCheckMesinEntry from '@/routes/operasi/input/patrol-check-mesin/entry';
import type { IdName } from '@/types';

export type ChecklistParamOption = {
    key: string;
    label: string;
};

export type ChecklistMeta = {
    title: string;
    form_number: string;
    revision: string;
    instruction: string;
};

export type ChecklistItem = {
    no: number;
    system: string;
    peralatan: string;
    applicable: Record<string, boolean>;
    checked: Record<string, boolean>;
    status: '' | 'N' | 'TN';
    catatan: string;
    eviden?: string | null;
    eviden_url?: string | null;
};

export type ChecklistEntrySummary = {
    id: number;
    tanggal: string;
    tanggal_label: string;
    shift: string;
    jam: string;
    operator_name: string;
    normal_count: number;
    tn_count: number;
    total_items: number;
    has_catatan: boolean;
};

export type ChecklistActiveEntry = {
    id: number | null;
    tanggal: string;
    shift: string;
    jam: string;
    operator_name: string;
    items: ChecklistItem[];
    catatan: string;
};

type ChecklistProps = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name: string | null;
    };
    filters: {
        unit_id: number;
        year: number;
        month: number;
        machine_id: number | null;
        nama_mesin: string;
        entry_id?: number | null;
    };
    options: {
        units: IdName[];
        machines: { id: number; name: string }[];
        years: number[];
    };
    patrol_meta: ChecklistMeta;
    patrol_param_options: ChecklistParamOption[];
    user_shifts?: Record<string, string>;
    user_operator_name?: string;
    entries: ChecklistEntrySummary[];
    active_entry: ChecklistActiveEntry;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
    body * {
        visibility: hidden !important;
    }
    .print-container, .print-container * {
        visibility: visible !important;
    }
    .print-container {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: white !important;
    }
    .no-print {
        display: none !important;
    }
    .print-green-head th {
        background-color: #1f5c3a !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export function PatrolCheckChecklistView({
    unit,
    filters,
    options,
    patrol_meta,
    patrol_param_options,
    user_shifts,
    user_operator_name,
    entries,
    active_entry,
    can_write,
}: ChecklistProps) {
    const [tanggal, setTanggal] = useState(active_entry.tanggal);
    const [shift, setShift] = useState(active_entry.shift);
    const [jam, setJam] = useState(active_entry.jam);
    const [operatorName, setOperatorName] = useState(
        active_entry.operator_name,
    );
    const [items, setItems] = useState<ChecklistItem[]>(active_entry.items);
    const [files, setFiles] = useState<Record<number, File | null>>({});
    const [catatan, setCatatan] = useState(active_entry.catatan);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [areaFilter, setAreaFilter] = useState<string>('ALL');

    const handleDateChange = (nextDate: string) => {
        setTanggal(nextDate);

        if (active_entry.id === null) {
            if (user_shifts?.[nextDate]) {
                setShift(user_shifts[nextDate]);
            }

            if (user_operator_name) {
                setOperatorName(user_operator_name);
            }
        }

        setDirty(true);
    };

    const compact = useCompactLayout();
    const { can } = usePermissions();

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;

    const shiftQuotaCount = useMemo(
        () =>
            entries.filter((e) => e.tanggal === tanggal && e.shift === shift)
                .length,
        [entries, tanggal, shift],
    );

    const missingTnItems = useMemo(
        () =>
            items.filter(
                (it) => it.status === 'TN' && it.catatan.trim() === '',
            ),
        [items],
    );

    const areas = useMemo(() => {
        const list: string[] = [];

        for (const it of items) {
            if (!list.includes(it.system)) {
                list.push(it.system);
            }
        }

        return list;
    }, [items]);

    const visit = (
        patch: Record<string, string | number | boolean | null | undefined>,
    ) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        router.get(
            patrolCheckMesin.index().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                month: filters.month,
                machine_id: filters.machine_id,
                nama_mesin: filters.nama_mesin,
                ...patch,
            },
            { preserveState: false, preserveScroll: true },
        );
    };

    const toggleParam = (index: number, paramKey: string) => {
        if (!can_write) {
            return;
        }

        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== index || !it.applicable[paramKey]) {
                    return it;
                }

                return {
                    ...it,
                    checked: {
                        ...it.checked,
                        [paramKey]: !it.checked[paramKey],
                    },
                };
            }),
        );
        setDirty(true);
    };

    const setRowStatus = (index: number, next: 'N' | 'TN' | '') => {
        if (!can_write) {
            return;
        }

        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== index) {
                    return it;
                }

                const resolved = it.status === next ? '' : next;

                return resolved === 'TN'
                    ? { ...it, status: resolved }
                    : {
                          ...it,
                          status: resolved,
                          catatan: '',
                          eviden: null,
                          eviden_url: null,
                      };
            }),
        );

        if (next !== 'TN') {
            setFiles((prev) => ({ ...prev, [index]: null }));
        }

        setDirty(true);
    };

    const updateRowNote = (index: number, note: string) => {
        if (!can_write) {
            return;
        }

        setItems((prev) =>
            prev.map((it, idx) =>
                idx === index ? { ...it, catatan: note } : it,
            ),
        );
        setDirty(true);
    };

    const pickEviden = (index: number, file: File) => {
        if (!can_write) {
            return;
        }

        setFiles((prev) => ({ ...prev, [index]: file }));
        setDirty(true);
    };

    const removeEviden = (index: number) => {
        if (!can_write) {
            return;
        }

        setFiles((prev) => ({ ...prev, [index]: null }));
        setItems((prev) =>
            prev.map((it, idx) =>
                idx === index ? { ...it, eviden: null, eviden_url: null } : it,
            ),
        );
        setDirty(true);
    };

    const markAllNormal = () => {
        if (!can_write) {
            return;
        }

        setItems((prev) =>
            prev.map((it) => {
                const nextChecked: Record<string, boolean> = {};

                for (const opt of patrol_param_options) {
                    nextChecked[opt.key] = Boolean(it.applicable[opt.key]);
                }

                return {
                    ...it,
                    checked: nextChecked,
                    status: 'N',
                    catatan: '',
                    eviden: null,
                    eviden_url: null,
                };
            }),
        );
        setFiles({});
        setDirty(true);
        toast.success(
            'Seluruh parameter relevan dicentang & kondisi ditandai Normal (N).',
        );
    };

    const handleSave = () => {
        if (!can_write) {
            return;
        }

        if (missingTnItems.length > 0) {
            toast.error(
                `Kolom TN wajib diberi uraian alasan / tindakan awal (${missingTnItems[0].peralatan}).`,
            );

            return;
        }

        setSaving(true);
        router.post(
            patrolCheckMesin.store().url,
            {
                patrol_format: 'checklist',
                unit_id: filters.unit_id,
                machine_id: filters.machine_id,
                nama_mesin: filters.nama_mesin,
                entry_id: active_entry.id,
                tanggal,
                shift,
                jam,
                operator_name: operatorName,
                items: items.map((it, idx) => ({
                    ...it,
                    eviden_file:
                        it.status === 'TN' ? (files[idx] ?? null) : null,
                })),
                catatan,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setFiles({});
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleDeleteEntry = (entryId: number) => {
        if (!can_write) {
            return;
        }

        router.delete(patrolCheckMesinEntry.destroy(entryId).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmDelete(false),
        });
    };

    const handleExportExcel = async () => {
        const sheetData: (string | number)[][] = [[], [], [], []];
        const totalCols = 5 + patrol_param_options.length; // No + Area + Komponen + params + N + TN + Catatan
        sheetData.push([
            `Tanggal: ${tanggal}`,
            `Shift: ${shift}`,
            `Jam: ${jam}`,
            `Unit/Mesin: ${filters.nama_mesin}`,
            `Operator: ${operatorName}`,
            '',
            '',
            '',
            '',
            `${patrol_meta.form_number} · ${patrol_meta.revision}`,
        ]);
        sheetData.push([`Petunjuk: ${patrol_meta.instruction}`]);
        sheetData.push([
            'No',
            'Area',
            'Komponen / Peralatan',
            ...patrol_param_options.map((p) => p.label),
            'N',
            'TN',
            'Catatan / Tindakan Awal',
        ]);

        const firstItemRow = sheetData.length;

        for (const item of items) {
            sheetData.push([
                item.no,
                item.system,
                item.peralatan,
                ...patrol_param_options.map((p) =>
                    !item.applicable[p.key]
                        ? '—'
                        : item.checked[p.key]
                          ? '✓'
                          : '☐',
                ),
                item.status === 'N' ? '✓' : '',
                item.status === 'TN' ? '✓' : '',
                item.catatan || '',
            ]);
        }

        const lastItemRow = sheetData.length - 1;
        sheetData.push(['Catatan penting / tindak lanjut:', catatan || '-']);

        const colWidths = [5, 14, 28, 10, 10, 10, 11, 13, 6, 6, 32];
        const sheet = createSheet('Patrol Check Mesin', sheetData);
        const { workbook, worksheet } = sheet;

        await buildDocumentHeader(sheet, {
            totalCols,
            colWidths,
            titleLines: [
                'JASA PENDUKUNG TEKNIS 6 KIT',
                `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                patrol_meta.title.toUpperCase(),
            ],
            barTitle: `MESIN: ${filters.nama_mesin} (${patrol_meta.form_number} · ${patrol_meta.revision})`,
        });

        mergeCells(worksheet, 4, 4, 4, totalCols - 2);
        mergeCells(worksheet, 5, 0, 5, totalCols - 1);
        mergeCells(
            worksheet,
            lastItemRow + 1,
            1,
            lastItemRow + 1,
            totalCols - 1,
        );

        setColWidths(worksheet, colWidths);
        paintSheet(worksheet, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null;
            }

            if (r === 6) {
                return {
                    fill: '1F5C3A',
                    bold: true,
                    color: XLSX_COLORS.white,
                    align: 'center',
                    valign: 'center',
                    wrap: true,
                    border: true,
                };
            }

            if (r >= firstItemRow && r <= lastItemRow) {
                const rowItem = items[r - firstItemRow];

                if (rowItem?.status === 'TN') {
                    return {
                        ...(c === 2 || c === totalCols - 1
                            ? SPECS.cellLeft
                            : SPECS.cell),
                        fill: XLSX_COLORS.redLight,
                    };
                }

                return c === 2 || c === totalCols - 1
                    ? SPECS.cellLeft
                    : SPECS.cell;
            }

            return r === 4 || r === 5
                ? SPECS.note
                : { ...SPECS.cellLeft, bold: c === 0 };
        });

        await downloadWorkbook(
            workbook,
            `Patrol_Check_Mesin_${unit.name.replace(/\s+/g, '_')}_${filters.nama_mesin.replace(/\s+/g, '_')}_${tanggal}.xlsx`,
        );
    };

    const visibleItems = useMemo(
        () =>
            items
                .map((item, idx) => ({ item, idx }))
                .filter(
                    ({ item }) =>
                        areaFilter === 'ALL' || item.system === areaFilter,
                ),
        [items, areaFilter],
    );

    // ------------------------------------------------------------------
    // MOBILE COMPACT VIEW
    // ------------------------------------------------------------------
    if (compact) {
        return (
            <>
                <Head
                    title={`Patrol Check - ${filters.nama_mesin} - ${unit.name}`}
                />
                <div
                    className={`flex flex-col gap-3 p-4 ${can_write ? 'pb-28' : ''}`}
                >
                    <OperasiSelect
                        label="Mesin"
                        value={filters.nama_mesin}
                        onChange={(value) => {
                            const matched = options.machines.find(
                                (m) => m.name === value,
                            );
                            visit({
                                nama_mesin: value,
                                machine_id: matched ? matched.id : null,
                                entry_id: null,
                                new: 1,
                            });
                        }}
                        options={options.machines.map((m) => ({
                            value: m.name,
                            label: m.name,
                        }))}
                        className="w-full"
                    />

                    {/* Saved check switcher */}
                    <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                {patrol_meta.form_number} · {entries.length}{' '}
                                pemeriksaan
                            </span>
                            {can_write && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        active_entry.id === null
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="h-7 gap-1 px-2 text-xs"
                                    onClick={() =>
                                        visit({ entry_id: null, new: 1 })
                                    }
                                >
                                    <Plus className="size-3.5" />
                                    Baru
                                </Button>
                            )}
                        </div>

                        {entries.length > 0 && (
                            <div className="-mx-1 flex snap-x gap-1.5 overflow-x-auto px-1 pb-1">
                                {entries.map((entry) => {
                                    const active = active_entry.id === entry.id;

                                    return (
                                        <button
                                            key={entry.id}
                                            type="button"
                                            onClick={() =>
                                                visit({
                                                    entry_id: entry.id,
                                                    new: null,
                                                })
                                            }
                                            className={cn(
                                                'flex shrink-0 snap-start flex-col items-start rounded-lg border px-2.5 py-1.5 text-left text-[11.5px]',
                                                active
                                                    ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary'
                                                    : 'border-border bg-background text-muted-foreground',
                                            )}
                                        >
                                            <span className="font-semibold text-foreground">
                                                {entry.tanggal_label} ·{' '}
                                                {entry.jam}
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <span>{entry.shift}</span>
                                                <span className="text-emerald-600">
                                                    {entry.normal_count}N
                                                </span>
                                                {entry.tn_count > 0 && (
                                                    <span className="font-semibold text-rose-600">
                                                        {entry.tn_count}TN
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Check Identity Card */}
                    <div className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Identitas Patrol Check
                            </span>
                            <Badge
                                variant={
                                    shiftQuotaCount >= 2
                                        ? 'default'
                                        : 'secondary'
                                }
                                className={
                                    shiftQuotaCount >= 2
                                        ? 'bg-emerald-600 text-white'
                                        : ''
                                }
                            >
                                {shift.toLowerCase().startsWith('shift')
                                    ? shift
                                    : `Shift ${shift}`}
                                : {shiftQuotaCount}/2
                            </Badge>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <label className="flex flex-col gap-1 text-[11.5px] font-medium text-muted-foreground">
                                Tanggal
                                <Input
                                    type="date"
                                    value={tanggal}
                                    disabled={!can_write}
                                    onChange={(e) =>
                                        handleDateChange(e.target.value)
                                    }
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[11.5px] font-medium text-muted-foreground">
                                Jam
                                <Input
                                    type="time"
                                    value={jam}
                                    disabled={!can_write}
                                    onChange={(e) => {
                                        setJam(e.target.value);
                                        setDirty(true);
                                    }}
                                />
                            </label>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <div className="flex flex-col gap-1 text-[11.5px] font-medium text-muted-foreground">
                                <span>Shift (User)</span>
                                <div className="flex h-9 items-center rounded-md border border-border bg-muted/40 px-2.5 text-[12.5px] font-semibold text-foreground">
                                    {shift}
                                </div>
                            </div>
                            <div className="flex flex-col gap-1 text-[11.5px] font-medium text-muted-foreground">
                                <span>Operator (User)</span>
                                <div className="flex h-9 items-center truncate rounded-md border border-border bg-muted/40 px-2.5 text-[12.5px] font-semibold text-foreground">
                                    {operatorName}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Area Filter & Quick Action */}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex flex-wrap gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    areaFilter === 'ALL' ? 'default' : 'outline'
                                }
                                className="h-8 px-2.5 text-xs"
                                onClick={() => setAreaFilter('ALL')}
                            >
                                Semua ({items.length})
                            </Button>
                            {areas.map((area) => (
                                <Button
                                    key={area}
                                    type="button"
                                    size="sm"
                                    variant={
                                        areaFilter === area
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="h-8 px-2.5 text-xs"
                                    onClick={() => setAreaFilter(area)}
                                >
                                    {area}
                                </Button>
                            ))}
                        </div>
                        {can_write && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={markAllNormal}
                                className="h-8 border-emerald-600/40 text-xs text-emerald-700 dark:text-emerald-400"
                            >
                                <CheckCheck className="mr-1 size-3.5" />
                                Semua Normal
                            </Button>
                        )}
                    </div>

                    {/* Component Checklist Cards */}
                    <div className="flex flex-col gap-2.5">
                        {visibleItems.map(({ item, idx }) => {
                            const tnMissing =
                                item.status === 'TN' &&
                                item.catatan.trim() === '';

                            return (
                                <div
                                    key={`${item.no}-${item.peralatan}`}
                                    className={cn(
                                        'flex flex-col gap-2 rounded-xl border bg-card p-3',
                                        item.status === 'TN'
                                            ? 'border-rose-400 bg-rose-50/40 dark:bg-rose-950/20'
                                            : 'border-border',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-[11px] font-bold text-muted-foreground tabular-nums">
                                                    #{item.no}
                                                </span>
                                                <Badge
                                                    variant="secondary"
                                                    className="px-1.5 py-0 text-[10px]"
                                                >
                                                    {item.system}
                                                </Badge>
                                            </div>
                                            <p className="mt-0.5 text-[14px] font-semibold text-foreground">
                                                {item.peralatan}
                                            </p>
                                        </div>
                                        <ChoiceChips
                                            options={['N', 'TN']}
                                            value={item.status}
                                            disabled={!can_write}
                                            onChange={(val) =>
                                                setRowStatus(
                                                    idx,
                                                    (val || '') as
                                                        'N' | 'TN' | '',
                                                )
                                            }
                                            tone={(code) =>
                                                code === 'N'
                                                    ? 'border-emerald-600 bg-emerald-600 text-white'
                                                    : 'border-rose-600 bg-rose-600 text-white'
                                            }
                                        />
                                    </div>

                                    {/* Applicable parameters */}
                                    <div className="flex flex-wrap gap-1.5">
                                        {patrol_param_options.map((opt) => {
                                            const isApp = Boolean(
                                                item.applicable[opt.key],
                                            );
                                            const isChk = Boolean(
                                                item.checked[opt.key],
                                            );

                                            if (!isApp) {
                                                return null;
                                            }

                                            return (
                                                <button
                                                    key={opt.key}
                                                    type="button"
                                                    disabled={!can_write}
                                                    onClick={() =>
                                                        toggleParam(
                                                            idx,
                                                            opt.key,
                                                        )
                                                    }
                                                    className={cn(
                                                        'min-h-8 rounded-lg border px-2.5 text-[12px] font-medium transition active:scale-95',
                                                        isChk
                                                            ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200'
                                                            : 'border-border bg-background text-muted-foreground',
                                                    )}
                                                >
                                                    {isChk ? '✓ ' : ''}
                                                    {opt.label}
                                                </button>
                                            );
                                        })}
                                    </div>

                                    {item.status === 'TN' && (
                                        <div className="flex flex-col gap-2 rounded-lg border border-rose-300 bg-rose-50/70 p-2.5 dark:border-rose-700/60 dark:bg-rose-950/30">
                                            <label className="flex flex-col gap-1 text-[11.5px] font-semibold text-rose-900 dark:text-rose-200">
                                                Uraian Alasan / Tindakan Awal
                                                (Wajib)
                                                <Input
                                                    value={item.catatan}
                                                    disabled={!can_write}
                                                    placeholder="Tuliskan uraian alasan tidak normal & tindakan awal..."
                                                    onChange={(e) =>
                                                        updateRowNote(
                                                            idx,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={cn(
                                                        'h-8 bg-background text-[12.5px] font-normal text-foreground',
                                                        tnMissing &&
                                                            'border-rose-500 ring-1 ring-rose-500',
                                                    )}
                                                />
                                            </label>
                                            {tnMissing && (
                                                <span className="text-[11px] font-medium text-rose-600">
                                                    Kolom TN wajib diberi uraian
                                                    alasan / tindakan awal.
                                                </span>
                                            )}
                                            <div className="flex items-center justify-between gap-2 pt-0.5">
                                                <span className="text-[11.5px] font-semibold text-rose-900 dark:text-rose-200">
                                                    Eviden Foto
                                                </span>
                                                <EvidencePhotoField
                                                    label={`Eviden ${item.peralatan}`}
                                                    url={
                                                        item.eviden_url ?? null
                                                    }
                                                    file={files[idx] ?? null}
                                                    onPick={(file) =>
                                                        pickEviden(idx, file)
                                                    }
                                                    onRemove={() =>
                                                        removeEviden(idx)
                                                    }
                                                    disabled={!can_write}
                                                    size="sm"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <label className="flex flex-col gap-1.5 text-[12px] font-medium text-muted-foreground">
                        Catatan penting / tindak lanjut
                        <Textarea
                            value={catatan}
                            disabled={!can_write}
                            rows={3}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan penting atau tindak lanjut pemeriksaan..."
                        />
                    </label>

                    {can_write && active_entry.id !== null && (
                        <div className="flex justify-end">
                            {confirmDelete ? (
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setConfirmDelete(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={() =>
                                            handleDeleteEntry(active_entry.id!)
                                        }
                                    >
                                        Ya, Hapus Pemeriksaan Ini
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="gap-1.5 text-destructive"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 className="size-4" />
                                    Hapus Pemeriksaan Ini
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button
                            size="lg"
                            onClick={handleSave}
                            disabled={saving || missingTnItems.length > 0}
                        >
                            <Save className="size-4" />
                            {saving
                                ? 'Menyimpan…'
                                : active_entry.id
                                  ? 'Simpan Perubahan'
                                  : 'Simpan Pemeriksaan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    // ------------------------------------------------------------------
    // DESKTOP VIEW
    // ------------------------------------------------------------------
    return (
        <>
            <Head
                title={`Patrol Check Mesin - ${filters.nama_mesin} - ${unit.name}`}
            />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-col gap-4 p-4 sm:p-6">
                {/* Header */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        {can('operasi.input.view') && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    router.get(operasiInput.index().url)
                                }
                                title="Kembali"
                            >
                                <ArrowLeft className="size-4" />
                            </Button>
                        )}
                        <div>
                            <h1 className="text-xl font-bold tracking-tight">
                                Patrol Check Mesin &amp; Alat Bantu
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                {unit.name} · {filters.nama_mesin} ·{' '}
                                {patrol_meta.form_number} (
                                {patrol_meta.revision})
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500 text-amber-700"
                            >
                                Belum disimpan
                            </Badge>
                        ) : active_entry.id ? (
                            <Badge className="bg-emerald-600 text-white">
                                Tersimpan ({tanggal} {jam})
                            </Badge>
                        ) : (
                            <Badge variant="secondary">Pemeriksaan Baru</Badge>
                        )}

                        {can_write && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        visit({ entry_id: null, new: 1 })
                                    }
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Pemeriksaan Baru
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={
                                        saving || missingTnItems.length > 0
                                    }
                                    className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan Form'}
                                </Button>
                            </>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleExportExcel}
                            className="gap-1.5 border-emerald-600/30 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400"
                        >
                            <FileSpreadsheet className="size-4" />
                            Excel
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                window.open(
                                    patrolCheckMesin.pdf({
                                        query: {
                                            unit_id: filters.unit_id,
                                            year: filters.year,
                                            month: filters.month,
                                            machine_id: filters.machine_id,
                                            nama_mesin: filters.nama_mesin,
                                            ...(active_entry.id
                                                ? { entry_id: active_entry.id }
                                                : {}),
                                        },
                                    }).url,
                                    '_blank',
                                )
                            }
                            className="gap-1.5 border-red-600/30 text-red-700 hover:bg-red-50 dark:text-red-400"
                        >
                            <Download className="size-4" />
                            PDF
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5"
                        >
                            <Printer className="size-4" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters & History List */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Layanan"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({
                                unit_id: Number(value),
                                machine_id: null,
                                nama_mesin: '',
                                entry_id: null,
                            })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                        className="w-56"
                    />
                    <OperasiSelect
                        label="Mesin Pembangkit"
                        value={filters.nama_mesin}
                        onChange={(value) => {
                            const matched = options.machines.find(
                                (m) => m.name === value,
                            );
                            visit({
                                nama_mesin: value,
                                machine_id: matched ? matched.id : null,
                                entry_id: null,
                            });
                        }}
                        options={options.machines.map((m) => ({
                            value: m.name,
                            label: m.name,
                        }))}
                        className="w-52"
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) =>
                            visit({ month: Number(value), entry_id: null })
                        }
                        options={OPERASI_MONTHS.map((label, idx) => ({
                            value: String(idx + 1),
                            label,
                        }))}
                        className="w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) =>
                            visit({ year: Number(value), entry_id: null })
                        }
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                        className="w-28"
                    />
                    <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                        <Clock className="size-4" />
                        <span>
                            {shift.toLowerCase().startsWith('shift')
                                ? shift
                                : `Shift ${shift}`}{' '}
                            ({tanggal}):{' '}
                            <strong
                                className={
                                    shiftQuotaCount >= 2
                                        ? 'text-emerald-600'
                                        : 'text-amber-600'
                                }
                            >
                                {shiftQuotaCount}/2 pemeriksaan
                            </strong>
                        </span>
                    </div>
                </div>

                {/* Saved Entries Strip */}
                {entries.length > 0 && (
                    <div className="no-print flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/20 p-2.5">
                        <span className="text-xs font-semibold text-muted-foreground">
                            Pemeriksaan {periodLabel} ({entries.length}):
                        </span>
                        {entries.map((entry) => {
                            const active = active_entry.id === entry.id;

                            return (
                                <div
                                    key={entry.id}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition',
                                        active
                                            ? 'border-primary bg-primary/10 font-semibold text-foreground ring-1 ring-primary'
                                            : 'border-border bg-card text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            visit({
                                                entry_id: entry.id,
                                                new: null,
                                            })
                                        }
                                        className="flex items-center gap-1.5"
                                    >
                                        <span>
                                            {entry.tanggal_label} ·{' '}
                                            {entry.shift} ({entry.jam})
                                        </span>
                                        <span className="text-emerald-600">
                                            {entry.normal_count}N
                                        </span>
                                        {entry.tn_count > 0 && (
                                            <span className="font-bold text-rose-600">
                                                {entry.tn_count}TN
                                            </span>
                                        )}
                                    </button>
                                    {can_write && (
                                        <button
                                            type="button"
                                            title="Hapus pemeriksaan"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Hapus pemeriksaan ${entry.tanggal_label} Shift ${entry.shift} (${entry.jam})?`,
                                                    )
                                                ) {
                                                    handleDeleteEntry(entry.id);
                                                }
                                            }}
                                            className="ml-1 rounded p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="ml-auto h-7 gap-1 text-xs"
                            onClick={() =>
                                window.open(
                                    patrolCheckMesin.pdf({
                                        query: {
                                            unit_id: filters.unit_id,
                                            year: filters.year,
                                            month: filters.month,
                                            machine_id: filters.machine_id,
                                            nama_mesin: filters.nama_mesin,
                                        },
                                    }).url,
                                    '_blank',
                                )
                            }
                        >
                            <Download className="size-3.5" />
                            PDF Bulan Ini ({entries.length} hal.)
                        </Button>
                    </div>
                )}

                {/* Main Sheet */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Header with PLN & MKP Logos */}
                    <div className="mb-3 grid grid-cols-12 items-stretch border-2 border-black dark:border-white">
                        <div className="col-span-2 flex items-center justify-center border-r-2 border-black p-2 dark:border-white">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="max-h-12 w-auto object-contain"
                            />
                        </div>
                        <div className="col-span-8 flex flex-col justify-center text-center">
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                JASA PENDUKUNG TEKNIS 6 KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold tracking-wide text-foreground uppercase">
                                {patrol_meta.title}
                            </div>
                        </div>
                        <div className="col-span-2 flex items-center justify-center border-l-2 border-black p-2 dark:border-white">
                            <img
                                src="/logo/mkp.jpg"
                                alt="MKP"
                                className="max-h-12 w-auto object-contain"
                            />
                        </div>
                    </div>

                    {/* Meta Strip (Tanggal / Shift / Jam / Unit-Mesin / Operator / No. Form) */}
                    <div className="mb-2 grid grid-cols-2 gap-2 rounded border border-black bg-muted/20 p-2 text-xs md:grid-cols-6 dark:border-white">
                        <label className="flex flex-col gap-1 font-semibold">
                            Tanggal
                            <Input
                                type="date"
                                value={tanggal}
                                disabled={!can_write}
                                onChange={(e) =>
                                    handleDateChange(e.target.value)
                                }
                                className="h-7 bg-background text-xs"
                            />
                        </label>
                        <div className="flex flex-col justify-between gap-1 font-semibold">
                            <span>Shift</span>
                            <div
                                className="flex h-7 items-center truncate rounded border border-border bg-muted/40 px-2 font-bold"
                                title="Diambil otomatis dari data shift / jadwal user"
                            >
                                {shift}
                            </div>
                        </div>
                        <label className="flex flex-col gap-1 font-semibold">
                            Jam
                            <Input
                                type="time"
                                value={jam}
                                disabled={!can_write}
                                onChange={(e) => {
                                    setJam(e.target.value);
                                    setDirty(true);
                                }}
                                className="h-7 bg-background text-xs"
                            />
                        </label>
                        <div className="flex flex-col justify-between gap-1 font-semibold">
                            <span>Unit / Mesin</span>
                            <div className="flex h-7 items-center rounded border border-border bg-muted/40 px-2 font-bold">
                                {filters.nama_mesin}
                            </div>
                        </div>
                        <div className="flex flex-col justify-between gap-1 font-semibold">
                            <span>Operator</span>
                            <div
                                className="flex h-7 items-center truncate rounded border border-border bg-muted/40 px-2 font-bold"
                                title="Diambil otomatis dari data pegawai / akun user"
                            >
                                {operatorName}
                            </div>
                        </div>
                        <div className="flex flex-col justify-between gap-1 text-right">
                            <span className="font-semibold text-muted-foreground">
                                Kode Dokumen
                            </span>
                            <div className="flex h-7 items-center justify-end gap-1.5 font-bold text-emerald-800 dark:text-emerald-300">
                                <span>{patrol_meta.form_number}</span>
                                <span>·</span>
                                <span>{patrol_meta.revision}</span>
                            </div>
                        </div>
                    </div>

                    {/* Instruction + Quick Fill */}
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <p className="text-muted-foreground italic">
                            <strong className="text-foreground not-italic">
                                Petunjuk:
                            </strong>{' '}
                            {patrol_meta.instruction}
                        </p>
                        {can_write && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={markAllNormal}
                                className="no-print h-7 gap-1.5 border-emerald-600/40 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400"
                            >
                                <CheckCheck className="size-3.5" />
                                Tandai Normal Semua
                            </Button>
                        )}
                    </div>

                    {missingTnItems.length > 0 && (
                        <div className="no-print mb-2 flex items-center gap-2 rounded-md border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 dark:border-rose-700 dark:bg-rose-950/30 dark:text-rose-200">
                            <AlertTriangle className="size-4 shrink-0" />
                            <span>
                                {missingTnItems.length} komponen berstatus{' '}
                                <strong>TN</strong> belum memiliki catatan /
                                tindakan awal.
                            </span>
                        </div>
                    )}

                    {/* Main Checklist Table */}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full min-w-[960px] border-collapse border-2 border-black text-xs dark:border-white">
                            <thead className="print-green-head bg-[#1f5c3a] text-white">
                                <tr>
                                    <th className="w-10 border border-black px-2 py-2 text-center dark:border-white">
                                        No
                                    </th>
                                    <th className="w-28 border border-black px-2 py-2 text-center dark:border-white">
                                        Area
                                    </th>
                                    <th className="min-w-52 border border-black px-2.5 py-2 text-left dark:border-white">
                                        Komponen / Peralatan
                                    </th>
                                    {patrol_param_options.map((opt) => (
                                        <th
                                            key={opt.key}
                                            className="w-20 border border-black px-1.5 py-2 text-center dark:border-white"
                                        >
                                            {opt.label}
                                        </th>
                                    ))}
                                    <th className="w-12 border border-black px-1 py-2 text-center dark:border-white">
                                        N
                                    </th>
                                    <th className="w-12 border border-black px-1 py-2 text-center dark:border-white">
                                        TN
                                    </th>
                                    <th className="min-w-64 border border-black px-2.5 py-2 text-left dark:border-white">
                                        Uraian Alasan / Tindakan Awal &amp;
                                        Eviden Foto (Khusus TN)
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item, idx) => {
                                    const areaUpper = item.system.toUpperCase();
                                    const areaTone =
                                        areaUpper === 'MESIN'
                                            ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                                            : areaUpper === 'ALAT BANTU'
                                              ? 'bg-sky-50 text-sky-900 dark:bg-sky-950/40 dark:text-sky-200'
                                              : 'bg-muted/30';
                                    const tnMissing =
                                        item.status === 'TN' &&
                                        item.catatan.trim() === '';

                                    return (
                                        <tr
                                            key={`${item.no}-${item.peralatan}`}
                                            className={cn(
                                                'hover:bg-muted/20',
                                                item.status === 'TN' &&
                                                    'bg-rose-50/70 dark:bg-rose-950/20',
                                            )}
                                        >
                                            <td className="border border-black px-2 py-1.5 text-center text-muted-foreground tabular-nums dark:border-white/50">
                                                {item.no}
                                            </td>
                                            <td
                                                className={cn(
                                                    'border border-black px-2 py-1.5 text-center text-[11px] font-bold dark:border-white/50',
                                                    areaTone,
                                                )}
                                            >
                                                {item.system}
                                            </td>
                                            <td className="border border-black px-2.5 py-1.5 font-medium dark:border-white/50">
                                                {item.peralatan}
                                            </td>
                                            {patrol_param_options.map((opt) => {
                                                const isApp = Boolean(
                                                    item.applicable[opt.key],
                                                );
                                                const isChk = Boolean(
                                                    item.checked[opt.key],
                                                );

                                                return (
                                                    <td
                                                        key={opt.key}
                                                        className="border border-black p-1 text-center dark:border-white/50"
                                                    >
                                                        {isApp ? (
                                                            <div className="flex justify-center">
                                                                <Checkbox
                                                                    checked={
                                                                        isChk
                                                                    }
                                                                    disabled={
                                                                        !can_write
                                                                    }
                                                                    aria-label={`${opt.label} ${item.peralatan}`}
                                                                    onCheckedChange={() =>
                                                                        toggleParam(
                                                                            idx,
                                                                            opt.key,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        ) : (
                                                            <span className="font-bold text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                            <td className="border border-black p-1 text-center dark:border-white/50">
                                                <div className="flex justify-center">
                                                    <Checkbox
                                                        checked={
                                                            item.status === 'N'
                                                        }
                                                        disabled={!can_write}
                                                        aria-label={`Normal ${item.peralatan}`}
                                                        onCheckedChange={() =>
                                                            setRowStatus(
                                                                idx,
                                                                'N',
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </td>
                                            <td className="border border-black p-1 text-center dark:border-white/50">
                                                <div className="flex justify-center">
                                                    <Checkbox
                                                        checked={
                                                            item.status === 'TN'
                                                        }
                                                        disabled={!can_write}
                                                        aria-label={`Tidak Normal ${item.peralatan}`}
                                                        onCheckedChange={() =>
                                                            setRowStatus(
                                                                idx,
                                                                'TN',
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </td>
                                            <td className="border border-black p-1.5 dark:border-white/50">
                                                {item.status === 'TN' ? (
                                                    <div className="flex items-center gap-2">
                                                        <Input
                                                            value={item.catatan}
                                                            disabled={
                                                                !can_write
                                                            }
                                                            placeholder="Wajib: uraian alasan & tindakan awal..."
                                                            onChange={(e) =>
                                                                updateRowNote(
                                                                    idx,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className={cn(
                                                                'h-8 flex-1 bg-background px-2 text-xs',
                                                                tnMissing &&
                                                                    'border-rose-500 ring-1 ring-rose-500 placeholder:text-rose-600',
                                                            )}
                                                        />
                                                        <EvidencePhotoField
                                                            label={`Eviden ${item.peralatan}`}
                                                            url={
                                                                item.eviden_url ??
                                                                null
                                                            }
                                                            file={
                                                                files[idx] ??
                                                                null
                                                            }
                                                            onPick={(file) =>
                                                                pickEviden(
                                                                    idx,
                                                                    file,
                                                                )
                                                            }
                                                            onRemove={() =>
                                                                removeEviden(
                                                                    idx,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            size="sm"
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="px-2 text-center text-muted-foreground select-none">
                                                        —
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer Notes (NO Signature Block per User Request #5) */}
                    <div className="mt-3 rounded border border-black p-2.5 dark:border-white">
                        <div className="mb-1 flex items-center justify-between text-xs font-bold">
                            <span>Catatan penting / tindak lanjut:</span>
                            <span className="no-print flex items-center gap-1 text-[11px] font-normal text-muted-foreground">
                                <CheckCircle2 className="size-3.5 text-emerald-600" />
                                Pengesahan / tanda tangan digabung di Laporan
                                Project Operasi.
                            </span>
                        </div>
                        <Textarea
                            value={catatan}
                            disabled={!can_write}
                            rows={2}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan penting atau tindak lanjut pemeriksaan patrol check..."
                            className="text-xs"
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

PatrolCheckChecklistView.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Patrol Check Mesin', href: patrolCheckMesin.index() },
    ],
};
