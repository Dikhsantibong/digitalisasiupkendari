import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Copy,
    Download,
    FileSpreadsheet,
    Info,
    Printer,
    RotateCcw,
    Save,
    ShieldCheck,
    Shuffle,
    Users,
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
import patrolCheck from '@/routes/har/jadwal/patrol-check';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type OperatorRow = {
    employee_id: number;
    name: string;
    nip: string | null;
    position: string | null;
    regu: string | null;
    rencana: Record<string, string | number>;
    realisasi: Record<string, string | number>;
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
    rows: OperatorRow[];
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
        padding: 3px 2px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-red-cell {
        background-color: #dc2626 !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-piket-cell {
        background-color: #00b0f0 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function HarJadwalPatrolCheckPage({
    unit,
    filters,
    options,
    days,
    target_working_days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<OperatorRow[]>(initialRows);
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
            patrolCheck.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Toggle cell value between "1" and undefined
    const toggleCell = (
        employeeId: number,
        category: 'rencana' | 'realisasi',
        day: number,
    ) => {
        if (!can_write) return;

        setRows((prev) =>
            prev.map((r) => {
                if (r.employee_id !== employeeId) return r;
                const nextMap = { ...r[category] };
                const dayKey = String(day);
                const currentVal = nextMap[dayKey];

                if (currentVal && String(currentVal) === '1') {
                    delete nextMap[dayKey];
                } else {
                    nextMap[dayKey] = '1';
                }

                return {
                    ...r,
                    [category]: nextMap,
                };
            }),
        );
        setDirty(true);
    };

    // Auto rotate piket across non-red working days sequentially across operators
    const handleAutoRotate = () => {
        if (!can_write || rows.length === 0) return;

        const workingDays = days.filter((d) => !d.is_red);
        if (workingDays.length === 0) return;

        setRows((prev) => {
            const next = prev.map((r) => ({
                ...r,
                rencana: {} as Record<string, string | number>,
            }));

            workingDays.forEach((wd, index) => {
                const operatorIndex = index % next.length;
                next[operatorIndex].rencana[String(wd.day)] = '1';
            });

            return next;
        });
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

    // Reset to initial
    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    // Save changes
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);

        router.post(
            patrolCheck.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    employee_id: r.employee_id,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
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

    // Summary calculations
    const { totalRencana, totalRealisasi, performance } = useMemo(() => {
        let ren = 0;
        let rea = 0;

        rows.forEach((r) => {
            ren += Object.values(r.rencana ?? {}).filter(
                (v) => String(v) === '1',
            ).length;
            rea += Object.values(r.realisasi ?? {}).filter(
                (v) => String(v) === '1',
            ).length;
        });

        const perf =
            target_working_days > 0
                ? Math.round((rea / target_working_days) * 100)
                : 0;

        return {
            totalRencana: ren,
            totalRealisasi: rea,
            performance: perf,
        };
    }, [rows, target_working_days]);

    // Export Excel
    const handleExportExcel = async () => {
        const safeUnit = unit.name.replace(/\s+/g, '_');
        const fileName = `Jadwal_Patrol_Check_${safeUnit}_${filters.month}_${filters.year}.xlsx`;

        const headerRow1 = [
            'NAMA',
            'RENCANA / REALISASI',
            ...days.map((d) => d.day),
            'RENCANA',
            'REALISASI',
            'TARGET',
            'ANALISA KINERJA',
        ];

        const dataRows: (string | number)[][] = [];

        rows.forEach((r, idx) => {
            const rencanaCols = days.map((d) => {
                if (d.is_red) return 'OFF';
                return String(r.rencana[String(d.day)] ?? '') === '1' ? 1 : '';
            });

            const realisasiCols = days.map((d) => {
                if (d.is_red) return 'OFF';
                return String(r.realisasi[String(d.day)] ?? '') === '1'
                    ? 1
                    : '';
            });

            // Rencana row
            dataRows.push([
                r.name,
                'RENCANA',
                ...rencanaCols,
                idx === 0 ? totalRencana : '',
                idx === 0 ? totalRealisasi : '',
                idx === 0 ? target_working_days : '',
                idx === 0 ? `${performance}%` : '',
            ]);

            // Realisasi row
            dataRows.push(['', 'REALISASI', ...realisasiCols, '', '', '', '']);
        });

        const sheetData: (string | number)[][] = [
            [],
            [],
            [],
            [],
            headerRow1,
            ...dataRows,
            [],
            ['Keterangan: 1 = Hari Piket, OFF = Off/Libur'],
            [
                'Note: Jika personel berhalangan, harap digantikan dengan personel lain',
            ],
        ];

        const { workbook, worksheet: ws } = createSheet(
            'Patrol Check',
            sheetData,
        );

        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colRencana = dayStart + days.length;
        const totalCols = colRencana + 4;
        const headerRowIdx = 4;
        const dataStart = 5;
        const dataEnd = dataStart + dataRows.length - 1;

        const blueCell: StyleSpec = {
            fill: XLSX_COLORS.blue,
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const offCell: StyleSpec = {
            fill: XLSX_COLORS.red600,
            color: XLSX_COLORS.white,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }

            if (r === headerRowIdx) {
                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    return day?.is_red ? offCell : SPECS.headerGrey;
                }
                return SPECS.headerGrey;
            }

            if (r >= dataStart && r <= dataEnd) {
                if (c === 0) {
                    return SPECS.cellLeft;
                }
                if (c === 1) {
                    return SPECS.labelCenter;
                }
                if (c >= dayStart && c <= dayEnd) {
                    const value = String(sheetData[r]?.[c] ?? '');
                    if (value === 'OFF') {
                        return offCell;
                    }
                    if (value === '1') {
                        return blueCell;
                    }
                    return SPECS.cell;
                }
                return { ...SPECS.cell, bold: true };
            }

            // Trailing keterangan / note lines
            if (r > dataEnd) {
                return c === 0
                    ? { align: 'left', italic: true, size: 9 }
                    : null;
            }

            return null;
        });

        for (let p = 0; p < rows.length; p++) {
            const top = dataStart + p * 2;
            mergeCells(ws, top, 0, top + 1, 0); // NAMA column spans rencana + realisasi
        }
        // Summary columns span the whole personnel block
        for (let c = colRencana; c < totalCols; c++) {
            mergeCells(ws, dataStart, c, dataEnd, c);
        }

        const colWidths = [
            24,
            14,
            ...Array(days.length).fill(3.5),
            9,
            9,
            8,
            11,
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
                barTitle: `JADWAL PIKET PATROL CHECK HARIAN - ${monthName.toUpperCase()} ${filters.year}`,
            },
        );
        await downloadWorkbook(workbook, fileName);
    };

    return (
        <>
            <Head title={`Jadwal Piket Patrol Check Harian - ${unit.name}`} />
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
                                    Jadwal Piket Patrol Check Harian
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Jadwal inspeksi patroli harian pemantauan
                                vibrasi, temperatur, kebocoran, dan kondisi
                                mesin.
                            </p>
                        </div>
                    </div>

                    {/* Top Action Buttons */}
                    <div className="flex flex-wrap items-center gap-2">
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAutoRotate}
                                    title="Rotasi bergiliran otomatis untuk semua hari kerja aktif"
                                    className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <Shuffle className="size-3.5 text-sky-500" />
                                    Rotasi Otomatis (Rencana)
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleCopyRencanaToRealisasi}
                                    title="Salin seluruh jadwal Rencana ke Realisasi"
                                    className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <Copy className="size-3.5 text-emerald-500" />
                                    Salin Rencana ke Realisasi
                                </Button>
                            </>
                        )}
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
                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={saving || !dirty}
                                className="gap-1.5"
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan…' : 'Simpan Jadwal'}
                            </Button>
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
                            href={`${patrolCheck.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
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
                            onClick={() => window.print()}
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
                    {/* Official Document Header (Matches Image) */}
                    <div className="border-b border-border p-4">
                        <div className="flex flex-col items-center justify-between gap-3 border border-border/80 p-3 sm:flex-row">
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

                            {/* Center Title */}
                            <div className="text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase md:text-sm">
                                    JASA PENDUKUNG TEKNIS - 6 SITE
                                </div>
                                <div className="text-xs font-bold text-foreground uppercase md:text-sm">
                                    PLN NP UP KENDARI -{' '}
                                    {unit.name.toUpperCase()}
                                </div>
                                <div className="mt-0.5 text-xs font-semibold text-foreground">
                                    LAPORAN PROJECT
                                </div>
                                <div className="text-xs font-bold text-foreground">
                                    JADWAL PIKET PATROL CHECK HARIAN
                                </div>
                                <div className="mt-0.5 text-xs font-semibold text-foreground">
                                    PERIODE : {monthName.toUpperCase()}{' '}
                                    {filters.year}
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

                        {/* Legend / Keterangan Box (Matches p0-p5 layout) */}
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded border border-border/60 bg-muted/20 p-3 text-xs">
                            <div className="flex flex-wrap items-center gap-4 font-medium text-foreground">
                                <span className="font-bold">Keterangan:</span>
                                <div className="flex items-center gap-2">
                                    <span className="size-3.5 shrink-0 border border-black/80 bg-[#00b0f0] shadow-xs" />
                                    <span className="text-[11px] font-semibold text-foreground">
                                        : Hari Piket
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="size-3.5 shrink-0 rounded-xs bg-red-600" />
                                    <span className="text-[11px] font-semibold text-foreground">
                                        : Off / Libur
                                    </span>
                                </div>
                            </div>
                            <div className="text-[11px] text-muted-foreground italic">
                                * Note: Jika personel berhalangan, harap
                                digantikan dengan personel lain
                            </div>
                        </div>
                    </div>

                    {/* Schedule Table */}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="print-table w-full border-collapse text-center text-xs">
                            <thead>
                                <tr className="border-b border-border bg-muted/30">
                                    <th
                                        style={{ minWidth: '180px' }}
                                        className="border-r border-border p-2.5 text-left font-bold text-foreground"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <Users className="size-3.5 text-muted-foreground" />
                                            NAMA
                                        </div>
                                    </th>
                                    <th
                                        style={{ minWidth: '120px' }}
                                        className="border-r border-border p-2 font-bold text-foreground"
                                    >
                                        RENCANA / REALISASI
                                    </th>
                                    {days.map((day) => (
                                        <th
                                            key={day.day}
                                            style={{
                                                minWidth: '30px',
                                                width: '30px',
                                            }}
                                            className={`border-r border-border p-1 text-center font-bold ${
                                                day.is_red
                                                    ? 'print-red-cell bg-red-600 text-white dark:bg-red-700'
                                                    : 'bg-muted/40 text-foreground'
                                            }`}
                                            title={
                                                day.holiday ??
                                                (day.is_weekend
                                                    ? 'Akhir Pekan'
                                                    : undefined)
                                            }
                                        >
                                            <div className="text-[11px] leading-tight">
                                                {day.day}
                                            </div>
                                            <div className="text-[9px] font-normal uppercase opacity-85">
                                                {day.dow}
                                            </div>
                                        </th>
                                    ))}
                                    <th
                                        style={{ minWidth: '70px' }}
                                        className="border-r border-border bg-muted/40 p-2 font-bold text-foreground"
                                    >
                                        RENCANA
                                    </th>
                                    <th
                                        style={{ minWidth: '70px' }}
                                        className="border-r border-border bg-muted/40 p-2 font-bold text-foreground"
                                    >
                                        REALISASI
                                    </th>
                                    <th
                                        style={{ minWidth: '65px' }}
                                        className="border-r border-border bg-muted/40 p-2 font-bold text-foreground"
                                    >
                                        TARGET
                                    </th>
                                    <th
                                        style={{ minWidth: '95px' }}
                                        className="bg-muted/40 p-2 font-bold text-foreground"
                                    >
                                        ANALISA KINERJA
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={2 + days.length + 4}
                                            className="p-8 text-center text-sm text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <ShieldCheck className="size-8 text-muted-foreground/50" />
                                                <p className="font-semibold text-foreground">
                                                    Tidak ada data operator
                                                    untuk unit ini.
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Data nama diambil dari
                                                    personil berposisi Operator
                                                    pada unit yang bersangkutan
                                                    (tidak termasuk Manager UL,
                                                    Staf, dan Seluruh Team
                                                    Leader).
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, rIndex) => (
                                        <Fragment key={row.employee_id}>
                                            {/* Subrow 1: Rencana */}
                                            <tr className="border-b border-border transition-colors hover:bg-muted/10">
                                                <td
                                                    rowSpan={2}
                                                    className="border-r border-border bg-background p-2 text-left align-middle"
                                                >
                                                    <div className="font-semibold text-foreground uppercase">
                                                        {row.name}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground">
                                                        {row.nip && (
                                                            <span>
                                                                NIP. {row.nip}
                                                            </span>
                                                        )}
                                                        {row.regu && (
                                                            <Badge
                                                                variant="outline"
                                                                className="h-4 px-1 text-[9px] font-normal"
                                                            >
                                                                Regu {row.regu}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="border-r border-border bg-muted/10 p-1.5 font-semibold text-muted-foreground">
                                                    RENCANA
                                                </td>

                                                {/* Day cells for Rencana */}
                                                {days.map((day) => {
                                                    const dKey = String(
                                                        day.day,
                                                    );
                                                    const val =
                                                        row.rencana[dKey];
                                                    const isPiket =
                                                        String(val ?? '') ===
                                                        '1';

                                                    if (day.is_red) {
                                                        return (
                                                            <td
                                                                key={day.day}
                                                                className="print-red-cell border-r border-border bg-red-600 dark:bg-red-700"
                                                            />
                                                        );
                                                    }

                                                    return (
                                                        <td
                                                            key={day.day}
                                                            onClick={() =>
                                                                toggleCell(
                                                                    row.employee_id,
                                                                    'rencana',
                                                                    day.day,
                                                                )
                                                            }
                                                            className={`border-r border-border p-1 text-center font-bold transition-colors select-none ${
                                                                isPiket
                                                                    ? 'print-piket-cell bg-[#00b0f0] text-black'
                                                                    : 'bg-background hover:bg-sky-50 dark:hover:bg-sky-950/20'
                                                            } ${can_write ? 'cursor-pointer' : 'cursor-default'}`}
                                                            title={
                                                                can_write
                                                                    ? isPiket
                                                                        ? 'Klik untuk membatalkan piket'
                                                                        : 'Klik untuk menetapkan piket rencana'
                                                                    : undefined
                                                            }
                                                        >
                                                            {isPiket ? '1' : ''}
                                                        </td>
                                                    );
                                                })}

                                                {/* Summary Columns: Only rendered on the first row with full table rowspan */}
                                                {rIndex === 0 && (
                                                    <>
                                                        <td
                                                            rowSpan={
                                                                rows.length * 2
                                                            }
                                                            className="border-r border-border bg-background p-2 text-center align-middle text-sm font-extrabold text-foreground"
                                                        >
                                                            {totalRencana}
                                                        </td>
                                                        <td
                                                            rowSpan={
                                                                rows.length * 2
                                                            }
                                                            className="border-r border-border bg-background p-2 text-center align-middle text-sm font-extrabold text-foreground"
                                                        >
                                                            {totalRealisasi}
                                                        </td>
                                                        <td
                                                            rowSpan={
                                                                rows.length * 2
                                                            }
                                                            className="border-r border-border bg-background p-2 text-center align-middle text-sm font-extrabold text-foreground"
                                                        >
                                                            {
                                                                target_working_days
                                                            }
                                                        </td>
                                                        <td
                                                            rowSpan={
                                                                rows.length * 2
                                                            }
                                                            className="bg-background p-2 text-center align-middle text-base font-black"
                                                        >
                                                            <span
                                                                className={
                                                                    performance >=
                                                                    100
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : performance >=
                                                                            80
                                                                          ? 'text-sky-600 dark:text-sky-400'
                                                                          : 'text-amber-600 dark:text-amber-400'
                                                                }
                                                            >
                                                                {performance}%
                                                            </span>
                                                        </td>
                                                    </>
                                                )}
                                            </tr>

                                            {/* Subrow 2: Realisasi */}
                                            <tr className="border-b-2 border-border transition-colors hover:bg-muted/10">
                                                <td className="border-r border-border bg-muted/10 p-1.5 font-semibold text-muted-foreground">
                                                    REALISASI
                                                </td>

                                                {/* Day cells for Realisasi */}
                                                {days.map((day) => {
                                                    const dKey = String(
                                                        day.day,
                                                    );
                                                    const val =
                                                        row.realisasi[dKey];
                                                    const isPiket =
                                                        String(val ?? '') ===
                                                        '1';

                                                    if (day.is_red) {
                                                        return (
                                                            <td
                                                                key={day.day}
                                                                className="print-red-cell border-r border-border bg-red-600 dark:bg-red-700"
                                                            />
                                                        );
                                                    }

                                                    return (
                                                        <td
                                                            key={day.day}
                                                            onClick={() =>
                                                                toggleCell(
                                                                    row.employee_id,
                                                                    'realisasi',
                                                                    day.day,
                                                                )
                                                            }
                                                            className={`border-r border-border p-1 text-center font-bold transition-colors select-none ${
                                                                isPiket
                                                                    ? 'print-piket-cell bg-[#00b0f0] text-black'
                                                                    : 'bg-background hover:bg-sky-50 dark:hover:bg-sky-950/20'
                                                            } ${can_write ? 'cursor-pointer' : 'cursor-default'}`}
                                                            title={
                                                                can_write
                                                                    ? isPiket
                                                                        ? 'Klik untuk membatalkan piket'
                                                                        : 'Klik untuk menetapkan piket realisasi'
                                                                    : undefined
                                                            }
                                                        >
                                                            {isPiket ? '1' : ''}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        </Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer Legend matching user's image */}
                    <div className="mt-2 space-y-1 text-xs">
                        <div className="flex items-start gap-4">
                            <span className="w-24 font-bold text-foreground">
                                Keterangan:
                            </span>
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-3.5 w-6 rounded border border-border bg-[#00b0f0]" />
                                    <span className="font-medium text-foreground">
                                        : Hari Piket
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-3.5 w-6 rounded border border-border bg-red-600" />
                                    <span className="font-medium text-foreground">
                                        : Off/Libur
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="flex items-start gap-4 pt-1">
                            <span className="w-24 font-bold text-foreground italic">
                                Note:
                            </span>
                            <p className="text-muted-foreground italic">
                                Jika personel berhalangan, harap digantikan
                                dengan personel lain
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

HarJadwalPatrolCheckPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Pemeliharaan', href: jadwal.index() },
        { title: 'Patrol Check Harian', href: patrolCheck.index() },
    ],
};
