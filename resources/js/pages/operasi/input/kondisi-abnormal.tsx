import { Head, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Clock,
    Download,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import kondisiAbnormalRoutes from '@/routes/operasi/input/kondisi-abnormal';
import type { IdName } from '@/types';

type AbnormalRow = {
    id?: number;
    no_urut: number;
    uraian_kondisi: string;
    tanggal: string;
    is_abnormal: number;
    durasi_abnormal: number;
    is_gangguan: number;
    durasi_gangguan: number;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: AbnormalRow[];
    summary: {
        total_abnormal_count: number;
        total_abnormal_durasi: number;
        total_gangguan_count: number;
        total_gangguan_durasi: number;
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

export default function KondisiAbnormalInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<AbnormalRow[]>(initialRows);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    useEffect(() => {
        setRows(initialRows);
        setIsDirty(false);
    }, [initialRows]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            kondisiAbnormalRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleRowChange = <K extends keyof AbnormalRow>(
        index: number,
        field: K,
        value: AbnormalRow[K],
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
                uraian_kondisi: '',
                tanggal: '',
                is_abnormal: 0,
                durasi_abnormal: 0,
                is_gangguan: 0,
                durasi_gangguan: 0,
            },
        ]);
        setIsDirty(true);
    };

    const handleClearRow = (index: number) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            uraian_kondisi: '',
            tanggal: '',
            is_abnormal: 0,
            durasi_abnormal: 0,
            is_gangguan: 0,
            durasi_gangguan: 0,
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            kondisiAbnormalRoutes.store().url,
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
        const url = kondisiAbnormalRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const totalAbnormal = rows.filter((r) => r.is_abnormal === 1).length;
    const totalAbnormalDurasi = rows.reduce((acc, r) => acc + (parseFloat(String(r.durasi_abnormal)) || 0), 0);
    const totalGangguan = rows.filter((r) => r.is_gangguan === 1).length;
    const totalGangguanDurasi = rows.reduce((acc, r) => acc + (parseFloat(String(r.durasi_gangguan)) || 0), 0);

    return (
        <>
            <Head title="Input Kondisi Abnormal & Gangguan Pembangkit" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Kondisi Abnormal dan Gangguan Pembangkit"
                    description="Pencatatan dan pemantauan kejadian kondisi abnormal serta gangguan mesin pembangkit beserta durasi kejadian."
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
                            <span className="text-xs font-medium text-muted-foreground">Kejadian Abnormal</span>
                            <AlertTriangle className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalAbnormal} <span className="text-xs font-normal text-muted-foreground">kali</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Kondisi abnormal mesin</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Durasi Abnormal</span>
                            <Clock className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalAbnormalDurasi.toFixed(1)} <span className="text-xs font-normal text-muted-foreground">Jam</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Total waktu abnormal</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Kejadian Gangguan</span>
                            <AlertOctagon className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {totalGangguan} <span className="text-xs font-normal text-muted-foreground">kali</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Trip / gangguan operasi</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Durasi Gangguan</span>
                            <Clock className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {totalGangguanDurasi.toFixed(1)} <span className="text-xs font-normal text-muted-foreground">Jam</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Total waktu gangguan</p>
                    </div>
                </div>

                {/* Table matching Excel template */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="bg-[#ea7315]/10 border-b border-border px-4 py-2.5 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <AlertOctagon className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold uppercase tracking-wider text-foreground">
                                Laporan Kondisi Abnormal dan Gangguan Pembangkit
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
                                    <TableHead rowSpan={2} className="min-w-[280px] font-bold text-foreground border-r border-border">
                                        URAIAN KONDISI
                                    </TableHead>
                                    <TableHead rowSpan={2} className="w-36 text-center font-bold text-foreground border-r border-border">
                                        TANGGAL
                                    </TableHead>
                                    <TableHead colSpan={4} className="text-center font-bold text-foreground border-b border-border">
                                        STATUS
                                    </TableHead>
                                    {can_write && (
                                        <TableHead rowSpan={2} className="w-12 text-center font-bold text-foreground border-l border-border">
                                            AKSI
                                        </TableHead>
                                    )}
                                </TableRow>
                                <TableRow className="bg-[#ea7315]/15 hover:bg-[#ea7315]/15">
                                    <TableHead className="w-24 text-center font-bold text-foreground border-r border-border">
                                        ABNORMAL
                                    </TableHead>
                                    <TableHead className="w-28 text-center font-bold text-foreground border-r border-border">
                                        DURASI (JAM)
                                    </TableHead>
                                    <TableHead className="w-24 text-center font-bold text-foreground border-r border-border">
                                        GANGGUAN
                                    </TableHead>
                                    <TableHead className="w-28 text-center font-bold text-foreground">
                                        DURASI (JAM)
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, index) => (
                                    <TableRow key={row.no_urut} className="hover:bg-muted/40">
                                        <TableCell className="text-center font-bold text-muted-foreground border-r border-border">
                                            {row.no_urut}
                                        </TableCell>
                                        <TableCell className="border-r border-border p-2">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={row.uraian_kondisi}
                                                    onChange={(e) =>
                                                        handleRowChange(index, 'uraian_kondisi', e.target.value)
                                                    }
                                                    className="h-8 text-xs"
                                                    placeholder="Uraian kondisi abnormal / gangguan..."
                                                />
                                            ) : (
                                                <span className="text-xs">{row.uraian_kondisi || '-'}</span>
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
                                                <div className="flex items-center justify-center">
                                                    <Checkbox
                                                        checked={row.is_abnormal === 1}
                                                        onCheckedChange={(checked) =>
                                                            handleRowChange(index, 'is_abnormal', checked ? 1 : 0)
                                                        }
                                                        aria-label="Abnormal"
                                                    />
                                                </div>
                                            ) : (
                                                <span className="font-bold text-xs">
                                                    {row.is_abnormal === 1 ? '1' : ''}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="border-r border-border p-2 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={row.durasi_abnormal || ''}
                                                    onChange={(e) =>
                                                        handleRowChange(
                                                            index,
                                                            'durasi_abnormal',
                                                            parseFloat(e.target.value) || 0,
                                                        )
                                                    }
                                                    className="h-8 text-xs text-center font-mono"
                                                    placeholder="0"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">
                                                    {row.durasi_abnormal > 0 ? row.durasi_abnormal : ''}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="border-r border-border p-2 text-center">
                                            {can_write ? (
                                                <div className="flex items-center justify-center">
                                                    <Checkbox
                                                        checked={row.is_gangguan === 1}
                                                        onCheckedChange={(checked) =>
                                                            handleRowChange(index, 'is_gangguan', checked ? 1 : 0)
                                                        }
                                                        aria-label="Gangguan"
                                                    />
                                                </div>
                                            ) : (
                                                <span className="font-bold text-xs">
                                                    {row.is_gangguan === 1 ? '1' : ''}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-2 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={row.durasi_gangguan || ''}
                                                    onChange={(e) =>
                                                        handleRowChange(
                                                            index,
                                                            'durasi_gangguan',
                                                            parseFloat(e.target.value) || 0,
                                                        )
                                                    }
                                                    className="h-8 text-xs text-center font-mono"
                                                    placeholder="0"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">
                                                    {row.durasi_gangguan > 0 ? row.durasi_gangguan : ''}
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
                                ))}
                                <TableRow className="bg-[#ea7315]/20 font-bold hover:bg-[#ea7315]/20">
                                    <TableCell colSpan={3} className="text-center font-bold border-r border-border">
                                        TOTAL
                                    </TableCell>
                                    <TableCell className="text-center font-bold text-amber-600 dark:text-amber-400 border-r border-border">
                                        {totalAbnormal}
                                    </TableCell>
                                    <TableCell className="text-center font-bold font-mono border-r border-border">
                                        {totalAbnormalDurasi > 0 ? totalAbnormalDurasi.toFixed(1) : 0}
                                    </TableCell>
                                    <TableCell className="text-center font-bold text-rose-600 dark:text-rose-400 border-r border-border">
                                        {totalGangguan}
                                    </TableCell>
                                    <TableCell className="text-center font-bold font-mono">
                                        {totalGangguanDurasi > 0 ? totalGangguanDurasi.toFixed(1) : 0}
                                    </TableCell>
                                    {can_write && <TableCell className="border-l border-border" />}
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    {/* Bottom Instructions and Actions */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-t border-border bg-card p-3">
                        <div className="text-xs text-muted-foreground leading-relaxed">
                            <span className="font-semibold text-foreground">NOTE:</span>
                            <ol className="list-decimal pl-4 mt-0.5 space-y-0.5">
                                <li>KOLOM ABNORMAL DAN GANGGUAN DI ISI CENTANG / ANGKA 1</li>
                                <li>KOLOM DURASI DI ISI JAM</li>
                            </ol>
                        </div>
                        {can_write && (
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
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

KondisiAbnormalInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Kondisi Abnormal & Gangguan', href: kondisiAbnormalRoutes.index() },
    ],
};
