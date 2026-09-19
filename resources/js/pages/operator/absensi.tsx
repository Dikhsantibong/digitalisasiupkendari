import { Head, router } from '@inertiajs/react';
import {
    CalendarCog,
    ChevronLeft,
    ChevronRight,
    Printer,
    Save,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
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
import { dashboard } from '@/routes';
import absensi from '@/routes/operator/absensi';
import type { IdName } from '@/types';
import { toast } from 'sonner';

type Day = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    holiday: string | null;
};

type Employee = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    regu: string | null;
    cells: Record<string, string> | Record<string, never>;
};

type Code = { code: string; label: string; type: string; hitung_hadir: boolean };

type Props = {
    filters: { unit_id: number; year: number; month: number; group_type: string };
    options: {
        units: IdName[];
        years: number[];
        group_types: { value: string; label: string }[];
    };
    days: Day[];
    employees: Employee[];
    codes: Code[];
    patterns: { regu: string; sequence: string }[];
    can_write: boolean;
};

type GridRow = {
    _key: number;
    nip: string | null;
    name: string;
    regu: string | null;
    [key: string]: string | number | null;
};

type SelectedCellState = {
    employeeId: number;
    employeeName: string;
    regu: string | null;
    day: number;
    dow: string;
    isWeekend: boolean;
    isHoliday: boolean;
    holiday: string | null;
    currentCode: string;
    rect: {
        top: number;
        bottom: number;
        left: number;
        right: number;
        width: number;
        height: number;
    };
};

const SHIFT_CYCLE = ['OFF', 'OFF', 'S', 'S', 'P', 'P', 'M', 'M'] as const;

type CyclePhase = {
    index: number;
    code: string;
    label: string;
    sublabel: string;
};

const CYCLE_PHASES: CyclePhase[] = [
    { index: 0, code: 'OFF', label: 'OFF (Hari ke-1)', sublabel: 'OFF → OFF → S → S → P → P → M → M' },
    { index: 1, code: 'OFF', label: 'OFF (Hari ke-2)', sublabel: 'OFF → S → S → P → P → M → M → OFF' },
    { index: 2, code: 'S', label: 'Sore (Hari ke-1)', sublabel: 'S → S → P → P → M → M → OFF → OFF' },
    { index: 3, code: 'S', label: 'Sore (Hari ke-2)', sublabel: 'S → P → P → M → M → OFF → OFF → S' },
    { index: 4, code: 'P', label: 'Pagi (Hari ke-1)', sublabel: 'P → P → M → M → OFF → OFF → S → S' },
    { index: 5, code: 'P', label: 'Pagi (Hari ke-2)', sublabel: 'P → M → M → OFF → OFF → S → S → P' },
    { index: 6, code: 'M', label: 'Malam (Hari ke-1)', sublabel: 'M → M → OFF → OFF → S → S → P → P' },
    { index: 7, code: 'M', label: 'Malam (Hari ke-2)', sublabel: 'M → OFF → OFF → S → S → P → P → M' },
];

function normalizeShiftCode(val: unknown): string | null {
    if (!val) return null;
    const s = String(val).trim().toUpperCase();
    if (s === 'OFF' || s === 'OF' || s === 'LIBUR') return 'OFF';
    if (s === 'S' || s === 'SIANG' || s === 'SORE') return 'S';
    if (s === 'P' || s === 'PAGI') return 'P';
    if (s === 'M' || s === 'MALAM') return 'M';
    return null;
}

function detectStartIndex(d1: string, d2: string | null): number {
    if (d1 === 'OFF') {
        return d2 === 'S' ? 1 : 0;
    }
    if (d1 === 'S') {
        return d2 === 'P' ? 3 : 2;
    }
    if (d1 === 'P') {
        return d2 === 'M' ? 5 : 4;
    }
    if (d1 === 'M') {
        return d2 === 'OFF' ? 7 : 6;
    }
    return 0;
}

export const CODE_STYLES: Record<string, { bg: string; text: string; border: string; label: string }> = {
    P: { bg: '#ffffff', text: '#000000', border: '#94a3b8', label: 'Pagi' },
    S: { bg: '#00b0f0', text: '#000000', border: '#0090c8', label: 'Sore' },
    M: { bg: '#adadad', text: '#000000', border: '#8c8c8c', label: 'Malam' },
    OFF: { bg: '#c00000', text: '#ffffff', border: '#990000', label: 'Off / Libur' },
    C: { bg: '#000000', text: '#ffffff', border: '#000000', label: 'Cuti' },
    SKT: { bg: '#44b3e1', text: '#000000', border: '#309ac4', label: 'Sakit' },
    Sa: { bg: '#44b3e1', text: '#000000', border: '#309ac4', label: 'Sakit' },
    I: { bg: '#61cbf3', text: '#000000', border: '#42b4df', label: 'Izin' },
    A: { bg: '#404040', text: '#ffffff', border: '#262626', label: 'Alpha' },
};

export const getCodeStyle = (code: string | null | undefined) => {
    if (!code) return { bg: '#ffffff', text: '#000000', border: '#cbd5e1', label: '' };
    return CODE_STYLES[code] ?? { bg: '#f1f5f9', text: '#0f172a', border: '#cbd5e1', label: code };
};

const getCodeBadgeClass = (code: string): string => {
    switch (code) {
        case 'P':
            return 'bg-white text-black border-slate-400 font-black';
        case 'S':
            return 'bg-[#00b0f0] text-black border-[#0090c8] font-black';
        case 'M':
            return 'bg-[#adadad] text-black border-[#8c8c8c] font-black';
        case 'OFF':
            return 'bg-[#c00000] text-white border-[#990000] font-black';
        case 'C':
            return 'bg-black text-white border-black font-black';
        case 'SKT':
        case 'Sa':
            return 'bg-[#44b3e1] text-black border-[#309ac4] font-black';
        case 'I':
            return 'bg-[#61cbf3] text-black border-[#42b4df] font-black';
        case 'A':
            return 'bg-[#404040] text-white border-[#262626] font-black';
        default:
            return 'bg-muted text-muted-foreground border-border font-medium';
    }
};

