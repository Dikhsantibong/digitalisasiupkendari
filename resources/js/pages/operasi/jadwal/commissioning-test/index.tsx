import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Download,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RefreshCw,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import {
    MobileTimelineForm,
    TimelineField,
    weekColumnOf,
    weekKeyOf,
    YEAR_WEEK_DAYS,
} from '@/components/mobile/timeline-form';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { AutoWrapCellInput } from '@/components/ui/auto-wrap-input';
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
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import commissioningTest from '@/routes/operasi/jadwal/commissioning-test';
import type { IdName } from '@/types';

type CommissioningRow = {
    id: number | null;
    machine_id: number | null;
    no_urut: number;
    nama_mesin: string;
    section: string;
    beban_50: string[];
    beban_75: string[];
    beban_100: string[];
    realisasi?: string[];
    keterangan?: string;
};

type MachineItem = {
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
    };
    options: {
        units: IdName[];
        years: number[];
    };
    machines: MachineItem[];
    rows: CommissioningRow[];
    can_write: boolean;
};

const DEFAULT_SECTION = 'A. PEMBUATAN DATA TEKNIKS';
const CUSTOM_MACHINE_OPTION = '__custom__';

const BEBAN_CATEGORIES = [
    {
        key: '50',
        label: 'Beban 50%',
        onClass: 'border-amber-500 bg-amber-300 text-black',
    },
    {
        key: '75',
        label: 'Beban 75%',
        onClass: 'border-orange-500 bg-orange-400 text-black',
    },
    {
        key: '100',
        label: 'Beban 100%',
        onClass: 'border-emerald-600 bg-emerald-500 text-black',
    },
];

