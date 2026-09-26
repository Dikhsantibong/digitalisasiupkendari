import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Download,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
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
import { OperasiSelect } from '@/components/operasi/filter-select';
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
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import performanceTest from '@/routes/operasi/jadwal/performance-test';
import type { IdName } from '@/types';

type PerformanceTestRow = {
    id: number | null;
    no_urut: number;
    nama_mesin: string;
    section: string;
    beban_50: string[];
    beban_75: string[];
    beban_100: string[];
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
    rows: PerformanceTestRow[];
    can_write: boolean;
};

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
        background-color: #93c5fd !important;
        font-weight: bold !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function JadwalPerformanceTestIndex({
    unit,
    filters,
    options,
    machines,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PerformanceTestRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state for adding new machine
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newNamaMesin, setNewNamaMesin] = useState('');
    const [newSection, setNewSection] = useState('A. PEMBUATAN DATA TEKNIKS');

    // Navigation helper
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            performanceTest.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Toggle cell for 50%, 75%, 100%
    const handleToggleCell = (
        rowIndex: number,
        type: '50' | '75' | '100',
        weekKey: string,
    ) => {
        if (!can_write) return;

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

    // Update machine name
    const handleUpdateName = (rowIndex: number, newName: string) => {
        if (!can_write) return;
        setRows((prev) => {
            const updated = [...prev];
            updated[rowIndex] = {
                ...updated[rowIndex],
                nama_mesin: newName,
            };
            return updated;
        });
        setDirty(true);
    };

    // Add new machine
    const handleAddMesin = () => {
        if (!newNamaMesin.trim()) return;
        setRows((prev) => [
            ...prev,
            {
                id: null,
                no_urut: prev.length + 1,
                nama_mesin: newNamaMesin.trim().toUpperCase(),
                section: newSection.trim() || 'A. PEMBUATAN DATA TEKNIKS',
                beban_50: [],
                beban_75: [],
                beban_100: [],
            },
        ]);
        setNewNamaMesin('');
        setIsAddOpen(false);
        setDirty(true);
    };

    // Remove machine row
    const handleRemoveRow = (rowIndex: number) => {
        if (!can_write) return;
        setRows((prev) => {
            const filtered = prev.filter((_, idx) => idx !== rowIndex);
            return filtered.map((r, idx) => ({ ...r, no_urut: idx + 1 }));
        });
        setDirty(true);
    };

    // Reset
    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    // Save
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);

        router.post(
            performanceTest.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    nama_mesin: r.nama_mesin,
                    section: r.section || 'A. PEMBUATAN DATA TEKNIKS',
                    beban_50: r.beban_50,
                    beban_75: r.beban_75,
                    beban_100: r.beban_100,
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

    // Column totals calculation across 48 weeks
    const { columnTotals, grandTotal } = useMemo(() => {
        const totals: Record<string, number> = {};
        for (let m = 1; m <= 12; m++) {
            for (let w = 1; w <= 4; w++) {
                const key = `${m}-${w}`;
                let count = 0;
                rows.forEach((r) => {
                    if (r.beban_50?.includes(key)) count++;
                    if (r.beban_75?.includes(key)) count++;
                    if (r.beban_100?.includes(key)) count++;
                });
                totals[key] = count;
            }
        }
        const grand = Object.values(totals).reduce((sum, c) => sum + c, 0);
        return { columnTotals: totals, grandTotal: grand };
    }, [rows]);

    // Export Excel (.xlsx) matching exact formatting
    const handleExportExcel = async () => {
        const sheetData: (string | number)[][] = [[], [], [], []];

        // Header Row 1: NO, MESIN PEMBANGKIT, UJI BBN, BULAN (across 48 cols), JUMLAH
        const rowH1: (string | number)[] = [
            'NO',
            'MESIN PEMBANGKIT',
            'UJI BBN',
        ];
        rowH1.push('BULAN');
        for (let i = 1; i < 48; i++) {
            rowH1.push('');
        }
        rowH1.push('JUMLAH');
        sheetData.push(rowH1);

        // Header Row 2: Months
        const rowH2: (string | number)[] = ['', '', ''];
        MONTH_NAMES.forEach((m) => {
            rowH2.push(m, '', '', '');
        });
        rowH2.push('');
        sheetData.push(rowH2);

        // Header Row 3: Weeks 1..4
        const rowH3: (string | number)[] = ['', '', ''];
        for (let m = 1; m <= 12; m++) {
            rowH3.push(1, 2, 3, 4);
        }
        rowH3.push('');
        sheetData.push(rowH3);

        const weekStart = 3;
        const weekEnd = 50; // 12 months x 4 weeks
        const colJumlah = 51;
        const totalCols = 52;
        const headerTop = 4;
        const headerMid = 5;
        const headerBottom = 6;
        const dataStart = 7;

        let currentRowIndex = dataStart;
        const sectionRowIndices: number[] = [];
        let currentSection: string | null = null;

        // Data Rows
        rows.forEach((r, idx) => {
            const section = r.section || 'A. PEMBUATAN DATA TEKNIKS';
            if (section !== currentSection) {
                currentSection = section;
                const secRow: (string | number)[] = [section];
                for (let c = 1; c < totalCols; c++) {
                    secRow.push('');
                }
                sheetData.push(secRow);
                sectionRowIndices.push(currentRowIndex);
                currentRowIndex++;
            }

            const b50 = r.beban_50 || [];
            const b75 = r.beban_75 || [];
            const b100 = r.beban_100 || [];

            // 50%
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
            r50.push(b50.length > 0 ? b50.length : '');
            sheetData.push(r50);
            currentRowIndex++;

            // 75%
            const r75: (string | number)[] = ['', '', '75%'];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    r75.push(b75.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            r75.push(b75.length > 0 ? b75.length : '');
            sheetData.push(r75);
            currentRowIndex++;

            // 100%
            const r100: (string | number)[] = ['', '', '100%'];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    r100.push(b100.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            r100.push(b100.length > 0 ? b100.length : '');
            sheetData.push(r100);
            currentRowIndex++;
        });

        // Total Row
        const totRow: (string | number)[] = ['', 'TOTAL', ''];
        for (let m = 1; m <= 12; m++) {
            for (let w = 1; w <= 4; w++) {
                const c = columnTotals[`${m}-${w}`] || 0;
                totRow.push(c > 0 ? c : '');
            }
        }
        totRow.push(grandTotal > 0 ? grandTotal : '');
        sheetData.push(totRow);
        const totalRowIdx = currentRowIndex;
        currentRowIndex++;

        // Recap table (Table 2)
        sheetData.push([]);
        currentRowIndex++;
        const recapHeaderIdx = currentRowIndex;
        sheetData.push(['NO', 'MESIN PEMBANGKIT', 'UJI BBN', 'DATA']);
        currentRowIndex++;

        rows.forEach((r, rIdx) => {
            const cnt50 = (r.beban_50 || []).length;
            const cnt75 = (r.beban_75 || []).length;
            const cnt100 = (r.beban_100 || []).length;

            sheetData.push([
                r.no_urut || rIdx + 1,
                r.nama_mesin,
                '50%',
                cnt50 > 0 ? cnt50 : '',
            ]);
            currentRowIndex++;
            sheetData.push(['', '', '75%', cnt75 > 0 ? cnt75 : '']);
            currentRowIndex++;
            sheetData.push(['', '', '100%', cnt100 > 0 ? cnt100 : '']);
            currentRowIndex++;
        });

        const { workbook, worksheet: ws } = createSheet(
            'Performance Test',
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
            fill: '93C5FD', // soft light blue
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header
            }
            if (r === headerTop || r === headerMid || r === headerBottom) {
                return SPECS.headerOrange;
            }

            if (sectionRowIndices.includes(r)) {
                return SPECS.category;
            }

            if (r >= dataStart && r < totalRowIdx) {
                if (c === 0) {
                    return SPECS.labelCenter;
                }
                if (c === 1) {
                    return SPECS.label;
                }
                if (c === 2) {
                    return SPECS.labelCenter;
                }
                if (c >= weekStart && c <= weekEnd) {
                    return String(sheetData[r]?.[c] ?? '') === '1'
                        ? markedCell
                        : SPECS.cell;
                }
                return { ...SPECS.cell, bold: true }; // JUMLAH
            }

            if (r === totalRowIdx) {
                if (c === colJumlah) {
                    return grandTotalCell;
                }
                return c === 1
                    ? { ...SPECS.total, align: 'center' }
                    : SPECS.total;
            }

            // Bottom recap table (4 columns)
            if (r >= recapHeaderIdx && c < 4) {
                if (r === recapHeaderIdx) {
                    return SPECS.headerOrange;
                }
                if (c === 1) {
                    return SPECS.label;
                }
                if (c === 2) {
                    return SPECS.labelCenter;
                }
                return SPECS.cell;
            }

            return null;
        });

        // Merge Header 1, 2, 3
        mergeCells(ws, headerTop, 0, headerBottom, 0); // NO
        mergeCells(ws, headerTop, 1, headerBottom, 1); // MESIN PEMBANGKIT
        mergeCells(ws, headerTop, 2, headerBottom, 2); // UJI BBN
        mergeCells(ws, headerTop, 3, headerTop, 50); // BULAN
        mergeCells(ws, headerTop, colJumlah, headerBottom, colJumlah); // JUMLAH

        for (let m = 0; m < 12; m++) {
            const c = weekStart + m * 4;
            mergeCells(ws, headerMid, c, headerMid, c + 3); // Month names
        }

        // Section row merges
        sectionRowIndices.forEach((sIdx) => {
            mergeCells(ws, sIdx, 0, sIdx, totalCols - 1);
        });

        // Data rows merges (NO & MESIN PEMBANGKIT span 3 load rows)
        let rowTracker = dataStart;
        rows.forEach(() => {
            if (sectionRowIndices.includes(rowTracker)) {
                rowTracker++;
            }
            mergeCells(ws, rowTracker, 0, rowTracker + 2, 0);
            mergeCells(ws, rowTracker, 1, rowTracker + 2, 1);
            rowTracker += 3;
        });

        // Total row merge
        mergeCells(ws, totalRowIdx, 0, totalRowIdx, 2);

        // Recap table merges
        let recapRowTracker = recapHeaderIdx + 1;
        rows.forEach(() => {
            mergeCells(ws, recapRowTracker, 0, recapRowTracker + 2, 0);
            mergeCells(ws, recapRowTracker, 1, recapRowTracker + 2, 1);
            recapRowTracker += 3;
        });

        const colWidths = [4, 26, 8, ...Array(48).fill(2.6), 7];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE - KIT',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'JADWAL PELAKSANAAN PERFORMANCE TEST MESIN PEMBANGKIT',
                ],
                barTitle:
                    'JADWAL PELAKSANAAN PERFORMANCE TEST MESIN PEMBANGKIT',
            },
        );

        const filename = `Jadwal_Performance_Test_Mesin_${unit.name.replace(/\s+/g, '_')}_${filters.year}.xlsx`;
        await downloadWorkbook(workbook, filename);
    };

    return (
        <>
            <Head
                title={`Jadwal Pelaksanaan Performance Test Mesin - ${unit.name}`}
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
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Jadwal Pelaksanaan Performance Test Mesin
                            </h1>
                            <Badge
                                variant="secondary"
                                className="font-semibold"
                            >
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
                                    onClick={() => setIsAddOpen(true)}
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
                                    performanceTest.pdf({
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
                        <Info className="size-4" />
                        <span>
                            Klik pada kotak minggu untuk menandai &apos;1&apos;
                            pada uji beban (UJI BBN) 50%, 75%, atau 100%.
                        </span>
                    </div>
                </div>

                {/* Main Schedule Container */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Document Header matching media_1790360331806.jpg */}
                    <div className="mb-4 grid grid-cols-12 items-stretch border-2 border-black dark:border-white">
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
                                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6
                                SITE - KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold tracking-wide text-foreground uppercase">
                                JADWAL PELAKSANAAN PERFORMANCE TEST MESIN
                                PEMBANGKIT
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

                    {/* Table 1: Matrix 12 Bulan x 4 Minggu = 48 Kolom */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                    <th
                                        rowSpan={3}
                                        className="border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                        style={{ width: '30px' }}
                                    >
                                        NO
                                    </th>
                                    <th
                                        rowSpan={3}
                                        className="border border-black px-3 py-1.5 text-left text-[11px] dark:border-white"
                                        style={{ minWidth: '180px' }}
                                    >
                                        MESIN PEMBANGKIT
                                    </th>
                                    <th
                                        rowSpan={3}
                                        className="border border-black px-1.5 py-1.5 text-[11px] dark:border-white"
                                        style={{ width: '55px' }}
                                    >
                                        UJI BBN
                                    </th>
                                    <th
                                        colSpan={48}
                                        className="border border-black px-1 py-1 text-[11px] tracking-wide uppercase dark:border-white"
                                    >
                                        BULAN
                                    </th>
                                    <th
                                        rowSpan={3}
                                        className="border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                        style={{ width: '50px' }}
                                    >
                                        JUMLAH
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
                                    {MONTH_NAMES.map((m) => (
                                        <th
                                            key={m}
                                            colSpan={4}
                                            className="border border-black px-1 py-1 text-[10px] uppercase dark:border-white"
                                        >
                                            {m}
                                        </th>
                                    ))}
                                </tr>
                                <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
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
                                {(() => {
                                    let currentSection: string | null = null;
                                    return rows.map((row, rIdx) => {
                                        const section =
                                            row.section ||
                                            'A. PEMBUATAN DATA TEKNIKS';
                                        const renderSectionHeader =
                                            section !== currentSection;
                                        if (renderSectionHeader) {
                                            currentSection = section;
                                        }

                                        const b50 = row.beban_50 || [];
                                        const b75 = row.beban_75 || [];
                                        const b100 = row.beban_100 || [];

                                        return (
                                            <Fragment
                                                key={row.id ?? `row-${rIdx}`}
                                            >
                                                {/* Section Header Row */}
                                                {renderSectionHeader && (
                                                    <tr className="bg-muted/40 font-bold text-foreground">
                                                        <td
                                                            colSpan={53}
                                                            className="border border-black px-3 py-1 text-left text-xs font-semibold dark:border-white"
                                                        >
                                                            {section}
                                                        </td>
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
                                                        className="border border-black px-2 py-1 text-left font-semibold dark:border-white"
                                                    >
                                                        {can_write ? (
                                                            <Input
                                                                value={
                                                                    row.nama_mesin
                                                                }
                                                                onChange={(e) =>
                                                                    handleUpdateName(
                                                                        rIdx,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="h-7 text-xs font-semibold uppercase"
                                                            />
                                                        ) : (
                                                            <span className="uppercase">
                                                                {row.nama_mesin}
                                                            </span>
                                                        )}
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
                                                        {b50.length > 0
                                                            ? b50.length
                                                            : ''}
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
                                                        {b75.length > 0
                                                            ? b75.length
                                                            : ''}
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
                                                        {b100.length > 0
                                                            ? b100.length
                                                            : ''}
                                                    </td>
                                                </tr>
                                            </Fragment>
                                        );
                                    });
                                })()}

                                {/* Total Row */}
                                <tr className="bg-muted/40 font-bold text-foreground">
                                    <td
                                        colSpan={3}
                                        className="border border-black px-2 py-1.5 text-center text-[11px] font-bold uppercase dark:border-white"
                                    >
                                        TOTAL
                                    </td>
                                    {Array.from({ length: 12 }).map((_, mIdx) =>
                                        [1, 2, 3, 4].map((w) => {
                                            const key = `${mIdx + 1}-${w}`;
                                            const val = columnTotals[key] || 0;
                                            return (
                                                <td
                                                    key={`tot-${key}`}
                                                    className="border border-black px-0.5 py-1 text-center font-bold dark:border-white"
                                                >
                                                    {val > 0 ? val : ''}
                                                </td>
                                            );
                                        }),
                                    )}
                                    <td className="print-grand-total border border-black bg-sky-200 px-1 py-1 text-center font-bold text-black dark:bg-sky-600 dark:text-white dark:border-white">
                                        {grandTotal > 0 ? grandTotal : ''}
                                    </td>
                                    <td className="no-print border border-black dark:border-white"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Table 2: Recap Table Bawah Kiri (matching reference) */}
                    <div className="mt-6 w-full max-w-md">
                        <table className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                    <th
                                        className="border border-black px-2 py-1 text-[11px] dark:border-white"
                                        style={{ width: '35px' }}
                                    >
                                        NO
                                    </th>
                                    <th className="border border-black px-2 py-1 text-left text-[11px] dark:border-white">
                                        MESIN PEMBANGKIT
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
                                {rows.map((r, rIdx) => {
                                    const cnt50 = (r.beban_50 || []).length;
                                    const cnt75 = (r.beban_75 || []).length;
                                    const cnt100 = (r.beban_100 || []).length;

                                    return (
                                        <Fragment key={`recap-${r.id ?? rIdx}`}>
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
                                                    {cnt50 > 0 ? cnt50 : ''}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="border border-black px-1 py-1 font-semibold dark:border-white">
                                                    75%
                                                </td>
                                                <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                                    {cnt75 > 0 ? cnt75 : ''}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="border border-black px-1 py-1 font-semibold dark:border-white">
                                                    100%
                                                </td>
                                                <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                                    {cnt100 > 0 ? cnt100 : ''}
                                                </td>
                                            </tr>
                                        </Fragment>
                                    );
                                })}
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
                                Masukkan nama mesin pembangkit yang akan
                                ditambahkan ke jadwal performance test (misal:
                                MESIN#04 / CUMM #9).
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-2">
                            <div className="space-y-2">
                                <Label htmlFor="nama_mesin">
                                    Nama Mesin Pembangkit
                                </Label>
                                <Input
                                    id="nama_mesin"
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
                            <div className="space-y-2">
                                <Label htmlFor="section">Bagian / Bagian Form</Label>
                                <Input
                                    id="section"
                                    placeholder="A. PEMBUATAN DATA TEKNIKS"
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
                                disabled={!newNamaMesin.trim()}
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

JadwalPerformanceTestIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Performance Test Mesin', href: '#' },
    ],
};
