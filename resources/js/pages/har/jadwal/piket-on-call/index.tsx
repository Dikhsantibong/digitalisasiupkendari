import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileSpreadsheet,
    Info,
    PhoneCall,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    UserPlus,
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
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
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
import jadwal from '@/routes/har/jadwal';
import piketOnCall from '@/routes/har/jadwal/piket-on-call';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type PiketRow = {
    id: number | null;
    employee_id: number | null;
    nama: string;
    no_hp: string;
    kategori: string;
    target: number;
    piket: number[];
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
        month: number;
        year: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    days: DayInfo[];
    rows: PiketRow[];
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
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
        font-size: 8px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 2.5px 1px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-thead th {
        background-color: #d1d5db !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-category-row td {
        background-color: #f3f4f6 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-red-text {
        color: #dc2626 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-red-cell {
        background-color: #dc2626 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-piket-cell {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-weight: bold !important;
    }
}
`;

export default function HarJadwalPiketOnCallPage({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PiketRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state for adding new personnel
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newNama, setNewNama] = useState('');
    const [newNoHp, setNewNoHp] = useState('');
    const [newKategori, setNewKategori] = useState('HARMES');
    const [newTarget, setNewTarget] = useState(15);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const monthName = useMemo(() => {
        return OPERASI_MONTHS[filters.month - 1] ?? '';
    }, [filters.month]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            piketOnCall.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Toggle piket day on/off
    const togglePiket = (rowIndex: number, day: number) => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) return r;
                const set = new Set(r.piket || []);
                if (set.has(day)) {
                    set.delete(day);
                } else {
                    set.add(day);
                }
                return {
                    ...r,
                    piket: Array.from(set).sort((a, b) => a - b),
                };
            }),
        );
        setDirty(true);
    };

    // Update basic row fields (nama, no_hp, target, kategori)
    const updateField = (
        rowIndex: number,
        field: keyof PiketRow,
        value: string | number,
    ) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r, idx) =>
                idx === rowIndex ? { ...r, [field]: value } : r,
            ),
        );
        setDirty(true);
    };

    // Add personnel
    const handleAddPersonnel = () => {
        if (!newNama.trim()) return;

        setRows((prev) => [
            ...prev,
            {
                id: null,
                employee_id: null,
                nama: newNama.trim().toUpperCase(),
                no_hp: newNoHp.trim(),
                kategori: newKategori.trim().toUpperCase() || 'HARMES',
                target: Number(newTarget) || 15,
                piket: [],
            },
        ]);
        setDirty(true);
        setIsAddOpen(false);
        setNewNama('');
        setNewNoHp('');
    };

    // Remove row
    const handleRemoveRow = (rowIndex: number) => {
        if (!can_write) return;
        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
    };

    // Reset to server data
    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    // Save changes
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);

        router.post(
            piketOnCall.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    id: r.id,
                    employee_id: r.employee_id,
                    nama: r.nama,
                    no_hp: r.no_hp,
                    kategori: r.kategori,
                    target: r.target,
                    piket: r.piket,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Print
    const handlePrint = () => {
        window.print();
    };

    // Grouping by category
    const groupedRows = useMemo(() => {
        const groups: {
            roman: string;
            kategori: string;
            items: {
                row: PiketRow;
                originalIndex: number;
                runningNo: number;
            }[];
        }[] = [];
        const romanNumerals = [
            'I',
            'II',
            'III',
            'IV',
            'V',
            'VI',
            'VII',
            'VIII',
        ];

        const catMap = new Map<
            string,
            { row: PiketRow; originalIndex: number; runningNo: number }[]
        >();
        let runningNo = 1;

        rows.forEach((r, originalIndex) => {
            const cat = r.kategori ? r.kategori.trim().toUpperCase() : 'HARMES';
            if (!catMap.has(cat)) {
                catMap.set(cat, []);
            }
            catMap.get(cat)!.push({
                row: r,
                originalIndex,
                runningNo: runningNo++,
            });
        });

        let catIdx = 0;
        for (const [kategori, items] of catMap.entries()) {
            groups.push({
                roman: romanNumerals[catIdx] ?? String(catIdx + 1),
                kategori,
                items,
            });
            catIdx++;
        }

        return groups;
    }, [rows]);

    // Export to Excel
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Column Titles
        const rowH1: (string | number)[] = [
            'No.',
            'Nama',
            'No. Hp',
            ...Array(days.length).fill(
                `${monthName.toUpperCase()} ${filters.year}`,
            ),
            'TARGET',
            'REALISASI',
            'A. KINERJA',
        ];
        headerRows.push(rowH1);

        const rowH2: (string | number)[] = [
            '',
            '',
            '',
            ...days.map((d) => d.day),
            '',
            '',
            '',
        ];
        headerRows.push(rowH2);

        // Data Rows with Categories
        const categoryRowIdx = new Set<number>();
        groupedRows.forEach((group) => {
            // Category Row
            categoryRowIdx.add(headerRows.length);
            headerRows.push([
                group.roman,
                group.kategori,
                '',
                ...days.map(() => ''),
                '',
                '',
                '',
            ]);

            // Personnel Rows
            group.items.forEach(({ row, runningNo }) => {
                const realisasi = (row.piket || []).length;
                const target = row.target || 15;
                const kinerja =
                    target > 0
                        ? `${Math.round((realisasi / target) * 100)}%`
                        : '0%';

                const pRow: (string | number)[] = [
                    runningNo,
                    row.nama,
                    row.no_hp || '',
                    ...days.map((d) => (row.piket.includes(d.day) ? 1 : '')),
                    target,
                    realisasi,
                    kinerja,
                ];
                headerRows.push(pRow);
            });
        });

        const { workbook, worksheet: ws } = createSheet(
            'Piket_OnCall',
            headerRows,
        );

        const dayStart = 3;
        const dayEnd = dayStart + days.length - 1;
        const colTarget = dayStart + days.length;
        const totalCols = colTarget + 3;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;

        const piketRed: StyleSpec = {
            fill: XLSX_COLORS.red600,
            bold: true,
            color: XLSX_COLORS.white,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const piketCell: StyleSpec = {
            fill: XLSX_COLORS.zinc,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const redSoftCell: StyleSpec = {
            fill: XLSX_COLORS.redLight,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }

            if (r === headerTop || r === headerBottom) {
                if (r === headerBottom && c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    return day?.is_red
                        ? { ...SPECS.headerGrey, color: XLSX_COLORS.red600 }
                        : SPECS.headerGrey;
                }
                return SPECS.headerGrey;
            }

            if (categoryRowIdx.has(r)) {
                return SPECS.category;
            }

            if (r >= dataStart) {
                if (c === 0) {
                    return SPECS.cell;
                }
                if (c === 1) {
                    return SPECS.cellLeft;
                }
                if (c === 2) {
                    return SPECS.cell;
                }
                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    const isPiket = String(headerRows[r]?.[c] ?? '') === '1';
                    if (isPiket) {
                        return day?.is_red ? piketRed : piketCell;
                    }
                    return day?.is_red ? redSoftCell : SPECS.cell;
                }
                return { ...SPECS.cell, bold: true };
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // No.
        mergeCells(ws, headerTop, 1, headerBottom, 1); // Nama
        mergeCells(ws, headerTop, 2, headerBottom, 2); // No. Hp
        mergeCells(ws, headerTop, dayStart, headerTop, dayEnd); // Month label
        for (let c = colTarget; c < totalCols; c++) {
            mergeCells(ws, headerTop, c, headerBottom, c);
        }
        categoryRowIdx.forEach((r) => mergeCells(ws, r, 1, r, totalCols - 1));

        const colWidths = [5, 22, 14, ...Array(days.length).fill(3.2), 8, 9, 9];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS 6 SITE UP KENDARI',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'LAPORAN PIKET ONCALL PEMELIHARAAN PEMBANGKIT',
                ],
                barTitle: `JADWAL PIKET ONCALL - ${monthName.toUpperCase()} ${filters.year}`,
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_Piket_OnCall_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    return (
        <>
            <Head
                title={`Jadwal Piket Pemeliharaan (ON CALL) — ${unit.name}`}
            />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Screen Header Bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(jadwal.index().url)}
                            title="Kembali ke Daftar Jadwal"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Jadwal Piket Pemeliharaan (ON CALL)
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Penetapan regu & personil siaga darurat (On
                                Call) penanganan gangguan pembangkit 24 jam.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
                                disabled={saving}
                                className="gap-1.5 text-xs text-muted-foreground"
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsAddOpen(true)}
                                    className="gap-1.5"
                                >
                                    <UserPlus className="size-3.5" />
                                    Tambah Personil
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={saving || !dirty}
                                    className="gap-1.5"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan Jadwal'}
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExportExcel}
                            className="gap-1.5"
                            title="Unduh format Excel (.xlsx)"
                        >
                            <FileSpreadsheet className="size-3.5 text-emerald-600" />
                            Excel
                        </Button>
                        <a
                            href={`${piketOnCall.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                title="Unduh format PDF resmi"
                            >
                                <Download className="size-3.5 text-rose-600" />
                                PDF
                            </Button>
                        </a>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handlePrint}
                            className="gap-1.5"
                            title="Cetak Lanskap"
                        >
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters Row */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Pembangkit"
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

                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Main Printable Card */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* Official Document Header (Exact match to reference image) */}
                    <div className="border-b border-border p-4">
                        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                            {/* Left Logo: PLN Nusantara Power */}
                            <div className="flex w-36 items-center justify-center sm:justify-start">
                                <img
                                    src="/logo/sidebar-logo.png"
                                    alt="PLN Nusantara Power"
                                    className="max-h-12 object-contain"
                                    onError={(e) => {
                                        (
                                            e.target as HTMLElement
                                        ).style.display = 'none';
                                    }}
                                />
                            </div>

                            {/* Center Title Hierarchy */}
                            <div className="text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase md:text-sm">
                                    JASA PENDUKUNG TEKNIS 6 SITE UP KENDARI
                                </div>
                                <div className="text-xs font-bold text-foreground uppercase md:text-sm">
                                    JADWAL PIKET ONCALL PEMELIHARAAN PEMBANGKIT
                                    BULAN {monthName.toUpperCase()}{' '}
                                    {filters.year}
                                </div>
                                <div className="mt-0.5 text-xs font-bold text-foreground uppercase md:text-sm">
                                    {unit.name.toUpperCase()}
                                </div>
                            </div>

                            {/* Right Logo: MKP */}
                            <div className="flex w-36 items-center justify-center sm:justify-end">
                                <img
                                    src="/logo/mkp.jpg"
                                    alt="Mitra Karya Prima"
                                    className="max-h-12 object-contain"
                                    onError={(e) => {
                                        (
                                            e.target as HTMLElement
                                        ).style.display = 'none';
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Table Container */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-xs">
                            <thead className="print-thead bg-muted/60 dark:bg-muted/30">
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="w-10 border border-border p-2 text-center font-bold text-foreground"
                                    >
                                        No.
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-48 min-w-44 border border-border p-2 text-left font-bold text-foreground"
                                    >
                                        Nama
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-32 min-w-28 border border-border p-2 text-left font-bold text-foreground"
                                    >
                                        No. Hp
                                    </th>
                                    <th
                                        colSpan={days.length}
                                        className="border border-border p-2 text-center font-bold text-foreground uppercase"
                                    >
                                        {monthName} {filters.year}
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-16 border border-border p-2 text-center font-bold text-foreground"
                                    >
                                        TARGET
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-20 border border-border p-2 text-center font-bold text-foreground"
                                    >
                                        REALISASI
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-24 border border-border p-2 text-center font-bold text-foreground"
                                    >
                                        A. KINERJA
                                    </th>
                                    {can_write && (
                                        <th
                                            rowSpan={2}
                                            className="no-print w-10 border border-border p-2 text-center font-bold text-foreground"
                                        >
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th
                                            key={d.day}
                                            className={`w-7 border border-border p-1 text-center font-bold ${
                                                d.is_red
                                                    ? 'print-red-text text-red-600'
                                                    : 'text-foreground'
                                            }`}
                                            title={`${d.day} (${d.dow})${
                                                d.holiday
                                                    ? ` - ${d.holiday}`
                                                    : ''
                                            }`}
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {groupedRows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={
                                                days.length +
                                                (can_write ? 7 : 6)
                                            }
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Belum ada personil yang terdaftar
                                            untuk unit ini. Klik &quot;Tambah
                                            Personil&quot; untuk menambahkan
                                            data.
                                        </td>
                                    </tr>
                                ) : (
                                    groupedRows.map((group) => (
                                        <Fragment key={group.kategori}>
                                            {/* Category Header Row */}
                                            <tr className="print-category-row bg-muted/40 font-bold dark:bg-muted/20">
                                                <td className="border border-border p-1.5 text-center font-extrabold text-foreground">
                                                    {group.roman}
                                                </td>
                                                <td
                                                    colSpan={2}
                                                    className="border border-border p-1.5 text-left font-extrabold tracking-wider text-foreground uppercase"
                                                >
                                                    {group.kategori}
                                                </td>
                                                <td
                                                    colSpan={days.length}
                                                    className="border border-border p-1.5"
                                                />
                                                <td className="border border-border p-1.5" />
                                                <td className="border border-border p-1.5" />
                                                <td className="border border-border p-1.5" />
                                                {can_write && (
                                                    <td className="no-print border border-border p-1.5 text-center">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-6 text-primary hover:bg-primary/10"
                                                            title={`Tambah Personil ke ${group.kategori}`}
                                                            onClick={() => {
                                                                setNewKategori(
                                                                    group.kategori,
                                                                );
                                                                setIsAddOpen(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            <Plus className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>

                                            {/* Personnel Rows */}
                                            {group.items.map(
                                                ({
                                                    row,
                                                    originalIndex,
                                                    runningNo,
                                                }) => {
                                                    const realisasi = (
                                                        row.piket || []
                                                    ).length;
                                                    const target =
                                                        row.target || 15;
                                                    const kinerja =
                                                        target > 0
                                                            ? Math.round(
                                                                  (realisasi /
                                                                      target) *
                                                                      100,
                                                              )
                                                            : 0;

                                                    return (
                                                        <tr
                                                            key={originalIndex}
                                                            className="transition-colors hover:bg-muted/10"
                                                        >
                                                            {/* No. */}
                                                            <td className="border border-border p-1.5 text-center font-medium text-foreground">
                                                                {runningNo}
                                                            </td>

                                                            {/* Nama */}
                                                            <td className="border border-border p-1 font-bold text-foreground">
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.nama
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateField(
                                                                                originalIndex,
                                                                                'nama',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="w-full bg-transparent px-1.5 py-0.5 font-bold uppercase focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                        placeholder="Nama Personil"
                                                                    />
                                                                ) : (
                                                                    <span className="px-1.5 uppercase">
                                                                        {
                                                                            row.nama
                                                                        }
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* No. Hp */}
                                                            <td className="border border-border p-1 text-foreground">
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.no_hp
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateField(
                                                                                originalIndex,
                                                                                'no_hp',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="w-full bg-transparent px-1.5 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                        placeholder="08..."
                                                                    />
                                                                ) : (
                                                                    <span className="px-1.5 text-xs">
                                                                        {row.no_hp ||
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Day Cells (Interactive toggle) */}
                                                            {days.map((d) => {
                                                                const isPiket =
                                                                    row.piket.includes(
                                                                        d.day,
                                                                    );

                                                                let cellClass =
                                                                    'border border-border text-center select-none ';

                                                                if (isPiket) {
                                                                    if (
                                                                        d.is_red
                                                                    ) {
                                                                        cellClass +=
                                                                            'bg-[#dc2626] text-white font-extrabold print-red-cell ';
                                                                    } else {
                                                                        cellClass +=
                                                                            'bg-zinc-200 dark:bg-zinc-700 text-foreground font-extrabold print-piket-cell ';
                                                                    }
                                                                } else {
                                                                    if (
                                                                        d.is_red
                                                                    ) {
                                                                        cellClass +=
                                                                            'bg-red-50/40 dark:bg-red-950/10 ';
                                                                    }
                                                                }

                                                                if (can_write) {
                                                                    cellClass +=
                                                                        'cursor-pointer hover:opacity-80 transition-opacity';
                                                                }

                                                                return (
                                                                    <td
                                                                        key={
                                                                            d.day
                                                                        }
                                                                        className={
                                                                            cellClass
                                                                        }
                                                                        onClick={() =>
                                                                            togglePiket(
                                                                                originalIndex,
                                                                                d.day,
                                                                            )
                                                                        }
                                                                        title={`Tanggal ${d.day} (${d.dow})${
                                                                            d.holiday
                                                                                ? ` - ${d.holiday}`
                                                                                : ''
                                                                        }: ${
                                                                            isPiket
                                                                                ? 'Piket On Call (Klik untuk batalkan)'
                                                                                : 'Tidak Piket (Klik untuk jadwalkan)'
                                                                        }`}
                                                                    >
                                                                        {isPiket
                                                                            ? '1'
                                                                            : ''}
                                                                    </td>
                                                                );
                                                            })}

                                                            {/* TARGET */}
                                                            <td className="border border-border p-1 text-center font-bold text-foreground">
                                                                {can_write ? (
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        value={
                                                                            row.target
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateField(
                                                                                originalIndex,
                                                                                'target',
                                                                                Number(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ),
                                                                            )
                                                                        }
                                                                        className="w-12 bg-transparent text-center font-bold focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                    />
                                                                ) : (
                                                                    row.target
                                                                )}
                                                            </td>

                                                            {/* REALISASI */}
                                                            <td className="border border-border p-1.5 text-center font-bold text-foreground">
                                                                {realisasi}
                                                            </td>

                                                            {/* A. KINERJA */}
                                                            <td
                                                                className={`border border-border p-1.5 text-center font-bold ${
                                                                    kinerja >=
                                                                    100
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-amber-600 dark:text-amber-400'
                                                                }`}
                                                            >
                                                                {kinerja}%
                                                            </td>

                                                            {/* Aksi (No-Print) */}
                                                            {can_write && (
                                                                <td className="no-print border border-border p-1 text-center">
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                        onClick={() =>
                                                                            handleRemoveRow(
                                                                                originalIndex,
                                                                            )
                                                                        }
                                                                        title="Hapus Personil"
                                                                    >
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                </td>
                                                            )}
                                                        </tr>
                                                    );
                                                },
                                            )}
                                        </Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Bottom Legend & Notes */}
                    <div className="border-t border-border bg-muted/20 p-4">
                        <div className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-red-700 bg-[#dc2626]" />
                                    <span>Piket Hari Libur / Weekend</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-border bg-zinc-200 dark:bg-zinc-700" />
                                    <span>Piket Hari Kerja (1)</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-foreground">
                                        Target Standar:
                                    </span>
                                    <span>15 Hari Siaga / Personil</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>
                                    Realisasi & Analisa Kinerja dihitung
                                    otomatis dari jadwal on call.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Personil */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <UserPlus className="size-5 text-primary" />
                            Tambah Personil Piket On Call
                        </DialogTitle>
                        <DialogDescription>
                            Tambahkan personil pemeliharaan ke daftar jadwal
                            siaga on call unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label htmlFor="nama">Nama Personil</Label>
                            <Input
                                id="nama"
                                placeholder="Contoh: AMIRULLAH"
                                value={newNama}
                                onChange={(e) => setNewNama(e.target.value)}
                                autoFocus
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="no_hp">No. Handphone / WA</Label>
                            <Input
                                id="no_hp"
                                placeholder="Contoh: 085242411248"
                                value={newNoHp}
                                onChange={(e) => setNewNoHp(e.target.value)}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="kategori">
                                    Kategori Disiplin
                                </Label>
                                <select
                                    id="kategori"
                                    value={newKategori}
                                    onChange={(e) =>
                                        setNewKategori(e.target.value)
                                    }
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option value="HARMES">
                                        HARMES (Mesin)
                                    </option>
                                    <option value="HARLIS">
                                        HARLIS (Listrik)
                                    </option>
                                    <option value="HAR KONTROL & INSTRUMEN">
                                        HAR KONTROL & INSTRUMEN
                                    </option>
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="target">Target (Hari)</Label>
                                <Input
                                    id="target"
                                    type="number"
                                    min="0"
                                    value={newTarget}
                                    onChange={(e) =>
                                        setNewTarget(Number(e.target.value))
                                    }
                                />
                            </div>
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
                            onClick={handleAddPersonnel}
                            disabled={!newNama.trim()}
                        >
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
