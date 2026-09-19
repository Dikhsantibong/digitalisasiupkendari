import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
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
import jadwal from '@/routes/operasi/jadwal';
import commissioningTest from '@/routes/operasi/jadwal/commissioning-test';
import type { IdName } from '@/types';

type CommissioningRow = {
    id: number | null;
    section: string;
    no_urut: number;
    kegiatan: string;
    status: string | null; // 'ON' | 'OF' | 'SIAP OPERASI' | 'ABNORMAL' | 'TIDAK SIAP OPERASI' | null
    pic: string;
    paraf: string;
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
    employees: string[];
    rows: CommissioningRow[];
    catatan: string;
    can_write: boolean;
};

const STATUS_COLUMNS = [
    { key: 'ON', label: 'ON', width: 'w-12' },
    { key: 'OF', label: 'OF', width: 'w-12' },
    { key: 'SIAP OPERASI', label: 'SIAP OPERASI', width: 'w-24' },
    { key: 'ABNORMAL', label: 'ABNORMAL', width: 'w-20' },
    { key: 'TIDAK SIAP OPERASI', label: 'TIDAK SIAP OPERASI', width: 'w-28' },
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
        font-size: 8px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 3px 2px !important;
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
        background-color: #f3f4f6 !important;
        color: #000000 !important;
        font-weight: bold !important;
        text-align: left !important;
        padding-left: 6px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

export default function HarJadwalCommissioningTestPage({
    unit,
    filters,
    options,
    employees,
    rows: initialRows,
    catatan: initialCatatan,
    can_write,
}: Props) {
    const [rows, setRows] = useState<CommissioningRow[]>(initialRows);
    const [catatan, setCatatan] = useState(initialCatatan);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Dialog state
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newKegiatan, setNewKegiatan] = useState('');
    const [newSection, setNewSection] = useState('PERSIAPAN');

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setCatatan(initialCatatan);
        setDirty(false);
    }

    const monthName = useMemo(() => {
        return OPERASI_MONTHS[filters.month - 1] ?? '';
    }, [filters.month]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            commissioningTest.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Toggle status column
    const toggleStatus = (rowIndex: number, statusVal: string) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r, idx) => {
                if (idx !== rowIndex) return r;
                const newStatus = r.status === statusVal ? null : statusVal;
                return { ...r, status: newStatus };
            }),
        );
        setDirty(true);
    };

    // Update text fields (kegiatan, pic, paraf)
    const updateField = (
        rowIndex: number,
        field: keyof CommissioningRow,
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

    // Add activity
    const handleAddRow = () => {
        if (!newKegiatan.trim()) return;

        setRows((prev) => {
            const sectionItems = prev.filter((r) => r.section === newSection);
            return [
                ...prev,
                {
                    id: null,
                    section: newSection,
                    no_urut: sectionItems.length + 1,
                    kegiatan: newKegiatan.trim(),
                    status: null,
                    pic: '',
                    paraf: '',
                },
            ];
        });
        setDirty(true);
        setIsAddOpen(false);
        setNewKegiatan('');
    };

    // Remove row
    const handleRemoveRow = (rowIndex: number) => {
        if (!can_write) return;
        setRows((prev) => prev.filter((_, idx) => idx !== rowIndex));
        setDirty(true);
    };

    // Reset
    const handleReset = () => {
        setRows(initialRows);
        setCatatan(initialCatatan);
        setDirty(false);
    };

    // Save
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);

        router.post(
            commissioningTest.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                catatan: catatan,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    section: r.section,
                    no_urut: r.no_urut || idx + 1,
                    kegiatan: r.kegiatan,
                    status: r.status,
                    pic: r.pic,
                    paraf: r.paraf,
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

    // Grouping by section
    const groupedSections = useMemo(() => {
        const secMap = new Map<
            string,
            { row: CommissioningRow; originalIndex: number }[]
        >();

        rows.forEach((r, originalIndex) => {
            const sec = r.section || 'PERSIAPAN';
            if (!secMap.has(sec)) {
                secMap.set(sec, []);
            }
            secMap.get(sec)!.push({ row: r, originalIndex });
        });

        const list: {
            section: string;
            items: { row: CommissioningRow; originalIndex: number }[];
        }[] = [];

        for (const [section, items] of secMap.entries()) {
            list.push({ section, items });
        }

        return list;
    }, [rows]);

    // Export Excel
    const handleExportExcel = async () => {
        const headerRows: (string | number)[][] = [[], [], [], []];

        // Column Titles
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
        headerRows.push(rowH1);

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
        headerRows.push(rowH2);

        // Sections & Items
        const sectionRowIdx = new Set<number>();
        groupedSections.forEach((group) => {
            sectionRowIdx.add(headerRows.length);
            headerRows.push([group.section, '', '', '', '', '', '', '', '']);

            group.items.forEach(({ row }) => {
                const st = (row.status || '').toUpperCase();
                const rowData: (string | number)[] = [
                    row.no_urut,
                    row.kegiatan,
                    st === 'ON' ? '✓' : '',
                    st === 'OF' || st === 'OFF' ? '✓' : '',
                    st === 'SIAP OPERASI' ? '✓' : '',
                    st === 'ABNORMAL' ? '✓' : '',
                    st === 'TIDAK SIAP OPERASI' ? '✓' : '',
                    row.pic || '',
                    row.paraf || (row.pic ? '✓' : ''),
                ];
                headerRows.push(rowData);
            });
        });

        headerRows.push([]);
        const catatanHeaderIdx = headerRows.length;
        headerRows.push(['CATATAN']);
        const catatanTextIdx = headerRows.length;
        headerRows.push([
            catatan || 'disesuaikan dengan kondisi peralatan unit/sentral kit',
        ]);

        const { workbook, worksheet: ws } = createSheet(
            'Commissioning_Test',
            headerRows,
        );

        const totalCols = 9;
        const headerTop = 4;
        const headerBottom = 5;
        const dataStart = 6;
        const dataEnd = catatanHeaderIdx - 2; // last item row before the spacer

        const checkCell: StyleSpec = {
            fill: XLSX_COLORS.emeraldSoft,
            bold: true,
            color: XLSX_COLORS.black,
            align: 'center',
            valign: 'center',
            border: true,
        };

        paintSheet(ws, headerRows.length, totalCols, (r, c) => {
            if (r < 4) {
                return null; // document header is drawn separately
            }
            if (r === headerTop || r === headerBottom) {
                return SPECS.headerOrange;
            }

            if (sectionRowIdx.has(r)) {
                return SPECS.category;
            }

            if (r >= dataStart && r <= dataEnd) {
                if (c === 1) {
                    return SPECS.cellLeft;
                }
                if (c >= 2 && c <= 6) {
                    return String(headerRows[r]?.[c] ?? '') === '✓'
                        ? checkCell
                        : SPECS.cell;
                }
                if (c === 7) {
                    return SPECS.cellLeft;
                }
                return SPECS.cell;
            }

            if (r === catatanHeaderIdx) {
                return c === 0 ? SPECS.category : null;
            }
            if (r === catatanTextIdx) {
                return c === 0 ? SPECS.cellLeft : null;
            }

            return null;
        });

        mergeCells(ws, headerTop, 0, headerBottom, 0); // NO
        mergeCells(ws, headerTop, 1, headerBottom, 1); // KEGIATAN
        mergeCells(ws, headerTop, 2, headerTop, 6); // STATUS KESIAPAN PERALATAN
        mergeCells(ws, headerTop, 7, headerTop, 8); // PARAF
        sectionRowIdx.forEach((r) => mergeCells(ws, r, 0, r, totalCols - 1));
        mergeCells(ws, catatanHeaderIdx, 0, catatanHeaderIdx, totalCols - 1);
        mergeCells(ws, catatanTextIdx, 0, catatanTextIdx, totalCols - 1);

        const colWidths = [5, 34, 6, 6, 12, 11, 16, 12, 8];
        setColWidths(ws, colWidths);

        await buildDocumentHeader(
            { workbook, worksheet: ws },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT',
                    `LAPORAN PROJECT ${unit.name.toUpperCase()}`,
                    'LAPORAN COMMISSIONING TEST MESIN PEMBANGKIT',
                ],
                barTitle:
                    'JADWAL PELAKSANAAN COMMISSIONING TEST MESIN PEMBANGKIT',
            },
        );
        await downloadWorkbook(
            workbook,
            `Jadwal_Commissioning_Test_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
        );
    };

    return (
        <>
            <Head title={`Jadwal Commissioning Test — ${unit.name}`} />
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
                                    Jadwal Commissioning Test Mesin
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Jadwal dan checklist pengujian kesiapan
                                peralatan, persiapan, dan paralel generator
                                mesin pembangkit.
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
                            href={`${commissioningTest.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
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
                    {/* Official Document Header matching media_1789474426713.png */}
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

                            {/* Center Title Box (3 rows) */}
                            <div className="col-span-6 text-center text-xs font-bold text-foreground">
                                <div className="border-b border-black py-1.5 tracking-wide uppercase dark:border-border">
                                    JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE
                                    &amp; 6 SITE -KIT
                                </div>
                                <div className="border-b border-black py-1.5 uppercase dark:border-border">
                                    LAPORAN PROJECT {unit.name.toUpperCase()}
                                </div>
                                <div className="py-1.5 uppercase">
                                    JADWAL PELAKSANAAN COMMISSIONING TEST MESIN
                                    PEMBANGKIT
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
                                        className="w-96 min-w-80 border border-black p-2 text-left font-bold"
                                    >
                                        KEGIATAN
                                    </th>
                                    <th
                                        colSpan={5}
                                        className="border border-black p-1.5 text-center font-bold tracking-wider"
                                    >
                                        STATUS KESIAPAN PERALATAN
                                    </th>
                                    <th
                                        colSpan={2}
                                        className="border border-black p-1.5 text-center font-bold tracking-wider"
                                    >
                                        PARAF
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
                                    {STATUS_COLUMNS.map((col) => (
                                        <th
                                            key={col.key}
                                            className={`${col.width} border border-black p-1 text-center text-[11px] font-bold`}
                                        >
                                            {col.label}
                                        </th>
                                    ))}
                                    <th className="w-24 border border-black p-1 text-center font-bold">
                                        PIC
                                    </th>
                                    <th className="w-16 border border-black p-1 text-center font-bold">
                                        PARAF
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {groupedSections.map((group) => (
                                    <Fragment key={group.section}>
                                        {/* Section Header */}
                                        <tr className="print-section-header bg-muted/40 font-extrabold text-foreground">
                                            <td
                                                colSpan={can_write ? 10 : 9}
                                                className="border border-black p-1.5 pl-3 text-left tracking-wider uppercase"
                                            >
                                                {group.section}
                                            </td>
                                        </tr>

                                        {/* Item Rows */}
                                        {group.items.map(
                                            ({ row, originalIndex }) => (
                                                <tr
                                                    key={originalIndex}
                                                    className="transition-colors hover:bg-muted/10"
                                                >
                                                    {/* NO */}
                                                    <td className="border border-black p-1 text-center font-medium">
                                                        {row.no_urut}
                                                    </td>

                                                    {/* KEGIATAN */}
                                                    <td className="border border-black p-1 text-foreground">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={
                                                                    row.kegiatan
                                                                }
                                                                onChange={(e) =>
                                                                    updateField(
                                                                        originalIndex,
                                                                        'kegiatan',
                                                                        e.target
                                                                            .value,
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

                                                    {/* 5 Status Check Columns */}
                                                    {STATUS_COLUMNS.map(
                                                        (col) => {
                                                            const isChecked =
                                                                row.status ===
                                                                col.key;

                                                            return (
                                                                <td
                                                                    key={
                                                                        col.key
                                                                    }
                                                                    onClick={() =>
                                                                        toggleStatus(
                                                                            originalIndex,
                                                                            col.key,
                                                                        )
                                                                    }
                                                                    className={`border border-black text-center text-sm font-bold transition-colors select-none ${
                                                                        isChecked
                                                                            ? 'bg-emerald-50 font-extrabold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                                            : 'hover:bg-muted/40'
                                                                    } ${
                                                                        can_write
                                                                            ? 'cursor-pointer'
                                                                            : ''
                                                                    }`}
                                                                    title={`Status: ${col.label} (Klik untuk pilih)`}
                                                                >
                                                                    {isChecked ? (
                                                                        <span className="text-base font-extrabold text-black">
                                                                            &#10003;
                                                                        </span>
                                                                    ) : (
                                                                        ''
                                                                    )}
                                                                </td>
                                                            );
                                                        },
                                                    )}

                                                    {/* PIC */}
                                                    <td className="border border-black p-0.5">
                                                        {can_write ? (
                                                            <>
                                                                <input
                                                                    type="text"
                                                                    list={`pic-list-${originalIndex}`}
                                                                    value={
                                                                        row.pic
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateField(
                                                                            originalIndex,
                                                                            'pic',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="PIC"
                                                                    className="w-full bg-transparent px-1 py-0.5 text-center text-xs uppercase focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                />
                                                                <datalist
                                                                    id={`pic-list-${originalIndex}`}
                                                                >
                                                                    {employees.map(
                                                                        (
                                                                            emp,
                                                                        ) => (
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
                                                            <span className="block px-1 text-center text-xs uppercase">
                                                                {row.pic || '-'}
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* PARAF */}
                                                    <td className="border border-black p-0.5 text-center">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={
                                                                    row.paraf
                                                                }
                                                                onChange={(e) =>
                                                                    updateField(
                                                                        originalIndex,
                                                                        'paraf',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Paraf"
                                                                className="w-full bg-transparent px-1 py-0.5 text-center text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                            />
                                                        ) : (
                                                            <span className="px-1 text-xs">
                                                                {row.paraf ||
                                                                    '-'}
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Action (No-Print) */}
                                                    {can_write && (
                                                        <td className="no-print border border-black p-0.5 text-center">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                onClick={() =>
                                                                    handleRemoveRow(
                                                                        originalIndex,
                                                                    )
                                                                }
                                                                title="Hapus Kegiatan"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ),
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* CATATAN Box matching media_1789474426713.png */}
                    <div className="border-t border-black">
                        <div className="border-b border-black bg-muted/40 px-3 py-1.5 text-xs font-bold tracking-wider text-foreground uppercase">
                            CATATAN
                        </div>
                        <div className="p-3">
                            {can_write ? (
                                <textarea
                                    value={catatan}
                                    onChange={(e) => {
                                        setCatatan(e.target.value);
                                        setDirty(true);
                                    }}
                                    rows={2}
                                    className="w-full bg-transparent text-center text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                    placeholder="disesuaikan dengan kondisi peralatan unit/sentral kit"
                                />
                            ) : (
                                <div className="py-1 text-center text-xs text-muted-foreground">
                                    {catatan ||
                                        'disesuaikan dengan kondisi peralatan unit/sentral kit'}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Kegiatan */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <CheckCircle2 className="size-5 text-primary" />
                            Tambah Kegiatan Commissioning Test
                        </DialogTitle>
                        <DialogDescription>
                            Tambahkan poin checklist pengujian baru untuk unit{' '}
                            {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label htmlFor="section-select">
                                Bagian / Kategori
                            </Label>
                            <select
                                id="section-select"
                                value={newSection}
                                onChange={(e) => setNewSection(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="PERSIAPAN">PERSIAPAN</option>
                                <option value="PARAREL GENERATOR">
                                    PARAREL GENERATOR
                                </option>
                                <option value="PENGUJIAN LAINNYA">
                                    PENGUJIAN LAINNYA
                                </option>
                            </select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="kegiatan-input">
                                Uraian Kegiatan
                            </Label>
                            <Input
                                id="kegiatan-input"
                                placeholder="Contoh: Periksa sistem governor dan proteksi overspeed"
                                value={newKegiatan}
                                onChange={(e) => setNewKegiatan(e.target.value)}
                                autoFocus
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
                        >
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
