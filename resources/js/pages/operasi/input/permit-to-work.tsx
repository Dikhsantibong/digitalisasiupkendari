import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Download,
    FileCheck,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import permitToWorkRoutes from '@/routes/operasi/input/permit-to-work';
import type { IdName } from '@/types';

type PtwRow = {
    id?: number;
    no_urut: number;
    uraian: string;
    tanggal: string;
    status: 'open' | 'close';
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: PtwRow[];
    summary: {
        total_ptw: number;
        total_open: number;
        total_close: number;
        completion_rate: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    can_write: boolean;
};

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function PermitToWorkInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PtwRow[]>(initialRows);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    useEffect(() => {
        setRows(initialRows);
        setIsDirty(false);
    }, [initialRows]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            permitToWorkRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleRowChange = <K extends keyof PtwRow>(
        index: number,
        field: K,
        value: PtwRow[K],
    ) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            [field]: value,
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleAddRow = () => {
        const nextNo = rows.length + 1;
        setRows([
            ...rows,
            {
                no_urut: nextNo,
                uraian: '',
                tanggal: '',
                status: 'open',
            },
        ]);
        setIsDirty(true);
    };

    const handleClearRow = (index: number) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            uraian: '',
            tanggal: '',
            status: 'open',
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            permitToWorkRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handlePdf = () => {
        const url = permitToWorkRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const activeRows = rows.filter((r) => r.uraian.trim() !== '' || r.tanggal !== '');
    const totalOpen = activeRows.filter((r) => r.status === 'open').length;
    const totalClose = activeRows.filter((r) => r.status === 'close').length;
    const totalPtw = activeRows.length;
    const completionRate = totalPtw > 0 ? Math.round((totalClose / totalPtw) * 100) : 0;

    return (
        <>
            <Head title="Input Laporan Permit to Work (PTW)" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Permit to Work (PTW) Pembangkit"
                    description="Pencatatan dan pemantauan status izin kerja (Permit to Work) untuk kegiatan pemeliharaan dan operasional pembangkit."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <>
                                    <Button variant="outline" onClick={handleAddRow} className="gap-1.5">
                                        <Plus className="size-4" />
                                        Tambah Baris
                                    </Button>
                                    <Button
                                        onClick={handleSave}
                                        disabled={saving || !isDirty}
                                        className="gap-2"
                                    >
                                        <Save className="size-4" />
                                        {saving ? 'Menyimpan...' : 'Simpan Data'}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Total PTW Terbit</span>
                            <FileCheck className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-foreground">{totalPtw}</div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Dokumen izin kerja aktif</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Status Open</span>
                            <Clock className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalOpen}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Pekerjaan masih berjalan</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Status Close</span>
                            <CheckCircle2 className="size-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {totalClose}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Pekerjaan telah selesai</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Tingkat Penyelesaian</span>
                            <CheckCircle2 className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-primary">
                            {completionRate}%
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Rasio PTW close</p>
                    </div>
                </div>

                {/* Table matching Excel template */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="bg-[#ea7315]/10 border-b border-border px-4 py-2.5 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <FileCheck className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold uppercase tracking-wider text-foreground">
                                Laporan PTW Pembangkit
                            </span>
                        </div>
                        {isDirty && (
                            <span className="text-xs font-medium text-amber-600 dark:text-amber-400 animate-pulse">
                                ● Terdapat perubahan belum disimpan
                            </span>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#ea7315]/15 hover:bg-[#ea7315]/15">
                                    <TableHead rowSpan={2} className="w-14 text-center font-bold text-foreground border-r border-border">
                                        NO
                                    </TableHead>
                                    <TableHead rowSpan={2} className="min-w-[320px] font-bold text-foreground border-r border-border">
                                        URAIAN
                                    </TableHead>
                                    <TableHead rowSpan={2} className="w-40 text-center font-bold text-foreground border-r border-border">
                                        TANGGAL
                                    </TableHead>
                                    <TableHead colSpan={2} className="text-center font-bold text-foreground border-b border-border">
                                        STATUS
                                    </TableHead>
                                    {can_write && (
                                        <TableHead rowSpan={2} className="w-14 text-center font-bold text-foreground border-l border-border">
                                            AKSI
                                        </TableHead>
                                    )}
                                </TableRow>
                                <TableRow className="bg-[#ea7315]/15 hover:bg-[#ea7315]/15">
                                    <TableHead className="w-28 text-center font-bold text-foreground border-r border-border">
                                        OPEN
                                    </TableHead>
                                    <TableHead className="w-28 text-center font-bold text-foreground">
                                        CLOSE
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, index) => {
                                    const hasData = row.uraian.trim() !== '' || row.tanggal !== '';
                                    const isOpen = hasData && row.status === 'open';
                                    const isClose = hasData && row.status === 'close';

                                    return (
                                        <TableRow key={row.no_urut} className="hover:bg-muted/40">
                                            <TableCell className="text-center font-bold text-muted-foreground border-r border-border">
                                                {row.no_urut}
                                            </TableCell>
                                            <TableCell className="border-r border-border p-2">
                                                {can_write ? (
                                                    <Input
                                                        type="text"
                                                        value={row.uraian}
                                                        onChange={(e) =>
                                                            handleRowChange(index, 'uraian', e.target.value)
                                                        }
                                                        className="h-8 text-xs"
                                                        placeholder="Uraian pekerjaan PTW..."
                                                    />
                                                ) : (
                                                    <span className="text-xs">{row.uraian || '-'}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="border-r border-border p-2 text-center">
                                                {can_write ? (
                                                    <Input
                                                        type="date"
                                                        value={row.tanggal}
                                                        onChange={(e) =>
                                                            handleRowChange(index, 'tanggal', e.target.value)
                                                        }
                                                        className="h-8 text-xs text-center"
                                                    />
                                                ) : (
                                                    <span className="text-xs">{row.tanggal || '-'}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="border-r border-border p-2 text-center">
                                                {can_write ? (
                                                    <label className="inline-flex items-center gap-1.5 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name={`status-${row.no_urut}`}
                                                            checked={row.status === 'open'}
                                                            onChange={() => handleRowChange(index, 'status', 'open')}
                                                            className="accent-amber-500 size-4 cursor-pointer"
                                                        />
                                                        <span className="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                                            {isOpen ? '✓' : ''}
                                                        </span>
                                                    </label>
                                                ) : (
                                                    <span className="font-bold text-xs text-amber-600 dark:text-amber-400">
                                                        {isOpen ? '✓' : ''}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="p-2 text-center">
                                                {can_write ? (
                                                    <label className="inline-flex items-center gap-1.5 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name={`status-${row.no_urut}`}
                                                            checked={row.status === 'close'}
                                                            onChange={() => handleRowChange(index, 'status', 'close')}
                                                            className="accent-emerald-500 size-4 cursor-pointer"
                                                        />
                                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                                            {isClose ? '✓' : ''}
                                                        </span>
                                                    </label>
                                                ) : (
                                                    <span className="font-bold text-xs text-emerald-600 dark:text-emerald-400">
                                                        {isClose ? '✓' : ''}
                                                    </span>
                                                )}
                                            </TableCell>
                                            {can_write && (
                                                <TableCell className="border-l border-border p-1 text-center">
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7 text-muted-foreground hover:text-destructive"
                                                        onClick={() => handleClearRow(index)}
                                                        title="Kosongkan Baris"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                                <TableRow className="bg-[#ea7315]/20 font-bold hover:bg-[#ea7315]/20">
                                    <TableCell colSpan={3} className="text-center font-bold border-r border-border">
                                        TOTAL
                                    </TableCell>
                                    <TableCell className="text-center font-bold text-amber-600 dark:text-amber-400 border-r border-border">
                                        {totalOpen}
                                    </TableCell>
                                    <TableCell className="text-center font-bold text-emerald-600 dark:text-emerald-400">
                                        {totalClose}
                                    </TableCell>
                                    {can_write && <TableCell className="border-l border-border" />}
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    {can_write && (
                        <div className="flex items-center justify-between border-t border-border bg-card p-3">
                            <span className="text-xs text-muted-foreground">
                                * Pilih status OPEN untuk pekerjaan berjalan atau CLOSE untuk pekerjaan selesai
                            </span>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={handleAddRow} className="gap-1.5">
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    onClick={handleSave}
                                    disabled={saving || !isDirty}
                                    size="sm"
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Data'}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

PermitToWorkInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Permit to Work', href: permitToWorkRoutes.index() },
    ],
};
