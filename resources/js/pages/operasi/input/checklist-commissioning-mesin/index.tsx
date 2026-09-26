import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
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
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
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
import operasiInput from '@/routes/operasi/input';
import checklistCommissioningMesin from '@/routes/operasi/input/checklist-commissioning-mesin';
import type { IdName } from '@/types';

type ChecklistRow = {
    no: number | string;
    section: string;
    kegiatan: string;
    status: string;
    pic: string;
    paraf: string;
};

type MachineOption = {
    id: number;
    name: string;
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
        tanggal: string;
        machine_id: number | null;
        nama_mesin: string;
    };
    options: {
        units: IdName[];
        machines: MachineOption[];
        status_options: string[];
    };
    rows: ChecklistRow[];
    catatan: string;
    can_write: boolean;
};

const STATUS_LIST = [
    'ON',
    'OF',
    'SIAP OPERASI',
    'ABNORMAL',
    'TIDAK SIAP OPERASI',
] as const;

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
        padding: 2.5px 3px !important;
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
    .print-section-header {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function OperasiChecklistCommissioningMesinIndex({
    unit,
    filters,
    options,
    rows: initialRows,
    catatan: initialCatatan,
    can_write,
}: Props) {
    const [rows, setRows] = useState<ChecklistRow[]>(initialRows);
    const [catatan, setCatatan] = useState<string>(
        initialCatatan ||
            'disesuaikan dengan kondisi peralatan unit/sentral kit',
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    const { can } = usePermissions();

    // Dialog state for adding new row
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newSection, setNewSection] = useState('PERSIAPAN');
    const [newKegiatan, setNewKegiatan] = useState('');
    const [newStatus, setNewStatus] = useState('ON');

    // Navigation filter helper
    const visit = (patch: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        router.get(
            checklistCommissioningMesin.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    // Toggle status cell for a row
    const handleToggleStatus = (rowIndex: number, targetStatus: string) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const updated = [...prev];
            const current = updated[rowIndex];
            const newSt = current.status === targetStatus ? '' : targetStatus;
            updated[rowIndex] = { ...current, status: newSt };

            return updated;
        });
        setDirty(true);
    };

    // Update field in row
    const handleUpdateRow = (
        rowIndex: number,
        field: keyof ChecklistRow,
        value: string,
    ) => {
        if (!can_write) {
return;
}

        setRows((prev) => {
            const updated = [...prev];
            updated[rowIndex] = { ...updated[rowIndex], [field]: value };

            return updated;
        });
        setDirty(true);
    };

    // Add row
    const handleAddRow = () => {
        if (!newKegiatan.trim()) {
return;
}

        setRows((prev) => [
            ...prev,
            {
                no: prev.length + 1,
                section: newSection.trim() || 'PERSIAPAN',
                kegiatan: newKegiatan.trim(),
                status: newStatus,
                pic: '',
                paraf: '',
            },
        ]);
        setNewKegiatan('');
        setIsAddOpen(false);
        setDirty(true);
    };

    // Delete row
    const handleDeleteRow = (rowIndex: number) => {
        if (!can_write) {
return;
}

        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
    };

    // Reset
    const handleReset = () => {
        setRows(initialRows);
        setCatatan(
            initialCatatan ||
                'disesuaikan dengan kondisi peralatan unit/sentral kit',
        );
        setDirty(false);
    };

    // Save
    const handleSave = () => {
        if (!can_write) {
return;
}

        setSaving(true);

        router.post(
            checklistCommissioningMesin.store().url,
            {
                unit_id: filters.unit_id,
                tanggal: filters.tanggal,
                machine_id: filters.machine_id,
                nama_mesin: filters.nama_mesin,
                rows,
                catatan,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Export Excel (.xlsx) using ExcelJS
    const handleExportExcel = async () => {
        const sheetData: (string | number)[][] = [[], [], [], []];

        // Header Row 1
        const rowH1: (string | number)[] = [
            'NO',
            'KEGIATAN',
            'STATUS KESIAPAN PERALATAN',
            '',
            '',
            '',
            '',
            'PARAF',
            '',
        ];
        sheetData.push(rowH1);

        // Header Row 2
        const rowH2: (string | number)[] = [
            '',
            '',
            'ON',
            'OF',
            'SIAP OPERASI',
            'ABNORMAL',
            'TIDAK SIAP OPERASI',
            'PIC',
            'PARAF',
        ];
        sheetData.push(rowH2);

        const totalCols = 9;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;

        let currentRowIndex = dataStart;
        const sectionRowIndices: number[] = [];
        let currentSection: string | null = null;

        rows.forEach((r) => {
            if (r.section !== currentSection) {
                currentSection = r.section;
                const secRow: (string | number)[] = [currentSection];

                for (let c = 1; c < totalCols; c++) {
                    secRow.push('');
                }

                sheetData.push(secRow);
                sectionRowIndices.push(currentRowIndex);
                currentRowIndex++;
            }

            const st = r.status || '';
            const row: (string | number)[] = [
                r.no,
                r.kegiatan,
                st === 'ON' ? '✓' : '',
                st === 'OF' ? '✓' : '',
                st === 'SIAP OPERASI' ? '✓' : '',
                st === 'ABNORMAL' ? '✓' : '',
                st === 'TIDAK SIAP OPERASI' ? '✓' : '',
                r.pic || '',
                r.paraf || '',
            ];
            sheetData.push(row);
            currentRowIndex++;
        });

        // Catatan
        const catatanHeaderIdx = currentRowIndex;
        const catHeaderRow: (string | number)[] = ['CATATAN'];

        for (let c = 1; c < totalCols; c++) {
            catHeaderRow.push('');
        }

        sheetData.push(catHeaderRow);
        currentRowIndex++;

        sheetData.push([catatan || '']);
        const catatanBodyIdx = currentRowIndex;
        currentRowIndex++;

        const { workbook, worksheet: ws } = createSheet(
            'Checklist Comm Mesin',
            sheetData,
        );

        paintSheet(ws, sheetData.length, totalCols, (r, c) => {
            if (r < 4) {
return null;
}

            if (r === headerTop || r === headerBottom) {
                return SPECS.headerOrange;
            }

            if (sectionRowIndices.includes(r) || r === catatanHeaderIdx) {
                return SPECS.category;
            }

            if (r === catatanBodyIdx) {
                return SPECS.cell;
            }

            if (r >= dataStart && r < catatanHeaderIdx) {
                if (c === 0) {
return SPECS.labelCenter;
}

                if (c === 1) {
return SPECS.label;
}

                if (c >= 2 && c <= 6) {
                    return {
                        bold: true,
                        align: 'center',
                        valign: 'center',
                        border: true,
                    };
                }

                return SPECS.cell;
            }

            return null;
        });

        // Merges
        mergeCells(ws, headerTop, 0, headerBottom, 0); // NO
        mergeCells(ws, headerTop, 1, headerBottom, 1); // KEGIATAN
        mergeCells(ws, headerTop, 2, headerTop, 6); // STATUS KESIAPAN PERALATAN
        mergeCells(ws, headerTop, 7, headerTop, 8); // PARAF

        sectionRowIndices.forEach((sIdx) => {
            mergeCells(ws, sIdx, 0, sIdx, totalCols - 1);
        });

        mergeCells(ws, catatanHeaderIdx, 0, catatanHeaderIdx, totalCols - 1);
        mergeCells(ws, catatanBodyIdx, 0, catatanBodyIdx, totalCols - 1);

        const colWidths = [4, 40, 7, 7, 14, 12, 16, 12, 8];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'CHECKLIST COMMISSIONING TEST MESIN PEMBANGKIT',
                ],
                barTitle: `CHECKLIST COMMISSIONING TEST MESIN — ${filters.nama_mesin.toUpperCase()} (${filters.tanggal})`,
            },
        );

        const safeUnit = unit.name.replace(/\s+/g, '_');
        const safeMesin = filters.nama_mesin.replace(/\s+/g, '_');
        const filename = `Checklist_Commissioning_Mesin_${safeUnit}_${safeMesin}_${filters.tanggal}.xlsx`;
        await downloadWorkbook(workbook, filename);
    };

    return (
        <>
            <Head
                title={`Checklist Commissioning Test Mesin - ${filters.nama_mesin} - ${unit.name}`}
            />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Top Action Bar */}
                <div className="no-print flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-wrap items-center gap-3">
                        {can('operasi.input.view') && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get(operasiInput.index().url)
                                }
                                className="gap-2"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali ke Input Operasi
                            </Button>
                        )}
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Checklist Commissioning Test Mesin
                            </h1>
                            <Badge
                                variant="secondary"
                                className="font-semibold"
                            >
                                {unit.name}
                            </Badge>
                            <Badge variant="outline" className="bg-primary/5">
                                {filters.nama_mesin}
                            </Badge>
                            <Badge variant="outline">{filters.tanggal}</Badge>
                            {dirty && (
                                <Badge
                                    variant="destructive"
                                    className="animate-pulse"
                                >
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
                                    onClick={() => setIsAddOpen(true)}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Kegiatan
                                </Button>
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
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={!dirty || saving}
                                    className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Form'}
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
                                    checklistCommissioningMesin.pdf({
                                        query: {
                                            unit_id: filters.unit_id,
                                            tanggal: filters.tanggal,
                                            machine_id: filters.machine_id,
                                            nama_mesin: filters.nama_mesin,
                                        },
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
                        className="w-56"
                    />
                    <OperasiSelect
                        label="Mesin Pembangkit"
                        value={filters.nama_mesin}
                        onChange={(value) => {
                            const matched = options.machines.find(
                                (m) => m.name === value,
                            );
                            visit({
                                nama_mesin: value,
                                machine_id: matched ? matched.id : null,
                            });
                        }}
                        options={options.machines.map((m) => ({
                            value: m.name,
                            label: m.name,
                        }))}
                        className="w-52"
                    />
                    <div className="flex flex-col gap-1.5">
                        <Label
                            htmlFor="tanggal"
                            className="text-xs font-medium"
                        >
                            Tanggal Kegiatan
                        </Label>
                        <Input
                            id="tanggal"
                            type="date"
                            value={filters.tanggal}
                            onChange={(e) => visit({ tanggal: e.target.value })}
                            className="h-9 w-44 text-xs"
                        />
                    </div>
                    <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                        <Info className="size-4" />
                        <span>
                            Klik pada kolom status kesiapan untuk menandai
                            centang (ON, OF, SIAP OPERASI, dsb).
                        </span>
                    </div>
                </div>

                {/* Main Checklist Container */}
                <div className="print-container overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm">
                    {/* Official Document Header matching media_1790360567629.jpg */}
                    <div className="mb-3 grid grid-cols-12 items-stretch border-2 border-black dark:border-white">
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
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6
                                SITE -KIT
                            </div>
                            <div className="border-b border-black py-1 text-xs font-bold tracking-wide uppercase dark:border-white">
                                LAPORAN PROJECT {unit.name}
                            </div>
                            <div className="py-1 text-xs font-bold tracking-wide text-foreground uppercase">
                                CHECKLIST COMMISSIONING TEST MESIN PEMBANGKIT
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

                    {/* Sub-bar */}
                    <div className="mb-2 flex items-center justify-between text-xs font-bold uppercase">
                        <span className="border border-black bg-muted/20 px-3 py-1 dark:border-white">
                            MESIN: {filters.nama_mesin}
                        </span>
                        <span className="border border-black bg-muted/20 px-3 py-1 dark:border-white">
                            TANGGAL: {filters.tanggal}
                        </span>
                    </div>

                    {/* Table */}
                    {compact ? (
                        <div className="flex flex-col gap-3">
                            {rows.length === 0 && (
                                <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                                    Belum ada kegiatan. Tekan Tambah Kegiatan.
                                </p>
                            )}
                            {Array.from(
                                new Set(rows.map((row) => row.section)),
                            ).map((section) => (
                                <div
                                    key={section}
                                    className="flex flex-col gap-2 rounded-xl border border-border bg-background p-3"
                                >
                                    <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {section}
                                    </p>
                                    {rows.map((row, rIdx) =>
                                        row.section !== section ? null : (
                                            <div
                                                key={`${row.no}-${rIdx}`}
                                                className="flex flex-col gap-2 border-t border-border pt-2 first-of-type:border-t-0 first-of-type:pt-0"
                                            >
                                                <p className="text-[13.5px] text-foreground">
                                                    {row.kegiatan}
                                                </p>
                                                <ChoiceChips
                                                    options={[...STATUS_LIST]}
                                                    value={row.status}
                                                    onChange={(value) =>
                                                        handleToggleStatus(
                                                            rIdx,
                                                            value || row.status,
                                                        )
                                                    }
                                                    disabled={!can_write}
                                                />
                                                <div className="flex items-end gap-2">
                                                    <label className="flex flex-1 flex-col gap-1 text-[12px] text-muted-foreground">
                                                        PIC
                                                        <Input
                                                            value={row.pic}
                                                            onChange={(e) =>
                                                                handleUpdateRow(
                                                                    rIdx,
                                                                    'pic',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                        />
                                                    </label>
                                                    {can_write && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleDeleteRow(
                                                                    rIdx,
                                                                )
                                                            }
                                                            className="text-destructive"
                                                            aria-label="Hapus kegiatan"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            ))}
                            {can_write && (
                                <Button
                                    variant="outline"
                                    onClick={() => setIsAddOpen(true)}
                                    className="self-start"
                                >
                                    <Plus className="size-4" />
                                    Tambah Kegiatan
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="print-table w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                                <thead>
                                    <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                        <th
                                            rowSpan={2}
                                            className="border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                            style={{ width: '35px' }}
                                        >
                                            NO
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="border border-black px-3 py-1.5 text-left text-[11px] dark:border-white"
                                            style={{ minWidth: '260px' }}
                                        >
                                            KEGIATAN
                                        </th>
                                        <th
                                            colSpan={5}
                                            className="border border-black px-2 py-1 text-[11px] uppercase dark:border-white"
                                        >
                                            STATUS KESIAPAN PERALATAN
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="border border-black px-2 py-1 text-[11px] uppercase dark:border-white"
                                        >
                                            PARAF
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="no-print border border-black px-2 py-1.5 text-[11px] dark:border-white"
                                            style={{ width: '40px' }}
                                        >
                                            AKSI
                                        </th>
                                    </tr>
                                    <tr className="print-orange-header bg-[#ed7d31] font-bold text-black">
                                        <th
                                            className="border border-black px-1.5 py-1 text-[10px] dark:border-white"
                                            style={{ width: '50px' }}
                                        >
                                            ON
                                        </th>
                                        <th
                                            className="border border-black px-1.5 py-1 text-[10px] dark:border-white"
                                            style={{ width: '50px' }}
                                        >
                                            OF
                                        </th>
                                        <th
                                            className="border border-black px-2 py-1 text-[10px] dark:border-white"
                                            style={{ width: '90px' }}
                                        >
                                            SIAP OPERASI
                                        </th>
                                        <th
                                            className="border border-black px-2 py-1 text-[10px] dark:border-white"
                                            style={{ width: '80px' }}
                                        >
                                            ABNORMAL
                                        </th>
                                        <th
                                            className="border border-black px-2 py-1 text-[10px] dark:border-white"
                                            style={{ width: '110px' }}
                                        >
                                            TIDAK SIAP OPERASI
                                        </th>
                                        <th
                                            className="border border-black px-2 py-1 text-[10px] dark:border-white"
                                            style={{ width: '90px' }}
                                        >
                                            PIC
                                        </th>
                                        <th
                                            className="border border-black px-2 py-1 text-[10px] dark:border-white"
                                            style={{ width: '60px' }}
                                        >
                                            PARAF
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(() => {
                                        let currentSection: string | null =
                                            null;

                                        return rows.map((r, rIdx) => {
                                            const isNewSection =
                                                r.section !== currentSection;

                                            if (isNewSection) {
                                                currentSection = r.section;
                                            }

                                            return (
                                                <Fragment
                                                    key={`row-${r.no}-${rIdx}`}
                                                >
                                                    {isNewSection && (
                                                        <tr className="print-section-header bg-muted/60 font-bold text-foreground">
                                                            <td
                                                                colSpan={10}
                                                                className="border border-black px-3 py-1.5 text-left text-xs font-bold uppercase dark:border-white"
                                                            >
                                                                {r.section}
                                                            </td>
                                                        </tr>
                                                    )}

                                                    <tr className="hover:bg-muted/30">
                                                        <td className="border border-black px-1 py-1 font-medium dark:border-white">
                                                            {r.no}
                                                        </td>
                                                        <td className="border border-black px-2 py-1 text-left font-medium dark:border-white">
                                                            {can_write ? (
                                                                <Input
                                                                    value={
                                                                        r.kegiatan
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateRow(
                                                                            rIdx,
                                                                            'kegiatan',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    className="h-7 text-xs font-normal"
                                                                />
                                                            ) : (
                                                                <span>
                                                                    {r.kegiatan}
                                                                </span>
                                                            )}
                                                        </td>

                                                        {/* 5 Status Columns */}
                                                        {STATUS_LIST.map(
                                                            (st) => {
                                                                const isSelected =
                                                                    r.status ===
                                                                    st;

                                                                return (
                                                                    <td
                                                                        key={st}
                                                                        onClick={() =>
                                                                            handleToggleStatus(
                                                                                rIdx,
                                                                                st,
                                                                            )
                                                                        }
                                                                        className={`cursor-pointer border border-black px-1 py-1 text-center font-bold transition-colors dark:border-white ${
                                                                            isSelected
                                                                                ? 'bg-amber-100 text-black dark:bg-amber-900/60 dark:text-amber-100'
                                                                                : 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20'
                                                                        }`}
                                                                        title={`Klik untuk tandai ${st}`}
                                                                    >
                                                                        {isSelected ? (
                                                                            <Check className="mx-auto size-4 font-bold text-black dark:text-white" />
                                                                        ) : (
                                                                            ''
                                                                        )}
                                                                    </td>
                                                                );
                                                            },
                                                        )}

                                                        {/* PIC */}
                                                        <td className="border border-black p-1 dark:border-white">
                                                            {can_write ? (
                                                                <Input
                                                                    value={
                                                                        r.pic ||
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateRow(
                                                                            rIdx,
                                                                            'pic',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Nama PIC"
                                                                    className="h-7 text-center text-xs"
                                                                />
                                                            ) : (
                                                                <span>
                                                                    {r.pic}
                                                                </span>
                                                            )}
                                                        </td>

                                                        {/* Paraf */}
                                                        <td className="border border-black p-1 dark:border-white">
                                                            {can_write ? (
                                                                <Input
                                                                    value={
                                                                        r.paraf ||
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateRow(
                                                                            rIdx,
                                                                            'paraf',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Paraf"
                                                                    className="h-7 text-center text-xs"
                                                                />
                                                            ) : (
                                                                <span>
                                                                    {r.paraf}
                                                                </span>
                                                            )}
                                                        </td>

                                                        {/* Action */}
                                                        <td className="no-print border border-black px-1 py-1 dark:border-white">
                                                            {can_write && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        handleDeleteRow(
                                                                            rIdx,
                                                                        )
                                                                    }
                                                                    className="size-7 text-destructive hover:bg-destructive/10"
                                                                    title="Hapus Baris Kegiatan"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                </Fragment>
                                            );
                                        });
                                    })()}

                                    {/* CATATAN Section */}
                                    <tr className="print-section-header bg-muted/60 font-bold text-foreground">
                                        <td
                                            colSpan={10}
                                            className="border border-black px-3 py-1.5 text-left text-xs font-bold uppercase dark:border-white"
                                        >
                                            CATATAN
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Catatan Box */}
                    <div className="mt-2 space-y-1">
                        {can_write ? (
                            <Textarea
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                placeholder="disesuaikan dengan kondisi peralatan unit/sentral kit"
                                className="min-h-16 text-xs"
                            />
                        ) : (
                            <div className="rounded-md border border-border bg-muted/10 p-3 text-xs text-muted-foreground">
                                {catatan || '-'}
                            </div>
                        )}
                    </div>
                </div>

                {/* Dialog Tambah Kegiatan */}
                <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Plus className="size-5 text-primary" />
                                Tambah Kegiatan Commissioning Test
                            </DialogTitle>
                            <DialogDescription>
                                Masukkan nama kegiatan pemeriksaan kesiapan
                                peralatan yang akan diuji.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-2">
                            <div className="space-y-2">
                                <Label htmlFor="new_section">
                                    Bagian / Kategori
                                </Label>
                                <Input
                                    id="new_section"
                                    value={newSection}
                                    onChange={(e) =>
                                        setNewSection(e.target.value)
                                    }
                                    placeholder="Contoh: PERSIAPAN atau PARAREL GENERATOR"
                                    className="uppercase"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new_kegiatan">
                                    Uraian Kegiatan
                                </Label>
                                <Textarea
                                    id="new_kegiatan"
                                    value={newKegiatan}
                                    onChange={(e) =>
                                        setNewKegiatan(e.target.value)
                                    }
                                    placeholder="Contoh: Pastikan kondisi PMT Trafo dalam kondisi OPEN"
                                    className="min-h-16 text-xs"
                                />
                            </div>
                            <div>
                                <OperasiSelect
                                    label="Status Awal"
                                    value={newStatus}
                                    onChange={setNewStatus}
                                    options={STATUS_LIST.map((st) => ({
                                        value: st,
                                        label: st,
                                    }))}
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
                                onClick={handleAddRow}
                                disabled={!newKegiatan.trim()}
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

OperasiChecklistCommissioningMesinIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Checklist Commissioning Mesin', href: '#' },
    ],
};
