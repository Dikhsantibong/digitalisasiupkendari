import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCheck,
    Download,
    FileSpreadsheet,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Search,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    MobileTimelineForm,
    monthTimelineDays,
    RENC_REAL,
    TimelineField,
} from '@/components/mobile/timeline-form';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { AutoWrapCellInput } from '@/components/ui/auto-wrap-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import type { StyleSpec } from '@/lib/jadwal-excel';
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
import jadwal from '@/routes/operasi/jadwal';
import program from '@/routes/operasi/jadwal/program-5s-5r';
import type { IdName } from '@/types';

type EmployeeOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    regu: string | null;
    is_shift_leader: boolean;
};

type ServerRow = {
    id: number | null;
    employee_id: number | null;
    shift: string | null;
    pelaksana: string;
    rencana: number[];
    realisasi: number[];
    target: number;
};
type Row = ServerRow & { _key: number };
type Cat = 'rencana' | 'realisasi';

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
        shifts: string[];
        operators: EmployeeOption[];
        employees: EmployeeOption[];
    };
    days_in_month: number;
    rows: ServerRow[];
    can_write: boolean;
};

const PRINT_CSS = `@media print { @page { size:A4 landscape; margin:6mm; } body *{visibility:hidden!important;} .print-container,.print-container *{visibility:visible!important;} .print-container{position:absolute!important;left:0!important;top:0!important;width:100%!important;} .no-print{display:none!important;} .p-table th,.p-table td{border:1px solid #000!important;font-size:7px!important;} .p-th{background:#ea7315!important;color:#fff!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} .p-done{background:#cbd5e1!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} }`;

const hydrate = (rows: ServerRow[]): Row[] =>
    rows.map((r, i) => ({
        ...r,
        employee_id: r.employee_id ?? null,
        shift: r.shift ?? null,
        _key: i,
    }));

