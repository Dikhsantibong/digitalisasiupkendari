import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    FileSpreadsheet,
    Info,
    Palette,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    X,
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import jadwal from '@/routes/har/jadwal';
import p0P5 from '@/routes/har/jadwal/p0-p5';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type CellColor = 'yellow' | 'green' | '';

type MachineScheduleRow = {
    machine_id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
    capacity_kw: string | null;
    rencana: Record<string, string>;
    realisasi: Record<string, string>;
    durasi: Record<string, string | number>;
    warna?: {
        rencana?: Record<string, CellColor>;
        realisasi?: Record<string, CellColor>;
    };
    operating_hours: string;
    keterangan: string;
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
    rows: MachineScheduleRow[];
    can_write: boolean;
};

const CYCLE_OPTIONS = ['P0', 'P1', 'P2', 'P3', 'P4', 'P5'];

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
        padding: 2px 1px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-red-cell {
        background-color: #dc2626 !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-yellow-cell {
        background-color: #ffff00 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-green-cell {
        background-color: #92d050 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function HarJadwalP0P5Page({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<MachineScheduleRow[]>(initialRows);
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

    const serviceUnitLabel = useMemo(() => {
        if (unit.service_unit_name) {
            return unit.service_unit_name.toUpperCase();
        }
        return unit.name.toUpperCase();
    }, [unit]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            p0P5.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateCell = (
        machineId: number,
        category: 'rencana' | 'realisasi' | 'durasi',
        day: number,
        value: string,
        explicitColor?: CellColor,
    ) => {
        setRows((prev) =>
            prev.map((r) => {
                if (r.machine_id !== machineId) return r;
                const nextCat = { ...r[category] };
                const trimmed = value.trim().toUpperCase();
                const dayKey = String(day);

                if (trimmed === '') {
                    delete nextCat[dayKey];
                } else {
                    nextCat[dayKey] = trimmed;
                }

                // Manage color
                const nextWarna = {
                    rencana: { ...(r.warna?.rencana ?? {}) },
                    realisasi: { ...(r.warna?.realisasi ?? {}) },
                };

                if (category === 'rencana' || category === 'realisasi') {
                    if (trimmed === '') {
                        delete nextWarna[category][dayKey];
                    } else if (explicitColor !== undefined) {
                        if (explicitColor === '') {
                            delete nextWarna[category][dayKey];
                        } else {
                            nextWarna[category][dayKey] = explicitColor;
                        }
                    } else {
                        // Auto-assign color if none explicitly set yet
                        if (!nextWarna[category][dayKey]) {
                            if (trimmed === 'P2') {
                                nextWarna[category][dayKey] = 'yellow';
                            } else if (trimmed === 'P3') {
                                nextWarna[category][dayKey] = 'green';
                            } else if (category === 'realisasi') {
                                nextWarna[category][dayKey] = 'yellow';
                            }
                        }
                    }
                }

                return {
                    ...r,
                    [category]: nextCat,
                    warna: nextWarna,
                };
            }),
        );
        setDirty(true);
    };

    const setCellColor = (
        machineId: number,
        category: 'rencana' | 'realisasi',
        day: number,
        color: CellColor,
    ) => {
        setRows((prev) =>
            prev.map((r) => {
                if (r.machine_id !== machineId) return r;
                const dayKey = String(day);
                const nextWarna = {
                    rencana: { ...(r.warna?.rencana ?? {}) },
                    realisasi: { ...(r.warna?.realisasi ?? {}) },
                };

                if (color === '') {
                    delete nextWarna[category][dayKey];
                } else {
                    nextWarna[category][dayKey] = color;
                }

                return {
                    ...r,
                    warna: nextWarna,
                };
            }),
        );
        setDirty(true);
    };

    const updateTextField = (
        machineId: number,
        field: 'operating_hours' | 'keterangan',
        value: string,
    ) => {
        setRows((prev) =>
            prev.map((r) => {
                if (r.machine_id !== machineId) return r;
                return { ...r, [field]: value };
            }),
        );
        setDirty(true);
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            p0P5.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    machine_id: r.machine_id,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    durasi: r.durasi,
                    warna: r.warna,
                    operating_hours: r.operating_hours,
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

    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    const handlePrint = () => {
        window.print();
    };

    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [
            [],
            [],
            [],
            [],
            [
                'KET: P0=50 JAM, P1=125 JAM, P2=250 JAM, P3=500 JAM, P4=1.500 JAM, P5=3.000 JAM',
            ],
            [
                'KETERANGAN WARNA: [KUNING] Ganti Pelumas, [HIJAU] Ganti Pelumas+Cleaning Radiator',
            ],
        ];

        // Table Header
        const tableHeader1: (string | number)[] = [
            'NO',
            'MESIN / TIPE / S.N',
            monthName.toUpperCase(),
        ];
        days.forEach((d) => tableHeader1.push(d.day));
        tableHeader1.push('JAM OPERASI PEMELIHARAAN');
        tableHeader1.push('KETERANGAN');
        headerRows.push(tableHeader1);

        // Data Rows
        rows.forEach((r, idx) => {
            const machineLabel = `${r.name}${r.type ? ' ' + r.type : ''}${r.serial_number ? ' SN. ' + r.serial_number : ''}`;

            // RENC row
            const rencRow: (string | number)[] = [
                idx + 1,
                machineLabel,
                'RENC',
            ];
            days.forEach((d) => rencRow.push(r.rencana[String(d.day)] ?? ''));
            rencRow.push(r.operating_hours || '');
            rencRow.push(r.keterangan || '');
            headerRows.push(rencRow);

            // REAL row
            const realRow: (string | number)[] = ['', '', 'REAL'];
            days.forEach((d) => realRow.push(r.realisasi[String(d.day)] ?? ''));
            realRow.push('');
            realRow.push('');
            headerRows.push(realRow);

            // DURASI row
            const durasiRow: (string | number)[] = ['', 'WAKTU', 'DURASI'];
            days.forEach((d) => durasiRow.push(r.durasi[String(d.day)] ?? ''));
            durasiRow.push('');
            durasiRow.push('');
            headerRows.push(durasiRow);
        });

        const { workbook, worksheet: ws } = createSheet(
            'Jadwal_P0_P5',
            headerRows,
        );

        const colLabel = 2; // RENC / REAL / DURASI
        const dayStart = 3;
        const dayEnd = dayStart + days.length - 1;
        const colOps = dayStart + days.length;
        const totalCols = colOps + 2;
        const headerRowIdx = 6;
        const dataStart = 7;

        const yellowCell: StyleSpec = {
            fill: XLSX_COLORS.yellow,
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };
        const greenCell: StyleSpec = {
            fill: XLSX_COLORS.green,
            bold: true,
            color: XLSX_COLORS.black,
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

        const fillForDay = (
            category: 'rencana' | 'realisasi' | 'durasi',
            value: string,
            color: string | undefined,
            isRed: boolean,
        ): StyleSpec => {
            if (color === 'yellow') {
                return yellowCell;
            }
            if (color === 'green') {
                return greenCell;
            }
            if (value === 'P2') {
                return yellowCell;
            }
            if (value === 'P3') {
                return greenCell;
            }
            if (category === 'realisasi' && value.trim() !== '') {
                return yellowCell;
            }
            if (value.trim() !== '') {
                return { ...SPECS.cell, bold: true };
            }
            if (isRed) {
                return redSoftCell;
            }
            return SPECS.cell;
        };

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }
            if (r === 4 || r === 5) {
                // Legend lines (KET siklus + keterangan warna)
                return c === 0
                    ? {
                          bold: r === 4,
                          italic: r === 5,
                          size: 9,
                          align: 'left',
                          valign: 'center',
                      }
                    : null;
            }

            if (r === headerRowIdx) {
                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    return day?.is_red
                        ? {
                              fill: XLSX_COLORS.red600,
                              bold: true,
                              color: XLSX_COLORS.white,
                              align: 'center',
                              valign: 'center',
                              border: true,
                          }
                        : SPECS.headerGrey;
                }
                return SPECS.headerGrey;
            }

            if (r >= dataStart) {
                const sub = (r - dataStart) % 3; // 0 RENC, 1 REAL, 2 DURASI
                const machine = rows[Math.floor((r - dataStart) / 3)];

                if (c === 0) {
                    return SPECS.cell;
                }
                if (c === 1) {
                    return sub === 2 ? SPECS.label : SPECS.cellLeft;
                }
                if (c === colLabel) {
                    return SPECS.labelCenter;
                }
                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    const key = String(day.day);
                    const value = String(headerRows[r]?.[c] ?? '');
                    const category =
                        sub === 0
                            ? 'rencana'
                            : sub === 1
                              ? 'realisasi'
                              : 'durasi';
                    const color =
                        sub === 0
                            ? machine?.warna?.rencana?.[key]
                            : sub === 1
                              ? machine?.warna?.realisasi?.[key]
                              : undefined;
                    return fillForDay(
                        category,
                        value,
                        color,
                        Boolean(day?.is_red),
                    );
                }
                if (c === colOps) {
                    return SPECS.cell;
                }
                return SPECS.cellLeft;
            }

            return null;
        });

        mergeCells(ws, 4, 0, 4, totalCols - 1); // KET siklus
        mergeCells(ws, 5, 0, 5, totalCols - 1); // KETERANGAN WARNA
        for (let m = 0; m < rows.length; m++) {
            const top = dataStart + m * 3;
            mergeCells(ws, top, 0, top + 2, 0); // NO column
        }

        const colWidths = [5, 26, 8, ...Array(days.length).fill(3.5), 10, 22];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    `PLN NP UP KENDARI - ${unit.name.toUpperCase()}`,
                    'LAPORAN PROJECT',
                    `MKP: ${serviceUnitLabel} - ${monthName.toUpperCase()} ${filters.year}`,
                ],
                barTitle: 'JADWAL KEGIATAN PEMELIHARAAN P0 - P5',
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_P0_P5_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    // Helper to resolve cell styling based on value and warna map
    const getCellColorClass = (
        category: 'rencana' | 'realisasi',
        val: string,
        customColor: CellColor | undefined,
        isRedDay: boolean,
    ) => {
        if (customColor === 'yellow') {
            return 'bg-[#ffff00] text-black font-extrabold print-yellow-cell';
        }
        if (customColor === 'green') {
            return 'bg-[#92d050] text-black font-extrabold print-green-cell';
        }

        // Automatic fallback
        if (val === 'P2') {
            return 'bg-[#ffff00] text-black font-extrabold print-yellow-cell';
        }
        if (val === 'P3') {
            return 'bg-[#92d050] text-black font-extrabold print-green-cell';
        }

        if (category === 'realisasi' && val.trim() !== '') {
            return 'bg-[#ffff00] text-black font-extrabold print-yellow-cell';
        }

        if (val.trim() !== '') {
            return 'font-bold text-foreground';
        }

        if (isRedDay) {
            return 'bg-red-50 dark:bg-red-950/20 text-foreground print-red-cell';
        }

        return 'text-foreground';
    };

    return (
        <>
            <Head title={`Jadwal Pemeliharaan P0 - P5 — ${unit.name}`} />
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
                                    Jadwal Pemeliharaan P0 - P5
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Matriks rencana & realisasi pemeliharaan berkala
                                P0 s/d P5 sesuai jam operasi.
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
                            href={`${p0P5.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
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
                                    JADWAL KEGIATAN PEMELIHARAAN P0 - P5
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

                        {/* Legend / Keterangan Box (Includes Cycles & Colors matching User Request) */}
                        <div className="mt-3 grid grid-cols-1 gap-3 rounded border border-border/60 bg-muted/20 p-3 text-xs md:grid-cols-4">
                            <div className="font-bold text-foreground">
                                KET :
                            </div>
                            <div className="space-y-1 font-medium text-foreground">
                                <div>P0 = 50 JAM</div>
                                <div>P1 = 125 JAM</div>
                                <div>P2 = 250 JAM</div>
                            </div>
                            <div className="space-y-1 font-medium text-foreground">
                                <div>P3 = 500 JAM</div>
                                <div>P4 = 1.500 JAM</div>
                                <div>P5 = 3.000 JAM</div>
                            </div>

                            {/* Color Legend (Matches uploaded media_1789397561905.png) */}
                            <div className="space-y-1.5 font-medium text-foreground">
                                <div className="flex items-center gap-2">
                                    <span className="size-4 shrink-0 border border-black/80 bg-[#ffff00] shadow-xs" />
                                    <span className="text-[11px] font-semibold text-foreground">
                                        Ganti Pelumas
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="size-4 shrink-0 border border-black/80 bg-[#92d050] shadow-xs" />
                                    <span className="text-[11px] font-semibold text-foreground">
                                        Ganti Pelumas+Cleaning Radiator
                                    </span>
                                </div>
                                <div className="flex items-center gap-2 pt-0.5 text-[10px] text-muted-foreground">
                                    <span className="size-3 shrink-0 rounded-xs bg-red-600" />
                                    <span>Sabtu, Minggu & Libur Nasional</span>
                                </div>
                            </div>
                        </div>

                        {/* Info Row */}
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-foreground">
                            <div>MKP : {serviceUnitLabel}</div>
                            <div>
                                BULAN : {monthName.toUpperCase()} {filters.year}
                            </div>
                        </div>
                    </div>

                    {/* Matrix Table */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-[11px]">
                            <thead>
                                <tr className="border-b border-border bg-muted/40 font-bold text-foreground">
                                    <th
                                        rowSpan={2}
                                        className="w-10 border border-border px-1.5 py-2 text-center"
                                    >
                                        NO
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="min-w-44 border border-border px-2 py-2 text-left"
                                    >
                                        MESIN / TIPE / S.N
                                    </th>
                                    <th className="w-16 border border-border px-1 py-1.5 text-center uppercase">
                                        {monthName}
                                    </th>
                                    <th
                                        colSpan={days.length}
                                        className="border border-border px-1 py-1.5 text-center font-bold tracking-wider uppercase"
                                    >
                                        JENIS HAR
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="min-w-36 border border-border px-2 py-2 text-center"
                                    >
                                        JAM OPERASI PEMELIHARAAN
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="min-w-36 border border-border px-2 py-2 text-center"
                                    >
                                        KETERANGAN
                                    </th>
                                </tr>
                                <tr className="border-b border-border font-bold">
                                    <th className="border border-border bg-muted/60 px-1 py-1 text-center">
                                        {filters.year}
                                    </th>
                                    {days.map((d) => (
                                        <th
                                            key={`th-day-${d.day}`}
                                            className={`w-7 border border-border px-0.5 py-1 text-center text-[10px] ${
                                                d.is_red
                                                    ? 'print-red-cell bg-red-600 font-bold text-white'
                                                    : 'bg-muted/50 text-foreground'
                                            }`}
                                            title={
                                                d.holiday ??
                                                (d.is_weekend
                                                    ? 'Akhir Pekan'
                                                    : undefined)
                                            }
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={days.length + 5}
                                            className="border border-border p-8 text-center text-xs text-muted-foreground"
                                        >
                                            Belum ada mesin aktif pada unit{' '}
                                            {unit.name}.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, idx) => (
                                        <Fragment
                                            key={`machine-${row.machine_id}`}
                                        >
                                            {/* Sub-row 1: RENC */}
                                            <tr className="hover:bg-muted/5">
                                                {/* Cell 1: NO */}
                                                <td
                                                    rowSpan={3}
                                                    className="border border-border bg-card px-1 py-1.5 text-center font-bold text-foreground"
                                                >
                                                    {idx + 1}
                                                </td>

                                                {/* Cell 2: MESIN / TIPE / S.N */}
                                                <td
                                                    rowSpan={2}
                                                    className="border border-border bg-card px-2 py-1.5 text-left align-top"
                                                >
                                                    <div className="font-bold text-foreground">
                                                        {row.name}
                                                    </div>
                                                    {row.type && (
                                                        <div className="text-[10px] text-muted-foreground">
                                                            {row.type}
                                                        </div>
                                                    )}
                                                    {row.serial_number && (
                                                        <div className="font-mono text-[10px] text-muted-foreground">
                                                            SN.{' '}
                                                            {row.serial_number}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Sub Col: RENC */}
                                                <td className="border border-border bg-muted/20 px-1 py-1 text-center text-[10px] font-bold text-foreground">
                                                    RENC
                                                </td>

                                                {/* Day Cells: RENCANA */}
                                                {days.map((d) => {
                                                    const val =
                                                        row.rencana[
                                                            String(d.day)
                                                        ] ?? '';
                                                    const customColor =
                                                        row.warna?.rencana?.[
                                                            String(d.day)
                                                        ];
                                                    const colorClass =
                                                        getCellColorClass(
                                                            'rencana',
                                                            val,
                                                            customColor,
                                                            d.is_red,
                                                        );

                                                    return (
                                                        <td
                                                            key={`renc-${row.machine_id}-${d.day}`}
                                                            className={`group/cell relative border border-border p-0 text-center font-bold ${colorClass}`}
                                                        >
                                                            {can_write ? (
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        asChild
                                                                    >
                                                                        <div className="flex h-7 w-full cursor-pointer items-center justify-center select-none hover:opacity-85">
                                                                            {
                                                                                val
                                                                            }
                                                                        </div>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent
                                                                        align="center"
                                                                        side="top"
                                                                        className="no-print w-60 p-2.5 shadow-lg"
                                                                    >
                                                                        <div className="space-y-2 text-xs">
                                                                            <div className="font-semibold text-foreground">
                                                                                Tgl{' '}
                                                                                {
                                                                                    d.day
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    monthName
                                                                                }

                                                                                )
                                                                                —
                                                                                Rencana
                                                                            </div>

                                                                            {/* Cycle Quick Picks */}
                                                                            <div>
                                                                                <div className="mb-1 text-[10px] font-medium text-muted-foreground">
                                                                                    Pilih
                                                                                    Siklus
                                                                                    Pemeliharaan:
                                                                                </div>
                                                                                <div className="grid grid-cols-3 gap-1">
                                                                                    {CYCLE_OPTIONS.map(
                                                                                        (
                                                                                            opt,
                                                                                        ) => (
                                                                                            <Button
                                                                                                key={
                                                                                                    opt
                                                                                                }
                                                                                                size="sm"
                                                                                                variant={
                                                                                                    val ===
                                                                                                    opt
                                                                                                        ? 'default'
                                                                                                        : 'outline'
                                                                                                }
                                                                                                className="h-6 text-[11px] font-bold"
                                                                                                onClick={() =>
                                                                                                    updateCell(
                                                                                                        row.machine_id,
                                                                                                        'rencana',
                                                                                                        d.day,
                                                                                                        opt,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    opt
                                                                                                }
                                                                                            </Button>
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            </div>

                                                                            {/* Color Assignment */}
                                                                            <div className="border-t border-border pt-2">
                                                                                <div className="mb-1 text-[10px] font-medium text-muted-foreground">
                                                                                    Warna
                                                                                    &
                                                                                    Jenis
                                                                                    Kegiatan:
                                                                                </div>
                                                                                <div className="space-y-1">
                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs font-semibold hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'rencana',
                                                                                                d.day,
                                                                                                'yellow',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 border border-black/80 bg-[#ffff00]" />
                                                                                        <span>
                                                                                            Ganti
                                                                                            Pelumas
                                                                                        </span>
                                                                                        {(customColor ===
                                                                                            'yellow' ||
                                                                                            (!customColor &&
                                                                                                val ===
                                                                                                    'P2')) && (
                                                                                            <Check className="ml-auto size-3 text-primary" />
                                                                                        )}
                                                                                    </button>

                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs font-semibold hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'rencana',
                                                                                                d.day,
                                                                                                'green',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 border border-black/80 bg-[#92d050]" />
                                                                                        <span>
                                                                                            Ganti
                                                                                            Pelumas+Cleaning
                                                                                            Rad
                                                                                        </span>
                                                                                        {(customColor ===
                                                                                            'green' ||
                                                                                            (!customColor &&
                                                                                                val ===
                                                                                                    'P3')) && (
                                                                                            <Check className="ml-auto size-3 text-primary" />
                                                                                        )}
                                                                                    </button>

                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs text-muted-foreground hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'rencana',
                                                                                                d.day,
                                                                                                '',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 rounded-xs border border-border bg-card" />
                                                                                        <span>
                                                                                            Tanpa
                                                                                            Warna
                                                                                        </span>
                                                                                    </button>
                                                                                </div>
                                                                            </div>

                                                                            {/* Clear */}
                                                                            {val && (
                                                                                <div className="border-t border-border pt-1.5">
                                                                                    <Button
                                                                                        size="sm"
                                                                                        variant="ghost"
                                                                                        className="h-6 w-full gap-1 text-[11px] text-destructive hover:bg-destructive/10"
                                                                                        onClick={() =>
                                                                                            updateCell(
                                                                                                row.machine_id,
                                                                                                'rencana',
                                                                                                d.day,
                                                                                                '',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <Trash2 className="size-3" />
                                                                                        Hapus
                                                                                        Nilai
                                                                                    </Button>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            ) : (
                                                                <span className="block py-1">
                                                                    {val}
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}

                                                {/* JAM OPERASI PEMELIHARAAN (Rowspan 3) */}
                                                <td
                                                    rowSpan={3}
                                                    className="border border-border bg-card p-1 text-left align-top"
                                                >
                                                    {can_write ? (
                                                        <textarea
                                                            value={
                                                                row.operating_hours
                                                            }
                                                            onChange={(e) =>
                                                                updateTextField(
                                                                    row.machine_id,
                                                                    'operating_hours',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={4}
                                                            placeholder="P3: 1000 JAM&#10;P2: 1250 JAM"
                                                            className="w-full resize-none rounded-xs border border-transparent bg-transparent p-1 text-[11px] leading-relaxed font-medium text-foreground transition-colors hover:border-border focus:border-primary focus:bg-background focus:outline-none"
                                                        />
                                                    ) : (
                                                        <div className="p-1 text-[11px] leading-relaxed whitespace-pre-line text-foreground">
                                                            {row.operating_hours ||
                                                                '-'}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* KETERANGAN (Rowspan 3) */}
                                                <td
                                                    rowSpan={3}
                                                    className="border border-border bg-card p-1 text-left align-top"
                                                >
                                                    {can_write ? (
                                                        <textarea
                                                            value={
                                                                row.keterangan
                                                            }
                                                            onChange={(e) =>
                                                                updateTextField(
                                                                    row.machine_id,
                                                                    'keterangan',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={4}
                                                            placeholder="Catatan kegiatan..."
                                                            className="w-full resize-none rounded-xs border border-transparent bg-transparent p-1 text-[11px] leading-relaxed text-foreground transition-colors hover:border-border focus:border-primary focus:bg-background focus:outline-none"
                                                        />
                                                    ) : (
                                                        <div className="p-1 text-[11px] leading-relaxed whitespace-pre-line text-foreground">
                                                            {row.keterangan ||
                                                                '-'}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>

                                            {/* Sub-row 2: REAL */}
                                            <tr className="hover:bg-muted/5">
                                                <td className="border border-border bg-muted/20 px-1 py-1 text-center text-[10px] font-bold text-foreground">
                                                    REAL
                                                </td>
                                                {days.map((d) => {
                                                    const val =
                                                        row.realisasi[
                                                            String(d.day)
                                                        ] ?? '';
                                                    const customColor =
                                                        row.warna?.realisasi?.[
                                                            String(d.day)
                                                        ];
                                                    const colorClass =
                                                        getCellColorClass(
                                                            'realisasi',
                                                            val,
                                                            customColor,
                                                            d.is_red,
                                                        );

                                                    return (
                                                        <td
                                                            key={`real-${row.machine_id}-${d.day}`}
                                                            className={`group/cell relative border border-border p-0 text-center font-bold ${colorClass}`}
                                                        >
                                                            {can_write ? (
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        asChild
                                                                    >
                                                                        <div className="flex h-7 w-full cursor-pointer items-center justify-center select-none hover:opacity-85">
                                                                            {
                                                                                val
                                                                            }
                                                                        </div>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent
                                                                        align="center"
                                                                        side="top"
                                                                        className="no-print w-60 p-2.5 shadow-lg"
                                                                    >
                                                                        <div className="space-y-2 text-xs">
                                                                            <div className="font-semibold text-foreground">
                                                                                Tgl{' '}
                                                                                {
                                                                                    d.day
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    monthName
                                                                                }

                                                                                )
                                                                                —
                                                                                Realisasi
                                                                            </div>

                                                                            {/* Cycle Quick Picks */}
                                                                            <div>
                                                                                <div className="mb-1 text-[10px] font-medium text-muted-foreground">
                                                                                    Pilih
                                                                                    Siklus
                                                                                    Pemeliharaan:
                                                                                </div>
                                                                                <div className="grid grid-cols-3 gap-1">
                                                                                    {CYCLE_OPTIONS.map(
                                                                                        (
                                                                                            opt,
                                                                                        ) => (
                                                                                            <Button
                                                                                                key={
                                                                                                    opt
                                                                                                }
                                                                                                size="sm"
                                                                                                variant={
                                                                                                    val ===
                                                                                                    opt
                                                                                                        ? 'default'
                                                                                                        : 'outline'
                                                                                                }
                                                                                                className="h-6 text-[11px] font-bold"
                                                                                                onClick={() =>
                                                                                                    updateCell(
                                                                                                        row.machine_id,
                                                                                                        'realisasi',
                                                                                                        d.day,
                                                                                                        opt,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    opt
                                                                                                }
                                                                                            </Button>
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            </div>

                                                                            {/* Color Assignment */}
                                                                            <div className="border-t border-border pt-2">
                                                                                <div className="mb-1 text-[10px] font-medium text-muted-foreground">
                                                                                    Warna
                                                                                    &
                                                                                    Jenis
                                                                                    Kegiatan:
                                                                                </div>
                                                                                <div className="space-y-1">
                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs font-semibold hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'realisasi',
                                                                                                d.day,
                                                                                                'yellow',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 border border-black/80 bg-[#ffff00]" />
                                                                                        <span>
                                                                                            Ganti
                                                                                            Pelumas
                                                                                        </span>
                                                                                        {(customColor ===
                                                                                            'yellow' ||
                                                                                            (!customColor &&
                                                                                                (val ===
                                                                                                    'P2' ||
                                                                                                    (val &&
                                                                                                        val !==
                                                                                                            'P3')))) && (
                                                                                            <Check className="ml-auto size-3 text-primary" />
                                                                                        )}
                                                                                    </button>

                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs font-semibold hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'realisasi',
                                                                                                d.day,
                                                                                                'green',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 border border-black/80 bg-[#92d050]" />
                                                                                        <span>
                                                                                            Ganti
                                                                                            Pelumas+Cleaning
                                                                                            Rad
                                                                                        </span>
                                                                                        {(customColor ===
                                                                                            'green' ||
                                                                                            (!customColor &&
                                                                                                val ===
                                                                                                    'P3')) && (
                                                                                            <Check className="ml-auto size-3 text-primary" />
                                                                                        )}
                                                                                    </button>

                                                                                    <button
                                                                                        type="button"
                                                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs text-muted-foreground hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            setCellColor(
                                                                                                row.machine_id,
                                                                                                'realisasi',
                                                                                                d.day,
                                                                                                '',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <span className="size-3.5 shrink-0 rounded-xs border border-border bg-card" />
                                                                                        <span>
                                                                                            Tanpa
                                                                                            Warna
                                                                                        </span>
                                                                                    </button>
                                                                                </div>
                                                                            </div>

                                                                            {/* Clear */}
                                                                            {val && (
                                                                                <div className="border-t border-border pt-1.5">
                                                                                    <Button
                                                                                        size="sm"
                                                                                        variant="ghost"
                                                                                        className="h-6 w-full gap-1 text-[11px] text-destructive hover:bg-destructive/10"
                                                                                        onClick={() =>
                                                                                            updateCell(
                                                                                                row.machine_id,
                                                                                                'realisasi',
                                                                                                d.day,
                                                                                                '',
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <Trash2 className="size-3" />
                                                                                        Hapus
                                                                                        Nilai
                                                                                    </Button>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            ) : (
                                                                <span className="block py-1">
                                                                    {val}
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>

                                            {/* Sub-row 3: DURASI */}
                                            <tr className="hover:bg-muted/5">
                                                <td className="border border-border bg-muted/20 px-1 py-1 text-center text-[10px] font-bold text-foreground">
                                                    WAKTU
                                                </td>
                                                <td className="border border-border bg-muted/20 px-1 py-1 text-center text-[10px] font-bold text-foreground">
                                                    DURASI
                                                </td>
                                                {days.map((d) => {
                                                    const val =
                                                        row.durasi[
                                                            String(d.day)
                                                        ] ?? '';
                                                    return (
                                                        <td
                                                            key={`durasi-${row.machine_id}-${d.day}`}
                                                            className={`border border-border p-0 text-center text-[10px] ${
                                                                d.is_red
                                                                    ? 'print-red-cell bg-red-50 text-foreground dark:bg-red-950/20'
                                                                    : 'text-foreground'
                                                            }`}
                                                        >
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={val}
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateCell(
                                                                            row.machine_id,
                                                                            'durasi',
                                                                            d.day,
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder=""
                                                                    maxLength={
                                                                        4
                                                                    }
                                                                    className="h-7 w-full bg-transparent p-0 text-center text-[10px] font-medium text-foreground transition-colors hover:bg-muted/40 focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span className="block py-1">
                                                                    {val}
                                                                </span>
                                                            )}
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
                </div>

                {/* Helpful Instruction Note */}
                <div className="no-print flex items-start gap-2 rounded-md border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
                    <Info className="mt-0.5 size-4 shrink-0 text-primary" />
                    <div>
                        <span className="font-semibold text-foreground">
                            Petunjuk Pengisian:
                        </span>
                        <ul className="mt-1 list-inside list-disc space-y-0.5">
                            <li>
                                Klik pada kotak tanggal{' '}
                                <span className="font-semibold text-foreground">
                                    RENC
                                </span>{' '}
                                atau{' '}
                                <span className="font-semibold text-foreground">
                                    REAL
                                </span>{' '}
                                untuk memilih siklus (
                                <code className="font-mono font-bold text-primary">
                                    P0
                                </code>{' '}
                                s/d{' '}
                                <code className="font-mono font-bold text-primary">
                                    P5
                                </code>
                                ) dan warna kegiatan.
                            </li>
                            <li>
                                Warna{' '}
                                <span className="font-semibold text-[#854d0e] dark:text-[#fde047]">
                                    Kuning (#FFFF00)
                                </span>{' '}
                                melambangkan kegiatan{' '}
                                <span className="font-semibold text-foreground">
                                    Ganti Pelumas
                                </span>{' '}
                                (otomatis saat memilih P2).
                            </li>
                            <li>
                                Warna{' '}
                                <span className="font-semibold text-[#15803d] dark:text-[#86efac]">
                                    Hijau (#92D050)
                                </span>{' '}
                                melambangkan kegiatan{' '}
                                <span className="font-semibold text-foreground">
                                    Ganti Pelumas+Cleaning Radiator
                                </span>{' '}
                                (otomatis saat memilih P3).
                            </li>
                            <li>
                                Anda juga bebas mengatur warna kuning, hijau,
                                atau tanpa warna untuk setiap sel sesuai
                                kebutuhan di lapangan.
                            </li>
                            <li>
                                Pada baris{' '}
                                <span className="font-semibold text-foreground">
                                    DURASI
                                </span>
                                , isikan jam pengerjaan (misal: 8 jam).
                            </li>
                            <li>
                                Tekan tombol{' '}
                                <span className="font-semibold text-foreground">
                                    Simpan Jadwal
                                </span>{' '}
                                di kanan atas setelah melakukan perubahan.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
}

HarJadwalP0P5Page.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Pemeliharaan', href: jadwal.index() },
        { title: 'Jadwal P0 - P5', href: p0P5.index() },
    ],
};
