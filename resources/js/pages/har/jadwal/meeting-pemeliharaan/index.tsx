import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarCheck,
    Copy,
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
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import jadwal from '@/routes/har/jadwal';
import meetingPemeliharaan from '@/routes/har/jadwal/meeting-pemeliharaan';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type MeetingRow = {
    id: number | null;
    uraian: string;
    target: number;
    rencana: Record<string, string | number>;
    realisasi: Record<string, string | number>;
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
    rows: MeetingRow[];
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
    .print-orange-header {
        background-color: #ed7d31 !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-red-text {
        color: #dc2626 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function HarJadwalMeetingPemeliharaanPage({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<MeetingRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

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
            meetingPemeliharaan.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Toggle day value (0 <-> 1)
    const toggleCell = (
        rowIndex: number,
        category: 'rencana' | 'realisasi',
        day: number,
    ) => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) return r;
                const nextMap = { ...r[category] };
                const dayKey = String(day);
                const currentVal = Number(nextMap[dayKey] ?? 0);

                if (currentVal > 0) {
                    delete nextMap[dayKey];
                } else {
                    nextMap[dayKey] = 1;
                }

                return {
                    ...r,
                    [category]: nextMap,
                };
            }),
        );
        setDirty(true);
    };

    // Update target
    const handleTargetChange = (rowIndex: number, newTarget: number) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r, idx) =>
                idx === rowIndex ? { ...r, target: Math.max(0, newTarget) } : r,
            ),
        );
        setDirty(true);
    };

    // Update uraian
    const handleUraianChange = (rowIndex: number, newUraian: string) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r, idx) =>
                idx === rowIndex ? { ...r, uraian: newUraian } : r,
            ),
        );
        setDirty(true);
    };

    // Add row
    const handleAddRow = () => {
        if (!can_write) return;
        setRows((prev) => [
            ...prev,
            {
                id: null,
                uraian: 'Meeting Koordinasi Tambahan',
                target: 1,
                rencana: {},
                realisasi: {},
            },
        ]);
        setDirty(true);
    };

    // Remove row
    const handleRemoveRow = (rowIndex: number) => {
        if (!can_write || rows.length <= 1) return;
        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
    };

    // Copy entire Rencana to Realisasi
    const handleCopyRencanaToRealisasi = () => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r) => ({
                ...r,
                realisasi: { ...r.rencana },
            })),
        );
        setDirty(true);
    };

    // Reset
    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    // Save changes
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);

        router.post(
            meetingPemeliharaan.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    id: r.id,
                    uraian: r.uraian,
                    target: r.target,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    keterangan: r.keterangan,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    setSaving(false);
                },
                onError: () => {
                    setSaving(false);
                },
            },
        );
    };

    // Calculations per row
    const calculatedRows = useMemo(() => {
        return rows.map((r) => {
            const renValues = Object.values(r.rencana ?? {})
                .map(Number)
                .filter((v) => v > 0);
            const reaValues = Object.values(r.realisasi ?? {})
                .map(Number)
                .filter((v) => v > 0);
            const totalRencana = renValues.reduce((a, b) => a + b, 0);
            const totalRealisasi = reaValues.reduce((a, b) => a + b, 0);
            const target = Number(r.target) || 1;
            const performance =
                target > 0 ? Math.round((totalRealisasi / target) * 100) : 0;

            return {
                ...r,
                totalRencana,
                totalRealisasi,
                performance,
            };
        });
    }, [rows]);

    // Export Excel
    const handleExportExcel = async () => {
        const safeUnit = unit.name.replace(/\s+/g, '_');
        const fileName = `Jadwal_Meeting_Pemeliharaan_${safeUnit}_${filters.month}_${filters.year}.xlsx`;

        const headerRow1 = [
            'URAIAN',
            monthName.toUpperCase(),
            ...days.map((d) => d.dow),
            'RENCANA',
            'TARGET',
            'REALISASI',
            'ANALISA KINERJA',
        ];

        const headerRow2 = [
            '',
            String(filters.year),
            ...days.map((d) => d.day),
            '',
            '',
            '',
            '',
        ];

        const dataRows: (string | number)[][] = [];

        calculatedRows.forEach((r) => {
            const rencanaCols = days.map((d) =>
                Number(r.rencana[String(d.day)] ?? 0),
            );
            const realisasiCols = days.map((d) =>
                Number(r.realisasi[String(d.day)] ?? 0),
            );

            dataRows.push([
                r.uraian,
                'RENCANA',
                ...rencanaCols,
                r.totalRencana,
                r.target,
                r.totalRealisasi,
                `${r.performance}%`,
            ]);

            dataRows.push(['', 'REALISASI', ...realisasiCols, '', '', '', '']);
        });

        const sheetData: (string | number)[][] = [
            [],
            [],
            [],
            [],
            headerRow1,
            headerRow2,
            ...dataRows,
        ];

        const { workbook, worksheet: ws } = createSheet(
            'Meeting Pemeliharaan',
            sheetData,
        );

        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colRencana = dayStart + days.length;
        const totalCols = colRencana + 4;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;

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

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }
            if (r === headerTop || r === headerBottom) {
                return SPECS.headerOrange;
            }

            if (r >= dataStart) {
                const sub = (r - dataStart) % 2; // 0 rencana, 1 realisasi
                if (c === 0) {
                    return SPECS.cellLeft;
                }
                if (c === 1) {
                    return SPECS.labelCenter;
                }
                if (c >= dayStart && c <= dayEnd) {
                    const value = Number(sheetData[r]?.[c] ?? 0);
                    if (value > 0) {
                        return sub === 0 ? amberCell : emeraldCell;
                    }
                    return SPECS.cell;
                }
                return { ...SPECS.cell, bold: true };
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // URAIAN
        mergeCells(ws, headerTop, 1, headerBottom, 1); // month / year
        for (let c = colRencana; c < totalCols; c++) {
            mergeCells(ws, headerTop, c, headerBottom, c);
        }
        for (let u = 0; u < calculatedRows.length; u++) {
            const top = dataStart + u * 2;
            mergeCells(ws, top, 0, top + 1, 0); // URAIAN spans rencana + realisasi
        }

        const colWidths = [
            28,
            12,
            ...Array(days.length).fill(3.2),
            9,
            8,
            9,
            11,
        ];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'LAPORAN MEETING PEMELIHARAAN PEMBANGKIT',
                ],
                barTitle: 'JADWAL MEETING PEMELIHARAAN PEMBANGKIT',
            },
        );
        await downloadWorkbook(workbook, fileName);
    };

    return (
        <>
            <Head title={`Jadwal Meeting Pemeliharaan - ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Back link & Title */}
                <div className="no-print flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.get(jadwal.index().url, {
                                    unit_id: filters.unit_id,
                                    month: filters.month,
                                    year: filters.year,
                                })
                            }
                            className="gap-1.5 text-xs"
                        >
                            <ArrowLeft className="size-3.5" />
                            Kembali ke Menu Jadwal
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-lg font-bold tracking-tight text-foreground md:text-xl">
                                    Jadwal Meeting Pemeliharaan Pembangkit
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-amber-500/30 bg-amber-500/10 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                                >
                                    Pemeliharaan
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Unit:{' '}
                                <span className="font-semibold text-foreground">
                                    {unit.name}
                                </span>{' '}
                                | Periode:{' '}
                                <span className="font-semibold text-foreground">
                                    {monthName} {filters.year}
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Top Actions */}
                    <div className="flex flex-wrap items-center gap-2">
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddRow}
                                    title="Tambah baris kategori meeting pemeliharaan"
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5 text-primary" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleCopyRencanaToRealisasi}
                                    title="Salin jadwal Rencana ke Realisasi"
                                    className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <Copy className="size-3.5 text-emerald-500" />
                                    Salin Rencana ke Realisasi
                                </Button>
                                {dirty && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleReset}
                                        className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Reset
                                    </Button>
                                )}
                            </>
                        )}

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExportExcel}
                            className="gap-1.5 text-xs"
                        >
                            <FileSpreadsheet className="size-3.5 text-emerald-600" />
                            Excel
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                        >
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>

                        <a
                            href={`${meetingPemeliharaan.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5 text-xs"
                            >
                                <Download className="size-3.5 text-rose-500" />
                                Unduh PDF
                            </Button>
                        </a>

                        {can_write && (
                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={saving || !dirty}
                                className={`gap-1.5 text-xs ${
                                    dirty
                                        ? 'animate-pulse bg-primary text-primary-foreground shadow-sm'
                                        : ''
                                }`}
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
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
                        <div className="flex items-center gap-1.5 rounded-md bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-600 dark:text-amber-400">
                            <Info className="size-3.5" />
                            Ada perubahan yang belum disimpan.
                        </div>
                    )}
                </div>

                {/* Printable Document Container */}
                <div className="print-container flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                    {/* Header Banner matching user's official report */}
                    <div className="overflow-hidden rounded-md border border-border bg-background">
                        <div className="grid grid-cols-12 items-center divide-x divide-border">
                            {/* Left Logo: PLN Nusantara Power */}
                            <div className="col-span-3 flex h-24 items-center justify-center bg-white p-3">
                                <img
                                    src="/logo/sidebar-logo.png"
                                    alt="PLN Nusantara Power Logo"
                                    className="max-h-16 max-w-full object-contain"
                                />
                            </div>

                            {/* Center Title Banner */}
                            <div className="col-span-6 flex flex-col items-center justify-center p-3 text-center">
                                <h2 className="text-xs font-bold tracking-wider text-muted-foreground uppercase md:text-sm">
                                    JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI
                                </h2>
                                <h1 className="text-sm font-extrabold tracking-wide text-foreground uppercase md:text-base">
                                    LAPORAN PROJECT {unit.name}
                                </h1>
                                <p className="text-xs font-extrabold tracking-wider text-[#ed7d31] uppercase md:text-sm">
                                    JADWAL MEETING PEMELIHARAAN PEMBANGKIT
                                </p>
                            </div>

                            {/* Right Logo: Mitra Karya Prima */}
                            <div className="col-span-3 flex h-24 items-center justify-center bg-white p-3">
                                <img
                                    src="/logo/mkp.jpg"
                                    alt="MKP Logo"
                                    className="max-h-16 max-w-full object-contain"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Instruction hint */}
                    {can_write && (
                        <div className="no-print flex items-center justify-between rounded-md bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <Info className="size-4 text-[#ed7d31]" />
                                <span>
                                    <strong>Petunjuk:</strong> Klik pada sel
                                    tanggal untuk menandai jadwal meeting
                                    (toggle antara <strong>0</strong> dan{' '}
                                    <strong>1</strong>). Target bulanan dapat
                                    diubah langsung pada kolom Target.
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Meeting Schedule Table */}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="print-table w-full border-collapse text-center text-xs">
                            <thead>
                                {/* Top Header Row (Orange Background) */}
                                <tr className="print-orange-header border-b border-border bg-[#ed7d31] font-bold text-black">
                                    <th
                                        rowSpan={2}
                                        style={{ minWidth: '170px' }}
                                        className="border-r border-border p-2 text-left align-middle"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <CalendarCheck className="size-3.5" />
                                            URAIAN
                                        </div>
                                    </th>
                                    <th
                                        style={{ minWidth: '85px' }}
                                        className="border-r border-border p-1 text-center font-bold"
                                    >
                                        {monthName.toUpperCase()}
                                    </th>
                                    {days.map((day) => (
                                        <th
                                            key={day.day}
                                            style={{
                                                minWidth: '26px',
                                                width: '26px',
                                            }}
                                            className={`border-r border-border p-0.5 text-center font-bold ${
                                                day.is_red
                                                    ? 'print-red-text text-red-600'
                                                    : 'text-black'
                                            }`}
                                            title={
                                                day.holiday ??
                                                (day.is_weekend
                                                    ? 'Akhir Pekan'
                                                    : undefined)
                                            }
                                        >
                                            {day.dow}
                                        </th>
                                    ))}
                                    <th
                                        rowSpan={2}
                                        style={{ minWidth: '65px' }}
                                        className="border-r border-border p-2 text-center align-middle font-bold"
                                    >
                                        RENCANA
                                    </th>
                                    <th
                                        rowSpan={2}
                                        style={{ minWidth: '65px' }}
                                        className="border-r border-border p-2 text-center align-middle font-bold"
                                    >
                                        TARGET
                                    </th>
                                    <th
                                        rowSpan={2}
                                        style={{ minWidth: '65px' }}
                                        className="border-r border-border p-2 text-center align-middle font-bold"
                                    >
                                        REALISASI
                                    </th>
                                    <th
                                        rowSpan={2}
                                        style={{ minWidth: '95px' }}
                                        className="p-2 text-center align-middle font-bold"
                                    >
                                        ANALISA KINERJA
                                    </th>
                                </tr>

                                {/* Bottom Header Row (Year and Day Numbers) */}
                                <tr className="print-orange-header border-b border-border bg-[#ed7d31] font-bold text-black">
                                    <th className="border-r border-border p-1 text-center font-bold">
                                        {filters.year}
                                    </th>
                                    {days.map((day) => (
                                        <th
                                            key={day.day}
                                            className={`border-r border-border p-0.5 text-center font-bold ${
                                                day.is_red
                                                    ? 'print-red-text text-red-600'
                                                    : 'text-black'
                                            }`}
                                        >
                                            {day.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {calculatedRows.map((row, rIndex) => (
                                    <Fragment key={row.id ?? rIndex}>
                                        {/* Subrow 1: Rencana */}
                                        <tr className="border-b border-border transition-colors hover:bg-muted/10">
                                            <td
                                                rowSpan={2}
                                                className="border-r border-border bg-background p-2 text-left align-middle"
                                            >
                                                {can_write &&
                                                calculatedRows.length > 1 ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <input
                                                            type="text"
                                                            value={row.uraian}
                                                            onChange={(e) =>
                                                                handleUraianChange(
                                                                    rIndex,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full rounded border border-border bg-transparent px-2 py-1 text-xs font-semibold text-foreground focus:border-primary focus:outline-hidden"
                                                        />
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleRemoveRow(
                                                                    rIndex,
                                                                )
                                                            }
                                                            className="no-print size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                                            title="Hapus baris"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="font-semibold text-foreground">
                                                        {row.uraian}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="border-r border-border bg-muted/10 p-1.5 text-center font-semibold text-muted-foreground">
                                                RENCANA
                                            </td>

                                            {/* Days for Rencana */}
                                            {days.map((day) => {
                                                const dKey = String(day.day);
                                                const val = Number(
                                                    row.rencana[dKey] ?? 0,
                                                );
                                                const isFilled = val > 0;

                                                return (
                                                    <td
                                                        key={day.day}
                                                        onClick={() =>
                                                            toggleCell(
                                                                rIndex,
                                                                'rencana',
                                                                day.day,
                                                            )
                                                        }
                                                        className={`border-r border-border p-1 text-center font-semibold transition-colors select-none ${
                                                            isFilled
                                                                ? 'bg-amber-100 font-bold text-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
                                                                : 'bg-background text-foreground'
                                                        } ${can_write ? 'cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-950/20' : 'cursor-default'}`}
                                                        title={
                                                            can_write
                                                                ? isFilled
                                                                    ? 'Klik untuk mengubah menjadi 0'
                                                                    : 'Klik untuk menjadwalkan meeting (1)'
                                                                : undefined
                                                        }
                                                    >
                                                        {val}
                                                    </td>
                                                );
                                            })}

                                            {/* Summary with rowSpan=2 */}
                                            <td
                                                rowSpan={2}
                                                className="border-r border-border bg-background p-2 text-center align-middle text-sm font-extrabold text-foreground"
                                            >
                                                {row.totalRencana}
                                            </td>
                                            <td
                                                rowSpan={2}
                                                className="border-r border-border bg-background p-2 text-center align-middle"
                                            >
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={row.target}
                                                        onChange={(e) =>
                                                            handleTargetChange(
                                                                rIndex,
                                                                parseInt(
                                                                    e.target
                                                                        .value,
                                                                    10,
                                                                ) || 0,
                                                            )
                                                        }
                                                        className="w-14 rounded border border-border bg-transparent p-1 text-center text-sm font-extrabold text-foreground focus:border-primary focus:outline-hidden"
                                                    />
                                                ) : (
                                                    <span className="text-sm font-extrabold text-foreground">
                                                        {row.target}
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                rowSpan={2}
                                                className="border-r border-border bg-background p-2 text-center align-middle text-sm font-extrabold text-foreground"
                                            >
                                                {row.totalRealisasi}
                                            </td>
                                            <td
                                                rowSpan={2}
                                                className="bg-background p-2 text-center align-middle text-base font-black"
                                            >
                                                <span
                                                    className={
                                                        row.performance >= 100
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : row.performance >=
                                                                80
                                                              ? 'text-sky-600 dark:text-sky-400'
                                                              : 'text-amber-600 dark:text-amber-400'
                                                    }
                                                >
                                                    {row.performance}%
                                                </span>
                                            </td>
                                        </tr>

                                        {/* Subrow 2: Realisasi */}
                                        <tr className="border-b-2 border-border transition-colors hover:bg-muted/10">
                                            <td className="border-r border-border bg-muted/10 p-1.5 text-center font-semibold text-muted-foreground">
                                                REALISASI
                                            </td>

                                            {/* Days for Realisasi */}
                                            {days.map((day) => {
                                                const dKey = String(day.day);
                                                const val = Number(
                                                    row.realisasi[dKey] ?? 0,
                                                );
                                                const isFilled = val > 0;

                                                return (
                                                    <td
                                                        key={day.day}
                                                        onClick={() =>
                                                            toggleCell(
                                                                rIndex,
                                                                'realisasi',
                                                                day.day,
                                                            )
                                                        }
                                                        className={`border-r border-border p-1 text-center font-semibold transition-colors select-none ${
                                                            isFilled
                                                                ? 'bg-emerald-100 font-bold text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                : 'bg-background text-foreground'
                                                        } ${can_write ? 'cursor-pointer hover:bg-emerald-50 dark:hover:bg-emerald-950/20' : 'cursor-default'}`}
                                                        title={
                                                            can_write
                                                                ? isFilled
                                                                    ? 'Klik untuk mengubah menjadi 0'
                                                                    : 'Klik untuk menetapkan realisasi meeting (1)'
                                                                : undefined
                                                        }
                                                    >
                                                        {val}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

HarJadwalMeetingPemeliharaanPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Pemeliharaan', href: jadwal.index() },
        { title: 'Meeting Pemeliharaan', href: meetingPemeliharaan.index() },
    ],
};