const MONTH_NAMES = [
    'JANUARY',
    'FEBRUARY',
    'MARCH',
    'APRIL',
    'MAY',
    'JUNE',
    'JULY',
    'AUGUST',
    'SEPTEMBER',
    'OCTOBER',
    'NOVEMBER',
    'DECEMBER',
];

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
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 6.5px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 1.5px 0.5px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-orange-header {
        background-color: #ed7d31 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-marked-cell {
        background-color: #fef08a !important;
        font-weight: bold !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-grand-total {
        background-color: #00b0f0 !important;
        font-weight: bold !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

function splitSectionTitle(section: string): { prefix: string; label: string } {
    const trimmed = section.trim();
    const match = trimmed.match(/^([A-Z]+\.)\s+(.*)$/);

    if (match) {
        return { prefix: match[1], label: match[2] };
    }

    return { prefix: '', label: trimmed || 'PEMBUATAN DATA TEKNIKS' };
}

export default function JadwalCommissioningTestIndex({
    unit,
    filters,
    options,
    machines,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<CommissioningRow[]>(initialRows);
    const compact = useCompactLayout();
    const [mobileEditing, setMobileEditing] = useState(false);
    const [initialWeek] = useState(() => weekColumnOf(new Date()));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state for adding machine from Master Data Mesin or custom name
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [selectedMachineOption, setSelectedMachineOption] = useState<string>(
        machines.length > 0 ? String(machines[0].id) : CUSTOM_MACHINE_OPTION,
    );
    const [newNamaMesin, setNewNamaMesin] = useState('');
    const [newSection, setNewSection] = useState(DEFAULT_SECTION);

    const machineById = useMemo(() => {
        const map = new Map<number, string>();
        machines.forEach((m) => map.set(m.id, m.name.toUpperCase()));

        return map;
    }, [machines]);

    const missingMasterMachines = useMemo(() => {
        const existingIds = new Set(
            rows.map((r) => r.machine_id).filter((id): id is number => id !== null),
        );

        return machines.filter((m) => !existingIds.has(m.id));
    }, [machines, rows]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            commissioningTest.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    const handleToggleCell = (
        rowIndex: number,
        type: '50' | '75' | '100',
        weekKey: string,
    ) => {
        if (!can_write) {
            return;
        }

        setRows((prev) => {
            const updated = [...prev];
            const targetRow = { ...updated[rowIndex] };
            const propName =
                type === '50'
                    ? 'beban_50'
                    : type === '75'
                      ? 'beban_75'
                      : 'beban_100';
            const currentArr = [...(targetRow[propName] || [])];
            const existsIdx = currentArr.indexOf(weekKey);

            if (existsIdx >= 0) {
                currentArr.splice(existsIdx, 1);
            } else {
                currentArr.push(weekKey);
            }

            targetRow[propName] = currentArr;
            updated[rowIndex] = targetRow;

            return updated;
        });
        setDirty(true);
    };

    const handleUpdateName = (rowIndex: number, newName: string) => {
        if (!can_write) {
            return;
        }

        setRows((prev) => {
            const updated = [...prev];
            const existing = updated[rowIndex];
            const linkedMasterName = existing.machine_id
                ? machineById.get(existing.machine_id)
                : undefined;
            const matchesMaster =
                linkedMasterName !== undefined &&
                linkedMasterName === newName.trim().toUpperCase();

            updated[rowIndex] = {
                ...existing,
                machine_id: matchesMaster ? existing.machine_id : null,
                nama_mesin: newName,
            };

            return updated;
        });
        setDirty(true);
    };

    const handleSyncMasterMachines = () => {
        if (!can_write || machines.length === 0) {
            return;
        }

        setRows((prev) => {
            const refreshed = prev.map((row) => {
                if (row.machine_id !== null) {
                    const masterName = machineById.get(row.machine_id);
                    if (masterName) {
                        return { ...row, nama_mesin: masterName };
                    }
                }

                return row;
            });

            const existingIds = new Set(
                refreshed
                    .map((r) => r.machine_id)
                    .filter((id): id is number => id !== null),
            );

            const additions: CommissioningRow[] = machines
                .filter((m) => !existingIds.has(m.id))
                .map((m, idx) => ({
                    id: null,
                    machine_id: m.id,
                    no_urut: refreshed.length + idx + 1,
                    nama_mesin: m.name.toUpperCase(),
                    section: DEFAULT_SECTION,
                    beban_50: [],
                    beban_75: [],
                    beban_100: [],
                    realisasi: [],
                    keterangan: '',
                }));

            return [...refreshed, ...additions].map((r, idx) => ({
                ...r,
                no_urut: idx + 1,
            }));
        });
        setDirty(true);
    };

    const openAddDialog = () => {
        const defaultTarget =
            missingMasterMachines[0]?.id ??
            machines[0]?.id ??
            null;
        setSelectedMachineOption(
            defaultTarget !== null
                ? String(defaultTarget)
                : CUSTOM_MACHINE_OPTION,
        );
        setNewNamaMesin('');
        setNewSection(DEFAULT_SECTION);
        setIsAddOpen(true);
    };

    const handleAddMesin = () => {
        const isCustom = selectedMachineOption === CUSTOM_MACHINE_OPTION;
        const selectedMaster = !isCustom
            ? machines.find((m) => String(m.id) === selectedMachineOption)
            : undefined;
        const finalName = selectedMaster
            ? selectedMaster.name.toUpperCase()
            : newNamaMesin.trim().toUpperCase();

        if (!finalName) {
            return;
        }

        setRows((prev) => [
            ...prev,
            {
                id: null,
                machine_id: selectedMaster ? selectedMaster.id : null,
                no_urut: prev.length + 1,
                nama_mesin: finalName,
                section: newSection.trim().toUpperCase() || DEFAULT_SECTION,
                beban_50: [],
                beban_75: [],
                beban_100: [],
                realisasi: [],
                keterangan: '',
            },
        ]);
        setNewNamaMesin('');
        setIsAddOpen(false);
        setDirty(true);
    };

    const handleRemoveRow = (rowIndex: number) => {
        if (!can_write) {
            return;
        }

        setRows((prev) =>
            prev
                .filter((_, idx) => idx !== rowIndex)
                .map((r, idx) => ({ ...r, no_urut: idx + 1 })),
        );
        setDirty(true);
    };

    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    const handleSave = () => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            commissioningTest.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    machine_id: r.machine_id,
                    no_urut: idx + 1,
                    nama_mesin: r.nama_mesin,
                    section: r.section || DEFAULT_SECTION,
                    beban_50: r.beban_50 || [],
                    beban_75: r.beban_75 || [],
                    beban_100: r.beban_100 || [],
                    realisasi: r.realisasi || [],
                    keterangan: r.keterangan || '',
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const grandTotal = useMemo(() => {
        return rows.reduce(
            (sum, r) =>
                sum +
                (r.beban_50?.length || 0) +
                (r.beban_75?.length || 0) +
                (r.beban_100?.length || 0),
            0,
        );
    }, [rows]);

    const handleExportExcel = async () => {
        const weekStart = 3;
        const weekEnd = 50; // cols 3..50 (48 week columns)
        const colJumlah = 51;
        const totalCols = 52;
        const headerTop = 4;
        const headerMid = 5;
        const headerBottom = 6;
        const dataStart = 7;

        const sheetData: (string | number)[][] = [[], [], [], []];

        // Header Row 1: NO | MESIN PEMBANGKIT | BULAN (spanning col 2..51)
        const rowH1: (string | number)[] = ['NO', 'MESIN PEMBANGKIT', 'BULAN'];
        for (let c = 3; c < totalCols; c++) {
            rowH1.push('');
        }
        sheetData.push(rowH1);

        // Header Row 2: [blank, blank, blank] + Months x4 + JUMLAH
        const rowH2: (string | number)[] = ['', '', ''];
        MONTH_NAMES.forEach((m) => {
            rowH2.push(m, '', '', '');
        });
        rowH2.push('JUMLAH');
        sheetData.push(rowH2);

        // Header Row 3: [blank, blank, UJI BEBAN] + Weeks 1..4 + blank under JUMLAH
        const rowH3: (string | number)[] = ['', '', 'UJI BEBAN'];
        for (let m = 1; m <= 12; m++) {
            rowH3.push(1, 2, 3, 4);
        }
        rowH3.push('');
        sheetData.push(rowH3);

        let currentRowIndex = dataStart;
        const sectionRowIndices: number[] = [];
        let currentSection: string | null = null;

        rows.forEach((r, idx) => {
            const section = r.section || DEFAULT_SECTION;

            if (section !== currentSection) {
                currentSection = section;
                const { prefix, label } = splitSectionTitle(section);
                const secRow: (string | number)[] = [prefix, label];
                for (let c = 2; c < totalCols; c++) {
                    secRow.push('');
                }
                sheetData.push(secRow);
                sectionRowIndices.push(currentRowIndex);
                currentRowIndex++;
            }

            const b50 = r.beban_50 || [];
            const b75 = r.beban_75 || [];
            const b100 = r.beban_100 || [];

            const r50: (string | number)[] = [
                r.no_urut || idx + 1,
                r.nama_mesin,
                '50%',
            ];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    r50.push(b50.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            r50.push(b50.length);
            sheetData.push(r50);
            currentRowIndex++;

            const r75: (string | number)[] = ['', '', '75%'];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    r75.push(b75.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            r75.push(b75.length);
            sheetData.push(r75);
            currentRowIndex++;

            const r100: (string | number)[] = ['', '', '100%'];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    r100.push(b100.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            r100.push(b100.length);
            sheetData.push(r100);
            currentRowIndex++;
        });

        // TOTAL row (merged cols 0..50, grandTotal in col 51)
        const totRow: (string | number)[] = ['TOTAL'];
        for (let c = 1; c < colJumlah; c++) {
            totRow.push('');
        }
        totRow.push(grandTotal);
        sheetData.push(totRow);
        const totalRowIdx = currentRowIndex;
        currentRowIndex++;

        // Blank separator + bottom-left Recap Table
        sheetData.push([]);
        currentRowIndex++;
        const recapHeaderIdx = currentRowIndex;
        sheetData.push(['NO', 'NAMA MESIN PEMBANGKIT', 'UJI BBN', 'DATA']);
        currentRowIndex++;

        rows.forEach((r, rIdx) => {
            sheetData.push([
                r.no_urut || rIdx + 1,
                r.nama_mesin,
                '50%',
                (r.beban_50 || []).length,
            ]);
            currentRowIndex++;
            sheetData.push(['', '', '75%', (r.beban_75 || []).length]);
            currentRowIndex++;
            sheetData.push(['', '', '100%', (r.beban_100 || []).length]);
            currentRowIndex++;
        });

        const { workbook, worksheet: ws } = createSheet(
            'Commissioning Test',
            sheetData,
        );

        const markedCell: StyleSpec = {
            fill: XLSX_COLORS.amber,
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        const grandTotalCell: StyleSpec = {
            fill: XLSX_COLORS.blue,
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null;
            }

            if (r === headerTop || r === headerMid || r === headerBottom) {
                return SPECS.headerOrange;
            }

            if (sectionRowIndices.includes(r)) {
                return c === 0 ? SPECS.labelCenter : SPECS.category;
            }

            if (r >= dataStart && r < totalRowIdx) {
                if (c === 0 || c === 2) {
                    return SPECS.labelCenter;
                }

                if (c === 1) {
                    return SPECS.label;
                }

                if (c >= weekStart && c <= weekEnd) {
                    return String(sheetData[r]?.[c] ?? '') === '1'
                        ? markedCell
                        : SPECS.cell;
                }

                return { ...SPECS.cell, bold: true };
            }

            if (r === totalRowIdx) {
                if (c === colJumlah) {
                    return grandTotalCell;
                }

                return SPECS.total;
            }

            if (r >= recapHeaderIdx && c < 4) {
                if (r === recapHeaderIdx) {
                    return SPECS.headerOrange;
                }

                if (c === 1) {
                    return SPECS.label;
                }

                if (c === 0 || c === 2) {
                    return SPECS.labelCenter;
                }

                return SPECS.cell;
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // NO
        mergeCells(ws, headerTop, 1, headerBottom, 1); // MESIN PEMBANGKIT
        mergeCells(ws, headerTop, 2, headerTop, colJumlah); // BULAN
        mergeCells(ws, headerMid, colJumlah, headerBottom, colJumlah); // JUMLAH

        for (let m = 0; m < 12; m++) {
            const c = weekStart + m * 4;
            mergeCells(ws, headerMid, c, headerMid, c + 3);
        }

        sectionRowIndices.forEach((sIdx) => {
            mergeCells(ws, sIdx, 1, sIdx, totalCols - 1);
        });

        let rowTracker = dataStart;
        rows.forEach(() => {
            if (sectionRowIndices.includes(rowTracker)) {
                rowTracker++;
            }
            mergeCells(ws, rowTracker, 0, rowTracker + 2, 0);
            mergeCells(ws, rowTracker, 1, rowTracker + 2, 1);
            rowTracker += 3;
        });

        mergeCells(ws, totalRowIdx, 0, totalRowIdx, weekEnd);

        let recapRowTracker = recapHeaderIdx + 1;
        rows.forEach(() => {
            mergeCells(ws, recapRowTracker, 0, recapRowTracker + 2, 0);
            mergeCells(ws, recapRowTracker, 1, recapRowTracker + 2, 1);
            recapRowTracker += 3;
        });

        const colWidths = [4, 26, 9, ...Array(48).fill(2.6), 8];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE - KIT',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'JADWAL PELAKSANAAN COMMISSIONING TEST MESIN PEMBANGKIT',
                ],
                barTitle:
                    'JADWAL PELAKSANAAN COMMISSIONING TEST MESIN PEMBANGKIT',
            },
        );

        const filename = `Jadwal_Commissioning_Test_Mesin_${unit.name.replace(/\s+/g, '_')}_${filters.year}.xlsx`;
        await downloadWorkbook(workbook, filename);
    };

    const selectedMachineAlreadyAdded =
        selectedMachineOption !== CUSTOM_MACHINE_OPTION &&
        rows.some((r) => String(r.machine_id) === selectedMachineOption);

    const canSubmitAdd =
        selectedMachineOption === CUSTOM_MACHINE_OPTION
            ? newNamaMesin.trim() !== ''
            : selectedMachineOption !== '';

    return (
        <>
            <Head
                title={`Jadwal Pelaksanaan Commissioning Test Mesin - ${unit.name}`}
            />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Top Action Bar */}
                <div className="no-print flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get(jadwal.index().url)}
                            className="gap-2"
                        >
                            <ArrowLeft className="size-4" />
                            Kembali ke Jadwal
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Jadwal Pelaksanaan Commissioning Test Mesin
                            </h1>
                            <Badge variant="secondary" className="font-semibold">
                                {unit.name}
                            </Badge>
                            <Badge variant="outline">{filters.year}</Badge>
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
                                    onClick={handleSyncMasterMachines}
                                    disabled={machines.length === 0 || saving}
                                    className="gap-1.5"
                                    title="Muat & sinkronkan daftar mesin dari Master Data Mesin unit ini"
                                >
                                    <RefreshCw className="size-4" />
                                    Muat Master Mesin
                                    {missingMasterMachines.length > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-0.5 px-1.5 py-0 text-[10px]"
                                        >
                                            +{missingMasterMachines.length}
                                        </Badge>
                                    )}
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
                                    variant="outline"
                                    size="sm"
                                    onClick={openAddDialog}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Mesin
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={!dirty || saving}
                                    className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Jadwal'}
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
                                    commissioningTest.pdf({
                                        query: {
                                            unit_id: filters.unit_id,
                                            year: filters.year,
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
                    <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                        <Info className="size-4 shrink-0" />
                        <span>
                            Data mesin diambil dari Master Data Mesin. Klik sel
                            minggu untuk menandai &apos;1&apos; pada UJI BEBAN
                            50%, 75%, atau 100%.
                        </span>
                    </div>
                </div>

                {/* Main Schedule Container */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Document Header */}
                    <div className="mb-4 grid grid-cols-12 items-stretch border-2 border-black dark:border-white">
                        <div className="col-span-2 flex items-center justify-center border-r-2 border-black p-2 dark:border-white">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="max-h-12 w-auto object-contain"
                            />
                        </div>

                        <div className="col-span-8 flex flex-col justify-center text-center">
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6
                                SITE - KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold tracking-wide text-foreground uppercase">
                                JADWAL PELAKSANAAN COMMISSIONING TEST MESIN
                                PEMBANGKIT
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

                    {/* Table 1: Matrix 12 Bulan x 4 Minggu = 48 Kolom */}
                    {compact ? (
                        <div className="py-3">
                            <MobileTimelineForm<CommissioningRow>
                                days={YEAR_WEEK_DAYS}
                                rows={rows}
                                rowKey={(row, index) => row.id ?? `new-${index}`}
                                title={(row) => row.nama_mesin || 'Baris baru'}
                                categories={BEBAN_CATEGORIES}
                                isOn={(row, category, week) =>
                                    row[
                                        `beban_${category}` as
                                            | 'beban_50'
                                            | 'beban_75'
                                            | 'beban_100'
                                    ].includes(weekKeyOf(week))
                                }
                                onToggle={(row, category, week) =>
                                    handleToggleCell(
                                        rows.indexOf(row),
                                        category as '50' | '75' | '100',
                                        weekKeyOf(week),
                                    )
                                }
                                details={(row, index) => (
                                    <TimelineField
                                        label="Mesin Pembangkit"
                                        value={row.nama_mesin}
                                        onChange={(v) =>
                                            handleUpdateName(index, v)
                                        }
                                        readOnly={!can_write}
                                    />
                                )}
                                summary={(row) =>
                                    `50%: ${row.beban_50.length} · 75%: ${row.beban_75.length} · 100%: ${row.beban_100.length}`
                                }
                                onRemove={(_row, index) =>
                                    handleRemoveRow(index)
                                }
                                sectionOf={(row) =>
                                    row.section || DEFAULT_SECTION
                                }
                                dayLabel={(week) =>
                                    `${week.dow} ${filters.year} · minggu ${week.label}`
                                }
                                initialDay={initialWeek}
                                readOnly={!can_write}
                                editing={mobileEditing}
                                onEditingChange={setMobileEditing}
                                empty="Belum ada mesin aktif di Master Data Mesin."
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="excel-sheet print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                                <thead>
                                    <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                        <th
                                            rowSpan={3}
                                            className="border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                            style={{ width: '32px' }}
                                        >
                                            NO
                                        </th>
                                        <th
                                            rowSpan={3}
                                            className="border border-black px-3 py-1.5 text-[11px] dark:border-white"
                                            style={{ minWidth: '210px' }}
                                        >
                                            MESIN PEMBANGKIT
                                        </th>
                                        <th
                                            colSpan={50}
                                            className="border border-black px-1 py-1 text-[11px] tracking-wide uppercase dark:border-white"
                                        >
                                            BULAN
                                        </th>
                                        <th
                                            rowSpan={3}
                                            className="no-print border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                            style={{ width: '40px' }}
                                        >
                                            AKSI
                                        </th>
                                    </tr>
                                    <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                        <th
                                            className="border border-black px-1.5 py-1 text-[10px] dark:border-white"
                                            style={{ width: '64px' }}
                                        />
                                        {MONTH_NAMES.map((m) => (
                                            <th
                                                key={m}
                                                colSpan={4}
                                                className="border border-black px-1 py-1 text-[10px] uppercase dark:border-white"
                                            >
                                                {m}
                                            </th>
                                        ))}
                                        <th
                                            rowSpan={2}
                                            className="border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                            style={{ width: '52px' }}
                                        >
                                            JUMLAH
                                        </th>
                                    </tr>
                                    <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                        <th
                                            className="border border-black px-1.5 py-0.5 text-[10px] whitespace-nowrap dark:border-white"
                                            style={{ width: '64px' }}
                                        >
                                            UJI BEBAN
                                        </th>
                                        {Array.from({ length: 12 }).map(
                                            (_, mIdx) => (
                                                <Fragment key={mIdx}>
                                                    <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">
                                                        1
                                                    </th>
                                                    <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">
                                                        2
                                                    </th>
                                                    <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">
                                                        3
                                                    </th>
                                                    <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">
                                                        4
                                                    </th>
                                                </Fragment>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={53}
                                                className="border border-black px-4 py-6 text-center text-muted-foreground dark:border-white"
                                            >
                                                Belum ada mesin aktif pada Master
                                                Data Mesin untuk unit ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        (() => {
                                            let currentSection: string | null =
                                                null;

                                            return rows.map((row, rIdx) => {
                                                const section =
                                                    row.section ||
                                                    DEFAULT_SECTION;
                                                const renderSectionHeader =
                                                    section !== currentSection;

                                                if (renderSectionHeader) {
                                                    currentSection = section;
                                                }

                                                const { prefix, label } =
                                                    splitSectionTitle(section);
                                                const b50 = row.beban_50 || [];
                                                const b75 = row.beban_75 || [];
                                                const b100 = row.beban_100 || [];

                                                return (
                                                    <Fragment
                                                        key={
                                                            row.id ??
                                                            `row-${rIdx}`
                                                        }
                                                    >
                                                        {renderSectionHeader && (
                                                            <tr className="bg-muted/20 font-bold text-foreground">
                                                                <td className="border border-black px-1 py-1 text-center text-xs font-bold dark:border-white">
                                                                    {prefix}
                                                                </td>
                                                                <td
                                                                    colSpan={51}
                                                                    className="border border-black px-3 py-1 text-left text-xs font-bold dark:border-white"
                                                                >
                                                                    {label}
                                                                </td>
                                                                <td className="no-print border border-black dark:border-white" />
                                                            </tr>
                                                        )}

                                                        {/* Sub-row 1: 50% */}
                                                        <tr className="hover:bg-muted/30">
                                                            <td
                                                                rowSpan={3}
                                                                className="border border-black px-1 py-1 font-medium dark:border-white"
                                                            >
                                                                {row.no_urut ||
                                                                    rIdx + 1}
                                                            </td>
                                                            <td
                                                                rowSpan={3}
                                                                className="min-w-[210px] border border-black p-1 text-left align-middle whitespace-normal dark:border-white"
                                                            >
                                                                <AutoWrapCellInput
                                                                    value={
                                                                        row.nama_mesin
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        handleUpdateName(
                                                                            rIdx,
                                                                            val,
                                                                        )
                                                                    }
                                                                    className="font-semibold uppercase"
                                                                    readOnly={
                                                                        !can_write
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-semibold dark:border-white">
                                                                50%
                                                            </td>
                                                            {Array.from({
                                                                length: 12,
                                                            }).map((_, mIdx) =>
                                                                [1, 2, 3, 4].map(
                                                                    (w) => {
                                                                        const key = `${mIdx + 1}-${w}`;
                                                                        const isMarked =
                                                                            b50.includes(
                                                                                key,
                                                                            );

                                                                        return (
                                                                            <td
                                                                                key={`50-${key}`}
                                                                                onClick={() =>
                                                                                    handleToggleCell(
                                                                                        rIdx,
                                                                                        '50',
                                                                                        key,
                                                                                    )
                                                                                }
                                                                                className={`cursor-pointer border border-black px-0.5 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                                    isMarked
                                                                                        ? 'print-marked-cell bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                                }`}
                                                                                title={`Klik untuk ubah 50% Bln ${mIdx + 1} M${w}`}
                                                                            >
                                                                                {isMarked
                                                                                    ? '1'
                                                                                    : ''}
                                                                            </td>
                                                                        );
                                                                    },
                                                                ),
                                                            )}
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-bold dark:border-white">
                                                                {b50.length}
                                                            </td>
                                                            <td
                                                                rowSpan={3}
                                                                className="no-print border border-black px-1 py-1 dark:border-white"
                                                            >
                                                                {can_write && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            handleRemoveRow(
                                                                                rIdx,
                                                                            )
                                                                        }
                                                                        className="size-7 text-destructive hover:bg-destructive/10"
                                                                        title="Hapus Mesin"
                                                                    >
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                )}
                                                            </td>
                                                        </tr>

                                                        {/* Sub-row 2: 75% */}
                                                        <tr className="hover:bg-muted/30">
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-semibold dark:border-white">
                                                                75%
                                                            </td>
                                                            {Array.from({
                                                                length: 12,
                                                            }).map((_, mIdx) =>
                                                                [1, 2, 3, 4].map(
                                                                    (w) => {
                                                                        const key = `${mIdx + 1}-${w}`;
                                                                        const isMarked =
                                                                            b75.includes(
                                                                                key,
                                                                            );

                                                                        return (
                                                                            <td
                                                                                key={`75-${key}`}
                                                                                onClick={() =>
                                                                                    handleToggleCell(
                                                                                        rIdx,
                                                                                        '75',
                                                                                        key,
                                                                                    )
                                                                                }
                                                                                className={`cursor-pointer border border-black px-0.5 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                                    isMarked
                                                                                        ? 'print-marked-cell bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                                }`}
                                                                                title={`Klik untuk ubah 75% Bln ${mIdx + 1} M${w}`}
                                                                            >
                                                                                {isMarked
                                                                                    ? '1'
                                                                                    : ''}
                                                                            </td>
                                                                        );
                                                                    },
                                                                ),
                                                            )}
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-bold dark:border-white">
                                                                {b75.length}
                                                            </td>
                                                        </tr>

                                                        {/* Sub-row 3: 100% */}
                                                        <tr className="hover:bg-muted/30">
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-semibold dark:border-white">
                                                                100%
                                                            </td>
                                                            {Array.from({
                                                                length: 12,
                                                            }).map((_, mIdx) =>
                                                                [1, 2, 3, 4].map(
                                                                    (w) => {
                                                                        const key = `${mIdx + 1}-${w}`;
                                                                        const isMarked =
                                                                            b100.includes(
                                                                                key,
                                                                            );

                                                                        return (
                                                                            <td
                                                                                key={`100-${key}`}
                                                                                onClick={() =>
                                                                                    handleToggleCell(
                                                                                        rIdx,
                                                                                        '100',
                                                                                        key,
                                                                                    )
                                                                                }
                                                                                className={`cursor-pointer border border-black px-0.5 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                                    isMarked
                                                                                        ? 'print-marked-cell bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                                }`}
                                                                                title={`Klik untuk ubah 100% Bln ${mIdx + 1} M${w}`}
                                                                            >
                                                                                {isMarked
                                                                                    ? '1'
                                                                                    : ''}
                                                                            </td>
                                                                        );
                                                                    },
                                                                ),
                                                            )}
                                                            <td className="border border-black bg-muted/20 px-1 py-1 font-bold dark:border-white">
                                                                {b100.length}
                                                            </td>
                                                        </tr>
                                                    </Fragment>
                                                );
                                            });
                                        })()
                                    )}

                                    {/* Total Row */}
                                    <tr className="bg-muted/40 font-bold text-foreground">
                                        <td
                                            colSpan={51}
                                            className="border border-black px-2 py-1.5 text-center text-[11px] font-bold uppercase dark:border-white"
                                        >
                                            TOTAL
                                        </td>
                                        <td className="print-grand-total border border-black bg-[#00b0f0] px-1 py-1 text-center font-bold text-black dark:border-white">
                                            {grandTotal}
                                        </td>
                                        <td className="no-print border border-black dark:border-white" />
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Table 2: Recap Table Bawah Kiri */}
                    <div className="mt-6 w-full max-w-md">
                        <table className="excel-sheet print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                    <th
                                        className="border border-black px-2 py-1 text-[11px] dark:border-white"
                                        style={{ width: '35px' }}
                                    >
                                        NO
                                    </th>
                                    <th className="border border-black px-2 py-1 text-center text-[11px] dark:border-white">
                                        NAMA MESIN PEMBANGKIT
                                    </th>
                                    <th
                                        className="border border-black px-2 py-1 text-[11px] dark:border-white"
                                        style={{ width: '70px' }}
                                    >
                                        UJI BBN
                                    </th>
                                    <th
                                        className="border border-black px-2 py-1 text-[11px] dark:border-white"
                                        style={{ width: '70px' }}
                                    >
                                        DATA
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="border border-black px-3 py-3 text-center text-muted-foreground dark:border-white"
                                        >
                                            Belum ada data mesin.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((r, rIdx) => {
                                        const cnt50 = (r.beban_50 || []).length;
                                        const cnt75 = (r.beban_75 || []).length;
                                        const cnt100 = (r.beban_100 || [])
                                            .length;

                                        return (
                                            <Fragment
                                                key={`recap-${r.id ?? rIdx}`}
                                            >
                                                <tr>
                                                    <td
                                                        rowSpan={3}
                                                        className="border border-black px-1 py-1 font-medium dark:border-white"
                                                    >
                                                        {r.no_urut || rIdx + 1}
                                                    </td>
                                                    <td
                                                        rowSpan={3}
                                                        className="border border-black px-2 py-1 text-left font-semibold uppercase dark:border-white"
                                                    >
                                                        {r.nama_mesin}
                                                    </td>
                                                    <td className="border border-black px-1 py-1 font-semibold dark:border-white">
                                                        50%
                                                    </td>
                                                    <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                                        {cnt50}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="border border-black px-1 py-1 font-semibold dark:border-white">
                                                        75%
                                                    </td>
                                                    <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                                        {cnt75}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="border border-black px-1 py-1 font-semibold dark:border-white">
                                                        100%
                                                    </td>
                                                    <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                                        {cnt100}
                                                    </td>
                                                </tr>
                                            </Fragment>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Dialog Tambah Mesin */}
                <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Activity className="size-5 text-primary" />
                                Tambah Mesin Pembangkit
                            </DialogTitle>
                            <DialogDescription>
                                Pilih mesin dari Master Data Mesin unit{' '}
                                {unit.name} atau masukkan nama mesin khusus.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-2">
                            <div className="space-y-2">
                                <Label htmlFor="master_machine_select">
                                    Sumber Mesin
                                </Label>
                                <Select
                                    value={selectedMachineOption}
                                    onValueChange={setSelectedMachineOption}
                                >
                                    <SelectTrigger id="master_machine_select">
                                        <SelectValue placeholder="Pilih mesin dari Master Data" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {machines.map((m) => (
                                            <SelectItem
                                                key={m.id}
                                                value={String(m.id)}
                                            >
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                        <SelectItem
                                            value={CUSTOM_MACHINE_OPTION}
                                        >
                                            + Ketik Nama Mesin Custom...
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {selectedMachineAlreadyAdded && (
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        Mesin ini sudah ada di tabel. Anda tetap
                                        dapat menambahkannya lagi jika
                                        diperlukan.
                                    </p>
                                )}
                            </div>

                            {selectedMachineOption === CUSTOM_MACHINE_OPTION && (
                                <div className="space-y-2">
                                    <Label htmlFor="nama_mesin_custom">
                                        Nama Mesin Pembangkit (Custom)
                                    </Label>
                                    <Input
                                        id="nama_mesin_custom"
                                        placeholder="Contoh: MESIN#04 / CUMM #9"
                                        value={newNamaMesin}
                                        onChange={(e) =>
                                            setNewNamaMesin(e.target.value)
                                        }
                                        className="uppercase"
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                handleAddMesin();
                                            }
                                        }}
                                    />
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="section">
                                    Bagian / Kategori Form
                                </Label>
                                <Input
                                    id="section"
                                    placeholder={DEFAULT_SECTION}
                                    value={newSection}
                                    onChange={(e) =>
                                        setNewSection(e.target.value)
                                    }
                                    className="uppercase"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setIsAddOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={handleAddMesin}
                                disabled={!canSubmitAdd}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                Tambahkan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

JadwalCommissioningTestIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Commissioning Test Mesin', href: '#' },
    ],
};
