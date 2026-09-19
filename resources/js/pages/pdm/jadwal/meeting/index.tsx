import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CalendarClock,
    Check,
    CheckCheck,
    FileSpreadsheet,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_sunday: boolean;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type JadwalMeetingRow = {
    id: number | null;
    no_urut: number;
    uraian: string;
    rencana: number[];
    realisasi: number[];
    target: number;
    catatan?: string | null;
    keterangan?: string | null;
    sort_order?: number;
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
    rows: JadwalMeetingRow[];
    catatan: string;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .no-print {
        display: none !important;
    }
    .print-container {
        padding: 0 !important;
        margin: 0 !important;
    }
}
`;

export default function PdmJadwalMeetingIndex({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    catatan: initialCatatan,
    can_write,
}: Props) {
    const [rows, setRows] = useState<JadwalMeetingRow[]>(initialRows);
    const [catatan, setCatatan] = useState<string>(initialCatatan || '');
    const [dirty, setDirty] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    // Modal States
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingRowIndex, setEditingRowIndex] = useState<number | null>(null);

    // Add Form State
    const [newUraian, setNewUraian] = useState('');
    const [newTarget, setNewTarget] = useState(4);

    // Edit Form State
    const [editForm, setEditForm] = useState({
        no_urut: 1,
        uraian: '',
        target: 4,
    });

    const month_name = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            '/pdm/jadwal/meeting',
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    // Toggle a day in Rencana or Realisasi
    const handleToggleDay = (rowIndex: number, type: 'rencana' | 'realisasi', day: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) {
return r;
}

                const currentList = r[type] || [];
                const isMarked = currentList.includes(day);
                const nextList = isMarked
                    ? currentList.filter((d) => d !== day)
                    : [...currentList, day].sort((a, b) => a - b);

                return {
                    ...r,
                    [type]: nextList,
                };
            }),
        );
        setDirty(true);
    };

    // Quick Action: Toggle all workdays for Rencana or Realisasi
    const handleCheckAllWorkdays = (rowIndex: number, type: 'rencana' | 'realisasi') => {
        if (!can_write) {
return;
}

        const workdays = days.filter((d) => !d.is_red).map((d) => d.day);

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) {
return r;
}

                return {
                    ...r,
                    [type]: workdays,
                };
            }),
        );
        setDirty(true);
        toast.success(`Semua hari kerja ${type.toUpperCase()} berhasil diceklis.`);
    };

    // Clear all days for an item
    const handleClearAllDays = (rowIndex: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) {
return r;
}

                return {
                    ...r,
                    rencana: [],
                    realisasi: [],
                };
            }),
        );
        setDirty(true);
        toast.info('Checklist rencana & realisasi berhasil dikosongkan.');
    };

    // Update target inline
    const handleUpdateTarget = (rowIndex: number, target: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => (idx === rowIndex ? { ...r, target: Math.max(0, target) } : r)),
        );
        setDirty(true);
    };

    // Update uraian inline
    const handleUpdateUraian = (rowIndex: number, uraian: string) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => (idx === rowIndex ? { ...r, uraian } : r)),
        );
        setDirty(true);
    };

    // Delete a row
    const handleDeleteRow = (rowIndex: number) => {
        if (!can_write) {
return;
}

        const targetRow = rows[rowIndex];

        if (!targetRow) {
return;
}

        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
        toast.success(`Agenda ${targetRow.uraian} berhasil dihapus.`);
    };

    // Open Edit Modal
    const openEditRowModal = (rowIndex: number) => {
        const row = rows[rowIndex];

        if (!row) {
return;
}

        setEditingRowIndex(rowIndex);
        setEditForm({
            no_urut: row.no_urut,
            uraian: row.uraian,
            target: row.target,
        });
        setEditDialogOpen(true);
    };

    // Save Edit Row
    const handleSaveEditRow = () => {
        if (editingRowIndex === null || !editForm.uraian.trim()) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) =>
                idx === editingRowIndex
                    ? {
                          ...r,
                          no_urut: editForm.no_urut,
                          uraian: editForm.uraian.trim(),
                          target: Math.max(0, editForm.target),
                      }
                    : r,
            ),
        );

        setEditDialogOpen(false);
        setEditingRowIndex(null);
        setDirty(true);
        toast.success('Detail agenda meeting berhasil diperbarui.');
    };

    // Handle adding a new row
    const handleAddRow = () => {
        if (!newUraian.trim()) {
return;
}

        const newRow: JadwalMeetingRow = {
            id: null,
            no_urut: rows.length + 1,
            uraian: newUraian.trim(),
            rencana: [],
            realisasi: [],
            target: newTarget,
            sort_order: rows.length,
        };

        setRows([...rows, newRow]);
        setAddDialogOpen(false);
        setNewUraian('');
        setNewTarget(4);
        setDirty(true);
        toast.success(`Agenda meeting "${newUraian}" berhasil ditambahkan.`);
    };

    // Reset rows to initial state
    const handleReset = () => {
        setRows(initialRows);
        setCatatan(initialCatatan || '');
        setDirty(false);
        toast.info('Perubahan dibatalkan ke data terakhir yang tersimpan.');
    };

    // Save rows to server
    const handleSave = () => {
        setIsSaving(true);
        router.post(
            '/pdm/jadwal/meeting',
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                catatan,
                rows: rows.map((r, idx) => ({
                    ...r,
                    sort_order: idx,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Jadwal Meeting PdM Pembangkit berhasil disimpan.');
                },
                onError: () => {
                    toast.error('Gagal menyimpan jadwal ke server.');
                },
                onFinish: () => setIsSaving(false),
            },
        );
    };

    // Export to Excel matching reference layout
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Header Row 1 (row 4): Day abbreviations
        const rowH1: (string | number)[] = [
            'URAIAN',
            `${month_name.toUpperCase()} ${filters.year}`,
            ...days.map((d) => d.dow),
            'RENCANA',
            'TARGET',
            'REALISASI',
            'A. KINERJA',
        ];
        headerRows.push(rowH1);

        // Header Row 2 (row 5): Day numbers
        const rowH2: (string | number)[] = [
            '',
            '',
            ...days.map((d) => d.day),
            '',
            '',
            '',
            '',
        ];
        headerRows.push(rowH2);

        // Data Rows: 2 sub-rows per item
        rows.forEach((r) => {
            const rencList = r.rencana || [];
            const realList = r.realisasi || [];
            const target = r.target;
            const realisasi = realList.length;
            const kinerja = target > 0 ? `${Math.round((realisasi / target) * 100)}%` : '0%';

            // Row 1: RENCANA
            const rowRencana: (string | number)[] = [
                r.uraian,
                'RENCANA',
                ...days.map((d) => (rencList.includes(d.day) ? 1 : '')),
                rencList.length,
                target,
                realisasi,
                kinerja,
            ];
            headerRows.push(rowRencana);

            // Row 2: REALISASI
            const rowRealisasi: (string | number)[] = [
                '',
                'REALISASI',
                ...days.map((d) => (realList.includes(d.day) ? 1 : '')),
                '',
                '',
                '',
                '',
            ];
            headerRows.push(rowRealisasi);
        });

        const { workbook, worksheet: ws } = createSheet(
            'Jadwal_Meeting_PdM',
            headerRows,
        );

        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colRencana = dayStart + days.length;
        const colTarget = colRencana + 1;
        const colRealisasi = colRencana + 2;
        const colKinerja = colRencana + 3;
        const totalCols = colKinerja + 1;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
return null;
}

            // Orange headers (#ed7d31)
            if (r === headerTop || r === headerBottom) {
                return {
                    fill: XLSX_COLORS.orange,
                    color: XLSX_COLORS.white,
                    bold: true,
                    align: 'center',
                    valign: 'center',
                    border: true,
                };
            }

            if (r >= dataStart) {
                const subRowIdx = r - dataStart;
                const isRencanaRow = subRowIdx % 2 === 0;

                // Uraian column
                if (c === 0) {
                    return { ...SPECS.cellLeft, bold: true };
                }

                // Sub-label column (RENCANA / REALISASI)
                if (c === 1) {
                    return { ...SPECS.cell, bold: true, size: 8 };
                }

                // Days
                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    const val = headerRows[r]?.[c];
                    const bg = day?.is_red ? XLSX_COLORS.redLight : XLSX_COLORS.white;

                    if (val === 1) {
                        return isRencanaRow
                            ? { fill: XLSX_COLORS.amber, bold: true, align: 'center', valign: 'center', border: true }
                            : { fill: XLSX_COLORS.emerald, bold: true, align: 'center', valign: 'center', border: true };
                    }

                    return { ...SPECS.cell, fill: bg };
                }

                // TARGET (red text)
                if (c === colTarget) {
                    return {
                        fill: XLSX_COLORS.white,
                        color: XLSX_COLORS.red600,
                        bold: true,
                        align: 'center',
                        valign: 'center',
                        border: true,
                    };
                }

                // Summary columns
                return { ...SPECS.cell, bold: true };
            }

            return null;
        });

        // Merges for header
        mergeCells(ws, headerTop, 0, headerBottom, 0); // URAIAN
        mergeCells(ws, headerTop, 1, headerBottom, 1); // BULAN TAHUN
        mergeCells(ws, headerTop, colRencana, headerBottom, colRencana); // RENCANA
        mergeCells(ws, headerTop, colTarget, headerBottom, colTarget); // TARGET
        mergeCells(ws, headerTop, colRealisasi, headerBottom, colRealisasi); // REALISASI
        mergeCells(ws, headerTop, colKinerja, headerBottom, colKinerja); // A. KINERJA

        // Merges for data rows (each item spans 2 rows)
        rows.forEach((_, idx) => {
            const rowTop = dataStart + idx * 2;
            const rowBottom = rowTop + 1;
            mergeCells(ws, rowTop, 0, rowBottom, 0); // URAIAN
            mergeCells(ws, rowTop, colRencana, rowBottom, colRencana); // RENCANA
            mergeCells(ws, rowTop, colTarget, rowBottom, colTarget); // TARGET
            mergeCells(ws, rowTop, colRealisasi, rowBottom, colRealisasi); // REALISASI
            mergeCells(ws, rowTop, colKinerja, rowBottom, colKinerja); // A. KINERJA
        });

        const colWidths = [
            28,
            16,
            ...Array(days.length).fill(3.2),
            9,
            9,
            10,
            11,
        ];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT',
                    `LAPORAN PROJECT SENTRAL PLTD/PLTG/PLTM ${unit.name.toUpperCase()}`,
                    'JADWAL MEETING PdM Pembangkit',
                ],
                barTitle: `JADWAL MEETING PdM PEMBANGKIT - ${month_name.toUpperCase()} ${filters.year}`,
            },
        );

        await downloadWorkbook(
            workbook,
            `Jadwal_Meeting_PdM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    // Overall KPI statistics
    const stats = useMemo(() => {
        const totalItems = rows.length;
        const totalRencana = rows.reduce((acc, r) => acc + (r.rencana || []).length, 0);
        const totalRealisasi = rows.reduce((acc, r) => acc + (r.realisasi || []).length, 0);
        const totalTarget = rows.reduce((acc, r) => acc + r.target, 0);
        const avgKinerja =
            totalTarget > 0 ? Math.round((totalRealisasi / totalTarget) * 100) : 0;

        return { totalItems, totalRencana, totalRealisasi, totalTarget, avgKinerja };
    }, [rows]);

    return (
        <>
            <Head title={`Jadwal Meeting PdM - ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6 print-container">
                {/* Header & Actions */}
                <div className="flex flex-wrap items-center justify-between gap-3 no-print">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => router.get('/pdm/jadwal')}
                                className="size-8"
                                title="Kembali ke Menu Jadwal"
                            >
                                <ArrowLeft className="size-4" />
                            </Button>
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Jadwal Meeting PdM Pembangkit
                            </h1>
                        </div>
                        <p className="mt-0.5 text-xs text-muted-foreground pl-10">
                            Unit: <strong className="text-foreground">{unit.name}</strong> • Periode:{' '}
                            <strong className="text-foreground">
                                {month_name} {filters.year}
                            </strong>{' '}
                            • Koordinasi &amp; Evaluasi Berkala Predictive Maintenance
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:border-amber-700 dark:text-amber-300">
                                Ada perubahan belum disimpan
                            </span>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExportExcel}
                            className="h-8 gap-1.5 text-xs text-emerald-700 dark:text-emerald-400 border-emerald-600/30 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                        >
                            <FileSpreadsheet className="size-3.5" />
                            Unduh Excel
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                window.open(
                                    `/pdm/jadwal/meeting/pdf?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`,
                                    '_blank',
                                )
                            }
                            className="h-8 gap-1.5 text-xs"
                        >
                            <Printer className="size-3.5" />
                            Cetak / PDF
                        </Button>
                        {can_write && dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
                                disabled={isSaving}
                                className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                Batal
                            </Button>
                        )}
                        {can_write && (
                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={isSaving}
                                className="h-8 gap-1.5 text-xs font-semibold"
                            >
                                <Save className="size-3.5" />
                                {isSaving ? 'Menyimpan...' : 'Simpan Jadwal'}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs no-print">
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
                </div>

                {/* KPI Overview Cards */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 no-print">
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <CalendarClock className="size-4 text-primary" />
                            <span className="text-xs text-muted-foreground">Total Agenda Meeting</span>
                        </div>
                        <div className="mt-1 text-lg font-bold">{stats.totalItems} Agenda</div>
                    </Card>
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <Activity className="size-4 text-amber-600" />
                            <span className="text-xs text-muted-foreground">Total Checklist Rencana</span>
                        </div>
                        <div className="mt-1 text-lg font-bold">{stats.totalRencana} Hari</div>
                    </Card>
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <Check className="size-4 text-emerald-600" />
                            <span className="text-xs text-muted-foreground">Total Realisasi Terlaksana</span>
                        </div>
                        <div className="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            {stats.totalRealisasi} Hari
                        </div>
                    </Card>
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <UserCheck className="size-4 text-blue-600" />
                            <span className="text-xs text-muted-foreground">Rata-rata Kinerja</span>
                        </div>
                        <div className="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            {stats.avgKinerja}%
                        </div>
                    </Card>
                </div>

                {/* Main Table Wrapper */}
                <div className="rounded-md border border-border bg-card shadow-xs overflow-hidden">
                    {/* Header in View */}
                    <div className="border-b border-border p-4 text-center bg-muted/10">
                        <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Jasa Pendukung Teknis UP Kendari 11 Site &amp; 6 Site -KIT
                        </div>
                        <div className="text-sm font-semibold uppercase text-foreground">
                            Laporan Project Sentral PLTD/PLTG/PLTM {unit.name}
                        </div>
                        <div className="text-base font-bold uppercase tracking-tight text-foreground mt-0.5">
                            Jadwal Meeting PdM Pembangkit - {month_name} {filters.year}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1550px] border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-border bg-[#ed7d31] text-white">
                                    <th rowSpan={2} className="w-[300px] min-w-[300px] border-r border-[#d96a20] px-3 py-2 text-left font-bold">
                                        URAIAN
                                    </th>
                                    <th rowSpan={2} className="w-28 min-w-28 border-r border-[#d96a20] px-2 py-2 text-center font-bold uppercase">
                                        {month_name} {filters.year}
                                    </th>
                                    {days.map((d) => (
                                        <th
                                            key={`dow-${d.day}`}
                                            className="w-8 min-w-8 border-r border-[#d96a20] py-1 text-center font-bold text-[10px]"
                                        >
                                            {d.dow}
                                        </th>
                                    ))}
                                    <th rowSpan={2} className="w-16 min-w-16 border-r border-[#d96a20] px-1 py-2 text-center font-bold">
                                        RENCANA
                                    </th>
                                    <th rowSpan={2} className="w-14 min-w-14 border-r border-[#d96a20] px-1 py-2 text-center font-bold">
                                        TARGET
                                    </th>
                                    <th rowSpan={2} className="w-16 min-w-16 border-r border-[#d96a20] px-1 py-2 text-center font-bold">
                                        REALISASI
                                    </th>
                                    <th rowSpan={2} className="w-16 min-w-16 border-r border-[#d96a20] px-1 py-2 text-center font-bold">
                                        A. KINERJA
                                    </th>
                                    <th rowSpan={2} className="w-20 min-w-20 px-2 py-2 text-center font-bold no-print">
                                        Aksi
                                    </th>
                                </tr>
                                <tr className="border-b border-border bg-[#ed7d31] text-white text-[11px]">
                                    {days.map((d) => (
                                        <th
                                            key={`num-${d.day}`}
                                            title={d.holiday || (d.is_weekend ? 'Akhir Pekan' : `Hari ke-${d.day}`)}
                                            className="w-8 min-w-8 border-r border-[#d96a20] py-0.5 text-center font-bold"
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, rIdx) => {
                                    const rencList = row.rencana || [];
                                    const realList = row.realisasi || [];
                                    const target = row.target;
                                    const realisasi = realList.length;
                                    const kinerja = target > 0 ? Math.round((realisasi / target) * 100) : 0;

                                    return (
                                        <Fragment key={`meeting-row-${rIdx}`}>
                                            {/* Sub-row 1: RENCANA */}
                                            <tr className="border-b border-border/40 hover:bg-muted/10 transition-colors">
                                                {/* Uraian (rowSpan 2) */}
                                                <td rowSpan={2} className="border-r border-border px-3 py-1 font-semibold text-foreground w-[300px] min-w-[300px]">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={row.uraian}
                                                            onChange={(e) => handleUpdateUraian(rIdx, e.target.value)}
                                                            placeholder="Uraian agenda meeting..."
                                                            className="h-7 w-full rounded border border-input/60 bg-background px-2 text-xs font-semibold text-foreground placeholder:text-muted-foreground/50 hover:border-border focus:border-primary focus:outline-none"
                                                        />
                                                    ) : (
                                                        <span className="px-1">{row.uraian}</span>
                                                    )}
                                                </td>

                                                {/* Label RENCANA */}
                                                <td className="border-r border-border px-2 py-1 text-center font-bold text-[11px] text-amber-700 dark:text-amber-400 bg-amber-50/50 dark:bg-amber-950/20 w-28 min-w-28">
                                                    RENCANA
                                                </td>

                                                {/* Days for RENCANA */}
                                                {days.map((d) => {
                                                    const isMarked = rencList.includes(d.day);
                                                    const isRed = d.is_red;

                                                    return (
                                                        <td
                                                            key={`renc-${d.day}`}
                                                            onClick={() => handleToggleDay(rIdx, 'rencana', d.day)}
                                                            className={`border-r border-border p-0 text-center font-bold transition-colors w-8 min-w-8 ${
                                                                isRed
                                                                    ? isMarked
                                                                        ? 'bg-amber-400 text-black font-black'
                                                                        : 'bg-red-50 dark:bg-red-950/30 text-muted-foreground/40'
                                                                    : isMarked
                                                                    ? 'bg-amber-200 dark:bg-amber-800 text-amber-950 dark:text-amber-100 font-black'
                                                                    : 'text-muted-foreground/30 hover:bg-muted/40'
                                                            } ${can_write ? 'cursor-pointer select-none' : ''}`}
                                                            title={`Rencana Hari ${d.day} (${d.dow}): Klik untuk centang`}
                                                        >
                                                            <div className="flex h-6 w-full items-center justify-center">
                                                                {isMarked ? '1' : ''}
                                                            </div>
                                                        </td>
                                                    );
                                                })}

                                                {/* Total Rencana (rowSpan 2) */}
                                                <td rowSpan={2} className="border-r border-border px-1 py-1 text-center font-bold text-foreground w-16 min-w-16">
                                                    {rencList.length}
                                                </td>

                                                {/* Target (rowSpan 2, Red Number) */}
                                                <td rowSpan={2} className="border-r border-border px-1 py-1 text-center font-bold text-red-600 dark:text-red-400 w-14 min-w-14">
                                                    {can_write ? (
                                                        <input
                                                            type="number"
                                                            min={0}
                                                            max={31}
                                                            value={row.target}
                                                            onChange={(e) =>
                                                                handleUpdateTarget(rIdx, parseInt(e.target.value) || 0)
                                                            }
                                                            className="h-6 w-11 rounded border border-input/60 bg-background text-center text-xs font-bold text-red-600 dark:text-red-400 hover:border-border focus:border-primary focus:outline-none"
                                                        />
                                                    ) : (
                                                        row.target
                                                    )}
                                                </td>

                                                {/* Total Realisasi (rowSpan 2) */}
                                                <td rowSpan={2} className="border-r border-border px-1 py-1 text-center font-bold text-foreground w-16 min-w-16">
                                                    {realisasi}
                                                </td>

                                                {/* A. Kinerja (rowSpan 2) */}
                                                <td rowSpan={2} className="border-r border-border px-1 py-1 text-center font-bold w-16 min-w-16">
                                                    <span
                                                        className={
                                                            kinerja >= 80
                                                                ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                                                                : kinerja > 0
                                                                ? 'text-amber-600 dark:text-amber-400 font-bold'
                                                                : 'text-muted-foreground font-medium'
                                                        }
                                                    >
                                                        {kinerja}%
                                                    </span>
                                                </td>

                                                {/* Quick Actions (rowSpan 2) */}
                                                <td rowSpan={2} className="px-1 py-1 text-center no-print w-20 min-w-20">
                                                    <div className="flex items-center justify-center gap-1">
                                                        {can_write && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleCheckAllWorkdays(rIdx, 'rencana')}
                                                                    className="rounded p-1 text-amber-600 hover:bg-amber-100 dark:hover:bg-amber-950/40"
                                                                    title="Check semua hari kerja (Rencana)"
                                                                >
                                                                    <CheckCheck className="size-3.5" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleClearAllDays(rIdx)}
                                                                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                    title="Hapus semua checklist"
                                                                >
                                                                    <RotateCcw className="size-3.5" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openEditRowModal(rIdx)}
                                                                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                    title="Ubah uraian"
                                                                >
                                                                    <Pencil className="size-3.5" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDeleteRow(rIdx)}
                                                                    className="rounded p-1 text-destructive/70 hover:bg-destructive/10 hover:text-destructive"
                                                                    title="Hapus baris"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>

                                            {/* Sub-row 2: REALISASI */}
                                            <tr className="border-b border-border hover:bg-muted/10 transition-colors">
                                                {/* Label REALISASI */}
                                                <td className="border-r border-border px-2 py-1 text-center font-bold text-[11px] text-emerald-700 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20 w-28 min-w-28">
                                                    REALISASI
                                                </td>

                                                {/* Days for REALISASI */}
                                                {days.map((d) => {
                                                    const isMarked = realList.includes(d.day);
                                                    const isRed = d.is_red;

                                                    return (
                                                        <td
                                                            key={`real-${d.day}`}
                                                            onClick={() => handleToggleDay(rIdx, 'realisasi', d.day)}
                                                            className={`border-r border-border p-0 text-center font-bold transition-colors w-8 min-w-8 ${
                                                                isRed
                                                                    ? isMarked
                                                                        ? 'bg-emerald-500 text-white font-black'
                                                                        : 'bg-red-50 dark:bg-red-950/30 text-muted-foreground/40'
                                                                    : isMarked
                                                                    ? 'bg-emerald-200 dark:bg-emerald-800 text-emerald-950 dark:text-emerald-100 font-black'
                                                                    : 'text-muted-foreground/30 hover:bg-muted/40'
                                                            } ${can_write ? 'cursor-pointer select-none' : ''}`}
                                                            title={`Realisasi Hari ${d.day} (${d.dow}): Klik untuk centang`}
                                                        >
                                                            <div className="flex h-6 w-full items-center justify-center">
                                                                {isMarked ? '1' : ''}
                                                            </div>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Table Bottom Action: Tambah Agenda */}
                {can_write && (
                    <div className="flex items-center justify-between no-print">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAddDialogOpen(true)}
                            className="gap-1.5 text-xs border-dashed"
                        >
                            <Plus className="size-3.5" />
                            Tambah Agenda Meeting
                        </Button>
                    </div>
                )}

                {/* Footer Section: Catatan Pelaksanaan Meeting */}
                <div className="grid grid-cols-1 gap-4">
                    <Card className="no-print">
                        <CardHeader className="py-3 px-4">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Catatan Pelaksanaan Meeting
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 space-y-2">
                            {can_write ? (
                                <textarea
                                    rows={4}
                                    value={catatan}
                                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => {
                                        setCatatan(e.target.value);
                                        setDirty(true);
                                    }}
                                    placeholder="Tulis catatan operasional meeting..."
                                    className="w-full rounded-md border border-input/60 bg-background px-3 py-2 text-xs leading-relaxed text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            ) : (
                                <div className="rounded border border-dashed border-border p-3 text-xs leading-relaxed whitespace-pre-line text-muted-foreground bg-muted/10">
                                    {catatan}
                                </div>
                            )}
                            <p className="text-[11px] text-muted-foreground italic">
                                * Catatan ini akan otomatis dicetak pada lembar PDF dan laporan operasional.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Modal Tambah Agenda Meeting */}
            <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Tambah Agenda Meeting</DialogTitle>
                        <DialogDescription className="text-xs">
                            Masukkan uraian agenda meeting PdM dan target frekuensi pelaksanaan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div>
                            <Label className="text-xs">Uraian Agenda Meeting</Label>
                            <Input
                                value={newUraian}
                                onChange={(e) => setNewUraian(e.target.value)}
                                placeholder="Contoh: OFFICER PdM & Matlev..."
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">Target Pelaksanaan (Kali)</Label>
                            <Input
                                type="number"
                                min={1}
                                max={31}
                                value={newTarget}
                                onChange={(e) => setNewTarget(parseInt(e.target.value) || 4)}
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" size="sm" onClick={() => setAddDialogOpen(false)}>
                            Batal
                        </Button>
                        <Button size="sm" onClick={handleAddRow} disabled={!newUraian.trim()}>
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Modal Ubah Agenda Meeting */}
            <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Ubah Detail Agenda Meeting</DialogTitle>
                        <DialogDescription className="text-xs">
                            Perbarui nomor urut, uraian agenda, atau target pelaksanaan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div className="grid grid-cols-4 gap-2">
                            <div className="col-span-1">
                                <Label className="text-xs">No. Urut</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={editForm.no_urut}
                                    onChange={(e) =>
                                        setEditForm((prev) => ({
                                            ...prev,
                                            no_urut: parseInt(e.target.value) || 1,
                                        }))
                                    }
                                    className="mt-1 h-8 text-xs text-center"
                                />
                            </div>
                            <div className="col-span-3">
                                <Label className="text-xs">Target Pelaksanaan (Kali)</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={31}
                                    value={editForm.target}
                                    onChange={(e) =>
                                        setEditForm((prev) => ({
                                            ...prev,
                                            target: parseInt(e.target.value) || 0,
                                        }))
                                    }
                                    className="mt-1 h-8 text-xs"
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="text-xs">Uraian Agenda Meeting</Label>
                            <Input
                                value={editForm.uraian}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, uraian: e.target.value }))
                                }
                                placeholder="Uraian agenda meeting..."
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" size="sm" onClick={() => setEditDialogOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            size="sm"
                            onClick={handleSaveEditRow}
                            disabled={!editForm.uraian.trim()}
                        >
                            Simpan Perubahan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PdmJadwalMeetingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal PdM & Maturity Level', href: '/pdm/jadwal' },
        { title: 'Jadwal Meeting PdM Pembangkit', href: '/pdm/jadwal/meeting' },
    ],
};
