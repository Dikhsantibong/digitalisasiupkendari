import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCheck,
    Download,
    FileSpreadsheet,
    Info,
    Printer,
    RotateCcw,
    Save,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import {
    EntryBadge,
    MobileEntryCard,
    MobileEntryList,
    MobileFormScreen,
} from '@/components/mobile/entry-flow';
import { EvidencePhotoField } from '@/components/operasi/evidence-photo-field';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PatrolCheckChecklistView } from '@/components/operasi/patrol-check-checklist-view';
import type {
    ChecklistActiveEntry,
    ChecklistEntrySummary,
    ChecklistMeta,
    ChecklistParamOption,
} from '@/components/operasi/patrol-check-checklist-view';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { StyleSpec } from '@/lib/jadwal-excel';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import patrolCheckMesin from '@/routes/operasi/input/patrol-check-mesin';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    is_weekend: boolean;
    day_name: string;
};

type DayFinding = {
    catatan: string;
    eviden?: string | null;
    eviden_url?: string | null;
    eviden_file?: File | null;
};

type PatrolCheckItem = {
    no: number;
    system: string;
    peralatan: string;
    checks: Record<string, 'N' | 'T' | ''>;
    findings?: Record<string, DayFinding>;
};

type MachineOption = {
    id: number;
    name: string;
};

type Props = {
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
    };
    options: {
        units: IdName[];
        machines: MachineOption[];
        years: number[];
    };
    daysInMonth: number;
    daysInfo: DayInfo[];
    items: PatrolCheckItem[];
    shift_pagi: Record<string, string>;
    shift_sore: Record<string, string>;
    shift_malam: Record<string, string>;
    catatan: string;
    can_write: boolean;
    patrol_format?: 'monthly' | 'checklist';
    patrol_meta?: ChecklistMeta;
    patrol_param_options?: ChecklistParamOption[];
    shift_options?: string[];
    user_shifts?: Record<string, string>;
    user_operator_name?: string;
    entries?: ChecklistEntrySummary[];
    active_entry?: ChecklistActiveEntry | null;
};

