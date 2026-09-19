import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    ZapOff,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import * as XLSX from 'xlsx';
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
import blackstart from '@/routes/operasi/jadwal/blackstart';
import type { IdName } from '@/types';

type BlackstartRow = {
    id: number | null;
    no_urut: number;
    uraian: string;
    pic: string;
    rencana: string[];
    realisasi: string[];
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
    rows: BlackstartRow[];
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
}
`;

export default function OperasiJadwalBlackstartIndex({
    unit,
    filters,
    options,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<BlackstartRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state for adding new row
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newUraian, setNewUraian] = useState('');
    const [newPic, setNewPic] = useState('');

    // Navigation helper
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            blackstart.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Toggle cell for REN or REAL
    const handleToggleCell = (
        rowIndex: number,
        type: 'rencana' | 'realisasi',
        weekKey: string,
    ) => {
        if (!can_write) return;

        setRows((prev) => {
            const updated = [...prev];
            const targetRow = { ...updated[rowIndex] };
            const currentArr = [...(targetRow[type] || [])];

            const existsIdx = currentArr.indexOf(weekKey);
            if (existsIdx >= 0) {
                currentArr.splice(existsIdx, 1);
            } else {
                currentArr.push(weekKey);
            }

            targetRow[type] = currentArr;
            updated[rowIndex] = targetRow;
            return updated;
        });
        setDirty(true);
    };

    // Update uraian or pic
    const handleUpdateField = (
        rowIndex: number,
        field: 'uraian' | 'pic',
        value: string,
    ) => {
        if (!can_write) return;
        setRows((prev) => {
            const updated = [...prev];
            updated[rowIndex] = { ...updated[rowIndex], [field]: value };
            return updated;
        });
        setDirty(true);
    };

    // Add new item
    const handleAddRow = () => {
        if (!newUraian.trim()) return;
        setRows((prev) => [
            ...prev,
            {
                id: null,
                no_urut: prev.length + 1,
                uraian: newUraian.trim().toUpperCase(),
                pic: newPic.trim(),
                rencana: [],
                realisasi: [],
            },
        ]);
        setNewUraian('');
        setNewPic('');
        setIsAddOpen(false);
        setDirty(true);
    };

    // Remove item
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
            blackstart.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    uraian: r.uraian,
                    pic: r.pic,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
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

    // Calculations: column totals & grand total
    const { columnTotals, grandTotal, recapData } = useMemo(() => {
        const totals: Record<string, number> = {};
        for (let m = 1; m <= 12; m++) {
            for (let w = 1; w <= 4; w++) {
                const key = `${m}-${w}`;
                let count = 0;
                rows.forEach((r) => {
                    if (r.rencana?.includes(key)) count++;
                    if (r.realisasi?.includes(key)) count++;
                });
                totals[key] = count;
            }
        }
        const grand = Object.values(totals).reduce((sum, c) => sum + c, 0);

        const recap = rows.map((r, i) => {
            const renCount = r.rencana?.length || 0;
            const realCount = r.realisasi?.length || 0;
            const kinerja = renCount > 0 ? `${Math.round((realCount / renCount) * 100)}%` : '0%';

            return {
                no_urut: r.no_urut || i + 1,
                uraian: r.uraian,
                rencana: renCount,
                realisasi: realCount,
                kinerja,
            };
        });

        return { columnTotals: totals, grandTotal: grand, recapData: recap };
    }, [rows]);

    // Export Excel (.xlsx)
    const handleExportExcel = () => {
        const wb = XLSX.utils.book_new();

        const sheetData: (string | number)[][] = [
            ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT'],
            [`LAPORAN PROJECT ${unit.name.toUpperCase()}`],
            ['JADWAL PEMERIKSAAN INSTALASI BLACKSTART'],
            [],
        ];

        // Header Row 1
        const rowH1: (string | number)[] = ['NO', 'URAIAN PEMERIKSAAN', 'REN & REAL', 'PIC PEMBUAT'];
        MONTH_NAMES.forEach((m) => {
            rowH1.push(m, '', '', '');
        });
        rowH1.push('JUMLAH');
        sheetData.push(rowH1);

        // Header Row 2
        const rowH2: (string | number)[] = ['', '', '', ''];
        for (let m = 1; m <= 12; m++) {
            rowH2.push(1, 2, 3, 4);
        }
        rowH2.push('');
        sheetData.push(rowH2);

        // Section row
        sheetData.push(['A.', 'PEMBUATAN DATA TEKNIKS', '', '', ...Array(49).fill('')]);

        // Data Rows
        rows.forEach((r, idx) => {
            const ren = r.rencana || [];
            const real = r.realisasi || [];

            // REN
            const rRen: (string | number)[] = [r.no_urut || idx + 1, r.uraian, 'REN', r.pic || ''];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    rRen.push(ren.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            rRen.push(ren.length > 0 ? ren.length : 0);
            sheetData.push(rRen);

            // REAL
            const rReal: (string | number)[] = ['', '', 'REAL', ''];
            for (let m = 1; m <= 12; m++) {
                for (let w = 1; w <= 4; w++) {
                    rReal.push(real.includes(`${m}-${w}`) ? 1 : '');
                }
            }
            rReal.push(real.length > 0 ? real.length : 0);
            sheetData.push(rReal);
        });

        // Total Row
        const totRow: (string | number)[] = ['', 'TOTAL', '', ''];
        for (let m = 1; m <= 12; m++) {
            for (let w = 1; w <= 4; w++) {
                const c = columnTotals[`${m}-${w}`] || 0;
                totRow.push(c > 0 ? c : '');
            }
        }
        totRow.push(grandTotal > 0 ? grandTotal : 0);
        sheetData.push(totRow);

        // Recap table
        sheetData.push([]);
        sheetData.push(['NO', 'URAIAN', 'RENCANA', 'REALISASI', 'A. KINERJA']);
        recapData.forEach((rc) => {
            sheetData.push([rc.no_urut, rc.uraian, rc.rencana, rc.realisasi, rc.kinerja]);
        });

        const ws = XLSX.utils.aoa_to_sheet(sheetData);
        XLSX.utils.book_append_sheet(wb, ws, 'Blackstart');
        const filename = `Jadwal_Pemeriksaan_Instalasi_Blackstart_${unit.name.replace(/\s+/g, '_')}_${filters.year}.xlsx`;
        XLSX.writeFile(wb, filename);
    };

    return (
        <>
            <Head title={`Jadwal Pemeriksaan Instalasi Blackstart - ${unit.name}`} />
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
                                Jadwal Pemeriksaan Instalasi Blackstart
                            </h1>
                            <Badge variant="secondary" className="font-semibold">
                                {unit.name}
                            </Badge>
                            <Badge variant="outline">{filters.year}</Badge>
                            {dirty && (
                                <Badge variant="destructive" className="animate-pulse">
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
                                    Tambah Uraian
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
                                    blackstart.pdf({
                                        query: { unit_id: filters.unit_id, year: filters.year },
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
                        <span>Klik pada kotak minggu untuk menandai &apos;1&apos; pada baris RENCANA (REN) atau REALISASI (REAL).</span>
                    </div>
                </div>

                {/* Main Schedule Container */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Document Header */}
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
                            <div className="border-b border-black py-1 text-xs font-bold uppercase tracking-wide dark:border-white">
                                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold uppercase tracking-wide dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold uppercase tracking-wide text-foreground">
                                JADWAL PEMERIKSAAN INSTALASI BLACKSTART
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

                    {/* Table 1: Matrix 12 Bulan x 4 Minggu */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr className="print-orange-header bg-[#ed7d31] text-black font-bold">
                                    <th rowSpan={2} className="border border-black px-2 py-1.5 text-[11px] dark:border-white" style={{ width: '30px' }}>
                                        NO
                                    </th>
                                    <th rowSpan={2} className="border border-black px-3 py-1.5 text-left text-[11px] dark:border-white" style={{ minWidth: '180px' }}>
                                        URAIAN PEMERIKSAAN
                                    </th>
                                    <th rowSpan={2} className="border border-black px-1.5 py-1.5 text-[11px] dark:border-white" style={{ width: '60px' }}>
                                        REN &amp; REAL
                                    </th>
                                    <th rowSpan={2} className="border border-black px-2 py-1.5 text-[11px] dark:border-white" style={{ width: '90px' }}>
                                        PIC PEMBUAT
                                    </th>
                                    {MONTH_NAMES.map((m) => (
                                        <th
                                            key={m}
                                            colSpan={4}
                                            className="border border-black px-1 py-1 text-[10px] uppercase dark:border-white"
                                        >
                                            {m}
                                        </th>
                                    ))}
                                    <th rowSpan={2} className="border border-black px-2 py-1.5 text-[11px] dark:border-white" style={{ width: '50px' }}>
                                        JUMLAH
                                    </th>
                                    <th rowSpan={2} className="no-print border border-black px-2 py-1.5 text-[11px] dark:border-white" style={{ width: '40px' }}>
                                        AKSI
                                    </th>
                                </tr>
                                <tr className="print-orange-header bg-[#ed7d31] text-black font-bold">
                                    {Array.from({ length: 12 }).map((_, mIdx) => (
                                        <Fragment key={mIdx}>
                                            <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">1</th>
                                            <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">2</th>
                                            <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">3</th>
                                            <th className="border border-black px-1 py-0.5 text-[10px] dark:border-white">4</th>
                                        </Fragment>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {/* Section Header Row */}
                                <tr className="bg-muted/40 font-bold">
                                    <td className="border border-black px-1 py-1 dark:border-white">A.</td>
                                    <td colSpan={52} className="border border-black px-2 py-1 text-left uppercase dark:border-white">
                                        PEMBUATAN DATA TEKNIKS
                                    </td>
                                </tr>

                                {rows.map((row, rIdx) => {
                                    const ren = row.rencana || [];
                                    const real = row.realisasi || [];

                                    return (
                                        <Fragment key={row.id ?? `row-${rIdx}`}>
                                            {/* Sub-row 1: REN */}
                                            <tr className="hover:bg-muted/30">
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black px-1 py-1 font-medium dark:border-white"
                                                >
                                                    {row.no_urut || rIdx + 1}
                                                </td>
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black px-2 py-1 text-left font-semibold dark:border-white"
                                                >
                                                    {can_write ? (
                                                        <Input
                                                            value={row.uraian}
                                                            onChange={(e) =>
                                                                handleUpdateField(rIdx, 'uraian', e.target.value)
                                                            }
                                                            className="h-7 text-xs font-semibold uppercase"
                                                        />
                                                    ) : (
                                                        <span className="uppercase">{row.uraian}</span>
                                                    )}
                                                </td>
                                                <td className="border border-black bg-muted/20 px-1 py-1 font-semibold dark:border-white">
                                                    REN
                                                </td>
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black px-1 py-1 dark:border-white"
                                                >
                                                    {can_write ? (
                                                        <Input
                                                            value={row.pic || ''}
                                                            onChange={(e) =>
                                                                handleUpdateField(rIdx, 'pic', e.target.value)
                                                            }
                                                            placeholder="PIC"
                                                            className="h-7 text-xs"
                                                        />
                                                    ) : (
                                                        <span>{row.pic}</span>
                                                    )}
                                                </td>
                                                {Array.from({ length: 12 }).map((_, mIdx) =>
                                                    [1, 2, 3, 4].map((w) => {
                                                        const key = `${mIdx + 1}-${w}`;
                                                        const isMarked = ren.includes(key);
                                                        return (
                                                            <td
                                                                key={`ren-${key}`}
                                                                onClick={() =>
                                                                    handleToggleCell(rIdx, 'rencana', key)
                                                                }
                                                                className={`cursor-pointer border border-black px-0.5 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                    isMarked
                                                                        ? 'print-marked-cell bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                }`}
                                                                title={`Klik untuk ubah Rencana Bln ${mIdx + 1} M${w}`}
                                                            >
                                                                {isMarked ? '1' : ''}
                                                            </td>
                                                        );
                                                    }),
                                                )}
                                                <td className="border border-black bg-muted/20 px-1 py-1 font-bold dark:border-white">
                                                    {ren.length > 0 ? ren.length : '0'}
                                                </td>
                                                <td
                                                    rowSpan={2}
                                                    className="no-print border border-black px-1 py-1 dark:border-white"
                                                >
                                                    {can_write && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleRemoveRow(rIdx)}
                                                            className="size-7 text-destructive hover:bg-destructive/10"
                                                            title="Hapus Uraian"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>

                                            {/* Sub-row 2: REAL */}
                                            <tr className="hover:bg-muted/30">
                                                <td className="border border-black bg-muted/20 px-1 py-1 font-semibold dark:border-white">
                                                    REAL
                                                </td>
                                                {Array.from({ length: 12 }).map((_, mIdx) =>
                                                    [1, 2, 3, 4].map((w) => {
                                                        const key = `${mIdx + 1}-${w}`;
                                                        const isMarked = real.includes(key);
                                                        return (
                                                            <td
                                                                key={`real-${key}`}
                                                                onClick={() =>
                                                                    handleToggleCell(rIdx, 'realisasi', key)
                                                                }
                                                                className={`cursor-pointer border border-black px-0.5 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                    isMarked
                                                                        ? 'print-marked-cell bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                        : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                }`}
                                                                title={`Klik untuk ubah Realisasi Bln ${mIdx + 1} M${w}`}
                                                            >
                                                                {isMarked ? '1' : ''}
                                                            </td>
                                                        );
                                                    }),
                                                )}
                                                <td className="border border-black bg-muted/20 px-1 py-1 font-bold dark:border-white">
                                                    {real.length > 0 ? real.length : '0'}
                                                </td>
                                            </tr>
                                        </Fragment>
                                    );
                                })}

                                {/* Total Row */}
                                <tr className="print-orange-header bg-[#ed7d31] text-black font-bold">
                                    <td
                                        colSpan={4}
                                        className="border border-black px-2 py-1.5 text-center text-[11px] uppercase dark:border-white"
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
                                    <td className="border border-black px-1 py-1 text-center font-bold dark:border-white">
                                        {grandTotal > 0 ? grandTotal : '0'}
                                    </td>
                                    <td className="no-print border border-black dark:border-white"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Table 2: Recap Kinerja Bawah Kiri (matching reference) */}
                    <div className="mt-6 w-full max-w-lg">
                        <table className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr className="print-orange-header bg-[#ed7d31] text-black font-bold">
                                    <th className="border border-black px-2 py-1 text-[11px] dark:border-white" style={{ width: '35px' }}>
                                        NO
                                    </th>
                                    <th className="border border-black px-2 py-1 text-left text-[11px] dark:border-white">
                                        URAIAN
                                    </th>
                                    <th className="border border-black px-2 py-1 text-[11px] dark:border-white" style={{ width: '75px' }}>
                                        RENCANA
                                    </th>
                                    <th className="border border-black px-2 py-1 text-[11px] dark:border-white" style={{ width: '75px' }}>
                                        REALISASI
                                    </th>
                                    <th className="border border-black px-2 py-1 text-[11px] dark:border-white" style={{ width: '85px' }}>
                                        A. KINERJA
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {recapData.map((rc) => (
                                    <tr key={rc.no_urut}>
                                        <td className="border border-black px-1 py-1 font-medium dark:border-white">
                                            {rc.no_urut}
                                        </td>
                                        <td className="border border-black px-2 py-1 text-left font-semibold uppercase dark:border-white">
                                            {rc.uraian}
                                        </td>
                                        <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                            {rc.rencana}
                                        </td>
                                        <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                            {rc.realisasi}
                                        </td>
                                        <td className="border border-black px-1 py-1 font-bold dark:border-white">
                                            {rc.kinerja}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Dialog Tambah Uraian */}
                <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ZapOff className="size-5 text-primary" />
                                Tambah Uraian Pemeriksaan Blackstart
                            </DialogTitle>
                            <DialogDescription>
                                Masukkan uraian pemeriksaan instalasi blackstart (misal: PANEL KONTROL BLACKSTART).
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-2">
                            <div className="space-y-2">
                                <Label htmlFor="uraian">Uraian Pemeriksaan</Label>
                                <Input
                                    id="uraian"
                                    placeholder="Contoh: PANEL KONTROL BLACKSTART"
                                    value={newUraian}
                                    onChange={(e) => setNewUraian(e.target.value)}
                                    className="uppercase"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddRow();
                                        }
                                    }}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pic">PIC Pembuat (Opsional)</Label>
                                <Input
                                    id="pic"
                                    placeholder="Contoh: Koord Operasi"
                                    value={newPic}
                                    onChange={(e) => setNewPic(e.target.value)}
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsAddOpen(false)}>
                                Batal
                            </Button>
                            <Button
                                onClick={handleAddRow}
                                disabled={!newUraian.trim()}
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

OperasiJadwalBlackstartIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Pemeriksaan Instalasi Blackstart', href: '#' },
    ],
};