function OperatorPicker({
    operators,
    employees,
    shifts,
    selectedEmployeeId,
    onSelect,
}: {
    operators: EmployeeOption[];
    employees: EmployeeOption[];
    shifts: string[];
    selectedEmployeeId: number | null;
    onSelect: (emp: EmployeeOption) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const q = query.trim().toLowerCase();
    const matches = (emp: EmployeeOption) =>
        q === '' ||
        emp.name.toLowerCase().includes(q) ||
        (emp.position ?? '').toLowerCase().includes(q) ||
        (emp.regu ? `shift ${emp.regu} regu ${emp.regu}`.toLowerCase().includes(q) : false);

    const groupedOperators = shifts
        .map((shift) => ({
            shift,
            items: operators.filter((op) => op.regu === shift && matches(op)),
        }))
        .filter((g) => g.items.length > 0);

    const otherList = employees.filter(matches);
    const hasAny = groupedOperators.length > 0 || otherList.length > 0;

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    setQuery('');
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title="Pilih dari operator shift / pegawai unit"
                    aria-label="Pilih operator atau pegawai"
                    className="no-print size-6 shrink-0 text-muted-foreground hover:text-primary"
                >
                    <Users className="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-72 p-0"
                collisionPadding={8}
            >
                <div className="border-b border-border p-2">
                    <div className="flex items-center gap-1.5 rounded-md border border-border bg-background px-2 py-1">
                        <Search className="size-3.5 shrink-0 text-muted-foreground" />
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari operator atau shift…"
                            className="w-full bg-transparent text-xs outline-none placeholder:text-muted-foreground"
                        />
                    </div>
                </div>
                <div className="max-h-64 overflow-y-auto p-1">
                    {!hasAny && (
                        <p className="px-2 py-4 text-center text-xs text-muted-foreground">
                            Tidak ditemukan. Anda dapat mengetik pelaksana
                            custom langsung pada kolom.
                        </p>
                    )}
                    {groupedOperators.map((group) => (
                        <div key={group.shift} className="mb-1 last:mb-0">
                            <p className="px-2 py-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Operator Shift {group.shift}
                            </p>
                            {group.items.map((emp) => (
                                <button
                                    key={emp.id}
                                    type="button"
                                    onClick={() => {
                                        onSelect(emp);
                                        setOpen(false);
                                    }}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent',
                                        selectedEmployeeId === emp.id &&
                                            'bg-primary/10 font-semibold text-primary',
                                    )}
                                >
                                    <span className="truncate">{emp.name}</span>
                                    <span className="shrink-0 text-[10px] text-muted-foreground">
                                        {emp.is_shift_leader
                                            ? 'Leader'
                                            : `Shift ${emp.regu}`}
                                    </span>
                                </button>
                            ))}
                        </div>
                    ))}
                    {otherList.length > 0 && (
                        <div className="mt-1 border-t border-border pt-1">
                            <p className="px-2 py-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Pegawai Unit Lainnya
                            </p>
                            {otherList.map((emp) => (
                                <button
                                    key={emp.id}
                                    type="button"
                                    onClick={() => {
                                        onSelect(emp);
                                        setOpen(false);
                                    }}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent',
                                        selectedEmployeeId === emp.id &&
                                            'bg-primary/10 font-semibold text-primary',
                                    )}
                                >
                                    <span className="truncate">{emp.name}</span>
                                    {emp.position && (
                                        <span className="max-w-28 truncate text-[10px] text-muted-foreground">
                                            {emp.position}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <div className="border-t border-border bg-muted/40 px-2.5 py-1.5 text-[10.5px] text-muted-foreground">
                    Atau ketik langsung nama pelaksana custom di kolom teks.
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function Program5s5rPage({
    unit,
    filters,
    options,
    days_in_month,
    rows: initial,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initial));
    const compact = useCompactLayout();
    const [mobileEditing, setMobileEditing] = useState(false);
    const timelineDays = useMemo(
        () => monthTimelineDays(filters.year, filters.month, days_in_month),
        [filters.year, filters.month, days_in_month],
    );
    const [nextKey, setNextKey] = useState(initial.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const sig = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [last, setLast] = useState(sig);

    if (last !== sig) {
        setLast(sig);
        setRows(hydrate(initial));
        setNextKey(initial.length);
        setDirty(false);
    }

    const monthName = useMemo(
        () => OPERASI_MONTHS[filters.month - 1] ?? '',
        [filters.month],
    );
    const days = useMemo(
        () => Array.from({ length: days_in_month }, (_, i) => i + 1),
        [days_in_month],
    );

    const visit = (p: Partial<Props['filters']>) =>
        router.get(
            program.index().url,
            { ...filters, ...p },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const toggle = (key: number, cat: Cat, day: number) => {
        if (!can_write) {
            return;
        }

        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== key) {
                    return r;
                }

                const s = new Set(r[cat]);
                s.has(day) ? s.delete(day) : s.add(day);

                return { ...r, [cat]: Array.from(s).sort((a, b) => a - b) };
            }),
        );
        setDirty(true);
    };

    const setField = (
        key: number,
        f: 'pelaksana' | 'target',
        v: string,
    ) => {
        setRows((p) =>
            p.map((r) => {
                if (r._key !== key) {
                    return r;
                }

                if (f === 'target') {
                    return { ...r, target: Math.max(0, parseInt(v, 10) || 0) };
                }

                // Typing a custom pelaksana name detaches the linked shift operator.
                return { ...r, pelaksana: v, employee_id: null, shift: null };
            }),
        );
        setDirty(true);
    };

    const assignEmployee = (key: number, emp: EmployeeOption) => {
        setRows((p) =>
            p.map((r) =>
                r._key === key
                    ? {
                          ...r,
                          employee_id: emp.id,
                          shift: emp.regu,
                          pelaksana: emp.name,
                      }
                    : r,
            ),
        );
        setDirty(true);
    };

    const addRow = () => {
        setRows((p) => [
            ...p,
            {
                _key: nextKey,
                id: null,
                employee_id: null,
                shift: null,
                pelaksana: '',
                rencana: [],
                realisasi: [],
                target: 4,
            },
        ]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    /**
     * Load / sync shift operators of the unit into the schedule:
     * adds any shift operator not yet listed, preserving existing dates & custom rows.
     */
    const loadShiftOperators = () => {
        if (!can_write || options.operators.length === 0) {
            return;
        }

        const existingEmpIds = new Set(
            rows.map((r) => r.employee_id).filter((id): id is number => id !== null),
        );
        const existingNames = new Set(
            rows.map((r) => r.pelaksana.trim().toLowerCase()),
        );

        const missing = options.operators.filter(
            (op) =>
                !existingEmpIds.has(op.id) &&
                !existingNames.has(op.name.trim().toLowerCase()),
        );

        if (missing.length === 0) {
            toast.info(
                'Seluruh operator shift pada unit ini sudah ada di dalam jadwal.',
            );

            return;
        }

        let cursor = nextKey;
        const added: Row[] = missing.map((op) => ({
            _key: cursor++,
            id: null,
            employee_id: op.id,
            shift: op.regu,
            pelaksana: op.name,
            rencana: [],
            realisasi: [],
            target: 4,
        }));

        setRows((prev) => [...prev, ...added]);
        setNextKey(cursor);
        setDirty(true);
        toast.success(
            `${added.length} operator shift berhasil ditambahkan ke jadwal.`,
        );
    };

    const remove = (key: number) => {
        setRows((p) => p.filter((r) => r._key !== key));
        setDirty(true);
    };

    const markPlan = () => {
        if (!can_write) {
            return;
        }

        setRows((p) => p.map((r) => ({ ...r, rencana: days.slice() })));
        setDirty(true);
    };

    const reset = () => {
        setRows(hydrate(initial));
        setDirty(false);
    };

    const save = () => {
        if (!can_write) {
            return;
        }

        if (rows.some((r) => r.pelaksana.trim() === '')) {
            toast.error(
                'Nama pelaksana tidak boleh kosong. Isi pelaksana atau hapus baris kosong.',
            );

            return;
        }

        setSaving(true);
        router.post(
            program.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    id: r.id,
                    employee_id: r.employee_id,
                    shift: r.shift,
                    pelaksana: r.pelaksana.trim(),
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    target: r.target,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleExcel = async () => {
        const H: (string | number)[][] = [[], [], [], []];
        H.push([
            'PELAKSANA',
            'STATUS',
            ...days.map(String),
            'RENCANA',
            'TARGET',
            'REALISASI',
            'A. KINERJA',
        ]);
        rows.forEach((r) => {
            const kin =
                r.target > 0
                    ? Math.round((r.realisasi.length / r.target) * 100)
                    : 0;
            H.push([
                r.pelaksana,
                'RENCANA',
                ...days.map((d) => (r.rencana.includes(d) ? 1 : '')),
                r.rencana.length,
                r.target,
                r.realisasi.length,
                `${kin}%`,
            ]);
            H.push([
                '',
                'REALISASI',
                ...days.map((d) => (r.realisasi.includes(d) ? 1 : '')),
                '',
                '',
                '',
                '',
            ]);
        });
        const { workbook, worksheet: ws } = createSheet('5S5R', H);
        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colRenc = dayStart + days.length;
        const colKinerja = colRenc + 3;
        const totalCols = colKinerja + 1;
        const done: StyleSpec = {
            fill: XLSX_COLORS.slate,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };
        paintSheet(ws, H.length, totalCols, (r, c) => {
            if (r < 4) {
                return null;
            }

            if (r === 4) {
                return SPECS.headerOrange;
            }

            if (c === 0) {
                return SPECS.cellLeft;
            }

            if (c === 1) {
                return SPECS.labelCenter;
            }

            if (c >= dayStart && c <= dayEnd) {
                return H[r]?.[c] === 1 ? done : SPECS.cell;
            }

            return { ...SPECS.cell, bold: true };
        });
        rows.forEach((_, i) => {
            const top = 5 + i * 2;
            mergeCells(ws, top, 0, top + 1, 0);

            for (let c = colRenc; c <= colKinerja; c++) {
                mergeCells(ws, top, c, top + 1, c);
            }
        });
        const colWidths = [28, 12, ...Array(days.length).fill(3.2), 9, 8, 9, 11];
        setColWidths(ws, colWidths);
        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS 11 & 6 SITE - KIT',
                    `LAPORAN PROJECT — ${unit.name.toUpperCase()}`,
                    'JADWAL PELAKSANAAN 5S5R',
                ],
                barTitle: `JADWAL PELAKSANAAN 5S5R — ${monthName.toUpperCase()} ${filters.year}`,
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_5S5R_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    const th =
        'p-th border border-black bg-[#ea7315] p-1 text-center text-[10px] font-bold text-white';
    const dayCells = (r: Row, cat: Cat) =>
        days.map((d) => {
            const on = r[cat].includes(d);

            return (
                <td
                    key={`${cat}-${d}`}
                    onClick={() => toggle(r._key, cat, d)}
                    className={`border border-black text-center text-[11px] font-bold select-none ${on ? 'p-done bg-slate-300 dark:bg-slate-600' : 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}
                >
                    {on ? '1' : ''}
                </td>
            );
        });

    const allPickable = useMemo(
        () => [...options.operators, ...options.employees],
        [options.operators, options.employees],
    );

    return (
        <>
            <Head title={`Jadwal 5S5R — ${unit.name}`} />
            <style>{PRINT_CSS}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(jadwal.index().url)}
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Jadwal Pelaksanaan 5S5R Pembangkit
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pelaksana otomatis ditarik dari operator per
                                shift dan dapat di-custom (pilih pegawai atau
                                ketik manual). Klik sel RENCANA / REALISASI
                                untuk mengisi 1.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={reset}
                                disabled={saving}
                                className="gap-1.5 text-xs text-muted-foreground"
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <>
                                {options.operators.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={loadShiftOperators}
                                        className="gap-1.5 text-xs"
                                        title="Tambahkan seluruh operator shift aktif unit ini ke daftar pelaksana"
                                    >
                                        <UserCheck className="size-3.5 text-primary" />
                                        Muat Operator Shift
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={markPlan}
                                    className="gap-1.5 text-xs"
                                >
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Rencana Semua
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={addRow}
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Custom
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={save}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExcel}
                            className="gap-1.5 text-xs"
                        >
                            <FileSpreadsheet className="size-3.5 text-emerald-600" />
                            Excel
                        </Button>
                        <a
                            href={`${program.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5 text-xs"
                            >
                                <Download className="size-3.5 text-rose-600" />
                                PDF
                            </Button>
                        </a>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                        >
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(v) => visit({ unit_id: Number(v) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((l, i) => ({
                            value: String(i + 1),
                            label: l,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                <div className="print-container rounded-md border border-border bg-card">
                    <div className="border-b border-border p-3">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img
                                    src="/logo/sidebar-logo.png"
                                    alt="PLN"
                                    className="max-h-11 object-contain"
                                    onError={(e) => {
                                        (
                                            e.target as HTMLElement
                                        ).style.display = 'none';
                                    }}
                                />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold text-foreground uppercase">
                                    JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT
                                </div>
                                <div className="text-[11px] font-semibold text-foreground uppercase">
                                    LAPORAN PROJECT — {unit.name.toUpperCase()}
                                </div>
                                <div className="text-[11px] font-bold text-foreground uppercase">
                                    JADWAL PELAKSANAAN 5S5R —{' '}
                                    {monthName.toUpperCase()} {filters.year}
                                </div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img
                                    src="/logo/mkp.jpg"
                                    alt="MKP"
                                    className="max-h-11 object-contain"
                                    onError={(e) => {
                                        (
                                            e.target as HTMLElement
                                        ).style.display = 'none';
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                    {compact ? (
                        <div className="p-3">
                            <MobileTimelineForm<Row>
                                days={timelineDays}
                                rows={rows}
                                rowKey={(row) => row._key}
                                title={(row) => row.pelaksana || 'Pelaksana baru'}
                                categories={RENC_REAL}
                                isOn={(row, category, day) =>
                                    row[category as Cat].includes(day)
                                }
                                onToggle={(row, category, day) =>
                                    toggle(row._key, category as Cat, day)
                                }
                                details={(row) => (
                                    <>
                                        {can_write && allPickable.length > 0 && (
                                            <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                                Pilih Operator / Pegawai
                                                <select
                                                    value={
                                                        row.employee_id
                                                            ? String(
                                                                  row.employee_id,
                                                              )
                                                            : ''
                                                    }
                                                    onChange={(e) => {
                                                        const found =
                                                            allPickable.find(
                                                                (emp) =>
                                                                    String(
                                                                        emp.id,
                                                                    ) ===
                                                                    e.target
                                                                        .value,
                                                            );
                                                        if (found) {
                                                            assignEmployee(
                                                                row._key,
                                                                found,
                                                            );
                                                        }
                                                    }}
                                                    className="h-9 rounded-md border border-input bg-background px-2.5 text-sm text-foreground"
                                                >
                                                    <option value="">
                                                        — Custom / ketik manual
                                                        —
                                                    </option>
                                                    {options.shifts.map(
                                                        (shift) => {
                                                            const list =
                                                                options.operators.filter(
                                                                    (op) =>
                                                                        op.regu ===
                                                                        shift,
                                                                );
                                                            if (
                                                                list.length ===
                                                                0
                                                            ) {
                                                                return null;
                                                            }

                                                            return (
                                                                <optgroup
                                                                    key={shift}
                                                                    label={`Operator Shift ${shift}`}
                                                                >
                                                                    {list.map(
                                                                        (
                                                                            op,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    op.id
                                                                                }
                                                                                value={
                                                                                    op.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    op.name
                                                                                }
                                                                                {op.is_shift_leader
                                                                                    ? ' (Leader)'
                                                                                    : ''}
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </optgroup>
                                                            );
                                                        },
                                                    )}
                                                    {options.employees.length >
                                                        0 && (
                                                        <optgroup label="Pegawai Unit Lainnya">
                                                            {options.employees.map(
                                                                (emp) => (
                                                                    <option
                                                                        key={
                                                                            emp.id
                                                                        }
                                                                        value={
                                                                            emp.id
                                                                        }
                                                                    >
                                                                        {
                                                                            emp.name
                                                                        }
                                                                        {emp.position
                                                                            ? ` (${emp.position})`
                                                                            : ''}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </optgroup>
                                                    )}
                                                </select>
                                            </label>
                                        )}
                                        <TimelineField
                                            label="Pelaksana (bisa custom)"
                                            value={row.pelaksana}
                                            onChange={(v) =>
                                                setField(
                                                    row._key,
                                                    'pelaksana',
                                                    v,
                                                )
                                            }
                                            readOnly={!can_write}
                                        />
                                        <TimelineField
                                            label="Target"
                                            type="number"
                                            value={row.target}
                                            onChange={(v) =>
                                                setField(row._key, 'target', v)
                                            }
                                            readOnly={!can_write}
                                        />
                                    </>
                                )}
                                summary={(row) =>
                                    `Rencana ${row.rencana.length} · Realisasi ${row.realisasi.length} / target ${row.target} · Kinerja ${row.target > 0 ? Math.round((row.realisasi.length / row.target) * 100) : 0}%`
                                }
                                onRemove={(row) => remove(row._key)}
                                readOnly={!can_write}
                                editing={mobileEditing}
                                onEditingChange={setMobileEditing}
                                empty="Belum ada pelaksana."
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="excel-sheet p-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th
                                            rowSpan={2}
                                            className={`${th} min-w-52 text-left whitespace-normal`}
                                        >
                                            Pelaksana
                                        </th>
                                        <th rowSpan={2} className={th}>
                                            Rencana / Realisasi
                                        </th>
                                        <th
                                            colSpan={days.length}
                                            className={th}
                                        >
                                            Bulan {monthName}
                                        </th>
                                        <th rowSpan={2} className={th}>
                                            Rencana
                                        </th>
                                        <th rowSpan={2} className={th}>
                                            Target
                                        </th>
                                        <th rowSpan={2} className={th}>
                                            Realisasi
                                        </th>
                                        <th rowSpan={2} className={th}>
                                            A. Kinerja
                                        </th>
                                        {can_write && (
                                            <th
                                                rowSpan={2}
                                                className={`${th} no-print`}
                                            >
                                                Aksi
                                            </th>
                                        )}
                                    </tr>
                                    <tr>
                                        {days.map((d) => (
                                            <th
                                                key={d}
                                                className={`${th} w-6`}
                                            >
                                                {d}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r) => {
                                        const rc = r.rencana.length;
                                        const re = r.realisasi.length;
                                        const kin =
                                            r.target > 0
                                                ? Math.round(
                                                      (re / r.target) * 100,
                                                  )
                                                : 0;

                                        return (
                                            <Fragment key={r._key}>
                                                <tr className="hover:bg-muted/10">
                                                    <td
                                                        rowSpan={2}
                                                        className="min-w-52 border border-black p-1 text-left align-middle whitespace-normal"
                                                    >
                                                        <div className="flex items-center gap-1">
                                                            <div className="min-w-0 flex-1">
                                                                <AutoWrapCellInput
                                                                    value={
                                                                        r.pelaksana
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        setField(
                                                                            r._key,
                                                                            'pelaksana',
                                                                            val,
                                                                        )
                                                                    }
                                                                    placeholder="Ketik pelaksana atau pilih →"
                                                                    className="font-semibold"
                                                                    readOnly={
                                                                        !can_write
                                                                    }
                                                                />
                                                            </div>
                                                            {can_write &&
                                                                allPickable.length >
                                                                    0 && (
                                                                    <OperatorPicker
                                                                        operators={
                                                                            options.operators
                                                                        }
                                                                        employees={
                                                                            options.employees
                                                                        }
                                                                        shifts={
                                                                            options.shifts
                                                                        }
                                                                        selectedEmployeeId={
                                                                            r.employee_id
                                                                        }
                                                                        onSelect={(
                                                                            emp,
                                                                        ) =>
                                                                            assignEmployee(
                                                                                r._key,
                                                                                emp,
                                                                            )
                                                                        }
                                                                    />
                                                                )}
                                                        </div>
                                                    </td>
                                                    <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold">
                                                        RENCANA
                                                    </td>
                                                    {dayCells(r, 'rencana')}
                                                    <td
                                                        rowSpan={2}
                                                        className="border border-black p-1 text-center align-middle font-bold"
                                                    >
                                                        {rc}
                                                    </td>
                                                    <td
                                                        rowSpan={2}
                                                        className="border border-black p-0 text-center align-middle"
                                                    >
                                                        {can_write ? (
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                value={
                                                                    r.target ||
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setField(
                                                                        r._key,
                                                                        'target',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="h-7 w-12 bg-transparent text-center text-xs font-bold focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                placeholder="0"
                                                            />
                                                        ) : (
                                                            r.target
                                                        )}
                                                    </td>
                                                    <td
                                                        rowSpan={2}
                                                        className="border border-black p-1 text-center align-middle font-extrabold"
                                                    >
                                                        {re}
                                                    </td>
                                                    <td
                                                        rowSpan={2}
                                                        className={`border border-black p-1 text-center align-middle font-extrabold ${kin >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}
                                                    >
                                                        {kin}%
                                                    </td>
                                                    {can_write && (
                                                        <td
                                                            rowSpan={2}
                                                            className="no-print border border-black p-0 text-center align-middle"
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-6 text-muted-foreground hover:text-destructive"
                                                                onClick={() =>
                                                                    remove(
                                                                        r._key,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </td>
                                                    )}
                                                </tr>
                                                <tr className="hover:bg-muted/10">
                                                    <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold">
                                                        REALISASI
                                                    </td>
                                                    {dayCells(r, 'realisasi')}
                                                </tr>
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Program5s5rPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Jadwal 5S5R', href: program.index() },
    ],
};
