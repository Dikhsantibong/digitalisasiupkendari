import { Head, router } from '@inertiajs/react';
import {
    Download,
    Fuel,
    Save,
    TrendingDown,
    Truck,
    Warehouse,
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
import harInput from '@/routes/operasi/input';
import resourcePembangkitRoutes from '@/routes/operasi/input/resource-pembangkit';
import type { IdName } from '@/types';

type ResourceRow = {
    tanggal: number;
    stok_awal: number;
    pemakaian: number;
    pengiriman: number;
    stok_akhir: number;
    keterangan?: string;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: ResourceRow[];
    summary: {
        total_pemakaian: number;
        total_pengiriman: number;
        latest_stok_akhir: number;
        days_count: number;
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

export default function ResourcePembangkitInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<ResourceRow[]>(initialRows);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    useEffect(() => {
        setRows(initialRows);
        setIsDirty(false);
    }, [initialRows]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            resourcePembangkitRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleCellChange = (
        index: number,
        field: 'stok_awal' | 'pemakaian' | 'pengiriman',
        value: string,
    ) => {
        const numVal = parseFloat(value) || 0;
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            [field]: numVal,
        };

        // Recalculate stok_akhir for this row
        const awal = updated[index].stok_awal;
        const pakai = updated[index].pemakaian;
        const kirim = updated[index].pengiriman;
        updated[index].stok_akhir = Math.max(0, awal - pakai + kirim);

        // Auto cascade to next days if next day stok_awal is 0 or matches previous
        for (let i = index + 1; i < updated.length; i++) {
            if (updated[i].stok_awal === 0 || updated[i].stok_awal === updated[i - 1].stok_akhir) {
                updated[i].stok_awal = updated[i - 1].stok_akhir;
                updated[i].stok_akhir = Math.max(
                    0,
                    updated[i].stok_awal - updated[i].pemakaian + updated[i].pengiriman,
                );
            }
        }

        setRows(updated);
        setIsDirty(true);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            resourcePembangkitRoutes.store().url,
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
        const url = resourcePembangkitRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const totalPemakaian = rows.reduce((acc, r) => acc + (r.pemakaian || 0), 0);
    const totalPengiriman = rows.reduce((acc, r) => acc + (r.pengiriman || 0), 0);
    const lastRowWithData = [...rows]
        .reverse()
        .find((r) => r.stok_akhir > 0 || r.pemakaian > 0 || r.pengiriman > 0);
    const latestStok = lastRowWithData ? lastRowWithData.stok_akhir : 0;

    return (
        <>
            <Head title="Input Resource Pembangkit — BBM" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Resource Pembangkit"
                    description="Pencatatan dan pemantauan harian stok awal, pemakaian, penerimaan, dan stok akhir BBM pembangkit."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={handleSave}
                                    disabled={saving || !isDirty}
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Data'}
                                </Button>
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
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Total Pemakaian BBM</span>
                            <TrendingDown className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalPemakaian.toLocaleString('id-ID')} <span className="text-sm font-normal text-muted-foreground">Liter</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Total konsumsi bulan ini</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Total Pengiriman BBM</span>
                            <Truck className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-primary">
                            {totalPengiriman.toLocaleString('id-ID')} <span className="text-sm font-normal text-muted-foreground">Liter</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Penerimaan dari supplier</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Stok Akhir Saat Ini</span>
                            <Warehouse className="size-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {latestStok.toLocaleString('id-ID')} <span className="text-sm font-normal text-muted-foreground">Liter</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Posisi sisa stok tangki</p>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="bg-[#ea7315]/10 border-b border-border px-4 py-2.5 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Fuel className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold uppercase tracking-wider text-foreground">
                                Laporan BBM Pembangkit
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
                                    <TableHead className="w-16 text-center font-bold text-foreground">TGL</TableHead>
                                    <TableHead className="min-w-[150px] text-right font-bold text-foreground">STOK AWAL (L)</TableHead>
                                    <TableHead className="min-w-[150px] text-right font-bold text-foreground">PEMAKAIAN (L)</TableHead>
                                    <TableHead className="min-w-[150px] text-right font-bold text-foreground">PENGIRIMAN (L)</TableHead>
                                    <TableHead className="min-w-[150px] text-right font-bold text-foreground">STOK AKHIR (L)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, index) => (
                                    <TableRow key={row.tanggal} className="hover:bg-muted/40">
                                        <TableCell className="text-center font-bold">
                                            {row.tanggal}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={row.stok_awal || ''}
                                                    onChange={(e) =>
                                                        handleCellChange(index, 'stok_awal', e.target.value)
                                                    }
                                                    className="h-8 text-right font-mono text-xs"
                                                    placeholder="0"
                                                />
                                            ) : (
                                                <span className="font-mono text-xs">
                                                    {row.stok_awal.toLocaleString('id-ID')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={row.pemakaian || ''}
                                                    onChange={(e) =>
                                                        handleCellChange(index, 'pemakaian', e.target.value)
                                                    }
                                                    className="h-8 text-right font-mono text-xs text-amber-600 dark:text-amber-400"
                                                    placeholder="0"
                                                />
                                            ) : (
                                                <span className="font-mono text-xs text-amber-600 dark:text-amber-400">
                                                    {row.pemakaian.toLocaleString('id-ID')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={row.pengiriman || ''}
                                                    onChange={(e) =>
                                                        handleCellChange(index, 'pengiriman', e.target.value)
                                                    }
                                                    className="h-8 text-right font-mono text-xs text-primary"
                                                    placeholder="0"
                                                />
                                            ) : (
                                                <span className="font-mono text-xs text-primary">
                                                    {row.pengiriman.toLocaleString('id-ID')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs font-bold text-foreground">
                                            {row.stok_akhir.toLocaleString('id-ID')}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-[#ea7315]/20 font-bold hover:bg-[#ea7315]/20">
                                    <TableCell className="text-center font-bold">TOTAL</TableCell>
                                    <TableCell className="text-right">-</TableCell>
                                    <TableCell className="text-right text-amber-600 dark:text-amber-400">
                                        {totalPemakaian.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="text-right text-primary">
                                        {totalPengiriman.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="text-right text-foreground">
                                        {latestStok.toLocaleString('id-ID')}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    {can_write && (
                        <div className="flex items-center justify-between border-t border-border bg-card p-3">
                            <span className="text-xs text-muted-foreground">
                                * Nilai Stok Akhir dihitung otomatis: Stok Awal - Pemakaian + Pengiriman
                            </span>
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
        </>
    );
}

ResourcePembangkitInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: harInput.index() },
        { title: 'Resource Pembangkit', href: resourcePembangkitRoutes.index() },
    ],
};
