import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Check,
    CheckCheck,
    Download,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import harian from '@/routes/har/jadwal/harian';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type HarianRow = {
    id: number | null;
    no_urut: number;
    kegiatan: string;
    target: number;
    rencana_count: number;
    realisasi_count: number;
    jadwal: number[];
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
        month: number;
        year: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    days: DayInfo[];
    target_working_days: number;
    rows: HarianRow[];
    can_write: boolean;
};

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
    .print-thead th {
        background-color: #ffffff !important;
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
        background-color: #ff0000 !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function HarJadwalHarianPage({
    unit,
    filters,
    options,
    days,
    target_working_days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<HarianRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state for adding activity
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newKegiatan, setNewKegiatan] = useState('');
    const [newTarget, setNewTarget] = useState(target_working_days);

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

    const workingDaysList = useMemo(() => {
        return days.filter((d) => !d.is_red).map((d) => d.day);
    }, [days]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            harian.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Toggle single day on/off for a row
    const toggleDay = (rowIndex: number, day: number) => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) return r;
                const set = new Set(r.jadwal || []);
                if (set.has(day)) {
                    set.delete(day);
                } else {
                    set.add(day);
                }
                const updatedJadwal = Array.from(set).sort((a, b) => a - b);
                return {
                    ...r,
                    jadwal: updatedJadwal,
                    realisasi_count: updatedJadwal.length,
                };
            }),
        );
        setDirty(true);
    };

    // Update row fields
    const updateField = (
        rowIndex: number,
        field: keyof HarianRow,
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

    // Quick Fill: Mark all working days for all activities
    const handleMarkAllWorkingDays = () => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r) => ({
                ...r,
                jadwal: [...workingDaysList],
                realisasi_count: workingDaysList.length,
            })),
        );
        setDirty(true);
    };

    // Add activity
    const handleAddActivity = () => {
        if (!newKegiatan.trim()) return;

        setRows((prev) => [
            ...prev,
            {
                id: null,
                no_urut: prev.length + 1,
                kegiatan: newKegiatan.trim(),
                target: Number(newTarget) || target_working_days,
                rencana_count: Number(newTarget) || target_working_days,
                realisasi_count: 0,
                jadwal: [],
                keterangan: '',
            },
        ]);
        setDirty(true);
        setIsAddOpen(false);
        setNewKegiatan('');
    };

    // Remove row
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
            harian.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    kegiatan: r.kegiatan,
                    target: r.target,
                    rencana_count: r.rencana_count,
                    jadwal: r.jadwal,
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

    // Export Excel
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Column Titles
        const rowH1: (string | number)[] = [
            'No',
            'KEGIATAN',
            ...Array(days.length).fill(monthName.toUpperCase()),
            'TARGET',
            'RENCANA',
            'REALISASI',
            'ANALISA KINERJA',
            'Keterangan',
        ];
        headerRows.push(rowH1);

        const rowH2: (string | number)[] = [
            '',
            '',
            ...days.map((d) => d.day),
            '',
            '',
            '',
            '',
            '',
        ];
        headerRows.push(rowH2);

        // Data Rows
        rows.forEach((r, idx) => {
            const realisasi = (r.jadwal || []).length;
            const target = r.target || target_working_days;
            const kinerja =
                target > 0
                    ? `${Math.round((realisasi / target) * 100)}%`
                    : '0%';

            const rowData: (string | number)[] = [
                idx + 1,
                r.kegiatan,
                ...days.map((d) => {
                    if (d.is_red) return 'LIBUR';
                    return r.jadwal.includes(d.day) ? 1 : '';
                }),
                target,
                r.rencana_count || target_working_days,
                realisasi,
                kinerja,
                r.keterangan || '',
            ];
            headerRows.push(rowData);
        });

        const { workbook, worksheet: ws } = createSheet(
            'Jadwal_Harian',
            headerRows,
        );

        // Column layout offsets
        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colTarget = dayStart + days.length;
        const colKeterangan = colTarget + 4;
        const totalCols = colKeterangan + 1;
        const headerTop = 4; // rowH1
        const headerBottom = 5; // rowH2
        const dataStart = 6;

        const redCell: StyleSpec = { fill: XLSX_COLORS.red, border: true };
        const doneCell: StyleSpec = {
            fill: XLSX_COLORS.slate,
            bold: true,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            // Document header is drawn separately
            if (r < 4) {
                return null;
            }

            // Header band (two rows)
            if (r === headerTop || r === headerBottom) {
                if (r === headerBottom && c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    return day?.is_red
                        ? { ...SPECS.headerGrey, color: XLSX_COLORS.red600 }
                        : SPECS.headerGrey;
                }
                return SPECS.headerGrey;
            }

            // Data rows
            if (r >= dataStart) {
                if (c === 0) {
                    return SPECS.cell;
                }
                if (c === 1) {
                    return SPECS.cellLeft;
                }
                if (c >= dayStart && c <= dayEnd) {
                    const value = headerRows[r]?.[c];
                    if (value === 'LIBUR') {
                        return redCell;
                    }
                    if (value === 1) {
                        return doneCell;
                    }
                    return SPECS.cell;
                }
                if (c === colKeterangan) {
                    return SPECS.cellLeft;
                }
                return { ...SPECS.cell, bold: true };
            }

            return null;
        });

        // Merged column headers
        mergeCells(ws, headerTop, 0, headerBottom, 0); // No
        mergeCells(ws, headerTop, 1, headerBottom, 1); // KEGIATAN
        mergeCells(ws, headerTop, dayStart, headerTop, dayEnd); // Month label
        for (let c = colTarget; c <= colKeterangan; c++) {
            mergeCells(ws, headerTop, c, headerBottom, c);
        }

        const colWidths = [
            5,
            30,
            ...Array(days.length).fill(3.5),
            9,
            9,
            9,
            11,
            22,
        ];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS - 6 SITE',
                    `PLN NP UP KENDARI - ${unit.name.toUpperCase()}`,
                    'LAPORAN PROJECT',
                ],
                barTitle: `JADWAL KEGIATAN PEMELIHARAAN - ${monthName.toUpperCase()} ${filters.year}`,
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_Kegiatan_Harian_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    return (
        <>
            <Head
                title={`Jadwal Kegiatan Harian Pemeliharaan — ${unit.name}`}
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
                                    Jadwal Kegiatan Harian Pemeliharaan
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Penjadwalan program kerja harian pemeliharaan
                                mesin pembangkit dan pemantauan kinerja.
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
                                    onClick={handleMarkAllWorkingDays}
                                    className="gap-1.5 text-xs"
                                    title="Tandai seluruh hari kerja non-libur"
                                >
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Semua Hari Kerja
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsAddOpen(true)}
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Kegiatan
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs"
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
                            className="gap-1.5 text-xs"
                            title="Unduh format Excel (.xlsx)"
                        >
                            <FileSpreadsheet className="size-3.5 text-emerald-600" />
                            Excel
                        </Button>
                        <a
                            href={`${harian.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5 text-xs"
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
                            className="gap-1.5 text-xs"
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
                    {/* Official Document Header matching media_1789470333971.png */}
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            {/* Left Logo: PLN */}
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

                            {/* Center Title Hierarchy */}
                            <div className="col-span-4 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                    JASA PENDUKUNG TEKNIS - 6 SITE
                                </div>
                                <div className="text-xs font-bold text-foreground uppercase">
                                    PLN NP UP KENDARI -{' '}
                                    {unit.name.toUpperCase()}
                                </div>
                                <div className="mt-0.5 text-[11px] font-semibold text-foreground uppercase">
                                    LAPORAN PROJECT
                                </div>
                                <div className="text-[11px] font-bold text-foreground uppercase">
                                    JADWAL KEGIATAN PEMELIHARAAN
                                </div>
                            </div>

                            {/* Right Center Logo: MKP */}
                            <div className="col-span-2 flex items-center justify-center border-l border-black p-2 dark:border-border">
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

                            {/* Far Right: Metadata Box */}
                            <div className="col-span-3 flex flex-col justify-between border-l border-black text-[10px] dark:border-border">
                                <div className="flex justify-between border-b border-black p-1 dark:border-border">
                                    <span className="font-semibold">
                                        Nomor Dokumen
                                    </span>
                                    <span>:</span>
                                </div>
                                <div className="flex justify-between border-b border-black p-1 dark:border-border">
                                    <span className="font-semibold">
                                        Revisi
                                    </span>
                                    <span>:</span>
                                </div>
                                <div className="flex justify-between border-b border-black p-1 dark:border-border">
                                    <span className="font-semibold">
                                        Tanggal Terbit
                                    </span>
                                    <span>:</span>
                                </div>
                                <div className="flex justify-between p-1">
                                    <span className="font-semibold">
                                        Halaman
                                    </span>
                                    <span>:</span>
                                </div>
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
                                        className="w-10 border border-black p-2 text-center font-bold text-foreground"
                                    >
                                        No
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-64 min-w-56 border border-black p-2 text-left font-bold text-foreground"
                                    >
                                        KEGIATAN
                                    </th>
                                    <th
                                        colSpan={days.length}
                                        className="border border-black p-1.5 text-center font-bold tracking-wider text-foreground uppercase"
                                    >
                                        {monthName}
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-14 border border-black p-2 text-center font-bold text-foreground"
                                    >
                                        TARGET
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-16 border border-black p-2 text-center font-bold text-foreground"
                                    >
                                        RENCANA
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-16 border border-black p-2 text-center font-bold text-foreground"
                                    >
                                        REALISASI
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-20 border border-black p-2 text-center font-bold text-foreground"
                                    >
                                        ANALISA KINERJA
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="w-48 min-w-40 border border-black p-2 text-left font-bold text-foreground"
                                    >
                                        Keterangan
                                    </th>
                                    {can_write && (
                                        <th
                                            rowSpan={2}
                                            className="no-print w-10 border border-black p-2 text-center font-bold text-foreground"
                                        >
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th
                                            key={d.day}
                                            className={`w-7 border border-black p-1 text-center font-bold ${
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
                                {rows.map((row, idx) => {
                                    const realisasi = (row.jadwal || []).length;
                                    const target =
                                        row.target || target_working_days;
                                    const kinerja =
                                        target > 0
                                            ? Math.round(
                                                  (realisasi / target) * 100,
                                              )
                                            : 0;

                                    return (
                                        <tr
                                            key={idx}
                                            className="transition-colors hover:bg-muted/10"
                                        >
                                            {/* No */}
                                            <td className="border border-black p-1 text-center font-medium text-foreground">
                                                {idx + 1}
                                            </td>

                                            {/* KEGIATAN */}
                                            <td className="border border-black p-1 text-foreground">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={row.kegiatan}
                                                        onChange={(e) =>
                                                            updateField(
                                                                idx,
                                                                'kegiatan',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full bg-transparent px-1.5 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                    />
                                                ) : (
                                                    <span className="px-1.5 text-xs">
                                                        {row.kegiatan}
                                                    </span>
                                                )}
                                            </td>

                                            {/* Day Cells */}
                                            {days.map((d) => {
                                                if (d.is_red) {
                                                    return (
                                                        <td
                                                            key={d.day}
                                                            className="print-red-cell border border-black bg-[#ff0000] select-none"
                                                            title={`${d.day} (${d.dow}): Libur / Akhir Pekan`}
                                                        />
                                                    );
                                                }

                                                const isDone =
                                                    row.jadwal.includes(d.day);

                                                return (
                                                    <td
                                                        key={d.day}
                                                        onClick={() =>
                                                            toggleDay(
                                                                idx,
                                                                d.day,
                                                            )
                                                        }
                                                        className={`border border-black text-center font-bold transition-colors select-none ${
                                                            isDone
                                                                ? 'bg-slate-200 text-foreground dark:bg-slate-700'
                                                                : 'hover:bg-muted/40'
                                                        } ${
                                                            can_write
                                                                ? 'cursor-pointer'
                                                                : ''
                                                        }`}
                                                        title={`Tanggal ${d.day} (${d.dow}): ${
                                                            isDone
                                                                ? 'Terlaksana (1) - Klik untuk batalkan'
                                                                : 'Belum Terlaksana - Klik untuk tandai (1)'
                                                        }`}
                                                    >
                                                        {isDone ? '1' : ''}
                                                    </td>
                                                );
                                            })}

                                            {/* TARGET */}
                                            <td className="border border-black p-1 text-center font-bold text-foreground">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={row.target}
                                                        onChange={(e) =>
                                                            updateField(
                                                                idx,
                                                                'target',
                                                                Number(
                                                                    e.target
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

                                            {/* RENCANA */}
                                            <td className="border border-black p-1 text-center font-bold text-foreground">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={
                                                            row.rencana_count
                                                        }
                                                        onChange={(e) =>
                                                            updateField(
                                                                idx,
                                                                'rencana_count',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="w-12 bg-transparent text-center font-bold focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                    />
                                                ) : (
                                                    row.rencana_count
                                                )}
                                            </td>

                                            {/* REALISASI */}
                                            <td className="border border-black p-1 text-center font-extrabold text-foreground">
                                                {realisasi}
                                            </td>

                                            {/* ANALISA KINERJA */}
                                            <td
                                                className={`border border-black p-1 text-center font-extrabold ${
                                                    kinerja >= 100
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-amber-600 dark:text-amber-400'
                                                }`}
                                            >
                                                {kinerja}%
                                            </td>

                                            {/* Keterangan */}
                                            <td className="border border-black p-1 text-foreground">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={
                                                            row.keterangan || ''
                                                        }
                                                        onChange={(e) =>
                                                            updateField(
                                                                idx,
                                                                'keterangan',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Catatan kegiatan..."
                                                        className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                    />
                                                ) : (
                                                    <span className="px-1 text-xs">
                                                        {row.keterangan || '-'}
                                                    </span>
                                                )}
                                            </td>

                                            {/* Aksi (No-Print) */}
                                            {can_write && (
                                                <td className="no-print border border-black p-1 text-center">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            handleRemoveRow(idx)
                                                        }
                                                        title="Hapus Kegiatan"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Bottom Legend & Summary */}
                    <div className="border-t border-border bg-muted/20 p-4">
                        <div className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-red-700 bg-[#ff0000]" />
                                    <span>Hari Libur / Akhir Pekan</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-border bg-slate-200 dark:bg-slate-700" />
                                    <span>Terlaksana (1)</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-foreground">
                                        Target Hari Kerja Bulan Ini:
                                    </span>
                                    <span>{target_working_days} Hari</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>
                                    Gunakan &quot;Tandai Semua Hari Kerja&quot;
                                    untuk mengisi otomatis hari kerja biasa.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Kegiatan */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Activity className="size-5 text-primary" />
                            Tambah Kegiatan Pemeliharaan Harian
                        </DialogTitle>
                        <DialogDescription>
                            Tambahkan aktivitas atau tugas pemeliharaan baru ke
                            jadwal harian unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label htmlFor="kegiatan-name">Nama Kegiatan</Label>
                            <Input
                                id="kegiatan-name"
                                placeholder="Contoh: Pengecekan Sistem Pelumasan Tambahan"
                                value={newKegiatan}
                                onChange={(e) => setNewKegiatan(e.target.value)}
                                autoFocus
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="kegiatan-target">
                                Target Hari Kerja
                            </Label>
                            <Input
                                id="kegiatan-target"
                                type="number"
                                min="0"
                                value={newTarget}
                                onChange={(e) =>
                                    setNewTarget(Number(e.target.value))
                                }
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
                            onClick={handleAddActivity}
                            disabled={!newKegiatan.trim()}
                        >
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
