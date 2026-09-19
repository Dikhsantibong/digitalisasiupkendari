import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    Package,
    Plus,
    Save,
    Trash2,
    Wrench,
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
import materialPeralatanRoutes from '@/routes/operasi/input/material-peralatan';
import type { IdName } from '@/types';

type MaterialItem = {
    id?: number;
    kategori: 'PERALATAN' | 'MATERIAL';
    kode_material: string;
    stok_code: string;
    nama_item: string;
    stok_awal: number;
    masuk: number;
    keluar: number;
    stok_akhir: number;
    satuan: string;
    harga_satuan: number;
    pemakaian_rata_rata: number;
    safety_stock: number;
    ilt: number;
    rop: number;
    roq: number;
    sort_order?: number;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    peralatan: MaterialItem[];
    material: MaterialItem[];
    summary: {
        total_peralatan: number;
        total_material: number;
        total_nilai_stok: number;
        low_stock_count: number;
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

export default function MaterialPeralatanInput({
    filters,
    peralatan: initialPeralatan,
    material: initialMaterial,
    options,
    can_write,
}: Props) {
    const [peralatan, setPeralatan] = useState<MaterialItem[]>(initialPeralatan);
    const [material, setMaterial] = useState<MaterialItem[]>(initialMaterial);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    useEffect(() => {
        setPeralatan(initialPeralatan);
        setMaterial(initialMaterial);
        setIsDirty(false);
    }, [initialPeralatan, initialMaterial]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            materialPeralatanRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleCellChange = (
        category: 'PERALATAN' | 'MATERIAL',
        index: number,
        field: keyof MaterialItem,
        value: string,
    ) => {
        const list = category === 'PERALATAN' ? [...peralatan] : [...material];
        const item = { ...list[index] };

        if (
            [
                'stok_awal',
                'masuk',
                'keluar',
                'harga_satuan',
                'pemakaian_rata_rata',
                'safety_stock',
                'ilt',
                'rop',
                'roq',
            ].includes(field)
        ) {
            const num = parseFloat(value) || 0;
            // @ts-expect-error dynamic assign numeric
            item[field] = num;

            // Recalculate stok_akhir
            const awal = item.stok_awal || 0;
            const masuk = item.masuk || 0;
            const keluar = item.keluar || 0;
            item.stok_akhir = Math.max(0, awal + masuk - keluar);
        } else {
            // @ts-expect-error dynamic assign string
            item[field] = value;
        }

        list[index] = item;

        if (category === 'PERALATAN') {
            setPeralatan(list);
        } else {
            setMaterial(list);
        }

        setIsDirty(true);
    };

    const handleAddItem = (category: 'PERALATAN' | 'MATERIAL') => {
        const newItem: MaterialItem = {
            kategori: category,
            kode_material: '',
            stok_code: '',
            nama_item: category === 'PERALATAN' ? 'Item Peralatan Baru' : 'Item Material Baru',
            stok_awal: 0,
            masuk: 0,
            keluar: 0,
            stok_akhir: 0,
            satuan: category === 'PERALATAN' ? 'Unit' : 'Pcs',
            harga_satuan: 0,
            pemakaian_rata_rata: 0,
            safety_stock: 2,
            ilt: 3,
            rop: 2,
            roq: 2,
        };

        if (category === 'PERALATAN') {
            setPeralatan([...peralatan, newItem]);
        } else {
            setMaterial([...material, newItem]);
        }

        setIsDirty(true);
    };

    const handleDeleteItem = (category: 'PERALATAN' | 'MATERIAL', index: number) => {
        const list = category === 'PERALATAN' ? [...peralatan] : [...material];
        const item = list[index];

        if (item.id) {
            router.delete(materialPeralatanRoutes.destroy(item.id).url, {
                preserveScroll: true,
            });
        } else {
            list.splice(index, 1);

            if (category === 'PERALATAN') {
                setPeralatan(list);
            } else {
                setMaterial(list);
            }

            setIsDirty(true);
        }
    };

    const handleSave = () => {
        setSaving(true);
        const allItems = [...peralatan, ...material];
        router.post(
            materialPeralatanRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                items: allItems,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handlePdf = () => {
        const url = materialPeralatanRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const totalNilaiStok = [...peralatan, ...material].reduce(
        (acc, item) => acc + (item.stok_akhir || 0) * (item.harga_satuan || 0),
        0,
    );

    const lowStockItems = [...peralatan, ...material].filter(
        (i) => (i.rop || 0) > 0 && (i.stok_akhir || 0) <= (i.rop || 0),
    ).length;

    return (
        <>
            <Head title="Input Material & Peralatan" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Material dan Peralatan"
                    description="Pencatatan inventaris, monitoring stok awal, material masuk, pemakaian, stok akhir, safety stock, dan reorder point."
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
                                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
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
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Item Peralatan</span>
                            <Wrench className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-foreground">{peralatan.length}</div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Bagian A. Peralatan</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Item Material</span>
                            <Package className="size-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {material.length}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Bagian B. Material</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Total Nilai Stok</span>
                            <Package className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-xl font-bold text-amber-600 dark:text-amber-400">
                            Rp {totalNilaiStok.toLocaleString('id-ID')}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Berdasarkan harga satuan</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Peringatan ROP</span>
                            <AlertTriangle className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {lowStockItems} <span className="text-xs font-normal text-muted-foreground">item</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Stok &le; Reorder Point</p>
                    </div>
                </div>

                {/* Main Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="bg-[#ea7315]/10 border-b border-border px-4 py-2.5 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Package className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold uppercase tracking-wider text-foreground">
                                Laporan Material dan Peralatan
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
                                    <TableHead className="w-28 text-center font-bold text-foreground">Kode Material</TableHead>
                                    <TableHead className="w-24 text-center font-bold text-foreground">stok Code</TableHead>
                                    <TableHead className="min-w-[220px] font-bold text-foreground">Nama Material/Consumable</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">Stok Awal</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">Material Masuk</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">material Keluar</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">Stok Akhir</TableHead>
                                    <TableHead className="w-20 text-center font-bold text-foreground">Satuan</TableHead>
                                    <TableHead className="w-28 text-right font-bold text-foreground">HARGA SATUAN</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">PEMAKAIAN RATA-RATA</TableHead>
                                    <TableHead className="w-24 text-right font-bold text-foreground">SAFETY STOCK</TableHead>
                                    <TableHead className="w-16 text-center font-bold text-foreground">ILT</TableHead>
                                    <TableHead className="w-16 text-center font-bold text-foreground">ROP</TableHead>
                                    <TableHead className="w-16 text-center font-bold text-foreground">RoQ</TableHead>
                                    {can_write && <TableHead className="w-12 text-center font-bold text-foreground">AKSI</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {/* Section A: PERALATAN */}
                                <TableRow className="bg-[#d4edda] dark:bg-emerald-950/40 font-bold hover:bg-[#d4edda]">
                                    <TableCell colSpan={can_write ? 15 : 14} className="text-emerald-900 dark:text-emerald-300 py-2">
                                        <div className="flex items-center justify-between">
                                            <span>A. PERALATAN</span>
                                            {can_write && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-6 text-xs bg-white/80 dark:bg-card"
                                                    onClick={() => handleAddItem('PERALATAN')}
                                                >
                                                    <Plus className="size-3" /> Tambah Peralatan
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>

                                {peralatan.map((item, index) => (
                                    <TableRow key={`peralatan-${item.id ?? index}`} className="hover:bg-muted/40">
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.kode_material}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'kode_material', e.target.value)}
                                                    className="h-7 text-xs font-mono"
                                                    placeholder="Kode..."
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.kode_material}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.stok_code}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'stok_code', e.target.value)}
                                                    className="h-7 text-xs font-mono"
                                                    placeholder="Stok Code..."
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.stok_code}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.nama_item}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'nama_item', e.target.value)}
                                                    className="h-7 text-xs"
                                                />
                                            ) : (
                                                <span className="text-xs font-medium">{item.nama_item}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.stok_awal || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'stok_awal', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.stok_awal}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.masuk || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'masuk', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono text-primary"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono text-primary">{item.masuk}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.keluar || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'keluar', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono text-amber-600 dark:text-amber-400"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono text-amber-600 dark:text-amber-400">{item.keluar}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center font-bold">
                                            <span className="inline-block rounded bg-rose-50 px-2 py-0.5 text-xs text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 font-mono">
                                                {item.stok_akhir}
                                            </span>
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.satuan}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'satuan', e.target.value)}
                                                    className="h-7 text-xs text-center"
                                                />
                                            ) : (
                                                <span className="text-xs">{item.satuan}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.harga_satuan || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'harga_satuan', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.harga_satuan ? item.harga_satuan.toLocaleString('id-ID') : '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={item.pemakaian_rata_rata || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'pemakaian_rata_rata', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.pemakaian_rata_rata || '0,0'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={item.safety_stock || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'safety_stock', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.safety_stock || '0,0'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.ilt || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'ilt', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.ilt || '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.rop || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'rop', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.rop || '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.roq || ''}
                                                    onChange={(e) => handleCellChange('PERALATAN', index, 'roq', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.roq || '-'}</span>
                                            )}
                                        </TableCell>
                                        {can_write && (
                                            <TableCell className="p-1 text-center">
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-7 text-muted-foreground hover:text-destructive"
                                                    onClick={() => handleDeleteItem('PERALATAN', index)}
                                                    title="Hapus Baris"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}

                                {/* Section B: MATERIAL */}
                                <TableRow className="bg-[#d4edda] dark:bg-emerald-950/40 font-bold hover:bg-[#d4edda]">
                                    <TableCell colSpan={can_write ? 15 : 14} className="text-emerald-900 dark:text-emerald-300 py-2">
                                        <div className="flex items-center justify-between">
                                            <span>B. MATERIAL</span>
                                            {can_write && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-6 text-xs bg-white/80 dark:bg-card"
                                                    onClick={() => handleAddItem('MATERIAL')}
                                                >
                                                    <Plus className="size-3" /> Tambah Material
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>

                                {material.map((item, index) => (
                                    <TableRow key={`material-${item.id ?? index}`} className="hover:bg-muted/40">
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.kode_material}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'kode_material', e.target.value)}
                                                    className="h-7 text-xs font-mono"
                                                    placeholder="Kode..."
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.kode_material}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.stok_code}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'stok_code', e.target.value)}
                                                    className="h-7 text-xs font-mono"
                                                    placeholder="Stok Code..."
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.stok_code}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.nama_item}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'nama_item', e.target.value)}
                                                    className="h-7 text-xs"
                                                />
                                            ) : (
                                                <span className="text-xs font-medium">{item.nama_item}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.stok_awal || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'stok_awal', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.stok_awal}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.masuk || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'masuk', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono text-primary"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono text-primary">{item.masuk}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.keluar || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'keluar', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono text-amber-600 dark:text-amber-400"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono text-amber-600 dark:text-amber-400">{item.keluar}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center font-bold">
                                            <span className="inline-block rounded bg-rose-50 px-2 py-0.5 text-xs text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 font-mono">
                                                {item.stok_akhir}
                                            </span>
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="text"
                                                    value={item.satuan}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'satuan', e.target.value)}
                                                    className="h-7 text-xs text-center"
                                                />
                                            ) : (
                                                <span className="text-xs">{item.satuan}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.harga_satuan || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'harga_satuan', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.harga_satuan ? item.harga_satuan.toLocaleString('id-ID') : '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={item.pemakaian_rata_rata || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'pemakaian_rata_rata', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.pemakaian_rata_rata || '0,0'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-right">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.1"
                                                    value={item.safety_stock || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'safety_stock', e.target.value)}
                                                    className="h-7 text-xs text-right font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.safety_stock || '0,0'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.ilt || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'ilt', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.ilt || '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.rop || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'rop', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.rop || '-'}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="p-1.5 text-center">
                                            {can_write ? (
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={item.roq || ''}
                                                    onChange={(e) => handleCellChange('MATERIAL', index, 'roq', e.target.value)}
                                                    className="h-7 text-xs text-center font-mono"
                                                />
                                            ) : (
                                                <span className="text-xs font-mono">{item.roq || '-'}</span>
                                            )}
                                        </TableCell>
                                        {can_write && (
                                            <TableCell className="p-1 text-center">
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-7 text-muted-foreground hover:text-destructive"
                                                    onClick={() => handleDeleteItem('MATERIAL', index)}
                                                    title="Hapus Baris"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {can_write && (
                        <div className="flex items-center justify-between border-t border-border bg-card p-3">
                            <span className="text-xs text-muted-foreground">
                                * Stok Akhir = Stok Awal + Material Masuk - Material Keluar
                            </span>
                            <Button
                                onClick={handleSave}
                                disabled={saving || !isDirty}
                                size="sm"
                                className="gap-2"
                            >
                                <Save className="size-4" />
                                {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

MaterialPeralatanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: harInput.index() },
        { title: 'Material & Peralatan', href: materialPeralatanRoutes.index() },
    ],
};
