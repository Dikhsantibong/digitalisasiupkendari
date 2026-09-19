import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CheckCheck,
    Download,
    FileSpreadsheet,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import type { StyleSpec } from '@/lib/jadwal-excel';
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

type PdmHarianRow = {
    id: number | null;
    kategori: string;
    is_category_header: boolean;
    no_urut: string;
    kegiatan: string;
    target: number;
    rencana_count: number;
    realisasi_count: number;
    jadwal: number[];
    keterangan?: string;
    sort_order?: number;
};

type DocumentMeta = {
    doc_number?: string | null;
    revision?: string | null;
    effective_date?: string | null;
    page_number?: string | null;
    disetujui_nama?: string | null;
    disetujui_jabatan?: string | null;
    dibuat_nama?: string | null;
    dibuat_jabatan?: string | null;
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
    days_in_month: number;
    month_name: string;
    target_working_days: number;
    rows: PdmHarianRow[];
    meta: DocumentMeta;
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
        background: white !important;
        color: black !important;
    }
    .no-print {
        display: none !important;
    }
}
`;

export default function PdmJadwalHarianIndex({
    unit,
    filters,
    options,
    days,
    days_in_month,
    month_name,
    target_working_days,
    rows: initialRows,
    meta: initialMeta,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PdmHarianRow[]>(initialRows);
    const [meta, setMeta] = useState<DocumentMeta>(initialMeta);
    const [isSaving, setIsSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingRowIndex, setEditingRowIndex] = useState<number | null>(null);
    const [editForm, setEditForm] = useState({
        kategori: 'I. MESIN',
        no_urut: '1',
        kegiatan: '',
        target: target_working_days,
    });
    const [newCategory, setNewCategory] = useState('I. MESIN');
    const [newKegiatan, setNewKegiatan] = useState('');
    const [newTarget, setNewTarget] = useState(target_working_days);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setMeta(initialMeta);
        setDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            '/pdm/jadwal/harian',
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    // Toggle day for a row
    const handleToggleDay = (rowIndex: number, day: number) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const next = [...prev];
            const current = next[rowIndex];
            const currentJadwal = current.jadwal || [];
            const exists = currentJadwal.includes(day);

            const newJadwal = exists
                ? currentJadwal.filter((d) => d !== day)
                : [...currentJadwal, day].sort((a, b) => a - b);

            next[rowIndex] = {
                ...current,
                jadwal: newJadwal,
                realisasi_count: newJadwal.length,
            };

            return next;
        });
        setDirty(true);
    };

    // Check all workdays for a row
    const handleCheckAllWorkdays = (rowIndex: number) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const next = [...prev];
            const workdays = days.filter((d) => !d.is_red).map((d) => d.day);
            next[rowIndex] = {
                ...next[rowIndex],
                jadwal: workdays,
                realisasi_count: workdays.length,
            };

            return next;
        });
        setDirty(true);
    };

    // Clear all checked days for a row
    const handleClearAllDays = (rowIndex: number) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const next = [...prev];
            next[rowIndex] = {
                ...next[rowIndex],
                jadwal: [],
                realisasi_count: 0,
            };

            return next;
        });
        setDirty(true);
    };

    // Update target
    const handleUpdateTarget = (rowIndex: number, target: number) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const next = [...prev];
            next[rowIndex] = {
                ...next[rowIndex],
                target: Math.max(0, target),
                rencana_count: Math.max(0, target),
            };

            return next;
        });
        setDirty(true);
    };

    // Update keterangan
    const handleUpdateKeterangan = (rowIndex: number, text: string) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const next = [...prev];
            next[rowIndex] = {
                ...next[rowIndex],
                keterangan: text,
            };

            return next;
        });
        setDirty(true);
    };

    // Delete row
    const handleDeleteRow = (rowIndex: number) => {
        if (!can_write) {
return;
}

        if (!confirm('Hapus baris kegiatan ini?')) {
return;
}

        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
        toast.info('Baris kegiatan dihapus.');
    };

    // Add new task
    const handleAddRow = () => {
        if (!newKegiatan.trim()) {
return;
}

        setRows((prev) => {
            const filtered = prev.filter((r) => r.kategori === newCategory && !r.is_category_header);
            const nextNo = filtered.length + 1;

            return [
                ...prev,
                {
                    id: null,
                    kategori: newCategory,
                    is_category_header: false,
                    no_urut: String(nextNo),
                    kegiatan: newKegiatan.trim(),
                    target: newTarget,
                    rencana_count: newTarget,
                    realisasi_count: 0,
                    jadwal: [],
                    keterangan: '',
                    sort_order: prev.length,
                },
            ];
        });

        setNewKegiatan('');
        setAddDialogOpen(false);
        setDirty(true);
        toast.success('Kegiatan baru berhasil ditambahkan ke tabel.');
    };

    // Open Edit Row Modal
    const openEditRowModal = (rowIndex: number) => {
        const row = rows[rowIndex];

        if (!row) {
return;
}

        setEditingRowIndex(rowIndex);
        setEditForm({
            kategori: row.kategori,
            no_urut: row.no_urut || '',
            kegiatan: row.kegiatan,
            target: row.target,
        });
        setEditDialogOpen(true);
    };

    // Save Edit Row
    const handleSaveEditRow = () => {
        if (editingRowIndex === null || !editForm.kegiatan.trim()) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) =>
                idx === editingRowIndex
                    ? {
                          ...r,
                          kategori: editForm.kategori,
                          no_urut: editForm.no_urut,
                          kegiatan: editForm.kegiatan.trim(),
                          target: Math.max(0, editForm.target),
                          rencana_count: Math.max(0, editForm.target),
                      }
                    : r,
            ),
        );

        setEditDialogOpen(false);
        setEditingRowIndex(null);
        setDirty(true);
        toast.success('Kegiatan berhasil diperbarui.');
    };

    // Reset rows to initial state
    const handleReset = () => {
        setRows(initialRows);
        setMeta(initialMeta);
        setDirty(false);
        toast.info('Perubahan dibatalkan ke data terakhir yang tersimpan.');
    };

    // Save rows and meta to server
    const handleSave = () => {
        setIsSaving(true);
        router.post(
            '/pdm/jadwal/harian',
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                meta,
                rows: rows.map((r, idx) => ({
                    ...r,
                    sort_order: idx,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Jadwal kegiatan PdM & MATLEV berhasil disimpan.');
                },
                onError: () => {
                    toast.error('Gagal menyimpan jadwal ke server.');
                },
                onFinish: () => setIsSaving(false),
            },
        );
    };

    // Export Excel (.xlsx) using ExcelJS
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Header Rows (0-based rows 4 and 5)
        const rowH1: (string | number)[] = [
            'No',
            'Nama Peralatan',
            ...Array(days.length).fill(month_name.toUpperCase()),
            'TARGET',
            'REALISASI',
            'A. KINERJA',
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
        ];
        headerRows.push(rowH2);

        // Data Rows (starting from 0-based row 6)
        rows.forEach((r) => {
            const jadwalList = r.jadwal || [];
            const realisasi = jadwalList.length;
            const target = r.target;
            const kinerja = target > 0 ? `${Math.round((realisasi / target) * 100)}%` : '0%';

            const rowData: (string | number)[] = [
                r.no_urut,
                r.kegiatan,
                ...days.map((d) => (jadwalList.includes(d.day) ? 1 : '')),
                target,
                realisasi,
                kinerja,
                r.keterangan || '',
            ];
            headerRows.push(rowData);
        });

        const { workbook, worksheet: ws } = createSheet(
            'Jadwal_PdM_Matlev',
            headerRows,
        );

        const dayStart = 2;
        const dayEnd = dayStart + days.length - 1;
        const colTarget = dayStart + days.length;
        const colRealisasi = colTarget + 1;
        const colKinerja = colTarget + 2;
        const colKeterangan = colTarget + 3;
        const totalCols = colKeterangan + 1;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
return null;
}

            if (r === headerTop || r === headerBottom) {
                if (r === headerBottom && c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];

                    return day?.is_red
                        ? { ...SPECS.headerGrey, fill: XLSX_COLORS.red, color: XLSX_COLORS.white, bold: true }
                        : SPECS.headerGrey;
                }

                return SPECS.headerGrey;
            }

            if (r >= dataStart) {
                const dataIndex = r - dataStart;
                const isCat = rows[dataIndex]?.is_category_header;
                const baseBg = isCat ? XLSX_COLORS.categoryGrey : XLSX_COLORS.white;

                if (c === 0) {
return { ...SPECS.cell, fill: baseBg, bold: isCat };
}

                if (c === 1) {
return { ...SPECS.cellLeft, fill: baseBg, bold: isCat };
}

                if (c >= dayStart && c <= dayEnd) {
                    const day = days[c - dayStart];
                    const val = headerRows[r]?.[c];

                    if (day?.is_red) {
                        return { fill: XLSX_COLORS.red, color: XLSX_COLORS.white, bold: true, align: 'center', valign: 'center', border: true };
                    }

                    if (val === 1) {
                        return { fill: XLSX_COLORS.emerald, bold: true, align: 'center', valign: 'center', border: true };
                    }

                    return { ...SPECS.cell, fill: baseBg };
                }

                if (c === colKeterangan) {
return { ...SPECS.cellLeft, fill: baseBg };
}

                return { ...SPECS.cell, fill: baseBg, bold: isCat };
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // No
        mergeCells(ws, headerTop, 1, headerBottom, 1); // Nama Peralatan
        mergeCells(ws, headerTop, dayStart, headerTop, dayEnd); // Month Label
        mergeCells(ws, headerTop, colTarget, headerBottom, colTarget); // TARGET
        mergeCells(ws, headerTop, colRealisasi, headerBottom, colRealisasi); // REALISASI
        mergeCells(ws, headerTop, colKinerja, headerBottom, colKinerja); // A. KINERJA
        mergeCells(ws, headerTop, colKeterangan, headerBottom, colKeterangan); // Keterangan

        const colWidths = [
            5,
            34,
            ...Array(days.length).fill(3.2),
            9,
            10,
            11,
            24,
        ];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS - 11 SITE',
                    `PLN NP UP KENDARI - ${unit.service_unit_name || unit.name.toUpperCase()}`,
                    'BAGIAN PdM & MATLEV',
                ],
                barTitle: `JADWAL KEGIATAN PdM & MATLEV - ${month_name.toUpperCase()} ${filters.year}`,
            },
        );

        await downloadWorkbook(
            workbook,
            `Jadwal_Kegiatan_PdM_Matlev_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    // Overall KPI statistics
    const stats = useMemo(() => {
        const taskRows = rows.filter((r) => !r.is_category_header);
        const totalTasks = taskRows.length;
        const totalRealisasi = taskRows.reduce((acc, r) => acc + (r.jadwal || []).length, 0);
        const totalTarget = taskRows.reduce((acc, r) => acc + r.target, 0);
        const avgKinerja =
            totalTarget > 0 ? Math.round((totalRealisasi / totalTarget) * 100) : 0;

        return { totalTasks, totalRealisasi, totalTarget, avgKinerja };
    }, [rows]);

    return (
        <>
            <Head title={`Jadwal Kegiatan PdM & Matlev - ${unit.name}`} />
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
                                Jadwal Kegiatan PdM &amp; Matlev
                            </h1>
                        </div>
                        <p className="mt-0.5 text-xs text-muted-foreground pl-10">
                            Unit: <strong className="text-foreground">{unit.name}</strong> • Periode:{' '}
                            <strong className="text-foreground">
                                {month_name} {filters.year}
                            </strong>{' '}
                            • Standar Matriks Predictive Maintenance &amp; Maturity Level
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
                                    `/pdm/jadwal/harian/pdf?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`,
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
                    <div className="ml-auto flex items-center gap-2">
                        {can_write && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setAddDialogOpen(true)}
                                className="h-9 gap-1.5 text-xs font-medium"
                            >
                                <Plus className="size-3.5" />
                                Tambah Kegiatan
                            </Button>
                        )}
                    </div>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 no-print">
                    <Card className="p-3 shadow-xs">
                        <div className="text-[11px] font-medium text-muted-foreground">Total Kegiatan</div>
                        <div className="mt-1 text-xl font-bold text-foreground">{stats.totalTasks} Item</div>
                    </Card>
                    <Card className="p-3 shadow-xs">
                        <div className="text-[11px] font-medium text-muted-foreground">Hari Kerja Efektif</div>
                        <div className="mt-1 text-xl font-bold text-foreground">{target_working_days} Hari</div>
                    </Card>
                    <Card className="p-3 shadow-xs">
                        <div className="text-[11px] font-medium text-muted-foreground">Total Realisasi Checklist</div>
                        <div className="mt-1 text-xl font-bold text-foreground">{stats.totalRealisasi} Kali</div>
                    </Card>
                    <Card className="p-3 shadow-xs">
                        <div className="text-[11px] font-medium text-muted-foreground">Rata-rata Pencapaian Kinerja</div>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-xl font-bold text-primary">{stats.avgKinerja}%</span>
                            <span className="text-[10px] text-muted-foreground">
                                ({stats.totalRealisasi}/{stats.totalTarget})
                            </span>
                        </div>
                    </Card>
                </div>

                {/* Main Table Card */}
                <div className="rounded-md border border-border bg-card shadow-xs overflow-x-auto">
                    {/* Official Document Header in View */}
                    <div className="border-b border-border p-4 text-center bg-muted/10">
                        <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Jasa Pendukung Teknis 11 Site
                        </div>
                        <div className="text-sm font-semibold uppercase text-foreground">
                            PLN NP UP Kendari - {unit.service_unit_name || `ULPLTD/PLTM/PLTG ${unit.name}`}
                        </div>
                        <div className="text-base font-bold uppercase tracking-tight text-foreground mt-0.5">
                            Jadwal Kegiatan PdM &amp; Matlev Bulan {month_name} {filters.year}
                        </div>
                        <div className="text-xs text-muted-foreground uppercase">
                            Bagian PdM &amp; MATLEV
                        </div>
                    </div>

                    <table className="w-full min-w-[1550px] border-collapse text-xs select-none">
                        <thead>
                            <tr className="border-b border-border bg-muted/60 text-muted-foreground">
                                <th rowSpan={2} className="w-10 min-w-10 border-r border-border px-2 py-2 text-center font-bold">
                                    No
                                </th>
                                <th rowSpan={2} className="min-w-[260px] border-r border-border px-3 py-2 text-left font-bold">
                                    Nama Peralatan
                                </th>
                                <th
                                    colSpan={days_in_month}
                                    className="border-r border-border px-2 py-1 text-center font-bold uppercase tracking-wider bg-muted/40"
                                >
                                    {month_name} {filters.year}
                                </th>
                                <th rowSpan={2} className="w-14 min-w-14 border-r border-border px-1 py-2 text-center font-bold">
                                    TARGET
                                </th>
                                <th rowSpan={2} className="w-16 min-w-16 border-r border-border px-1 py-2 text-center font-bold">
                                    REALISASI
                                </th>
                                <th rowSpan={2} className="w-16 min-w-16 border-r border-border px-1 py-2 text-center font-bold">
                                    A. KINERJA
                                </th>
                                <th rowSpan={2} className="min-w-[150px] border-r border-border px-2 py-2 text-left font-bold">
                                    Keterangan
                                </th>
                                <th rowSpan={2} className="w-20 min-w-20 px-2 py-2 text-center font-bold no-print">
                                    Aksi
                                </th>
                            </tr>
                            <tr className="border-b border-border bg-muted/30 text-[11px]">
                                {days.map((d) => (
                                    <th
                                        key={d.day}
                                        title={d.holiday || (d.is_weekend ? 'Akhir Pekan' : `Hari ke-${d.day}`)}
                                        className={`w-8 min-w-8 border-r border-border py-1 text-center font-medium ${
                                            d.is_red
                                                ? 'bg-[#ff0000] text-white font-bold'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {d.day}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, rIdx) => {
                                const isCategory = row.is_category_header;
                                const jadwalSet = new Set(row.jadwal || []);
                                const realisasi = (row.jadwal || []).length;
                                const kinerja =
                                    row.target > 0 ? Math.round((realisasi / row.target) * 100) : 0;

                                if (isCategory) {
                                    return (
                                        <tr
                                            key={`cat-${rIdx}`}
                                            className="border-b border-border bg-muted/50 font-bold text-foreground"
                                        >
                                            <td className="border-r border-border px-2 py-2 text-center font-bold">
                                                {row.no_urut}
                                            </td>
                                            <td
                                                colSpan={1 + days_in_month + 4}
                                                className="border-r border-border px-3 py-2 uppercase tracking-wide text-xs font-bold"
                                            >
                                                {row.kegiatan}
                                            </td>
                                            <td className="px-2 py-1 text-center no-print">
                                                {can_write && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setNewCategory(row.kategori);
                                                            setAddDialogOpen(true);
                                                        }}
                                                        className="h-6 gap-1 px-2 text-[11px] text-primary"
                                                        title="Tambah kegiatan di kategori ini"
                                                    >
                                                        <Plus className="size-3" />
                                                        Tambah
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                }

                                return (
                                    <tr
                                        key={`row-${rIdx}`}
                                        className="border-b border-border hover:bg-muted/10 transition-colors"
                                    >
                                        {/* No */}
                                        <td className="border-r border-border px-1 py-1.5 text-center text-muted-foreground font-medium w-10 min-w-10">
                                            {row.no_urut}
                                        </td>

                                        {/* Nama Peralatan */}
                                        <td className="border-r border-border px-3 py-1 font-medium text-foreground min-w-[260px]">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={row.kegiatan}
                                                    onChange={(e) => {
                                                        const val = e.target.value;
                                                        setRows((prev) =>
                                                            prev.map((r, idx) => (idx === rIdx ? { ...r, kegiatan: val } : r))
                                                        );
                                                        setDirty(true);
                                                    }}
                                                    placeholder="Nama peralatan / kegiatan..."
                                                    className="h-6 w-full rounded border border-transparent bg-transparent px-1 text-xs font-medium text-foreground placeholder:text-muted-foreground/50 hover:border-border focus:border-primary focus:bg-background"
                                                />
                                            ) : (
                                                row.kegiatan
                                            )}
                                        </td>

                                        {/* Day Columns 1..31 */}
                                        {days.map((d) => {
                                            const isMarked = jadwalSet.has(d.day);
                                            const isRed = d.is_red;

                                            return (
                                                <td
                                                    key={d.day}
                                                    onClick={() => handleToggleDay(rIdx, d.day)}
                                                    className={`w-8 min-w-8 border-r border-border p-0 text-center font-bold transition-colors ${
                                                        isRed
                                                            ? 'bg-[#ff0000] text-white'
                                                            : isMarked
                                                            ? 'bg-primary/10 text-primary font-black'
                                                            : 'text-muted-foreground/30 hover:bg-muted/40'
                                                    } ${can_write ? 'cursor-pointer select-none' : ''}`}
                                                    title={`Hari ke-${d.day} (${d.dow}): Klik untuk checklist`}
                                                >
                                                    <div className="flex h-7 w-8 min-w-8 items-center justify-center">
                                                        {isMarked ? '1' : ''}
                                                    </div>
                                                </td>
                                            );
                                        })}

                                        {/* Target */}
                                        <td className="border-r border-border px-1 py-1 text-center font-medium w-14 min-w-14">
                                            {can_write ? (
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={31}
                                                    value={row.target}
                                                    onChange={(e) =>
                                                        handleUpdateTarget(rIdx, parseInt(e.target.value) || 0)
                                                    }
                                                    className="h-6 w-11 rounded border border-transparent bg-transparent text-center text-xs font-semibold hover:border-border focus:border-primary focus:bg-background"
                                                />
                                            ) : (
                                                row.target
                                            )}
                                        </td>

                                        {/* Realisasi */}
                                        <td className="border-r border-border px-2 py-1.5 text-center font-bold text-foreground w-16 min-w-16">
                                            {realisasi}
                                        </td>

                                        {/* A. Kinerja */}
                                        <td className="border-r border-border px-2 py-1.5 text-center font-bold w-16 min-w-16">
                                            <span
                                                className={
                                                    kinerja >= 80
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : kinerja > 0
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {kinerja}%
                                            </span>
                                        </td>

                                        {/* Keterangan */}
                                        <td className="border-r border-border px-2 py-1 min-w-[150px]">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={row.keterangan || ''}
                                                    onChange={(e) =>
                                                        handleUpdateKeterangan(rIdx, e.target.value)
                                                    }
                                                    placeholder="-"
                                                    className="h-6 w-full rounded border border-transparent bg-transparent px-1.5 text-xs text-foreground placeholder:text-muted-foreground/50 hover:border-border focus:border-primary focus:bg-background"
                                                />
                                            ) : (
                                                <span className="text-xs">{row.keterangan || '-'}</span>
                                            )}
                                        </td>

                                        {/* Quick Actions */}
                                        <td className="px-1 py-1 text-center no-print w-20 min-w-20">
                                            <div className="flex items-center justify-center gap-1">
                                                {can_write && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleCheckAllWorkdays(rIdx)}
                                                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                            title="Check semua hari kerja"
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
                                                            title="Ubah kegiatan / kategori"
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
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Table Bottom Action: Tambah Kegiatan */}
                {can_write && (
                    <div className="flex items-center justify-between no-print">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAddDialogOpen(true)}
                            className="gap-1.5 text-xs border-dashed"
                        >
                            <Plus className="size-3.5" />
                            Tambah Baris Kegiatan
                        </Button>
                    </div>
                )}
            </div>

            {/* Modal Tambah Kegiatan */}
            <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Tambah Baris Kegiatan</DialogTitle>
                        <DialogDescription className="text-xs">
                            Masukkan detail peralatan atau kegiatan PdM &amp; Matlev baru.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div>
                            <Label className="text-xs">Kategori Kegiatan</Label>
                            <select
                                value={newCategory}
                                onChange={(e) => setNewCategory(e.target.value)}
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="I. MESIN">I. MESIN</option>
                                <option value="II. Mingguan">II. Mingguan</option>
                                <option value="III. Bulanan">III. Bulanan</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-xs">Nama Peralatan / Kegiatan</Label>
                            <Input
                                value={newKegiatan}
                                onChange={(e) => setNewKegiatan(e.target.value)}
                                placeholder="Contoh: Pengukuran Temperatur Bearing..."
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">Target (Hari/Kali)</Label>
                            <Input
                                type="number"
                                min={1}
                                max={31}
                                value={newTarget}
                                onChange={(e) => setNewTarget(parseInt(e.target.value) || target_working_days)}
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" size="sm" onClick={() => setAddDialogOpen(false)}>
                            Batal
                        </Button>
                        <Button size="sm" onClick={handleAddRow} disabled={!newKegiatan.trim()}>
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Modal Ubah Kegiatan */}
            <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Ubah Detail Kegiatan</DialogTitle>
                        <DialogDescription className="text-xs">
                            Perbarui detail kelompok kategori, nomor urut, nama peralatan/kegiatan, atau target hari.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div>
                            <Label className="text-xs">Kategori Kegiatan</Label>
                            <select
                                value={editForm.kategori}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, kategori: e.target.value }))
                                }
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="I. MESIN">I. MESIN</option>
                                <option value="II. Mingguan">II. Mingguan</option>
                                <option value="III. Bulanan">III. Bulanan</option>
                            </select>
                        </div>
                        <div className="grid grid-cols-4 gap-2">
                            <div className="col-span-1">
                                <Label className="text-xs">No. Urut</Label>
                                <Input
                                    value={editForm.no_urut}
                                    onChange={(e) =>
                                        setEditForm((prev) => ({ ...prev, no_urut: e.target.value }))
                                    }
                                    placeholder="1 / a"
                                    className="mt-1 h-8 text-xs text-center"
                                />
                            </div>
                            <div className="col-span-3">
                                <Label className="text-xs">Target (Hari/Kali)</Label>
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
                            <Label className="text-xs">Nama Peralatan / Kegiatan</Label>
                            <Input
                                value={editForm.kegiatan}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, kegiatan: e.target.value }))
                                }
                                placeholder="Nama kegiatan..."
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
                            disabled={!editForm.kegiatan.trim()}
                        >
                            Simpan Perubahan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PdmJadwalHarianIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal PdM & Maturity Level', href: '/pdm/jadwal' },
        { title: 'Jadwal Kegiatan PdM & MATLEV', href: '/pdm/jadwal/harian' },
    ],
};