const ABSENSI_STYLES = `
.operasi-grid .rdg {
    --rdg-header-row-height: 36px;
}
.operasi-grid .rdg-cell {
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    cursor: pointer;
}
.operasi-grid .rdg-name-cell {
    text-align: left;
    justify-content: flex-start;
    padding-left: 8px !important;
    cursor: default;
}
.operasi-grid .rdg-derived-cell {
    cursor: default;
    font-weight: 600;
}
.operasi-grid .rdg-cell:hover {
    background-color: rgba(0, 176, 240, 0.12);
}

/* Purple Headers (NIP, NAMA, REGU, % Hadir) */
.operasi-grid .rdg-purple-hdr {
    background-color: #7030a0 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-right: 1px solid rgba(255, 255, 255, 0.25) !important;
    text-align: center;
    padding: 0 !important;
}

/* National Holiday Header & Cell */
.operasi-grid .rdg-holiday-hdr {
    background-color: #ff0000 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    border-right: 1px solid rgba(255, 255, 255, 0.3) !important;
    text-align: center;
    padding: 0 !important;
}
.operasi-grid .rdg-holiday-cell {
    background-color: #fff0f0 !important;
}

/* Weekend Header & Cell (Sabtu - Minggu) */
.operasi-grid .rdg-weekend-hdr {
    background-color: #ffc000 !important;
    color: #c00000 !important;
    font-weight: 700 !important;
    border-right: 1px solid #d99b00 !important;
    text-align: center;
    padding: 0 !important;
}
.operasi-grid .rdg-weekend-cell {
    background-color: #fffdf5 !important;
}

/* Weekday Day Header */
.operasi-grid .rdg-weekday-hdr {
    background-color: #ffffff !important;
    border-right: 1px solid #e2e8f0 !important;
    text-align: center;
    padding: 0 !important;
}

/* Recap Header & Derived Percent Cell */
.operasi-grid .rdg-recap-header {
    background-color: #f1f5f9;
    padding: 0 !important;
}
.operasi-grid .rdg-pct-cell {
    background-color: #fce4d6 !important;
    color: #000000 !important;
    font-weight: 800 !important;
}
`;

// react-data-grid is virtualised and prints poorly, so a plain table (hidden on
// screen) carries the printout. The visibility trick isolates it from the app.
const PRINT_STYLES = `
@media print {
    @page { size: landscape; margin: 8mm; }
    body * { visibility: hidden; }
    .absensi-print, .absensi-print * { visibility: visible; }
    .absensi-print { position: absolute; inset: 0; }
    .no-print { display: none !important; }
}
.absensi-print table { border-collapse: collapse; width: 100%; font-size: 9px; font-family: Arial, sans-serif; }
.absensi-print th, .absensi-print td { border: 1px solid #555; padding: 2px 2px; text-align: center; }
.absensi-print th.purple { background-color: #7030a0 !important; color: #ffffff !important; font-weight: bold; }
.absensi-print th.wknd { background-color: #ffc000 !important; color: #c00000 !important; font-weight: bold; }
.absensi-print th.hol { background-color: #ff0000 !important; color: #ffffff !important; font-weight: bold; }
.absensi-print td.n { text-align: left; white-space: nowrap; }
.absensi-print td.wknd-cell { background-color: #fffdf5; }
.absensi-print td.hol-cell { background-color: #fff0f0; }
.absensi-print td.pct-cell { background-color: #fce4d6; font-weight: bold; }
`;

const hydrate = (employees: Employee[], days: Day[]): GridRow[] =>
    employees.map((employee) => {
        const row: GridRow = {
            _key: employee.id,
            nip: employee.nip,
            name: employee.name,
            regu: employee.regu,
        };

        for (const { day } of days) {
            row[`d${day}`] = (employee.cells as Record<string, string>)[String(day)] ?? null;
        }

        return row;
    });