const PRINT_CSS = `
@media print {
    @page {
        size: A4 landscape;
        margin: 4mm;
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
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
    }
    .no-print {
        display: none !important;
    }
    .print-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        font-size: 5.5px !important;
        table-layout: fixed !important;
    }
    .print-table th, .print-table td {
        border: 0.5px solid #000 !important;
        padding: 1px 0.5px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .sticky-col {
        position: static !important;
        box-shadow: none !important;
        background-color: transparent !important;
    }
    .print-day-red {
        background-color: #fca5a5 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-day-green {
        background-color: #bbf7d0 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-category-yellow {
        background-color: #fef08a !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-shift-header {
        background-color: #e2e8f0 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function OperasiPatrolCheckMesinIndex(props: Props) {
    if (props.patrol_format === 'checklist' && props.active_entry) {
        return (
            <PatrolCheckChecklistView
                key={`${props.active_entry.id ?? 'new'}-${props.active_entry.tanggal}-${props.active_entry.jam}`}
                unit={props.unit}
                filters={props.filters}
                options={props.options}
                patrol_meta={
                    props.patrol_meta ?? {
                        title: 'FORM PATROL CHECK MESIN & ALAT BANTU',
                        form_number: 'POA-OPR-CHK-01',
                        revision: 'Rev. 00',
                        instruction: '',
                    }
                }
                patrol_param_options={props.patrol_param_options ?? []}
                user_shifts={props.user_shifts}
                user_operator_name={props.user_operator_name}
                entries={props.entries ?? []}
                active_entry={props.active_entry}
                can_write={props.can_write}
            />
        );
    }

    return <MonthlyPatrolCheckView {...props} />;
}

function MonthlyPatrolCheckView({
    unit,
    filters,
    options,
    daysInMonth,
    daysInfo,
    items: initialItems,
    shift_pagi: initialShiftPagi,
    shift_sore: initialShiftSore,
    shift_malam: initialShiftMalam,
    catatan: initialCatatan,
    can_write,
}: Props) {
    const [items, setItems] = useState<PatrolCheckItem[]>(initialItems);
    const [shiftPagi, setShiftPagi] =
        useState<Record<string, string>>(initialShiftPagi);
    const [shiftSore, setShiftSore] =
        useState<Record<string, string>>(initialShiftSore);
    const [shiftMalam, setShiftMalam] =
        useState<Record<string, string>>(initialShiftMalam);
    const [catatan, setCatatan] = useState<string>(initialCatatan || '');
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    const { can } = usePermissions();
    // Phone: the checked days as cards, or one day's check on its own screen.
    const [formOpen, setFormOpen] = useState(false);
    // Phone layout edits one date at a time; start on today when the month is shown.
    const [selectedDay, setSelectedDay] = useState<number>(() => {
        const now = new Date();

        return now.getFullYear() === filters.year &&
            now.getMonth() + 1 === filters.month
            ? Math.min(now.getDate(), daysInMonth)
            : 1;
    });

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;

    // Navigation filter helper
    const visit = (patch: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        router.get(
            patrolCheckMesin.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    // Toggle cell value for an item on a specific day
    const handleToggleCell = (
        itemIndex: number,
        day: number,
        targetType: 'N' | 'T',
    ) => {
        if (!can_write) {
            return;
        }

        setItems((prev) => {
            const updated = [...prev];
            const currentItem = { ...updated[itemIndex] };
            const currentChecks = { ...(currentItem.checks || {}) };
            const currentFindings = { ...(currentItem.findings || {}) };
            const dayKey = String(day);
            const currentVal = currentChecks[dayKey];

            if (currentVal === targetType) {
                delete currentChecks[dayKey];
                delete currentFindings[dayKey];
            } else {
                currentChecks[dayKey] = targetType;

                if (targetType !== 'T') {
                    delete currentFindings[dayKey];
                } else if (!currentFindings[dayKey]) {
                    currentFindings[dayKey] = {
                        catatan: '',
                        eviden: null,
                        eviden_url: null,
                        eviden_file: null,
                    };
                }
            }

            currentItem.checks = currentChecks;
            currentItem.findings = currentFindings;
            updated[itemIndex] = currentItem;

            return updated;
        });
        setDirty(true);
    };

    const handleUpdateFindingNote = (
        itemIndex: number,
        day: number,
        note: string,
    ) => {
        if (!can_write) {
            return;
        }

        const dayKey = String(day);
        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== itemIndex) {
                    return it;
                }

                const prevFinding = it.findings?.[dayKey] ?? {
                    catatan: '',
                    eviden: null,
                    eviden_url: null,
                    eviden_file: null,
                };

                return {
                    ...it,
                    findings: {
                        ...(it.findings || {}),
                        [dayKey]: { ...prevFinding, catatan: note },
                    },
                };
            }),
        );
        setDirty(true);
    };

    const handlePickFindingPhoto = (
        itemIndex: number,
        day: number,
        file: File,
    ) => {
        if (!can_write) {
            return;
        }

        const dayKey = String(day);
        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== itemIndex) {
                    return it;
                }

                const prevFinding = it.findings?.[dayKey] ?? {
                    catatan: '',
                    eviden: null,
                    eviden_url: null,
                    eviden_file: null,
                };

                return {
                    ...it,
                    findings: {
                        ...(it.findings || {}),
                        [dayKey]: { ...prevFinding, eviden_file: file },
                    },
                };
            }),
        );
        setDirty(true);
    };

    const handleRemoveFindingPhoto = (itemIndex: number, day: number) => {
        if (!can_write) {
            return;
        }

        const dayKey = String(day);
        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== itemIndex) {
                    return it;
                }

                const prevFinding = it.findings?.[dayKey] ?? {
                    catatan: '',
                    eviden: null,
                    eviden_url: null,
                    eviden_file: null,
                };

                return {
                    ...it,
                    findings: {
                        ...(it.findings || {}),
                        [dayKey]: {
                            ...prevFinding,
                            eviden: null,
                            eviden_url: null,
                            eviden_file: null,
                        },
                    },
                };
            }),
        );
        setDirty(true);
    };

    // Quick action: mark all equipment as Normal (N) for today's date
    const handleMarkTodayNormal = () => {
        if (!can_write) {
            return;
        }

        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        const currentDay = now.getDate();

        if (
            filters.year !== currentYear ||
            filters.month !== currentMonth ||
            currentDay > daysInMonth
        ) {
            toast.info(
                `Aksi cepat hanya berlaku untuk periode bulan berjalan (${OPERASI_MONTHS[currentMonth - 1]} ${currentYear}).`,
            );

            return;
        }

        setItems((prev) =>
            prev.map((item) => ({
                ...item,
                checks: {
                    ...item.checks,
                    [String(currentDay)]: 'N',
                },
            })),
        );
        setDirty(true);
        toast.success(
            `Semua peralatan berhasil ditandai Normal (N) untuk tanggal ${currentDay}.`,
        );
    };

    // Shift updates
    const handleUpdateShift = (
        type: 'pagi' | 'sore' | 'malam',
        day: number,
        value: string,
    ) => {
        if (!can_write) {
            return;
        }

        const dayKey = String(day);
        const upper = value.toUpperCase();

        if (type === 'pagi') {
            setShiftPagi((prev) => ({ ...prev, [dayKey]: upper }));
        } else if (type === 'sore') {
            setShiftSore((prev) => ({ ...prev, [dayKey]: upper }));
        } else {
            setShiftMalam((prev) => ({ ...prev, [dayKey]: upper }));
        }

        setDirty(true);
    };

    // Reset
    const handleReset = () => {
        setItems(initialItems);
        setShiftPagi(initialShiftPagi);
        setShiftSore(initialShiftSore);
        setShiftMalam(initialShiftMalam);
        setCatatan(initialCatatan || '');
        setDirty(false);
    };

    // Save
    const handleSave = (onSaved?: () => void) => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            patrolCheckMesin.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                month: filters.month,
                machine_id: filters.machine_id,
                nama_mesin: filters.nama_mesin,
                items,
                shift_pagi: shiftPagi,
                shift_sore: shiftSore,
                shift_malam: shiftMalam,
                catatan,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    onSaved?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    // Export Excel (.xlsx) using ExcelJS
    const handleExportExcel = async () => {
        const sheetData: (string | number)[][] = [[], [], [], []];

        // Header Row 1: NO, PERALATAN, AGUSTUS 2026 (spans across all day cols)
        const rowH1: (string | number)[] = ['NO', 'PERALATAN'];
        rowH1.push(periodLabel.toUpperCase());

        for (let i = 1; i < daysInMonth * 2; i++) {
            rowH1.push('');
        }

        sheetData.push(rowH1);

        // Header Row 2: Days 1..daysInMonth
        const rowH2: (string | number)[] = ['', ''];

        for (let d = 1; d <= daysInMonth; d++) {
            rowH2.push(d, '');
        }

        sheetData.push(rowH2);

        // Header Row 3: N, T
        const rowH3: (string | number)[] = ['', ''];

        for (let d = 1; d <= daysInMonth; d++) {
            rowH3.push('N', 'T');
        }

        sheetData.push(rowH3);

        const totalCols = 2 + daysInMonth * 2;
        const headerTop = 4;
        const headerMid = 5;
        const headerBottom = 6;
        const dataStart = 7;

        let currentRowIndex = dataStart;
        const categoryRowIndices: number[] = [];
        let currentSystem: string | null = null;

        // Data Rows
        items.forEach((item) => {
            if (item.system !== currentSystem) {
                currentSystem = item.system;
                const catRow: (string | number)[] = [currentSystem];

                for (let c = 1; c < totalCols; c++) {
                    catRow.push('');
                }

                sheetData.push(catRow);
                categoryRowIndices.push(currentRowIndex);
                currentRowIndex++;
            }

            const checks = item.checks || {};
            const row: (string | number)[] = [item.no, item.peralatan];

            for (let d = 1; d <= daysInMonth; d++) {
                const val = checks[String(d)] || checks[d] || '';
                row.push(val === 'N' ? '✓' : '', val === 'T' ? '✓' : '');
            }

            sheetData.push(row);
            currentRowIndex++;
        });

        // Pelaksana Shift Section
        const shiftHeaderIdx = currentRowIndex;
        const shiftCatRow: (string | number)[] = ['PELAKSANA SHIFT'];

        for (let c = 1; c < totalCols; c++) {
            shiftCatRow.push('');
        }

        sheetData.push(shiftCatRow);
        currentRowIndex++;

        // Shift Pagi
        const rowSP: (string | number)[] = ['Shift Pagi : 08.00 - 16.00', ''];

        for (let d = 1; d <= daysInMonth; d++) {
            const v = shiftPagi[String(d)] || shiftPagi[d] || '';
            rowSP.push(v, '');
        }

        sheetData.push(rowSP);
        const shiftPagiIdx = currentRowIndex;
        currentRowIndex++;

        // Shift Sore
        const rowSS: (string | number)[] = ['Shift Sore : 16.00 - 24.00', ''];

        for (let d = 1; d <= daysInMonth; d++) {
            const v = shiftSore[String(d)] || shiftSore[d] || '';
            rowSS.push(v, '');
        }

        sheetData.push(rowSS);
        const shiftSoreIdx = currentRowIndex;
        currentRowIndex++;

        // Shift Malam
        const rowSM: (string | number)[] = ['Shift Malam : 00.00 - 08.00', ''];

        for (let d = 1; d <= daysInMonth; d++) {
            const v = shiftMalam[String(d)] || shiftMalam[d] || '';
            rowSM.push(v, '');
        }

        sheetData.push(rowSM);
        const shiftMalamIdx = currentRowIndex;
        currentRowIndex++;

        // Catatan
        sheetData.push([]);
        currentRowIndex++;
        sheetData.push([
            'NOTE : Patrol Check Dilaksanakan Pada Saat Shift Pagi',
        ]);
        currentRowIndex++;
        sheetData.push([`CATATAN : ${catatan || '-'}`]);
        currentRowIndex++;

        const { workbook, worksheet: ws } = createSheet(
            'Patrol Check Mesin',
            sheetData,
        );

        const dayRedSpec: StyleSpec = {
            fill: 'FCA5A5',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const dayGreenSpec: StyleSpec = {
            fill: 'BBF7D0',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const subNSpec: StyleSpec = {
            fill: 'FEF08A',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const subTSpec: StyleSpec = {
            fill: 'FEE2E2',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const yellowCategorySpec: StyleSpec = {
            fill: 'FEF08A',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'left',
            valign: 'center',
            border: true,
        };

        const shiftCategorySpec: StyleSpec = {
            fill: 'E2E8F0',
            bold: true,
            color: XLSX_COLORS.black,
            align: 'left',
            valign: 'center',
            border: true,
        };

        const markedNormalSpec: StyleSpec = {
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const markedTroubleSpec: StyleSpec = {
            bold: true,
            color: XLSX_COLORS.red600,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null;
            } // Document header

            if (r === headerTop) {
                return SPECS.headerOrange;
            }

            if (r === headerMid) {
                if (c < 2) {
                    return SPECS.headerOrange;
                }

                const dayIndex = Math.floor((c - 2) / 2);
                const isWeekend = daysInfo[dayIndex]?.is_weekend;

                return isWeekend ? dayRedSpec : dayGreenSpec;
            }

            if (r === headerBottom) {
                if (c < 2) {
                    return SPECS.headerOrange;
                }

                const isN = (c - 2) % 2 === 0;

                return isN ? subNSpec : subTSpec;
            }

            if (categoryRowIndices.includes(r)) {
                return yellowCategorySpec;
            }

            if (r === shiftHeaderIdx) {
                return shiftCategorySpec;
            }

            if (r >= shiftPagiIdx && r <= shiftMalamIdx) {
                if (c < 2) {
                    return { ...SPECS.label, bold: true };
                }

                return { ...SPECS.cell, bold: true };
            }

            if (r >= dataStart && r < shiftHeaderIdx) {
                if (c === 0) {
                    return SPECS.labelCenter;
                }

                if (c === 1) {
                    return SPECS.label;
                }

                const cellVal = String(sheetData[r]?.[c] ?? '');
                const isT = (c - 2) % 2 === 1;

                if (cellVal === '✓') {
                    return isT ? markedTroubleSpec : markedNormalSpec;
                }

                return SPECS.cell;
            }

            return null;
        });

        // Merge headers
        mergeCells(ws, headerTop, 0, headerBottom, 0); // NO
        mergeCells(ws, headerTop, 1, headerBottom, 1); // PERALATAN
        mergeCells(ws, headerTop, 2, headerTop, totalCols - 1); // AGUSTUS 2026

        // Merge each day across N and T
        for (let d = 0; d < daysInMonth; d++) {
            const col = 2 + d * 2;
            mergeCells(ws, headerMid, col, headerMid, col + 1);
        }

        // Category merges
        categoryRowIndices.forEach((cIdx) => {
            mergeCells(ws, cIdx, 0, cIdx, totalCols - 1);
        });

        // Shift category merge
        mergeCells(ws, shiftHeaderIdx, 0, shiftHeaderIdx, totalCols - 1);

        // Shift rows merge (col 0-1 for label, col 2-3 for each day)
        [shiftPagiIdx, shiftSoreIdx, shiftMalamIdx].forEach((sRow) => {
            mergeCells(ws, sRow, 0, sRow, 1);

            for (let d = 0; d < daysInMonth; d++) {
                const col = 2 + d * 2;
                mergeCells(ws, sRow, col, sRow, col + 1);
            }
        });

        const colWidths = [4.5, 26, ...Array(daysInMonth * 2).fill(2.8)];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS 6 KIT',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    `PATROL CHECK MESIN: ${filters.nama_mesin.toUpperCase()}`,
                ],
                barTitle: `PATROL CHECK MESIN — PERIODE ${periodLabel.toUpperCase()}`,
            },
        );

        const safeUnit = unit.name.replace(/\s+/g, '_');
        const safeMesin = filters.nama_mesin.replace(/\s+/g, '_');
        const filename = `Patrol_Check_Mesin_${safeUnit}_${safeMesin}_${filters.month}_${filters.year}.xlsx`;
        await downloadWorkbook(workbook, filename);
    };

    if (compact) {
        const day = daysInfo.find((d) => d.day === selectedDay) ?? daysInfo[0];
        const systems = items.reduce<
            {
                system: string;
                entries: { item: PatrolCheckItem; index: number }[];
            }[]
        >((list, item, index) => {
            const last = list[list.length - 1];

            if (last && last.system === item.system) {
                last.entries.push({ item, index });
            } else {
                list.push({ system: item.system, entries: [{ item, index }] });
            }

            return list;
        }, []);
        const markDayNormal = () => {
            setItems((prev) =>
                prev.map((item) => ({
                    ...item,
                    checks: { ...item.checks, [String(day.day)]: 'N' },
                })),
            );
            setDirty(true);
        };
        const shiftValue = (map: Record<string, string>) =>
            map[String(day.day)] || '';
        const checkedDays = daysInfo.filter((d) => items.some((item) => item.checks?.[String(d.day)]));

        return (
            <>
                <Head
                    title={`Patrol Check Mesin - ${filters.nama_mesin} - ${unit.name}`}
                />
                {!formOpen ? (
                    <MobileEntryList
                        heading={`Patrol check ${periodLabel}`}
                        count={checkedDays.length}
                        empty={`Belum ada patrol check ${filters.nama_mesin || ''} ${periodLabel}. Tekan Tambah untuk mengisi.`}
                        canWrite={can_write}
                        onAdd={() => {
                            const now = new Date();

                            if (now.getFullYear() === filters.year && now.getMonth() + 1 === filters.month) {
                                setSelectedDay(Math.min(now.getDate(), daysInMonth));
                            }

                            setFormOpen(true);
                        }}
                        onSave={() => handleSave()}
                        saving={saving}
                        dirty={dirty}
                        above={
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
                                        });
                                    }}
                                    options={options.machines.map((m) => ({
                                        value: m.name,
                                        label: m.name,
                                    }))}
                                    className="w-full"
                                />
                        }
                    >
                        {checkedDays.map((d) => {
                            const key = String(d.day);
                            const trouble = items.filter((item) => item.checks?.[key] === 'T').length;
                            const normal = items.filter((item) => item.checks?.[key] === 'N').length;

                            return (
                                <MobileEntryCard
                                    key={d.day}
                                    title={`${d.day_name}, ${d.day} ${periodLabel}`}
                                    badge={<EntryBadge tone={trouble > 0 ? 'danger' : 'done'}>{trouble > 0 ? `${trouble} T` : 'Normal'}</EntryBadge>}
                                    meta={[
                                        ['Normal', `${normal} item`],
                                        ['Petugas', [shiftPagi[key], shiftSore[key], shiftMalam[key]].filter(Boolean).join(' / ')],
                                    ]}
                                    onClick={() => {
                                        setSelectedDay(d.day);
                                        setFormOpen(true);
                                    }}
                                />
                            );
                        })}
                    </MobileEntryList>
                ) : (
                    <MobileFormScreen
                        title={day ? `${day.day_name}, ${day.day} ${periodLabel}` : 'Patrol check'}
                        subtitle={`Patrol Check · ${filters.nama_mesin}`}
                        onBack={() => setFormOpen(false)}
                        canWrite={can_write}
                        onSave={() => handleSave(() => setFormOpen(false))}
                        saving={saving}
                        saveDisabled={!dirty || !filters.nama_mesin}
                    >

                    <DayStrip
                        items={daysInfo.map((d) => ({
                            key: String(d.day),
                            label: String(d.day),
                            sub: d.day_name.slice(0, 3),
                            isRed: d.is_weekend,
                            done: items.some(
                                (item) => item.checks?.[String(d.day)],
                            ),
                        }))}
                        value={String(day?.day ?? '')}
                        onChange={(key) => setSelectedDay(Number(key))}
                    />

                    {day && (
                        <>
                            <div className="flex items-center justify-between gap-2 px-0.5">
                                <p className="text-[13px] font-semibold text-foreground">
                                    {day.day_name}, {day.day} {periodLabel}
                                </p>
                                {can_write && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={markDayNormal}
                                        className="border-emerald-600/40 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCheck className="size-4" />
                                        Semua Normal
                                    </Button>
                                )}
                            </div>

                            {systems.map((group) => (
                                <div
                                    key={group.system}
                                    className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3"
                                >
                                    <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group.system}
                                    </p>
                                    {group.entries.map(({ item, index }) => {
                                        const dayKey = String(day.day);
                                        const isTrouble =
                                            item.checks?.[dayKey] === 'T';
                                        const finding = item.findings?.[dayKey];

                                        return (
                                            <div
                                                key={`${item.no}-${item.peralatan}`}
                                                className="flex flex-col gap-2 border-t border-border pt-2 first-of-type:border-t-0 first-of-type:pt-0"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <span className="min-w-0 flex-1 text-[13.5px] text-foreground">
                                                        {item.peralatan}
                                                    </span>
                                                    <ChoiceChips
                                                        options={['N', 'T']}
                                                        value={
                                                            item.checks?.[
                                                                dayKey
                                                            ] ?? ''
                                                        }
                                                        onChange={(value) =>
                                                            handleToggleCell(
                                                                index,
                                                                day.day,
                                                                (value ||
                                                                    item
                                                                        .checks?.[
                                                                        dayKey
                                                                    ]) as
                                                                    'N' | 'T',
                                                            )
                                                        }
                                                        disabled={!can_write}
                                                        tone={(code) =>
                                                            code === 'N'
                                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                                : 'border-rose-600 bg-rose-600 text-white'
                                                        }
                                                    />
                                                </div>
                                                {isTrouble && (
                                                    <div className="flex flex-col gap-2 rounded-lg border border-rose-300 bg-rose-50/70 p-2.5 dark:border-rose-700/60 dark:bg-rose-950/30">
                                                        <Input
                                                            value={
                                                                finding?.catatan ??
                                                                ''
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            placeholder="Uraian alasan tidak normal / tindakan awal..."
                                                            onChange={(e) =>
                                                                handleUpdateFindingNote(
                                                                    index,
                                                                    day.day,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 bg-background text-[12.5px]"
                                                        />
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-[11.5px] font-semibold text-rose-900 dark:text-rose-200">
                                                                Eviden Foto
                                                            </span>
                                                            <EvidencePhotoField
                                                                label={`Eviden ${item.peralatan}`}
                                                                url={
                                                                    finding?.eviden_url ??
                                                                    null
                                                                }
                                                                file={
                                                                    finding?.eviden_file ??
                                                                    null
                                                                }
                                                                onPick={(
                                                                    file,
                                                                ) =>
                                                                    handlePickFindingPhoto(
                                                                        index,
                                                                        day.day,
                                                                        file,
                                                                    )
                                                                }
                                                                onRemove={() =>
                                                                    handleRemoveFindingPhoto(
                                                                        index,
                                                                        day.day,
                                                                    )
                                                                }
                                                                disabled={
                                                                    !can_write
                                                                }
                                                                size="sm"
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}

                            <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                                <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    Petugas per shift (inisial / regu)
                                </p>
                                <div className="grid grid-cols-3 gap-2">
                                    {(
                                        [
                                            ['pagi', 'Pagi', shiftPagi],
                                            ['sore', 'Sore', shiftSore],
                                            ['malam', 'Malam', shiftMalam],
                                        ] as const
                                    ).map(([type, label, map]) => (
                                        <label
                                            key={type}
                                            className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                        >
                                            {label}
                                            <Input
                                                value={shiftValue(map)}
                                                onChange={(e) =>
                                                    handleUpdateShift(
                                                        type,
                                                        day.day,
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={2}
                                                className="text-center font-bold uppercase"
                                                disabled={!can_write}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}

                    <label className="flex flex-col gap-1.5 text-[12px] text-muted-foreground">
                        Catatan bulan ini
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            rows={3}
                            disabled={!can_write}
                            placeholder="Catatan teknis atau temuan selama patrol check…"
                        />
                    </label>
                    </MobileFormScreen>
                )}
            </>
        );
    }

    return (
        <>
            <Head
                title={`Patrol Check Mesin - ${filters.nama_mesin} - ${unit.name}`}
            />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Top Action Bar */}
                <div className="no-print flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        {can('operasi.input.view') && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get(operasiInput.index().url)
                                }
                                className="gap-2"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali ke Input Operasi
                            </Button>
                        )}
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Patrol Check Mesin
                            </h1>
                            <Badge
                                variant="secondary"
                                className="font-semibold"
                            >
                                {unit.name}
                            </Badge>
                            <Badge variant="outline" className="bg-primary/5">
                                {filters.nama_mesin}
                            </Badge>
                            <Badge variant="outline">{periodLabel}</Badge>
                            {dirty && (
                                <Badge
                                    variant="destructive"
                                    className="animate-pulse"
                                >
                                    Belum Disimpan
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleMarkTodayNormal}
                                    className="gap-1.5 border-emerald-600/30 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/20"
                                    title="Tandai semua item Normal (N) untuk hari ini"
                                >
                                    <CheckCheck className="size-4" />
                                    Tandai Normal Hari Ini
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleReset}
                                    disabled={!dirty || saving}
                                    className="gap-1.5"
                                >
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => handleSave()}
                                    disabled={!dirty || saving}
                                    className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Form'}
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExportExcel}
                            className="gap-1.5 border-emerald-600/30 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/20"
                        >
                            <FileSpreadsheet className="size-4" />
                            Excel
                        </Button>
                        <Button
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
                                        },
                                    }).url,
                                    '_blank',
                                )
                            }
                            className="gap-1.5 border-red-600/30 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/20"
                        >
                            <Download className="size-4" />
                            PDF
                        </Button>
                        <Button
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

                {/* Filter Selector */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Layanan"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
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
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, idx) => ({
                            value: String(idx + 1),
                            label,
                        }))}
                        className="w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                        className="w-28"
                    />
                    <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                        <Info className="size-4" />
                        <span>
                            Klik sel <strong>N</strong> (Normal) atau{' '}
                            <strong>T</strong> (Tidak Normal/Temuan) untuk
                            menandai centang.
                        </span>
                    </div>
                </div>

                {/* Main Schedule Container */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Document Header matching media_1790360501717.jpg */}
                    <div className="mb-3 grid grid-cols-12 items-stretch border-2 border-black dark:border-white">
                        {/* Logo Left */}
                        <div className="col-span-2 flex items-center justify-center border-r-2 border-black p-2 dark:border-white">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="max-h-12 w-auto object-contain"
                            />
                        </div>

                        {/* Title Center */}
                        <div className="col-span-8 flex flex-col justify-center text-center">
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                JASA PENDUKUNG TEKNIS 6 KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold tracking-wide text-foreground uppercase">
                                PATROL CHECK MESIN
                            </div>
                        </div>

                        {/* Logo Right */}
                        <div className="col-span-2 flex items-center justify-center border-l-2 border-black p-2 dark:border-white">
                            <img
                                src="/logo/mkp.jpg"
                                alt="MKP"
                                className="max-h-12 w-auto object-contain"
                            />
                        </div>
                    </div>

                    {/* Machine Badge */}
                    <div className="mb-2">
                        <span className="inline-block border border-black bg-muted/30 px-3 py-1 text-xs font-bold uppercase dark:border-white">
                            MESIN: {filters.nama_mesin}
                        </span>
                    </div>

                    {/* Main Table: 29 Equipment Items + Shift */}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table
                            className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white"
                            style={{
                                minWidth: `${36 + 220 + daysInMonth * 48}px`,
                            }}
                        >
                            <thead>
                                <tr className="bg-muted/60 font-bold text-black dark:text-white">
                                    <th
                                        rowSpan={3}
                                        className="sticky-col sticky left-0 z-20 border border-black bg-slate-100 px-1 py-1 text-[11px] font-bold text-black dark:border-white dark:bg-slate-900 dark:text-white"
                                        style={{
                                            width: '36px',
                                            minWidth: '36px',
                                            maxWidth: '36px',
                                        }}
                                    >
                                        NO
                                    </th>
                                    <th
                                        rowSpan={3}
                                        className="sticky-col sticky left-[36px] z-20 border border-black bg-slate-100 px-2.5 py-1 text-left text-[11px] font-bold text-black shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)] dark:border-white dark:bg-slate-900 dark:text-white"
                                        style={{
                                            width: '220px',
                                            minWidth: '220px',
                                            maxWidth: '220px',
                                        }}
                                    >
                                        PERALATAN
                                    </th>
                                    <th
                                        colSpan={daysInMonth * 2}
                                        className="border border-black bg-slate-100 px-1 py-1.5 text-[11px] font-bold tracking-wider text-black uppercase dark:border-white dark:bg-slate-900 dark:text-white"
                                    >
                                        {periodLabel}
                                    </th>
                                </tr>
                                <tr>
                                    {daysInfo.map((d) => (
                                        <th
                                            key={`day-${d.day}`}
                                            colSpan={2}
                                            className={`border border-black px-0.5 py-1 text-[11px] font-bold dark:border-white ${
                                                d.is_weekend
                                                    ? 'print-day-red bg-red-300 text-black dark:bg-red-900/60 dark:text-red-100'
                                                    : 'print-day-green bg-emerald-200 text-black dark:bg-emerald-950/60 dark:text-emerald-100'
                                            }`}
                                            style={{
                                                width: '48px',
                                                minWidth: '48px',
                                            }}
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                                <tr>
                                    {daysInfo.map((d) => (
                                        <Fragment key={`sub-${d.day}`}>
                                            <th
                                                className="border border-black bg-[#fef08a] px-0.5 py-1 text-[10px] font-bold text-black dark:border-white"
                                                style={{
                                                    width: '24px',
                                                    minWidth: '24px',
                                                }}
                                                title={`Tanggal ${d.day} - Normal (N)`}
                                            >
                                                N
                                            </th>
                                            <th
                                                className="border border-black bg-[#fee2e2] px-0.5 py-1 text-[10px] font-bold text-black dark:border-white"
                                                style={{
                                                    width: '24px',
                                                    minWidth: '24px',
                                                }}
                                                title={`Tanggal ${d.day} - Temuan (T)`}
                                            >
                                                T
                                            </th>
                                        </Fragment>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item, itemIdx) => {
                                    const isNewSystem =
                                        itemIdx === 0 ||
                                        items[itemIdx - 1].system !==
                                            item.system;

                                    const checks = item.checks || {};

                                    return (
                                        <Fragment
                                            key={`item-${item.no}-${itemIdx}`}
                                        >
                                            {isNewSystem && (
                                                <tr className="print-category-yellow bg-[#fef08a] font-bold text-black">
                                                    <td
                                                        colSpan={
                                                            2 + daysInMonth * 2
                                                        }
                                                        className="border border-black px-2 py-1 text-left text-xs font-bold uppercase dark:border-white"
                                                    >
                                                        <div className="sticky left-2 inline-block">
                                                            {item.system}
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}

                                            <tr className="group hover:bg-muted/30">
                                                <td
                                                    className="sticky-col sticky left-0 z-10 border border-black bg-card px-1 py-1 font-medium group-hover:bg-muted/80 dark:border-white"
                                                    style={{
                                                        width: '36px',
                                                        minWidth: '36px',
                                                        maxWidth: '36px',
                                                    }}
                                                >
                                                    {item.no}
                                                </td>
                                                <td
                                                    className="sticky-col sticky left-[36px] z-10 border border-black bg-card px-2.5 py-1 text-left font-semibold shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)] group-hover:bg-muted/80 dark:border-white"
                                                    style={{
                                                        width: '220px',
                                                        minWidth: '220px',
                                                        maxWidth: '220px',
                                                    }}
                                                    title={item.peralatan}
                                                >
                                                    <div className="truncate text-xs font-semibold">
                                                        {item.peralatan}
                                                    </div>
                                                </td>
                                                {daysInfo.map((d) => {
                                                    const val =
                                                        checks[String(d.day)] ||
                                                        checks[d.day] ||
                                                        '';
                                                    const isN = val === 'N';
                                                    const isT = val === 'T';

                                                    return (
                                                        <Fragment
                                                            key={`cell-${item.no}-${d.day}`}
                                                        >
                                                            {/* Cell N */}
                                                            <td
                                                                onClick={() =>
                                                                    handleToggleCell(
                                                                        itemIdx,
                                                                        d.day,
                                                                        'N',
                                                                    )
                                                                }
                                                                className={`cursor-pointer border border-black p-0 text-center font-bold transition-colors dark:border-white ${
                                                                    isN
                                                                        ? 'bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                }`}
                                                                style={{
                                                                    width: '24px',
                                                                    minWidth:
                                                                        '24px',
                                                                    height: '26px',
                                                                }}
                                                                title={`Tanggal ${d.day}: Klik Normal (N)`}
                                                            >
                                                                {isN ? (
                                                                    <Check className="mx-auto size-3.5 font-bold text-black dark:text-white" />
                                                                ) : (
                                                                    ''
                                                                )}
                                                            </td>

                                                            {/* Cell T */}
                                                            <td
                                                                onClick={() =>
                                                                    handleToggleCell(
                                                                        itemIdx,
                                                                        d.day,
                                                                        'T',
                                                                    )
                                                                }
                                                                className={`cursor-pointer border border-black p-0 text-center font-bold transition-colors dark:border-white ${
                                                                    isT
                                                                        ? 'bg-red-200 text-red-700 dark:bg-red-950/80 dark:text-red-200'
                                                                        : 'hover:bg-red-50/50 dark:hover:bg-red-950/20'
                                                                }`}
                                                                style={{
                                                                    width: '24px',
                                                                    minWidth:
                                                                        '24px',
                                                                    height: '26px',
                                                                }}
                                                                title={`Tanggal ${d.day}: Klik Temuan/Tidak Normal (T)`}
                                                            >
                                                                {isT ? (
                                                                    <Check className="mx-auto size-3.5 font-bold text-red-600" />
                                                                ) : (
                                                                    ''
                                                                )}
                                                            </td>
                                                        </Fragment>
                                                    );
                                                })}
                                            </tr>
                                        </Fragment>
                                    );
                                })}

                                {/* PELAKSANA SHIFT Header */}
                                <tr className="print-shift-header bg-muted/60 font-bold text-foreground">
                                    <td
                                        colSpan={2 + daysInMonth * 2}
                                        className="border border-black px-2 py-1 text-left text-xs font-bold uppercase dark:border-white"
                                    >
                                        <div className="sticky left-2 inline-block">
                                            PELAKSANA SHIFT
                                        </div>
                                    </td>
                                </tr>

                                {/* Shift Pagi */}
                                <tr className="hover:bg-muted/20">
                                    <td
                                        colSpan={2}
                                        className="sticky-col sticky left-0 z-10 border border-black bg-card px-2.5 py-1 text-left font-bold text-foreground shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)] dark:border-white"
                                        style={{
                                            width: '256px',
                                            minWidth: '256px',
                                            maxWidth: '256px',
                                        }}
                                    >
                                        Shift Pagi : 08.00 - 16.00
                                    </td>
                                    {daysInfo.map((d) => (
                                        <td
                                            key={`sp-${d.day}`}
                                            colSpan={2}
                                            className="border border-black p-0.5 text-center dark:border-white"
                                            style={{
                                                width: '48px',
                                                minWidth: '48px',
                                            }}
                                        >
                                            {can_write ? (
                                                <Input
                                                    value={
                                                        shiftPagi[
                                                            String(d.day)
                                                        ] ||
                                                        shiftPagi[d.day] ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        handleUpdateShift(
                                                            'pagi',
                                                            d.day,
                                                            e.target.value,
                                                        )
                                                    }
                                                    maxLength={2}
                                                    className="h-6 w-full p-0 text-center text-xs font-bold uppercase"
                                                />
                                            ) : (
                                                <span className="text-xs font-bold">
                                                    {shiftPagi[String(d.day)] ||
                                                        shiftPagi[d.day] ||
                                                        ''}
                                                </span>
                                            )}
                                        </td>
                                    ))}
                                </tr>

                                {/* Shift Sore */}
                                <tr className="hover:bg-muted/20">
                                    <td
                                        colSpan={2}
                                        className="sticky-col sticky left-0 z-10 border border-black bg-card px-2.5 py-1 text-left font-bold text-foreground shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)] dark:border-white"
                                        style={{
                                            width: '256px',
                                            minWidth: '256px',
                                            maxWidth: '256px',
                                        }}
                                    >
                                        Shift Sore : 16.00 - 24.00
                                    </td>
                                    {daysInfo.map((d) => (
                                        <td
                                            key={`ss-${d.day}`}
                                            colSpan={2}
                                            className="border border-black p-0.5 text-center dark:border-white"
                                            style={{
                                                width: '48px',
                                                minWidth: '48px',
                                            }}
                                        >
                                            {can_write ? (
                                                <Input
                                                    value={
                                                        shiftSore[
                                                            String(d.day)
                                                        ] ||
                                                        shiftSore[d.day] ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        handleUpdateShift(
                                                            'sore',
                                                            d.day,
                                                            e.target.value,
                                                        )
                                                    }
                                                    maxLength={2}
                                                    className="h-6 w-full p-0 text-center text-xs font-bold uppercase"
                                                />
                                            ) : (
                                                <span className="text-xs font-bold">
                                                    {shiftSore[String(d.day)] ||
                                                        shiftSore[d.day] ||
                                                        ''}
                                                </span>
                                            )}
                                        </td>
                                    ))}
                                </tr>

                                {/* Shift Malam */}
                                <tr className="hover:bg-muted/20">
                                    <td
                                        colSpan={2}
                                        className="sticky-col sticky left-0 z-10 border border-black bg-card px-2.5 py-1 text-left font-bold text-foreground shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)] dark:border-white"
                                        style={{
                                            width: '256px',
                                            minWidth: '256px',
                                            maxWidth: '256px',
                                        }}
                                    >
                                        Shift Malam : 00.00 - 08.00
                                    </td>
                                    {daysInfo.map((d) => (
                                        <td
                                            key={`sm-${d.day}`}
                                            colSpan={2}
                                            className="border border-black p-0.5 text-center dark:border-white"
                                            style={{
                                                width: '48px',
                                                minWidth: '48px',
                                            }}
                                        >
                                            {can_write ? (
                                                <Input
                                                    value={
                                                        shiftMalam[
                                                            String(d.day)
                                                        ] ||
                                                        shiftMalam[d.day] ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        handleUpdateShift(
                                                            'malam',
                                                            d.day,
                                                            e.target.value,
                                                        )
                                                    }
                                                    maxLength={2}
                                                    className="h-6 w-full p-0 text-center text-xs font-bold uppercase"
                                                />
                                            ) : (
                                                <span className="text-xs font-bold">
                                                    {shiftMalam[
                                                        String(d.day)
                                                    ] ||
                                                        shiftMalam[d.day] ||
                                                        ''}
                                                </span>
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Tindak Lanjut Kondisi Tidak Normal (T) — hanya muncul bila ada sel T */}
                    {(() => {
                        const troubleRows: {
                            itemIdx: number;
                            day: number;
                            item: PatrolCheckItem;
                        }[] = [];

                        items.forEach((item, itemIdx) => {
                            Object.entries(item.checks || {}).forEach(
                                ([dKey, st]) => {
                                    if (st === 'T') {
                                        troubleRows.push({
                                            itemIdx,
                                            day: Number(dKey),
                                            item,
                                        });
                                    }
                                },
                            );
                        });

                        if (troubleRows.length === 0) {
                            return null;
                        }

                        return (
                            <div className="mt-4 rounded-md border border-rose-300 bg-rose-50/40 p-3 dark:border-rose-700/60 dark:bg-rose-950/20">
                                <p className="mb-2 text-xs font-bold text-rose-800 uppercase dark:text-rose-200">
                                    Tindak Lanjut Kondisi Tidak Normal (T) —
                                    Uraian Alasan &amp; Eviden Foto (
                                    {troubleRows.length})
                                </p>
                                <div className="flex flex-col gap-2">
                                    {troubleRows.map(
                                        ({ itemIdx, day, item }) => {
                                            const dayKey = String(day);
                                            const finding =
                                                item.findings?.[dayKey];

                                            return (
                                                <div
                                                    key={`${item.no}-${day}`}
                                                    className="flex flex-wrap items-center gap-2 rounded border border-rose-200 bg-card p-2 text-xs dark:border-rose-800"
                                                >
                                                    <Badge
                                                        variant="destructive"
                                                        className="shrink-0"
                                                    >
                                                        Tgl {day}
                                                    </Badge>
                                                    <span className="w-48 shrink-0 font-semibold">
                                                        {item.peralatan}
                                                    </span>
                                                    <Input
                                                        value={
                                                            finding?.catatan ??
                                                            ''
                                                        }
                                                        disabled={!can_write}
                                                        placeholder="Tuliskan uraian alasan tidak normal & tindakan awal..."
                                                        onChange={(e) =>
                                                            handleUpdateFindingNote(
                                                                itemIdx,
                                                                day,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-8 min-w-60 flex-1 text-xs"
                                                    />
                                                    <EvidencePhotoField
                                                        label={`Eviden ${item.peralatan} Tgl ${day}`}
                                                        url={
                                                            finding?.eviden_url ??
                                                            null
                                                        }
                                                        file={
                                                            finding?.eviden_file ??
                                                            null
                                                        }
                                                        onPick={(file) =>
                                                            handlePickFindingPhoto(
                                                                itemIdx,
                                                                day,
                                                                file,
                                                            )
                                                        }
                                                        onRemove={() =>
                                                            handleRemoveFindingPhoto(
                                                                itemIdx,
                                                                day,
                                                            )
                                                        }
                                                        disabled={!can_write}
                                                        size="sm"
                                                    />
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                            </div>
                        );
                    })()}

                    {/* Note & Catatan */}
                    <div className="mt-4 space-y-2 border-t border-border pt-3">
                        <div className="text-xs font-semibold text-muted-foreground">
                            NOTE : Patrol Check Dilaksanakan Pada Saat Shift
                            Pagi
                        </div>
                        <div className="space-y-1">
                            <span className="text-xs font-bold text-foreground">
                                CATATAN :
                            </span>
                            {can_write ? (
                                <Textarea
                                    value={catatan}
                                    onChange={(e) => {
                                        setCatatan(e.target.value);
                                        setDirty(true);
                                    }}
                                    placeholder="Tuliskan catatan teknis atau temuan operasional selama patrol check..."
                                    className="min-h-16 text-xs"
                                />
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {catatan || '-'}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

OperasiPatrolCheckMesinIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Patrol Check Mesin', href: '#' },
    ],
};
