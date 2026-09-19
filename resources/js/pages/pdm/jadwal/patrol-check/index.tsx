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
    ShieldCheck,
    Trash2,
    UserCheck,
    Users,
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

type EmployeeOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
};

type DayInfo = {
    day: number;
    dow: string;
    is_sunday: boolean;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type PatrolCheckRow = {
    id: number | null;
    kategori: string;
    is_category_header: boolean;
    no_urut: string | null;
    employee_id: number | null;
    nama: string;
    no_hp: string;
    target: number;
    realisasi: number;
    jadwal: number[];
    keterangan: string;
    sort_order: number;
};

type MetaState = {
    id: number | null;
    doc_number: string | null;
    revision: string | null;
    effective_date: string | null;
    page_number: string | null;
    mengetahui_employee_id: number | null;
    mengetahui_nama: string | null;
    mengetahui_jabatan: string | null;
    mengetahui_signature: string | null;
    disetujui_employee_id: number | null;
    disetujui_nama: string | null;
    disetujui_jabatan: string | null;
    disetujui_signature: string | null;
    dibuat_employee_id: number | null;
    dibuat_nama: string | null;
    dibuat_jabatan: string | null;
    dibuat_signature: string | null;
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
        employees: EmployeeOption[];
    };
    days: DayInfo[];
    target_working_days: number;
    rows: PatrolCheckRow[];
    meta: MetaState;
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
    .print-day-red {
        background-color: #ff0000 !important;
        color: #ffffff !important;
    }
}
`;

export default function PdmJadwalPatrolCheckIndex({
    unit,
    filters,
    options,
    days,
    target_working_days,
    rows: initialRows,
    meta: initialMeta,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PatrolCheckRow[]>(initialRows);
    const [meta, setMeta] = useState<MetaState>(initialMeta);
    const [dirty, setDirty] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    // Modal States
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingRowIndex, setEditingRowIndex] = useState<number | null>(null);

    // Add Form State
    const [newCategory, setNewCategory] = useState('I. HARMES');
    const [newEmployeeId, setNewEmployeeId] = useState<string>('');
    const [newNama, setNewNama] = useState('');
    const [newNoHp, setNewNoHp] = useState('');
    const [newTarget, setNewTarget] = useState(target_working_days);

    // Edit Form State
    const [editForm, setEditForm] = useState({
        kategori: 'I. HARMES',
        no_urut: '',
        employee_id: null as number | null,
        nama: '',
        no_hp: '',
        target: target_working_days,
    });

    const month_name = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    const daysInMonth = days.length;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            '/pdm/jadwal/patrol-check',
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    // Toggle a day checklist for a row
    const handleToggleDay = (rowIndex: number, day: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) {
return r;
}

                const currentJadwal = r.jadwal || [];
                const isMarked = currentJadwal.includes(day);
                const nextJadwal = isMarked
                    ? currentJadwal.filter((d) => d !== day)
                    : [...currentJadwal, day].sort((a, b) => a - b);

                return {
                    ...r,
                    jadwal: nextJadwal,
                    realisasi: nextJadwal.length,
                };
            }),
        );
        setDirty(true);
    };

    // Check all workdays for a row
    const handleCheckAllWorkdays = (rowIndex: number) => {
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
                    jadwal: workdays,
                    realisasi: workdays.length,
                };
            }),
        );
        setDirty(true);
        toast.success('Semua hari kerja personil berhasil diceklis.');
    };

    // Clear all days for a row
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
                    jadwal: [],
                    realisasi: 0,
                };
            }),
        );
        setDirty(true);
        toast.info('Checklist personil berhasil dikosongkan.');
    };

    // Update inline target
    const handleUpdateTarget = (rowIndex: number, target: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => (idx === rowIndex ? { ...r, target: Math.max(0, target) } : r)),
        );
        setDirty(true);
    };

    // Update inline No HP
    const handleUpdateNoHp = (rowIndex: number, noHp: string) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => (idx === rowIndex ? { ...r, no_hp: noHp } : r)),
        );
        setDirty(true);
    };

    // Update inline Nama
    const handleUpdateNama = (rowIndex: number, nama: string) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) => (idx === rowIndex ? { ...r, nama } : r)),
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
        toast.success(`Baris ${targetRow.nama || 'personil'} berhasil dihapus.`);
    };

    // Open Edit Modal
    const openEditRowModal = (rowIndex: number) => {
        const row = rows[rowIndex];

        if (!row) {
return;
}

        setEditingRowIndex(rowIndex);
        setEditForm({
            kategori: row.kategori,
            no_urut: row.no_urut || '',
            employee_id: row.employee_id,
            nama: row.nama,
            no_hp: row.no_hp || '',
            target: row.target,
        });
        setEditDialogOpen(true);
    };

    // Save Edit Row
    const handleSaveEditRow = () => {
        if (editingRowIndex === null || !editForm.nama.trim()) {
return;
}

        setRows((prev) =>
            prev.map((r, idx) =>
                idx === editingRowIndex
                    ? {
                          ...r,
                          kategori: editForm.kategori,
                          no_urut: editForm.no_urut,
                          employee_id: editForm.employee_id,
                          nama: editForm.nama.trim(),
                          no_hp: editForm.no_hp.trim(),
                          target: Math.max(0, editForm.target),
                      }
                    : r,
            ),
        );

        setEditDialogOpen(false);
        setEditingRowIndex(null);
        setDirty(true);
        toast.success('Data personil berhasil diperbarui.');
    };

    // Handle adding a new row
    const handleAddRow = () => {
        if (!newNama.trim()) {
return;
}

        const nextNo =
            rows.filter((r) => r.kategori === newCategory && !r.is_category_header).length + 1;

        const newRow: PatrolCheckRow = {
            id: null,
            kategori: newCategory,
            is_category_header: false,
            no_urut: String(nextNo),
            employee_id: newEmployeeId ? parseInt(newEmployeeId) : null,
            nama: newNama.trim(),
            no_hp: newNoHp.trim(),
            target: newTarget,
            realisasi: 0,
            jadwal: [],
            keterangan: '',
            sort_order: rows.length,
        };

        // Insert after the last item of this category
        let insertIndex = rows.length;

        for (let i = rows.length - 1; i >= 0; i--) {
            if (rows[i].kategori === newCategory) {
                insertIndex = i + 1;
                break;
            }
        }

        const nextRows = [...rows];
        nextRows.splice(insertIndex, 0, newRow);

        setRows(nextRows);
        setAddDialogOpen(false);
        setNewNama('');
        setNewNoHp('');
        setNewEmployeeId('');
        setNewTarget(target_working_days);
        setDirty(true);
        toast.success(`Personil ${newNama} berhasil ditambahkan ke ${newCategory}.`);
    };

    // Select employee in Add modal
    const handleSelectEmployeeAdd = (empIdStr: string) => {
        setNewEmployeeId(empIdStr);

        if (!empIdStr) {
return;
}

        const emp = options.employees.find((e) => String(e.id) === empIdStr);

        if (emp) {
            setNewNama(emp.name);
        }
    };

    // Select employee in Edit modal
    const handleSelectEmployeeEdit = (empIdStr: string) => {
        const empId = empIdStr ? parseInt(empIdStr) : null;

        if (!empId) {
            setEditForm((prev) => ({ ...prev, employee_id: null }));

            return;
        }

        const emp = options.employees.find((e) => e.id === empId);

        if (emp) {
            setEditForm((prev) => ({
                ...prev,
                employee_id: emp.id,
                nama: emp.name,
            }));
        }
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
            '/pdm/jadwal/patrol-check',
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
                    toast.success('Jadwal Piket Patrol Check PdM KIT berhasil disimpan.');
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

        const monthTitle = `${month_name.toUpperCase()} ${filters.year}`;

        // Header Row 1 (0-based row 4)
        const rowH1: (string | number)[] = [
            'No.',
            'Nama',
            'No. Hp',
            ...Array(days.length).fill(monthTitle),
            'TARGET',
            'REALISASI',
            'A. KINERJA',
        ];
        headerRows.push(rowH1);

        // Header Row 2 (0-based row 5)
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

        // Data Rows (0-based row 6 onwards)
        rows.forEach((r) => {
            const jadwalList = r.jadwal || [];
            const realisasi = jadwalList.length;
            const target = r.target;
            const kinerja = target > 0 ? `${Math.round((realisasi / target) * 100)}%` : '0%';

            if (r.is_category_header) {
                const rowCat: (string | number)[] = [
                    r.no_urut || '',
                    r.nama,
                    '',
                    ...Array(days.length).fill(''),
                    '',
                    '',
                    '',
                ];
                headerRows.push(rowCat);
            } else {
                const rowData: (string | number)[] = [
                    r.no_urut || '',
                    r.nama,
                    r.no_hp || '',
                    ...days.map((d) => (jadwalList.includes(d.day) ? 1 : '')),
                    target,
                    realisasi,
                    kinerja,
                ];
                headerRows.push(rowData);
            }
        });

        const { workbook, worksheet: ws } = createSheet(
            'Piket_Patrol_PdM_KIT',
            headerRows,
        );

        const dayStart = 3;
        const dayEnd = dayStart + days.length - 1;
        const colTarget = dayStart + days.length;
        const colRealisasi = colTarget + 1;
        const colKinerja = colTarget + 2;
        const totalCols = colKinerja + 1;
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
return { ...SPECS.cellLeft, fill: baseBg, bold: true };
}

                if (c === 2) {
return { ...SPECS.cell, fill: baseBg };
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

                return { ...SPECS.cell, fill: baseBg, bold: isCat };
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // No
        mergeCells(ws, headerTop, 1, headerBottom, 1); // Nama
        mergeCells(ws, headerTop, 2, headerBottom, 2); // No. Hp
        mergeCells(ws, headerTop, dayStart, headerTop, dayEnd); // Month
        mergeCells(ws, headerTop, colTarget, headerBottom, colTarget); // TARGET
        mergeCells(ws, headerTop, colRealisasi, headerBottom, colRealisasi); // REALISASI
        mergeCells(ws, headerTop, colKinerja, headerBottom, colKinerja); // A. KINERJA

        // Merge category rows horizontally across data columns
        rows.forEach((r, idx) => {
            if (r.is_category_header) {
                const rNum = dataStart + idx;
                mergeCells(ws, rNum, 1, rNum, totalCols - 1);
            }
        });

        const colWidths = [
            5,
            24,
            14,
            ...Array(days.length).fill(3.2),
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
                    'JASA PENDUKUNG TEKNIS 11 SITE',
                    `PLN NP UP KENDARI - ${unit.service_unit_name || unit.name.toUpperCase()}`,
                    'BAGIAN PdM & MATLEV',
                ],
                barTitle: `JADWAL PIKET PATROL CHEK PdM KIT - ${month_name.toUpperCase()} ${filters.year}`,
            },
        );

        await downloadWorkbook(
            workbook,
            `Jadwal_Piket_Patrol_Check_PdM_KIT_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    // Overall KPI statistics
    const stats = useMemo(() => {
        const personnelRows = rows.filter((r) => !r.is_category_header);
        const totalPersonnel = personnelRows.length;
        const totalRealisasi = personnelRows.reduce((acc, r) => acc + (r.jadwal || []).length, 0);
        const totalTarget = personnelRows.reduce((acc, r) => acc + r.target, 0);
        const avgKinerja =
            totalTarget > 0 ? Math.round((totalRealisasi / totalTarget) * 100) : 0;

        return { totalPersonnel, totalRealisasi, totalTarget, avgKinerja };
    }, [rows]);

    return (
        <>
            <Head title={`Jadwal Piket Patrol Check PdM KIT - ${unit.name}`} />
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
                                Jadwal Piket Patrol Check PdM KIT
                            </h1>
                        </div>
                        <p className="mt-0.5 text-xs text-muted-foreground pl-10">
                            Unit: <strong className="text-foreground">{unit.name}</strong> • Periode:{' '}
                            <strong className="text-foreground">
                                {month_name} {filters.year}
                            </strong>{' '}
                            • Matriks Piket Patroli Predictive (HARMES &amp; HARLIS)
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
                                    `/pdm/jadwal/patrol-check/pdf?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`,
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
                            <Users className="size-4 text-primary" />
                            <span className="text-xs text-muted-foreground">Total Personil</span>
                        </div>
                        <div className="mt-1 text-lg font-bold">{stats.totalPersonnel} Org</div>
                    </Card>
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-emerald-600" />
                            <span className="text-xs text-muted-foreground">Total Piket Realisasi</span>
                        </div>
                        <div className="mt-1 text-lg font-bold">{stats.totalRealisasi} Kali</div>
                    </Card>
                    <Card className="py-2.5 px-3">
                        <div className="flex items-center gap-2">
                            <Activity className="size-4 text-amber-600" />
                            <span className="text-xs text-muted-foreground">Target Hari Kerja</span>
                        </div>
                        <div className="mt-1 text-lg font-bold">{target_working_days} Hari</div>
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

                {/* Main Table Card */}
                <div className="rounded-md border border-border bg-card shadow-xs overflow-x-auto">
                    {/* Official Document Header in View */}
                    <div className="border-b border-border p-4 text-center bg-muted/10">
                        <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Jasa Pendukung Teknis 11 Site
                        </div>
                        <div className="text-sm font-semibold uppercase text-foreground">
                            PLN NP UP Kendari - ULPLTD/PLTG/PLTM {unit.name}
                        </div>
                        <div className="text-base font-bold uppercase tracking-tight text-foreground mt-0.5">
                            Jadwal Piket Patrol Chek PdM KIT Bulan {month_name} {filters.year}
                        </div>
                        <div className="text-xs text-muted-foreground uppercase">
                            ULPLTD/PLTG/PLTM {unit.name}
                        </div>
                    </div>

                    <table className="w-full min-w-[1550px] border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-border bg-muted/60 text-muted-foreground">
                                <th rowSpan={2} className="w-10 min-w-10 border-r border-border px-2 py-2 text-center font-bold">
                                    No.
                                </th>
                                <th rowSpan={2} className="min-w-[180px] border-r border-border px-3 py-2 text-left font-bold">
                                    Nama Personil
                                </th>
                                <th rowSpan={2} className="w-28 min-w-28 border-r border-border px-2 py-2 text-center font-bold">
                                    No. Hp
                                </th>
                                <th
                                    colSpan={daysInMonth}
                                    className="border-r border-border px-2 py-1 text-center font-bold uppercase tracking-wider bg-muted/40"
                                >
                                    {month_name} {filters.year}
                                </th>
                                <th rowSpan={2} className="w-14 min-w-14 border-r border-border px-1 py-2 text-center font-bold">
                                    TARGET
                                </th>
                                <th rowSpan={2} className="w-14 min-w-14 border-r border-border px-1 py-2 text-center font-bold">
                                    REALISASI
                                </th>
                                <th rowSpan={2} className="w-16 min-w-16 border-r border-border px-1 py-2 text-center font-bold">
                                    A. KINERJA
                                </th>
                                <th rowSpan={2} className="w-24 min-w-24 px-2 py-2 text-center font-bold no-print">
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
                                const jadwalArr = row.jadwal || [];
                                const realisasi = jadwalArr.length;
                                const target = row.target;
                                const kinerja = target > 0 ? Math.round((realisasi / target) * 100) : 0;

                                if (isCategory) {
                                    return (
                                        <tr
                                            key={`cat-${rIdx}`}
                                            className="border-b border-border bg-muted/50 font-bold text-foreground"
                                        >
                                            <td className="border-r border-border px-2 py-2 text-center">
                                                {row.no_urut}
                                            </td>
                                            <td
                                                colSpan={2 + daysInMonth + 3}
                                                className="border-r border-border px-3 py-2 uppercase tracking-wide text-xs"
                                            >
                                                {row.nama}
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
                                                        title="Tambah personil di kategori ini"
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
                                        {/* No Urut */}
                                        <td className="border-r border-border px-1 py-1.5 text-center text-muted-foreground font-medium">
                                            {row.no_urut}
                                        </td>

                                        {/* Nama */}
                                        <td className="border-r border-border px-2 py-1 font-semibold text-foreground">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={row.nama}
                                                    onChange={(e) => handleUpdateNama(rIdx, e.target.value)}
                                                    placeholder="Nama Teknisi..."
                                                    className="h-6 w-full rounded border border-transparent bg-transparent px-1 text-xs font-semibold text-foreground placeholder:text-muted-foreground/50 hover:border-border focus:border-primary focus:bg-background"
                                                />
                                            ) : (
                                                row.nama || '-'
                                            )}
                                        </td>

                                        {/* No. Hp */}
                                        <td className="border-r border-border px-1 py-1 text-center">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={row.no_hp || ''}
                                                    onChange={(e) => handleUpdateNoHp(rIdx, e.target.value)}
                                                    placeholder="08xxxxxxxx"
                                                    className="h-6 w-full rounded border border-transparent bg-transparent px-1 text-center text-[11px] text-muted-foreground hover:border-border focus:border-primary focus:bg-background"
                                                />
                                            ) : (
                                                <span className="text-[11px] text-muted-foreground">{row.no_hp || '-'}</span>
                                            )}
                                        </td>

                                        {/* Days Checklist (1..31) */}
                                        {days.map((d) => {
                                            const isMarked = jadwalArr.includes(d.day);
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
                                                    title={`Hari ${d.day} (${d.dow}): Klik untuk centang piket patrol`}
                                                >
                                                    <div className="flex h-7 w-8 min-w-8 items-center justify-center">
                                                        {isMarked ? '1' : ''}
                                                    </div>
                                                </td>
                                            );
                                        })}

                                        {/* Target */}
                                        <td className="border-r border-border px-1 py-1 text-center font-medium">
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
                                        <td className="border-r border-border px-2 py-1.5 text-center font-bold text-foreground">
                                            {realisasi}
                                        </td>

                                        {/* A. Kinerja */}
                                        <td className="border-r border-border px-2 py-1.5 text-center font-bold">
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

                                        {/* Quick Actions */}
                                        <td className="px-1 py-1 text-center no-print">
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
                                                            title="Ubah personil"
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

                {/* Table Bottom Action: Tambah Personil */}
                {can_write && (
                    <div className="flex items-center justify-between no-print">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAddDialogOpen(true)}
                            className="gap-1.5 text-xs border-dashed"
                        >
                            <Plus className="size-3.5" />
                            Tambah Personil Piket
                        </Button>
                    </div>
                )}
            </div>

            {/* Modal Tambah Personil */}
            <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Tambah Personil Piket</DialogTitle>
                        <DialogDescription className="text-xs">
                            Pilih teknisi dari daftar pegawai atau ketik nama personil baru.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div>
                            <Label className="text-xs">Kelompok Kategori</Label>
                            <select
                                value={newCategory}
                                onChange={(e) => setNewCategory(e.target.value)}
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="I. HARMES">I. HARMES (Pemeliharaan Mesin)</option>
                                <option value="II. HARLIS">II. HARLIS (Pemeliharaan Listrik)</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-xs">Pilih dari Pegawai Unit (Opsional)</Label>
                            <select
                                value={newEmployeeId}
                                onChange={(e) => handleSelectEmployeeAdd(e.target.value)}
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">-- Ketik Nama Manual / Pilih Pegawai --</option>
                                {options.employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.name} ({emp.position || 'Pegawai'})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label className="text-xs">Nama Personil</Label>
                            <Input
                                value={newNama}
                                onChange={(e) => setNewNama(e.target.value)}
                                placeholder="Nama lengkap personil..."
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">No. Handphone / WhatsApp</Label>
                            <Input
                                value={newNoHp}
                                onChange={(e) => setNewNoHp(e.target.value)}
                                placeholder="Contoh: 081234567890"
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">Target Hari Piket</Label>
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
                        <Button size="sm" onClick={handleAddRow} disabled={!newNama.trim()}>
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Modal Ubah Personil */}
            <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Ubah Data Personil</DialogTitle>
                        <DialogDescription className="text-xs">
                            Perbarui detail kelompok kategori, nomor urut, nama personil, atau kontak.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs">
                        <div>
                            <Label className="text-xs">Kelompok Kategori</Label>
                            <select
                                value={editForm.kategori}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, kategori: e.target.value }))
                                }
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="I. HARMES">I. HARMES (Pemeliharaan Mesin)</option>
                                <option value="II. HARLIS">II. HARLIS (Pemeliharaan Listrik)</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-xs">Pilih dari Pegawai Unit (Opsional)</Label>
                            <select
                                value={editForm.employee_id ? String(editForm.employee_id) : ''}
                                onChange={(e) => handleSelectEmployeeEdit(e.target.value)}
                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">-- Ketik Nama Manual / Pilih Pegawai --</option>
                                {options.employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.name} ({emp.position || 'Pegawai'})
                                    </option>
                                ))}
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
                                    placeholder="1"
                                    className="mt-1 h-8 text-xs text-center"
                                />
                            </div>
                            <div className="col-span-3">
                                <Label className="text-xs">Target Hari</Label>
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
                            <Label className="text-xs">Nama Personil</Label>
                            <Input
                                value={editForm.nama}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, nama: e.target.value }))
                                }
                                placeholder="Nama personil..."
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs">No. Handphone / WhatsApp</Label>
                            <Input
                                value={editForm.no_hp}
                                onChange={(e) =>
                                    setEditForm((prev) => ({ ...prev, no_hp: e.target.value }))
                                }
                                placeholder="08xxxxxxxx"
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
                            disabled={!editForm.nama.trim()}
                        >
                            Simpan Perubahan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PdmJadwalPatrolCheckIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal PdM & Maturity Level', href: '/pdm/jadwal' },
        { title: 'Jadwal Piket Patrol Check PdM KIT', href: '/pdm/jadwal/patrol-check' },
    ],
};