export default function AbsensiSchedule({ filters, options, days, employees, codes, patterns, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(() => hydrate(employees, days));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [selectedCell, setSelectedCell] = useState<SelectedCellState | null>(null);
    const [patternDialogOpen, setPatternDialogOpen] = useState(false);

    // Track starting phase index per Regu for advanced pattern generator dialog
    const [reguPhaseSettings, setReguPhaseSettings] = useState<Record<string, number>>(() => ({
        A: 0,
        B: 2,
        C: 4,
        D: 6,
    }));

    // Distinct list of regus in current employee list
    const distinctRegus = useMemo(() => {
        const set = new Set<string>();
        for (const emp of employees) {
            if (emp.regu) {
                set.add(emp.regu);
            }
        }
        return Array.from(set).sort();
    }, [employees]);

    // Re-hydrate when the filters change OR when the server sends new cells
    // (e.g. after "Generate pola" reloads the page).
    const signature = `${filters.unit_id}-${filters.year}-${filters.month}-${filters.group_type}-${JSON.stringify(
        employees.map((e) => [e.id, e.cells]),
    )}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(employees, days));
        setDirty(false);
    }

    const presentSet = useMemo(
        () => new Set(codes.filter((c) => c.hitung_hadir).map((c) => c.code)),
        [codes],
    );

    const dayKeys = useMemo(() => days.map((d) => `d${d.day}`), [days]);

    const recapFor = (row: GridRow, code: string): number =>
        dayKeys.reduce((sum, key) => sum + (row[key] === code ? 1 : 0), 0);

    const percentFor = (row: GridRow): string => {
        let present = 0;
        let scheduled = 0;

        for (const key of dayKeys) {
            const value = row[key];

            if (value === null || value === '') {
                continue;
            }

            scheduled += 1;

            if (presentSet.has(String(value))) {
                present += 1;
            }
        }

        return scheduled > 0 ? `${Math.round((present / scheduled) * 100)}%` : '—';
    };

    const columns = useMemo<readonly Column<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            {
                key: 'nip',
                name: 'NIP',
                frozen: true,
                width: 110,
                headerCellClass: 'rdg-purple-hdr',
                renderHeaderCell: () => (
                    <div className="w-full h-full flex items-center justify-center font-bold text-[11px] text-white select-none">
                        NIP
                    </div>
                ),
            },
            {
                key: 'name',
                name: 'Nama',
                frozen: true,
                minWidth: 190,
                headerCellClass: 'rdg-purple-hdr',
                cellClass: 'rdg-name-cell',
                renderHeaderCell: () => (
                    <div className="w-full h-full flex items-center justify-start pl-2 font-bold text-[11px] text-white select-none">
                        NAMA
                    </div>
                ),
            },
            {
                key: 'regu',
                name: 'Regu',
                frozen: true,
                width: 58,
                headerCellClass: 'rdg-purple-hdr',
                renderHeaderCell: () => (
                    <div className="w-full h-full flex items-center justify-center font-bold text-[11px] text-white select-none">
                        REGU
                    </div>
                ),
            },
        ];

        for (const day of days) {
            const toneHdr = day.is_holiday ? 'rdg-holiday-hdr' : day.is_weekend ? 'rdg-weekend-hdr' : 'rdg-weekday-hdr';
            const toneCell = day.is_holiday ? 'rdg-holiday-cell' : day.is_weekend ? 'rdg-weekend-cell' : '';
            cols.push({
                key: `d${day.day}`,
                name: `${day.day} ${day.dow}`,
                width: 44,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: toneHdr,
                cellClass: toneCell || undefined,
                renderHeaderCell: () => {
                    const isHol = day.is_holiday;
                    const isWk = day.is_weekend;
                    return (
                        <div className="flex flex-col items-center justify-center leading-none py-1 select-none w-full h-full">
                            <span
                                className={`text-[9px] uppercase font-bold tracking-tight ${
                                    isHol ? 'text-white' : isWk ? 'text-amber-950' : 'text-slate-500'
                                }`}
                            >
                                {day.dow}
                            </span>
                            <span
                                className={`text-[12px] font-black mt-0.5 ${
                                    isHol ? 'text-white' : isWk ? 'text-red-600' : 'text-slate-800'
                                }`}
                            >
                                {String(day.day).padStart(2, '0')}
                            </span>
                        </div>
                    );
                },
                renderCell: ({ row }) => {
                    const val = row[`d${day.day}`];
                    if (!val) {
                        return can_write ? (
                            <span className="text-muted-foreground/30 text-[10px] select-none">·</span>
                        ) : null;
                    }
                    const code = String(val);
                    const style = getCodeStyle(code);
                    return (
                        <span
                            style={{
                                backgroundColor: style.bg,
                                color: style.text,
                                borderColor: style.border,
                            }}
                            className="inline-flex items-center justify-center rounded-xs px-1 min-w-[26px] h-[20px] text-[11px] font-black border shadow-2xs leading-none select-none tracking-tight"
                        >
                            {code}
                        </span>
                    );
                },
            });
        }

        for (const code of codes) {
            const style = getCodeStyle(code.code);
            cols.push({
                key: `r_${code.code}`,
                name: code.code,
                width: 44,
                headerCellClass: 'rdg-recap-header',
                cellClass: 'rdg-derived-cell',
                renderHeaderCell: () => (
                    <div
                        style={{ backgroundColor: style.bg, color: style.text, borderColor: style.border }}
                        className="w-full h-full flex items-center justify-center font-bold text-[11px] border-b select-none"
                        title={`${code.code}: ${code.label}`}
                    >
                        {code.code}
                    </div>
                ),
                renderCell: ({ row }) => {
                    const val = recapFor(row, code.code);
                    return (
                        <span className={val > 0 ? 'font-bold text-foreground text-[11px]' : 'text-muted-foreground/30 text-[11px]'}>
                            {val || ''}
                        </span>
                    );
                },
            });
        }

        cols.push({
            key: 'pct',
            name: '% Hadir',
            width: 72,
            headerCellClass: 'rdg-purple-hdr',
            cellClass: 'rdg-derived-cell rdg-pct-cell',
            renderHeaderCell: () => (
                <div className="w-full h-full flex items-center justify-center text-center font-bold text-[10px] text-white leading-tight uppercase select-none">
                    % Hadir
                </div>
            ),
            renderCell: ({ row }) => <span className="font-black text-slate-900 text-[11px]">{percentFor(row)}</span>,
        });

        return cols;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [days, codes, can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(normalise(next));
            setDirty(true);
        },
    });

    const normalise = (next: GridRow[]): GridRow[] =>
        next.map((row) => {
            const copy = { ...row };

            for (const key of dayKeys) {
                const value = copy[key];

                if (value === null || value === '') {
                    copy[key] = null;
                    continue;
                }

                // Keep the raw uppercase code; unknown codes are nulled on save.
                copy[key] = String(value).toUpperCase();
            }

            return copy;
        });

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(absensi.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const save = () => {
        setSaving(true);
        const cells: { employee_id: number; day: number; code: string | null }[] = [];

        for (const row of rows) {
            for (const day of days) {
                cells.push({ employee_id: row._key, day: day.day, code: (row[`d${day.day}`] as string) ?? null });
            }
        }

        router.post(
            absensi.store().url,
            { ...filters, cells },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    // Fast client-side 8-day rotation pattern generator based on Day 1 (tanggal 1)
    const handleGenerateFromDay1 = () => {
        let count = 0;
        const nextRows = rows.map((row) => {
            const d1 = normalizeShiftCode(row.d1);
            if (!d1) return row;

            const d2 = normalizeShiftCode(row.d2);
            const startIndex = detectStartIndex(d1, d2);

            const copy = { ...row };
            for (let d = 1; d <= days.length; d++) {
                const cycleIndex = (startIndex + (d - 1)) % 8;
                copy[`d${d}`] = SHIFT_CYCLE[cycleIndex];
            }
            count++;
            return copy;
        });

        if (count === 0) {
            setPatternDialogOpen(true);
            toast.info(
                'Kolom Tanggal 1 masih kosong. Silakan isi tanggal 1 (OFF, S, P, atau M) di tabel terlebih dahulu, atau atur pola per regu di sini.',
            );
            return;
        }

        setRows(nextRows);
        setDirty(true);
        toast.success(`Jadwal sebulan berhasil di-generate untuk ${count} pegawai berdasarkan Tanggal 1 (Pola: OFF-OFF-S-S-P-P-M-M).`);
    };

    // Apply starting shift per Regu from modal dialog
    const handleApplyReguPatterns = (onlyDay1: boolean) => {
        let count = 0;
        const nextRows = rows.map((row) => {
            const regu = row.regu;
            if (!regu) return row;

            const phaseIndex = reguPhaseSettings[regu] ?? 0;
            const copy = { ...row };

            if (onlyDay1) {
                copy.d1 = SHIFT_CYCLE[phaseIndex];
                count++;
            } else {
                for (let d = 1; d <= days.length; d++) {
                    const cycleIndex = (phaseIndex + (d - 1)) % 8;
                    copy[`d${d}`] = SHIFT_CYCLE[cycleIndex];
                }
                count++;
            }
            return copy;
        });

        setRows(nextRows);
        setDirty(true);
        setPatternDialogOpen(false);
        if (onlyDay1) {
            toast.success(`Kolom Tanggal 1 berhasil diisi untuk ${count} pegawai.`);
        } else {
            toast.success(`Jadwal sebulan penuh berhasil diterapkan untuk ${count} pegawai.`);
        }
    };

    const applyPreset = (type: '4-regu' | '3-regu') => {
        if (type === '4-regu') {
            setReguPhaseSettings({ A: 0, B: 2, C: 4, D: 6 });
            toast.info('Preset 4 Regu diterapkan (A: OFF-1, B: S-1, C: P-1, D: M-1).');
        } else {
            setReguPhaseSettings({ A: 0, B: 3, C: 6, D: 1 });
            toast.info('Preset 3 Regu diterapkan (A: OFF-1, B: S-2, C: M-1).');
        }
    };

    // Cell selection handler for pop-up
    const handleSelectCode = (code: string | null) => {
        if (!selectedCell) return;
        const { employeeId, day } = selectedCell;
        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== employeeId) return r;
                return {
                    ...r,
                    [`d${day}`]: code ? code.toUpperCase() : null,
                };
            }),
        );
        setDirty(true);
        setSelectedCell(null);
    };

    // Apply pattern for a single employee from day startDay to month end
    const handleApplyPatternForEmployee = (startDayNum: number, phaseIndex: number) => {
        if (!selectedCell) return;
        const { employeeId, employeeName } = selectedCell;
        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== employeeId) return r;
                const copy = { ...r };
                for (let d = startDayNum; d <= days.length; d++) {
                    const cycleIndex = (phaseIndex + (d - startDayNum)) % 8;
                    copy[`d${d}`] = SHIFT_CYCLE[cycleIndex];
                }
                return copy;
            }),
        );
        setDirty(true);
        toast.success(`Pola shift diterapkan untuk ${employeeName} (Tgl ${startDayNum} s/d ${days.length}).`);
        setSelectedCell(null);
    };

    // Navigate day inside the cell modal
    const navigateDay = (delta: number) => {
        if (!selectedCell) return;
        const targetDay = selectedCell.day + delta;
        if (targetDay < 1 || targetDay > days.length) return;

        const row = rows.find((r) => r._key === selectedCell.employeeId);
        const dayInfo = days.find((d) => d.day === targetDay);
        const newLeft = selectedCell.rect.left + delta * 44;
        const newRect = {
            ...selectedCell.rect,
            left: newLeft,
            right: newLeft + selectedCell.rect.width,
        };

        setSelectedCell({
            ...selectedCell,
            day: targetDay,
            dow: dayInfo?.dow ?? '',
            isWeekend: dayInfo?.is_weekend ?? false,
            isHoliday: dayInfo?.is_holiday ?? false,
            holiday: dayInfo?.holiday ?? null,
            currentCode: (row?.[`d${targetDay}`] as string) ?? '',
            rect: newRect,
        });
    };

    // Quick apply pattern starting from current day based on code or regu
    const handleApplyQuickPattern = (startDayNum: number) => {
        if (!selectedCell) return;
        const { employeeId, employeeName, regu, currentCode } = selectedCell;

        let phaseIndex = 0;
        const norm = normalizeShiftCode(currentCode);
        if (norm) {
            phaseIndex = detectStartIndex(norm, null);
        } else {
            const reguPhase = regu ? (reguPhaseSettings[regu] ?? 0) : 0;
            phaseIndex = (reguPhase + (startDayNum - 1)) % 8;
        }

        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== employeeId) return r;
                const copy = { ...r };
                for (let d = startDayNum; d <= days.length; d++) {
                    const cycleIndex = (phaseIndex + (d - startDayNum)) % 8;
                    copy[`d${d}`] = SHIFT_CYCLE[cycleIndex];
                }
                return copy;
            }),
        );
        setDirty(true);
        toast.success(`Pola shift diterapkan untuk ${employeeName} (Tgl ${startDayNum} s/d ${days.length}).`);
        setSelectedCell(null);
    };

    // Close cell popup on Escape key
    useEffect(() => {
        if (!selectedCell) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setSelectedCell(null);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [selectedCell]);

    const isShift = filters.group_type === 'shift';
    const unitName = options.units.find((u) => u.id === filters.unit_id)?.name ?? '';
    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const groupLabel = options.group_types.find((g) => g.value === filters.group_type)?.label ?? '';

    return (
        <>
            <Head title="Absensi & Jadwal Kerja Shift" />
            <style>{PRINT_STYLES}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Absensi & Jadwal Kerja Shift"
                    description="Jadwal shift & kehadiran pegawai per unit & bulan. Sel dapat dipilih via pop-up atau otomatis di-generate dari Tanggal 1 mengikuti pola OFF, OFF, S, S, P, P, M, M."
                    actions={
                        <div className="flex flex-wrap items-center gap-2 no-print">
                            <Button variant="secondary" onClick={() => window.print()}>
                                <Printer className="size-4 mr-1.5" />
                                Cetak
                            </Button>

                            {can_write && isShift && (
                                <>
                                    <Button
                                        variant="default"
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-medium"
                                        onClick={handleGenerateFromDay1}
                                        title="Generate jadwal 1 bulan penuh untuk seluruh pegawai berdasarkan nilai di Tanggal 1 (Pola: OFF-OFF-S-S-P-P-M-M)"
                                    >
                                        <Sparkles className="size-4 mr-1.5" />
                                        Generate dari Tgl 1
                                    </Button>

                                    <Button
                                        variant="outline"
                                        onClick={() => setPatternDialogOpen(true)}
                                        title="Atur pola shift atau terapkan preset per regu"
                                    >
                                        <CalendarCog className="size-4 mr-1.5" />
                                        Atur Pola...
                                    </Button>
                                </>
                            )}

                            {can_write && (
                                <Button onClick={save} disabled={saving}>
                                    <Save className="size-4 mr-1.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 no-print">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Kelompok"
                        value={filters.group_type}
                        onChange={(value) => visit({ group_type: value })}
                        options={options.group_types}
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
                        <span className="text-[13px] font-medium text-amber-600 self-center">
                            Ada perubahan belum disimpan.
                        </span>
                    )}
                </div>

                {employees.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada pegawai {isShift ? 'shift (dengan regu)' : 'non-shift'} pada unit ini. Tambahkan lewat Master Pegawai
                        {isShift ? ' dan isi kolom Regu (A/B/C/D).' : '.'}
                    </div>
                ) : (
                    <div className="space-y-1">
                        <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                            <style>{OPERASI_GRID_STYLES}{ABSENSI_STYLES}</style>
                            <DataGrid
                                className="rdg-light"
                                style={{ blockSize: `${Math.min(Math.max(rows.length, 3) * 35 + 44, 620)}px` }}
                                columns={columns}
                                rows={rows}
                                rowKeyGetter={(row) => row._key}
                                onRowsChange={(next) => {
                                    setRows(normalise(next));
                                    setDirty(true);
                                }}
                                onSelectedCellChange={onSelectedCellChange}
                                onCellClick={(args, event) => {
                                    if (!can_write) return;
                                    const colKey = String(args.column.key);
                                    if (!colKey.startsWith('d')) return;
                                    const dayNum = parseInt(colKey.replace('d', ''), 10);
                                    if (isNaN(dayNum) || dayNum < 1 || dayNum > days.length) return;

                                    const targetElement = (event.target as HTMLElement).closest('.rdg-cell') as HTMLElement | null;
                                    const rect = targetElement?.getBoundingClientRect() ?? {
                                        top: event.clientY,
                                        bottom: event.clientY + 28,
                                        left: event.clientX - 22,
                                        right: event.clientX + 22,
                                        width: 44,
                                        height: 28,
                                    };

                                    const dayInfo = days.find((d) => d.day === dayNum);
                                    setSelectedCell({
                                        employeeId: args.row._key,
                                        employeeName: args.row.name,
                                        regu: args.row.regu,
                                        day: dayNum,
                                        dow: dayInfo?.dow ?? '',
                                        isWeekend: dayInfo?.is_weekend ?? false,
                                        isHoliday: dayInfo?.is_holiday ?? false,
                                        holiday: dayInfo?.holiday ?? null,
                                        currentCode: (args.row[`d${dayNum}`] as string) ?? '',
                                        rect: {
                                            top: rect.top,
                                            bottom: rect.bottom,
                                            left: rect.left,
                                            right: rect.right,
                                            width: rect.width,
                                            height: rect.height,
                                        },
                                    });
                                }}
                            />
                        </div>
                        <div className="flex items-center justify-between text-[11px] text-muted-foreground px-1 no-print">
                            <span>* Klik sel tanggal untuk memilih kode shift / kehadiran lewat pop-up. Paste dari Excel tetap didukung.</span>
                            {isShift && (
                                <span className="text-emerald-700 font-medium">
                                    💡 Tip: Cukup isi Tanggal 1, lalu klik &ldquo;Generate dari Tgl 1&rdquo; untuk mengisi otomatis sebulan.
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <div className="absensi-print hidden print:block">
                    <div style={{ textAlign: 'center', marginBottom: 10 }}>
                        <div style={{ fontWeight: 800, fontSize: 13, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                            JASA PENDUKUNG TEKNIK 6 SITE — PLTD CONTAINERIZED POASIA
                        </div>
                        <div style={{ fontWeight: 700, fontSize: 12, textTransform: 'uppercase' }}>
                            LAPORAN JADWAL KERJA {groupLabel} — {unitName}
                        </div>
                        <div style={{ fontSize: 11, color: '#333' }}>Periode: {periodLabel}</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th className="purple n" style={{ width: '85px' }}>NIP</th>
                                <th className="purple n">NAMA</th>
                                <th className="purple" style={{ width: '38px' }}>REGU</th>
                                {days.map((d) => (
                                    <th
                                        key={d.day}
                                        className={d.is_holiday ? 'hol' : d.is_weekend ? 'wknd' : ''}
                                        style={{ width: '22px', fontSize: '8px', lineHeight: '1.1' }}
                                    >
                                        <span>{d.dow}</span>
                                        <br />
                                        <strong>{String(d.day).padStart(2, '0')}</strong>
                                    </th>
                                ))}
                                {codes.map((c) => {
                                    const style = getCodeStyle(c.code);
                                    return (
                                        <th
                                            key={c.code}
                                            style={{
                                                backgroundColor: style.bg,
                                                color: style.text,
                                                borderColor: '#555',
                                                width: '24px',
                                                fontSize: '8px',
                                                padding: '1px',
                                            }}
                                        >
                                            {c.code}
                                        </th>
                                    );
                                })}
                                <th className="purple" style={{ width: '45px', fontSize: '8px' }}>% HADIR</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row._key}>
                                    <td className="n">{row.nip}</td>
                                    <td className="n" style={{ fontWeight: 600 }}>{row.name}</td>
                                    <td>{row.regu}</td>
                                    {days.map((d) => {
                                        const val = row[`d${d.day}`];
                                        if (!val) {
                                            return (
                                                <td
                                                    key={d.day}
                                                    className={d.is_holiday ? 'hol-cell' : d.is_weekend ? 'wknd-cell' : ''}
                                                />
                                            );
                                        }
                                        const code = String(val);
                                        const style = getCodeStyle(code);
                                        return (
                                            <td
                                                key={d.day}
                                                style={{
                                                    backgroundColor: style.bg,
                                                    color: style.text,
                                                    fontWeight: 'bold',
                                                }}
                                            >
                                                {code}
                                            </td>
                                        );
                                    })}
                                    {codes.map((c) => (
                                        <td key={c.code} style={{ fontWeight: recapFor(row, c.code) ? 'bold' : 'normal' }}>
                                            {recapFor(row, c.code) || ''}
                                        </td>
                                    ))}
                                    <td className="pct-cell">{percentFor(row)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Print Legend & Signature Block matching Excel */}
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', fontSize: '9px' }}>
                        {/* Left: Jam Kerja */}
                        <div style={{ width: '28%' }}>
                            <div style={{ background: '#00b0f0', border: '1px solid #0090c8', padding: '4px 6px', marginBottom: 4, borderRadius: 3, color: '#000000' }}>
                                <div style={{ fontWeight: 'bold', borderBottom: '1px solid rgba(0,0,0,0.2)', paddingBottom: 2, marginBottom: 2 }}>*JAM KERJA SHIFT :</div>
                                <div>PAGI : 08.00 S/D 16.00 WITA</div>
                                <div>SORE : 16.00 S/D 24.00 WITA</div>
                                <div>MALAM : 00.00 S/D 08.00 WITA</div>
                            </div>
                            <div style={{ background: '#00b0f0', border: '1px solid #0090c8', padding: '4px 6px', borderRadius: 3, color: '#000000' }}>
                                <div style={{ fontWeight: 'bold', borderBottom: '1px solid rgba(0,0,0,0.2)', paddingBottom: 2, marginBottom: 2 }}>*JAM KERJA NON SHIFT :</div>
                                <div>SENIN S/D KAMIS : 08.00 S/D 16.30 WITA</div>
                                <div>JUM&apos;AT : 07.30 S/D 16.30 WITA</div>
                            </div>
                        </div>

                        {/* Center: Keterangan */}
                        <div style={{ width: '42%', textAlign: 'center' }}>
                            <div style={{ display: 'flex', gap: 4, justifyContent: 'center', marginBottom: 6 }}>
                                <span style={{ background: '#ffc000', color: '#c00000', fontWeight: 'bold', padding: '2px 8px', border: '1px solid #555' }}>
                                    Hari Libur Sabtu - Minggu
                                </span>
                                <span style={{ background: '#ff0000', color: '#ffffff', fontWeight: 'bold', padding: '2px 8px', border: '1px solid #555' }}>
                                    Hari Libur Nasional
                                </span>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 3, textAlign: 'left', fontSize: '8px' }}>
                                {[
                                    { code: 'P', label: 'PAGI' },
                                    { code: 'S', label: 'SORE' },
                                    { code: 'M', label: 'MALAM' },
                                    { code: 'OFF', label: 'LIBUR' },
                                    { code: 'C', label: 'CUTI' },
                                    { code: 'SKT', label: 'SAKIT' },
                                    { code: 'I', label: 'IZIN' },
                                    { code: 'A', label: 'ALPHA' },
                                ].map((k) => {
                                    const style = getCodeStyle(k.code);
                                    return (
                                        <div key={k.code} style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
                                            <span style={{ background: style.bg, color: style.text, border: '1px solid #333', padding: '1px 3px', fontWeight: 'bold' }}>
                                                {k.code}
                                            </span>
                                            <span>{k.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Right: Signature */}
                        <div style={{ width: '22%', textAlign: 'center' }}>
                            <div style={{ fontSize: '9px', fontWeight: 'bold' }}>REPORT BY:</div>
                            <div style={{ fontSize: '9px', fontWeight: 'bold' }}>PROJECT LEADER</div>
                            <div style={{ height: 35 }} />
                            <div style={{ fontWeight: 'bold', textDecoration: 'underline' }}>HERWIN SYAHPUTRA</div>
                            <div>NID : 8126014PSI</div>
                        </div>
                    </div>
                </div>

                {/* Keterangan & Jam Kerja Screen View matching Excel layout */}
                <div className="rounded-lg border border-border bg-card p-3 text-xs text-foreground no-print shadow-xs">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-3">
                        {/* Left Box: Jam Kerja Shift & Non Shift (Bright Cyan #00b0f0) */}
                        <div className="lg:col-span-4 flex flex-col gap-2">
                            <div className="rounded border border-[#0090c8] bg-[#00b0f0] p-2 text-black shadow-xs">
                                <div className="font-black text-[11px] uppercase tracking-wide border-b border-black/20 pb-0.5 mb-1">
                                    *JAM KERJA SHIFT :
                                </div>
                                <div className="space-y-0.5 text-[11px] font-semibold">
                                    <div className="flex justify-between">
                                        <span>PAGI</span>
                                        <span>: 08.00 S/D 16.00 WITA</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>SORE</span>
                                        <span>: 16.00 S/D 24.00 WITA</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>MALAM</span>
                                        <span>: 00.00 S/D 08.00 WITA</span>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded border border-[#0090c8] bg-[#00b0f0] p-2 text-black shadow-xs">
                                <div className="font-black text-[11px] uppercase tracking-wide border-b border-black/20 pb-0.5 mb-1">
                                    *JAM KERJA NON SHIFT :
                                </div>
                                <div className="space-y-0.5 text-[11px] font-semibold">
                                    <div className="flex justify-between">
                                        <span>SENIN S/D KAMIS</span>
                                        <span>: 08.00 S/D 16.30 WITA</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>JUM&apos;AT</span>
                                        <span>: 07.30 S/D 16.30 WITA</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Right Box: Keterangan Hari Libur & Kode Kehadiran */}
                        <div className="lg:col-span-8 flex flex-col justify-between gap-2.5">
                            <div>
                                <div className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    KETERANGAN & KODE KEHADIRAN :
                                </div>

                                {/* Holiday Banners */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                    <div className="flex items-center justify-center py-1 px-3 rounded font-black text-xs bg-[#ffc000] text-[#c00000] border border-[#d99b00] shadow-xs">
                                        Hari Libur Sabtu - Minggu
                                    </div>
                                    <div className="flex items-center justify-center py-1 px-3 rounded font-black text-xs bg-[#ff0000] text-white border border-[#cc0000] shadow-xs">
                                        Hari Libur Nasional
                                    </div>
                                </div>

                                {/* 2-Column / 4-Column Code Legend Matrix */}
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                                    {[
                                        { code: 'P', label: 'PAGI' },
                                        { code: 'S', label: 'SORE' },
                                        { code: 'M', label: 'MALAM' },
                                        { code: 'OFF', label: 'LIBUR / OFF' },
                                        { code: 'C', label: 'CUTI' },
                                        { code: 'SKT', label: 'SAKIT (Sa)' },
                                        { code: 'I', label: 'IZIN' },
                                        { code: 'A', label: 'ALPHA' },
                                    ].map((item) => {
                                        const style = getCodeStyle(item.code);
                                        return (
                                            <div key={item.code} className="flex items-center gap-1.5 bg-muted/40 p-1 rounded border">
                                                <span
                                                    style={{
                                                        backgroundColor: style.bg,
                                                        color: style.text,
                                                        borderColor: style.border,
                                                    }}
                                                    className="inline-flex items-center justify-center rounded px-1.5 min-w-[32px] h-[22px] text-[11px] font-black border shadow-2xs leading-none shrink-0"
                                                >
                                                    {item.code}
                                                </span>
                                                <span className="font-bold text-[11px] truncate">{item.label}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Pattern Rotation Tip */}
                            <div className="text-[11px] text-muted-foreground bg-muted/40 p-2 rounded border flex flex-wrap items-center justify-between gap-1">
                                <span>
                                    Pola rotasi shift: <strong className="text-foreground">OFF, OFF, S, S, P, P, M, M</strong> (2x Libur, 2x Sore, 2x Pagi, 2x Malam).
                                </span>
                                <span className="font-semibold text-primary">
                                    PLTD Containerized Poasia
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Pop-up Pilihan Inputan Sel (Floating, Ringkas, Tepat di Atas/Bawah Kolom yang Diklik) */}
            {selectedCell && (() => {
                const POPUP_WIDTH = 250;
                const isAbove = selectedCell.rect.top >= 180;
                const left = Math.max(8, Math.min(window.innerWidth - POPUP_WIDTH - 8, selectedCell.rect.left + selectedCell.rect.width / 2 - POPUP_WIDTH / 2));
                const arrowLeft = Math.max(12, Math.min(POPUP_WIDTH - 12, selectedCell.rect.left + selectedCell.rect.width / 2 - left));

                const popoverStyle: React.CSSProperties = {
                    position: 'fixed',
                    left: `${left}px`,
                    width: `${POPUP_WIDTH}px`,
                    zIndex: 60,
                    ...(isAbove
                        ? { bottom: `${Math.max(8, window.innerHeight - selectedCell.rect.top + 6)}px` }
                        : { top: `${selectedCell.rect.bottom + 6}px` }),
                };

                return (
                    <>
                        {/* Backdrop transparan untuk mendeteksi klik luar tanpa menggelapkan layar */}
                        <div
                            className="fixed inset-0 z-50 bg-black/5"
                            onClick={() => setSelectedCell(null)}
                        />

                        {/* Floating Popover Card */}
                        <div
                            style={popoverStyle}
                            className="rounded-lg border border-border bg-card p-2 shadow-xl ring-1 ring-black/10 text-card-foreground transition-all animate-in fade-in-0 zoom-in-95 duration-100"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {/* Segitiga Penunjuk ke Sel Kolom */}
                            <div
                                className={`absolute w-2.5 h-2.5 bg-card border-border rotate-45 ${
                                    isAbove
                                        ? '-bottom-1.5 border-r border-b'
                                        : '-top-1.5 border-l border-t'
                                }`}
                                style={{ left: `${arrowLeft - 5}px` }}
                            />

                            {/* Header Ringkas */}
                            <div className="flex items-center justify-between border-b pb-1.5 mb-1.5 gap-1">
                                <div className="flex items-center gap-1 overflow-hidden min-w-0">
                                    <span className="font-bold text-[11px] truncate text-foreground" title={selectedCell.employeeName}>
                                        {selectedCell.employeeName}
                                    </span>
                                    {selectedCell.regu && (
                                        <span className="px-1 py-0.2 rounded bg-muted text-[9px] font-bold text-muted-foreground shrink-0">
                                            R.{selectedCell.regu}
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-0.5 shrink-0">
                                    <button
                                        type="button"
                                        disabled={selectedCell.day <= 1}
                                        onClick={() => navigateDay(-1)}
                                        className="size-4 flex items-center justify-center rounded hover:bg-muted text-muted-foreground disabled:opacity-30 disabled:pointer-events-none"
                                        title="Tgl sebelumnya"
                                    >
                                        <ChevronLeft className="size-3" />
                                    </button>
                                    <span className="text-[10px] font-extrabold px-1 py-0.2 bg-primary/10 text-primary rounded leading-none">
                                        {selectedCell.day} {selectedCell.dow}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={selectedCell.day >= days.length}
                                        onClick={() => navigateDay(1)}
                                        className="size-4 flex items-center justify-center rounded hover:bg-muted text-muted-foreground disabled:opacity-30 disabled:pointer-events-none"
                                        title="Tgl berikutnya"
                                    >
                                        <ChevronRight className="size-3" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedCell(null)}
                                        className="size-4 flex items-center justify-center rounded hover:bg-muted text-muted-foreground ml-0.5"
                                        title="Tutup (Esc)"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </div>
                            </div>

                            {/* Pilihan Shift Kerja (4 Tombol) */}
                            <div className="mb-1.5">
                                <div className="text-[9px] font-semibold text-muted-foreground mb-1 uppercase tracking-wider flex items-center justify-between">
                                    <span>Shift Kerja</span>
                                    {selectedCell.currentCode && (
                                        <span className="text-[9px] font-bold text-primary">
                                            Aktif: {selectedCell.currentCode}
                                        </span>
                                    )}
                                </div>
                                <div className="grid grid-cols-4 gap-1">
                                    {[
                                        { code: 'OFF', label: 'Libur' },
                                        { code: 'S', label: 'Sore' },
                                        { code: 'P', label: 'Pagi' },
                                        { code: 'M', label: 'Malam' },
                                    ].map((item) => {
                                        const style = getCodeStyle(item.code);
                                        const isCurrent = selectedCell.currentCode === item.code;
                                        return (
                                            <button
                                                key={item.code}
                                                type="button"
                                                onClick={() => handleSelectCode(item.code)}
                                                style={{
                                                    backgroundColor: style.bg,
                                                    color: style.text,
                                                    borderColor: style.border,
                                                }}
                                                className={`flex flex-col items-center justify-center py-1.5 px-0.5 rounded border text-center transition-transform hover:scale-105 active:scale-95 cursor-pointer shadow-xs ${
                                                    isCurrent ? 'ring-2 ring-primary scale-105 font-black' : 'font-bold'
                                                }`}
                                                title={`Pilih ${item.code} (${item.label})`}
                                            >
                                                <span className="text-xs font-black leading-none">{item.code}</span>
                                                <span className="text-[8px] font-bold leading-none mt-1 opacity-90">{item.label}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Pilihan Izin & Ketidakhadiran (4 Tombol) */}
                            <div className="mb-1.5">
                                <div className="text-[9px] font-semibold text-muted-foreground mb-1 uppercase tracking-wider">
                                    Izin & Ketidakhadiran
                                </div>
                                <div className="grid grid-cols-4 gap-1">
                                    {[
                                        { code: 'C', label: 'Cuti' },
                                        { code: 'SKT', label: 'Sakit' },
                                        { code: 'I', label: 'Izin' },
                                        { code: 'A', label: 'Alpha' },
                                    ].map((item) => {
                                        const style = getCodeStyle(item.code);
                                        const isCurrent = selectedCell.currentCode === item.code;
                                        return (
                                            <button
                                                key={item.code}
                                                type="button"
                                                onClick={() => handleSelectCode(item.code)}
                                                style={{
                                                    backgroundColor: style.bg,
                                                    color: style.text,
                                                    borderColor: style.border,
                                                }}
                                                className={`flex items-center justify-center py-1 px-0.5 rounded border text-center transition-transform hover:scale-105 active:scale-95 cursor-pointer shadow-xs ${
                                                    isCurrent ? 'ring-2 ring-primary font-black scale-105' : 'font-bold'
                                                }`}
                                                title={item.label}
                                            >
                                                <span className="text-[11px] font-black leading-none">{item.code}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Tombol Aksi Bawah: Kosongkan & Pola Cepat */}
                            <div className="pt-1.5 border-t flex items-center justify-between gap-1 text-[10px]">
                                <button
                                    type="button"
                                    onClick={() => handleSelectCode(null)}
                                    className="inline-flex items-center gap-1 text-muted-foreground hover:text-destructive py-0.5 px-1 rounded hover:bg-destructive/10"
                                    title="Kosongkan sel ini"
                                >
                                    <Trash2 className="size-3" />
                                    <span>Kosongkan</span>
                                </button>

                                {isShift && (
                                    <button
                                        type="button"
                                        onClick={() => handleApplyQuickPattern(selectedCell.day)}
                                        className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400 hover:text-emerald-800 font-semibold py-0.5 px-1.5 rounded hover:bg-emerald-50 dark:hover:bg-emerald-950/50"
                                        title="Terapkan pola rotasi 8 hari (OFF-OFF-S-S-P-P-M-M) mulai hari ini s/d akhir bulan untuk pegawai ini"
                                    >
                                        <Sparkles className="size-3" />
                                        <span>Pola ke Akhir Bulan</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    </>
                );
            })()}

            {/* Dialog 2: Modal Atur Pola Shift per Regu / Batch Generator */}
            <Dialog open={patternDialogOpen} onOpenChange={setPatternDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-1.5 text-base font-bold">
                            <CalendarCog className="size-5 text-primary" />
                            Pengaturan Pola Shift (OFF-OFF-S-S-P-P-M-M)
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Pola rotasi standar 8 hari: <strong>2x OFF, 2x Sore (S), 2x Pagi (P), 2x Malam (M)</strong>. Tentukan posisi awal shift untuk tiap regu di Tanggal 1 bulan ini.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        {/* Quick Presets */}
                        <div className="flex flex-wrap items-center gap-2 rounded-md bg-muted/40 p-2 border">
                            <span className="text-xs font-semibold text-foreground">Preset Otomatis:</span>
                            <Button
                                variant="secondary"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => applyPreset('4-regu')}
                            >
                                Pola Standar 4 Regu (A=OFF, B=S, C=P, D=M)
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => applyPreset('3-regu')}
                            >
                                Pola 3 Regu (A=OFF, B=S, C=M)
                            </Button>
                        </div>

                        {/* Regu Configurations */}
                        <div className="space-y-2.5">
                            <div className="text-xs font-semibold text-foreground">
                                Shift Awal Tanggal 1 per Regu:
                            </div>
                            {distinctRegus.length === 0 ? (
                                <div className="text-xs text-muted-foreground italic p-2 border border-dashed rounded">
                                    Tidak ada regu terdaftar pada pegawai unit ini.
                                </div>
                            ) : (
                                distinctRegus.map((regu) => {
                                    const currentPhase = reguPhaseSettings[regu] ?? 0;
                                    const reguEmpCount = employees.filter((e) => e.regu === regu).length;

                                    return (
                                        <div
                                            key={regu}
                                            className="flex flex-col gap-1.5 rounded-lg border border-border p-2.5 bg-card"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="default" className="text-xs font-bold px-2 py-0.5">
                                                        Regu {regu}
                                                    </Badge>
                                                    <span className="text-[11px] text-muted-foreground">
                                                        ({reguEmpCount} pegawai)
                                                    </span>
                                                </div>
                                                <select
                                                    value={currentPhase}
                                                    onChange={(e) =>
                                                        setReguPhaseSettings((prev) => ({
                                                            ...prev,
                                                            [regu]: Number(e.target.value),
                                                        }))
                                                    }
                                                    className="rounded border border-input bg-background px-2 py-1 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-primary"
                                                >
                                                    {CYCLE_PHASES.map((p) => (
                                                        <option key={p.index} value={p.index}>
                                                            Tgl 1: {p.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            {/* Preview of 8 days sequence */}
                                            <div className="flex items-center gap-1 pt-1 overflow-x-auto">
                                                <span className="text-[10px] text-muted-foreground shrink-0 mr-1">
                                                    Urutan 8 hari:
                                                </span>
                                                {Array.from({ length: 8 }).map((_, i) => {
                                                    const cycleIdx = (currentPhase + i) % 8;
                                                    const code = SHIFT_CYCLE[cycleIdx];
                                                    const style = getCodeStyle(code);
                                                    return (
                                                        <span
                                                            key={i}
                                                            style={{
                                                                backgroundColor: style.bg,
                                                                color: style.text,
                                                                borderColor: style.border,
                                                            }}
                                                            className="inline-flex items-center justify-center rounded px-1.5 py-0.5 text-[9px] font-black border leading-none shrink-0 shadow-2xs"
                                                        >
                                                            {code}
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <DialogFooter className="flex flex-col sm:flex-row gap-2 sm:justify-between pt-2 border-t">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-xs order-last sm:order-first"
                            onClick={() => setPatternDialogOpen(false)}
                        >
                            Batal
                        </Button>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-xs"
                                onClick={() => handleApplyReguPatterns(true)}
                                title="Hanya mengisi sel Tanggal 1 untuk diperiksa sebelum di-generate"
                            >
                                Isi Hanya Kolom Tgl 1
                            </Button>
                            <Button
                                variant="default"
                                size="sm"
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium"
                                onClick={() => handleApplyReguPatterns(false)}
                            >
                                <Sparkles className="size-3.5 mr-1" />
                                Terapkan ke Seluruh Bulan
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AbsensiSchedule.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Absensi & Jadwal', href: absensi.index() },
    ],
};
