import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    FileSpreadsheet,
    FileText,
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
import pembuatanIk from '@/routes/har/jadwal/pembuatan-ik';
import type { IdName } from '@/types';

type IkRow = {
    id: number | null;
    no_urut: number;
    instruksi_kerja: string;
    pic_pembuat: string;
    rencana_bulan: number[];
    realisasi_bulan: number[];
    keterangan?: string;
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
    employees: string[];
    rows: IkRow[];
    can_write: boolean;
};

const MONTH_LABELS = [
    'JAN',
    'FEB',
    'MAR',
    'APR',
    'MAY',
    'JUN',
    'JUL',
    'AUG',
    'SEP',
    'OCT',
    'NOV',
    'DEC',
];

const PRINT_CSS = `
@media print {
    @page {
        size: A4 landscape;
        margin: 6mm;
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
        font-size: 7.5px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 2px 1px !important;
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
    .print-month-rencana {
        color: #b45309 !important;
        font-weight: bold !important;
    }
    .print-month-realisasi {
        color: #047857 !important;
        font-weight: bold !important;
    }
    .print-month-both {
        color: #0f766e !important;
        font-weight: bold !important;
    }
}
`;

export default function HarJadwalPembuatanIkPage({
    unit,
    filters,
    options,
    employees,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<IkRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newTitle, setNewTitle] = useState('');
    const [newPic, setNewPic] = useState('');
    const [newMonth, setNewMonth] = useState(1);

    const signature = `${filters.unit_id}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            pembuatanIk.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Cycle month cell: Blank -> R -> ✓ -> R & ✓ -> Blank
    const toggleMonthCell = (rowIndex: number, month: number) => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) return r;
                const inRencana = r.rencana_bulan.includes(month);
                const inRealisasi = r.realisasi_bulan.includes(month);

                let nextRencana = [...r.rencana_bulan];
                let nextRealisasi = [...r.realisasi_bulan];

                if (!inRencana && !inRealisasi) {
                    // Blank -> R (Rencana)
                    nextRencana.push(month);
                } else if (inRencana && !inRealisasi) {
                    // R -> ✓ (Realisasi)
                    nextRencana = nextRencana.filter((m) => m !== month);
                    nextRealisasi.push(month);
                } else if (!inRencana && inRealisasi) {
                    // ✓ -> R & ✓ (Keduanya)
                    nextRencana.push(month);
                } else {
                    // R & ✓ -> Blank
                    nextRencana = nextRencana.filter((m) => m !== month);
                    nextRealisasi = nextRealisasi.filter((m) => m !== month);
                }

                return {
                    ...r,
                    rencana_bulan: nextRencana.sort((a, b) => a - b),
                    realisasi_bulan: nextRealisasi.sort((a, b) => a - b),
                };
            }),
        );
        setDirty(true);
    };

    // Update text fields
    const updateTextField = (
        rowIndex: number,
        field: 'instruksi_kerja' | 'pic_pembuat',
        value: string,
    ) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r, idx) =>
                idx === rowIndex ? { ...r, [field]: value } : r,
            ),
        );
        setDirty(true);
    };

    // Add new IK row
    const handleAddRow = () => {
        if (!newTitle.trim()) return;

        setRows((prev) => [
            ...prev,
            {
                id: null,
                no_urut: prev.length + 1,
                instruksi_kerja: newTitle.trim(),
                pic_pembuat: newPic.trim(),
                rencana_bulan: newMonth ? [Number(newMonth)] : [],
                realisasi_bulan: [],
            },
        ]);
        setDirty(true);
        setIsAddOpen(false);
        setNewTitle('');
        setNewPic('');
    };

    // Remove IK row
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
            pembuatanIk.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    instruksi_kerja: r.instruksi_kerja,
                    pic_pembuat: r.pic_pembuat,
                    rencana_bulan: r.rencana_bulan,
                    realisasi_bulan: r.realisasi_bulan,
                    keterangan: r.keterangan,
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

    // Calculations
    const { monthTotals, grandTotalIk, totalRencana, totalRealisasi, kinerja } =
        useMemo(() => {
            const totals: Record<number, number> = {};
            for (let m = 1; m <= 12; m++) {
                let count = 0;
                rows.forEach((r) => {
                    if (
                        r.rencana_bulan.includes(m) ||
                        r.realisasi_bulan.includes(m)
                    ) {
                        count++;
                    }
                });
                totals[m] = count;
            }

            let grandTotal = 0;
            let tRencana = 0;
            let tRealisasi = 0;

            rows.forEach((r) => {
                const uniqueMonths = new Set([
                    ...r.rencana_bulan,
                    ...r.realisasi_bulan,
                ]);
                grandTotal += uniqueMonths.size;
                tRencana += r.rencana_bulan.length;
                tRealisasi += r.realisasi_bulan.length;
            });

            const kin =
                tRencana > 0 ? Math.round((tRealisasi / tRencana) * 100) : 0;

            return {
                monthTotals: totals,
                grandTotalIk: grandTotal,
                totalRencana: tRencana,
                totalRealisasi: tRealisasi,
                kinerja: kin,
            };
        }, [rows]);

    // Export Excel
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Column Titles
        const rowH1: (string | number)[] = [
            'NO',
            'INSTRUKSI KERJA',
            'PIC PEMBUAT',
            ...Array(12).fill('BULAN'),
            'JUMLAH',
        ];
        headerRows.push(rowH1);

        const rowH2: (string | number)[] = ['', '', '', ...MONTH_LABELS, ''];
        headerRows.push(rowH2);

        // Category Row
        headerRows.push([
            'A.',
            'PEMBUATAN INTRUKSI KERJA',
            '',
            ...Array(12).fill(''),
            '',
        ]);

        // Data Rows
        rows.forEach((r, idx) => {
            const uniqueMonths = new Set([
                ...r.rencana_bulan,
                ...r.realisasi_bulan,
            ]);
            const rowData: (string | number)[] = [
                idx + 1,
                r.instruksi_kerja,
                r.pic_pembuat || '',
            ];

            for (let m = 1; m <= 12; m++) {
                const inRencana = r.rencana_bulan.includes(m);
                const inRealisasi = r.realisasi_bulan.includes(m);
                if (inRencana && inRealisasi) {
                    rowData.push('R & ✓');
                } else if (inRencana) {
                    rowData.push('R');
                } else if (inRealisasi) {
                    rowData.push('✓');
                } else {
                    rowData.push('');
                }
            }

            rowData.push(uniqueMonths.size);
            headerRows.push(rowData);
        });

        // Total Row
        const totalRow: (string | number)[] = [
            '',
            'TOTAL IK',
            '',
            ...Array.from({ length: 12 }, (_, i) => monthTotals[i + 1] ?? 0),
            grandTotalIk,
        ];
        headerRows.push(totalRow);

        headerRows.push([]);
        headerRows.push(['A.', 'IK', 'NILAI', 'A.DATA']);
        headerRows.push(['1', 'RENCANA', totalRencana, `${kinerja}%`]);
        headerRows.push(['2', 'REALISASI', totalRealisasi, '']);

        const { workbook, worksheet: ws } = createSheet(
            'Pembuatan_IK',
            headerRows,
        );

        const monthStart = 3;
        const monthEnd = 14; // 12 month columns
        const totalCols = 16;
        const catRowIdx = 6;
        const dataStart = 7;
        const dataEnd = dataStart + rows.length - 1;
        const totalRowIdx = dataEnd + 1;
        const recapHeaderIdx = totalRowIdx + 2;

        const amberCell: StyleSpec = {
            fill: XLSX_COLORS.amber,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const emeraldCell: StyleSpec = {
            fill: XLSX_COLORS.emerald,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const tealCell: StyleSpec = {
            fill: XLSX_COLORS.teal,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }
            if (r === 4 || r === 5) {
                return SPECS.headerOrange;
            }
            if (r === catRowIdx) {
                return SPECS.category;
            }

            if (r >= dataStart && r <= dataEnd) {
                if (c === 1) {
                    return SPECS.cellLeft;
                }
                if (c >= monthStart && c <= monthEnd) {
                    const value = String(headerRows[r]?.[c] ?? '');
                    if (value === 'R') {
                        return amberCell;
                    }
                    if (value === '✓') {
                        return emeraldCell;
                    }
                    if (value === 'R & ✓') {
                        return tealCell;
                    }
                    return SPECS.cell;
                }
                return SPECS.cell;
            }

            if (r === totalRowIdx) {
                return c === 1
                    ? { ...SPECS.total, align: 'left' }
                    : SPECS.total;
            }

            // Bottom recap mini-table (4 columns)
            if (r >= recapHeaderIdx && c < 4) {
                if (r === recapHeaderIdx) {
                    return SPECS.headerOrange;
                }
                return c === 1 ? SPECS.cellLeft : SPECS.cell;
            }

            return null;
        });

        mergeCells(ws, catRowIdx, 1, catRowIdx, totalCols - 1);

        const colWidths = [5, 34, 16, ...Array(12).fill(5), 8];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS 6 SITE - KIT UP KENDARI',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'LAPORAN PEMBUATAN IK PEMELIHARAAN PEMBANGKIT',
                ],
                barTitle: `JADWAL PEMBUATAN IK PEMELIHARAAN - TAHUN ${filters.year}`,
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_Pembuatan_IK_${unit.name.replace(/\s+/g, '_')}_${filters.year}.xlsx`,
        );
    };

    return (
        <>
            <Head title={`Jadwal Pembuatan IK Pemeliharaan — ${unit.name}`} />
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
                                    Jadwal Pembuatan IK Pemeliharaan
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Perencanaan dan realisasi penyusunan Instruksi
                                Kerja (IK) teknis pemeliharaan tahunan.
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
                                    <Plus className="size-3.5" />
                                    Tambah IK
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
                            href={`${pembuatanIk.pdf().url}?unit_id=${filters.unit_id}&year=${filters.year}`}
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
                    {/* Official Document Header (Exact match to reference image box layout) */}
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-center border border-black dark:border-border">
                            {/* Left Logo: PLN Nusantara Power */}
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
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

                            {/* Center 3-row Title Box */}
                            <div className="col-span-6 text-center text-xs font-bold text-foreground">
                                <div className="border-b border-black py-1.5 tracking-wide uppercase dark:border-border">
                                    JASA PENDUKUNG TEKNIS 6 SITE - KIT UP
                                    KENDARI
                                </div>
                                <div className="border-b border-black py-1.5 uppercase dark:border-border">
                                    LAPORAN PROJECT {unit.name.toUpperCase()}
                                </div>
                                <div className="py-1.5 uppercase">
                                    JADWAL PEMBUATAN IK PEMELIHARAAN PEMBANGKIT
                                    - TAHUN {filters.year}
                                </div>
                            </div>

                            {/* Right Logo: MKP */}
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
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
                            <thead className="print-orange-header bg-[#ed7d31] text-black">
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="w-12 border border-black p-2 text-center font-bold"
                                    >
                                        NO
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-72 min-w-64 border border-black p-2 text-left font-bold"
                                    >
                                        INSTRUKSI KERJA
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-44 min-w-36 border border-black p-2 text-left font-bold"
                                    >
                                        PIC PEMBUAT
                                    </th>
                                    <th
                                        colSpan={12}
                                        className="border border-black p-1.5 text-center font-bold tracking-wider"
                                    >
                                        BULAN
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-16 border border-black p-2 text-center font-bold"
                                    >
                                        JUMLAH
                                    </th>
                                    {can_write && (
                                        <th
                                            rowSpan={2}
                                            className="no-print w-10 border border-black p-2 text-center font-bold"
                                        >
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {MONTH_LABELS.map((m) => (
                                        <th
                                            key={m}
                                            className="w-8 border border-black p-1 text-center font-bold"
                                        >
                                            {m}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {/* Category Header Row */}
                                <tr className="bg-muted/30 font-bold">
                                    <td className="border border-black p-1.5 text-center font-extrabold">
                                        A.
                                    </td>
                                    <td
                                        colSpan={can_write ? 15 : 14}
                                        className="border border-black p-1.5 text-left font-extrabold tracking-wider uppercase"
                                    >
                                        PEMBUATAN INTRUKSI KERJA
                                    </td>
                                </tr>

                                {/* IK Data Rows */}
                                {rows.map((row, idx) => {
                                    const uniqueMonths = new Set([
                                        ...row.rencana_bulan,
                                        ...row.realisasi_bulan,
                                    ]);
                                    const rowJumlah = uniqueMonths.size;

                                    return (
                                        <tr
                                            key={idx}
                                            className="transition-colors hover:bg-muted/10"
                                        >
                                            {/* NO */}
                                            <td className="border border-black p-1 text-center font-medium">
                                                {idx + 1}
                                            </td>

                                            {/* INSTRUKSI KERJA */}
                                            <td className="border border-black p-0.5">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={
                                                            row.instruksi_kerja
                                                        }
                                                        onChange={(e) =>
                                                            updateTextField(
                                                                idx,
                                                                'instruksi_kerja',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full bg-transparent px-1.5 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                        placeholder="IK......................................................"
                                                    />
                                                ) : (
                                                    <span className="px-1.5 text-xs">
                                                        {row.instruksi_kerja}
                                                    </span>
                                                )}
                                            </td>

                                            {/* PIC PEMBUAT */}
                                            <td className="border border-black p-0.5">
                                                {can_write ? (
                                                    <>
                                                        <input
                                                            type="text"
                                                            list={`emp-list-${idx}`}
                                                            value={
                                                                row.pic_pembuat
                                                            }
                                                            onChange={(e) =>
                                                                updateTextField(
                                                                    idx,
                                                                    'pic_pembuat',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full bg-transparent px-1.5 py-0.5 text-xs uppercase focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                            placeholder="Nama PIC"
                                                        />
                                                        <datalist
                                                            id={`emp-list-${idx}`}
                                                        >
                                                            {employees.map(
                                                                (emp) => (
                                                                    <option
                                                                        key={
                                                                            emp
                                                                        }
                                                                        value={
                                                                            emp
                                                                        }
                                                                    />
                                                                ),
                                                            )}
                                                        </datalist>
                                                    </>
                                                ) : (
                                                    <span className="px-1.5 text-xs uppercase">
                                                        {row.pic_pembuat || '-'}
                                                    </span>
                                                )}
                                            </td>

                                            {/* 12 Month Cells */}
                                            {Array.from(
                                                { length: 12 },
                                                (_, mIdx) => {
                                                    const m = mIdx + 1;
                                                    const inRencana =
                                                        row.rencana_bulan.includes(
                                                            m,
                                                        );
                                                    const inRealisasi =
                                                        row.realisasi_bulan.includes(
                                                            m,
                                                        );

                                                    let cellContent = '';
                                                    let cellClass =
                                                        'border border-black text-center font-bold select-none text-xs ';

                                                    if (
                                                        inRencana &&
                                                        inRealisasi
                                                    ) {
                                                        cellContent = 'R & ✓';
                                                        cellClass +=
                                                            'bg-teal-100 dark:bg-teal-950 text-teal-800 dark:text-teal-200 print-month-both ';
                                                    } else if (inRencana) {
                                                        cellContent = 'R';
                                                        cellClass +=
                                                            'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-200 print-month-rencana ';
                                                    } else if (inRealisasi) {
                                                        cellContent = '✓';
                                                        cellClass +=
                                                            'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-200 print-month-realisasi ';
                                                    }

                                                    if (can_write) {
                                                        cellClass +=
                                                            'cursor-pointer hover:bg-primary/10 transition-colors';
                                                    }

                                                    return (
                                                        <td
                                                            key={m}
                                                            className={
                                                                cellClass
                                                            }
                                                            onClick={() =>
                                                                toggleMonthCell(
                                                                    idx,
                                                                    m,
                                                                )
                                                            }
                                                            title={`Bulan ${MONTH_LABELS[mIdx]}: Klik untuk ubah (Kosong -> R -> ✓ -> R & ✓)`}
                                                        >
                                                            {cellContent}
                                                        </td>
                                                    );
                                                },
                                            )}

                                            {/* JUMLAH */}
                                            <td className="border border-black p-1 text-center font-bold">
                                                {rowJumlah}
                                            </td>

                                            {/* Action (No-Print) */}
                                            {can_write && (
                                                <td className="no-print border border-black p-0.5 text-center">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            handleRemoveRow(idx)
                                                        }
                                                        title="Hapus Baris"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}

                                {/* TOTAL IK Row */}
                                <tr className="bg-muted/40 font-bold">
                                    <td
                                        colSpan={3}
                                        className="border border-black p-1.5 text-center font-extrabold tracking-wider"
                                    >
                                        TOTAL IK
                                    </td>
                                    {Array.from({ length: 12 }, (_, mIdx) => (
                                        <td
                                            key={mIdx}
                                            className="border border-black p-1 text-center font-extrabold"
                                        >
                                            {monthTotals[mIdx + 1] ?? 0}
                                        </td>
                                    ))}
                                    <td className="border border-black p-1 text-center font-extrabold">
                                        {grandTotalIk}
                                    </td>
                                    {can_write && (
                                        <td className="no-print border border-black" />
                                    )}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Bottom Section: Recap Table & Legends */}
                    <div className="flex flex-col items-start justify-between gap-4 border-t border-border bg-muted/10 p-4 md:flex-row">
                        {/* Recap Table (Matches image) */}
                        <div className="w-full overflow-hidden rounded-xs border border-black md:w-96">
                            <table className="w-full border-collapse text-xs">
                                <thead className="print-orange-header bg-[#ed7d31] text-black">
                                    <tr>
                                        <th className="w-10 border border-black p-1.5 text-center font-bold">
                                            A.
                                        </th>
                                        <th className="border border-black p-1.5 text-left font-bold">
                                            IK
                                        </th>
                                        <th className="w-20 border border-black p-1.5 text-center font-bold">
                                            NILAI
                                        </th>
                                        <th className="w-20 border border-black p-1.5 text-center font-bold">
                                            A.DATA
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="border border-black p-1.5 text-center font-medium">
                                            1
                                        </td>
                                        <td className="border border-black p-1.5 font-bold">
                                            RENCANA
                                        </td>
                                        <td className="border border-black p-1.5 text-center font-extrabold">
                                            {totalRencana}
                                        </td>
                                        <td className="border border-black p-1.5 text-center font-extrabold text-emerald-600">
                                            {kinerja}%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-black p-1.5 text-center font-medium">
                                            2
                                        </td>
                                        <td className="border border-black p-1.5 font-bold">
                                            REALISASI
                                        </td>
                                        <td className="border border-black p-1.5 text-center font-extrabold">
                                            {totalRealisasi}
                                        </td>
                                        <td className="border border-black p-1.5 text-center" />
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {/* Interactive Legend Box */}
                        <div className="flex flex-col gap-2 rounded-md border border-border bg-card p-3 text-xs text-muted-foreground">
                            <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                <Info className="size-4 text-primary" />
                                <span>Petunjuk Pengisian Matriks IK:</span>
                            </div>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <div className="flex items-center gap-1.5">
                                    <span className="rounded-xs border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                                        R
                                    </span>
                                    <span>Rencana Pembuatan</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="rounded-xs border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                        ✓
                                    </span>
                                    <span>Realisasi Selesai</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="rounded-xs border border-teal-300 bg-teal-100 px-2 py-0.5 text-xs font-bold text-teal-800">
                                        R &amp; ✓
                                    </span>
                                    <span>Selesai Sesuai Rencana</span>
                                </div>
                            </div>
                            <p className="text-[11px] italic">
                                Klik langsung pada sel bulan untuk mengganti
                                status. Perhitungan JUMLAH, TOTAL IK, dan Rekap
                                A.DATA dihitung otomatis.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Baris IK */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileText className="size-5 text-primary" />
                            Tambah Baris Instruksi Kerja (IK)
                        </DialogTitle>
                        <DialogDescription>
                            Tambahkan judul dokumen IK baru ke jadwal tahunan
                            unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label htmlFor="ik-title">
                                Nama / Judul Instruksi Kerja
                            </Label>
                            <Input
                                id="ik-title"
                                placeholder="Contoh: IK Penggantian Filter Pelumas Mesin"
                                value={newTitle}
                                onChange={(e) => setNewTitle(e.target.value)}
                                autoFocus
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ik-pic">
                                PIC Pembuat (Personil)
                            </Label>
                            <Input
                                id="ik-pic"
                                placeholder="Pilih atau ketik nama PIC..."
                                list="dialog-pic-list"
                                value={newPic}
                                onChange={(e) => setNewPic(e.target.value)}
                            />
                            <datalist id="dialog-pic-list">
                                {employees.map((emp) => (
                                    <option key={emp} value={emp} />
                                ))}
                            </datalist>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ik-month">
                                Target Bulan Rencana
                            </Label>
                            <select
                                id="ik-month"
                                value={newMonth}
                                onChange={(e) =>
                                    setNewMonth(Number(e.target.value))
                                }
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {MONTH_LABELS.map((m, idx) => (
                                    <option key={m} value={idx + 1}>
                                        {m} - Bulan {idx + 1}
                                    </option>
                                ))}
                            </select>
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
                            onClick={handleAddRow}
                            disabled={!newTitle.trim()}
                        >
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
