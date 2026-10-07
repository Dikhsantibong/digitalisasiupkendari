import React from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNum, type FisikItem, type PemakaianItem } from '../types';

type Props = {
    fuelLabel?: string;
    persediaanAwal: number;
    setPersediaanAwal: (val: number) => void;
    penerimaanRange: string;
    setPenerimaanRange: (val: string) => void;
    penerimaanTotal: number;
    setPenerimaanTotal: (val: number) => void;
    jumlahStock: number;
    pemakaianList: PemakaianItem[];
    pemakaianTotal: number;
    onAddPemakaian: () => void;
    onUpdatePemakaian: (index: number, field: keyof PemakaianItem, val: string | number) => void;
    onRemovePemakaian: (index: number) => void;
    pengirimanTotal: number;
    setPengirimanTotal: (val: number) => void;
    administrasiTotal: number;
    fisikList: FisikItem[];
    fisikTotal: number;
    onAddFisik: () => void;
    onUpdateFisik: (index: number, field: keyof FisikItem, val: string | number) => void;
    onRemoveFisik: (index: number) => void;
    selisihTotal: number;
    catatan: string;
    setCatatan: (val: string) => void;
};

export function FuelEditorSection({
    fuelLabel = 'BBM',
    persediaanAwal,
    setPersediaanAwal,
    penerimaanRange,
    setPenerimaanRange,
    penerimaanTotal,
    setPenerimaanTotal,
    jumlahStock,
    pemakaianList,
    pemakaianTotal,
    onAddPemakaian,
    onUpdatePemakaian,
    onRemovePemakaian,
    pengirimanTotal,
    setPengirimanTotal,
    administrasiTotal,
    fisikList,
    fisikTotal,
    onAddFisik,
    onUpdateFisik,
    onRemoveFisik,
    selisihTotal,
    catatan,
    setCatatan,
}: Props) {
    return (
        <div className="space-y-6">
            {/* Section 1: Saldo Awal & Penerimaan */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="border-b border-border pb-2">
                    <h4 className="text-sm font-semibold text-foreground">
                        A. Saldo Persediaan Awal &amp; B. Penerimaan Bahan Bakar ({fuelLabel})
                    </h4>
                    <p className="text-[12px] text-muted-foreground">
                        Data saldo opname fisik bulan sebelumnya dan total penerimaan bahan bakar pada periode berjalan.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">
                            A. Persediaan Awal (Liter)
                        </Label>
                        <Input
                            type="number"
                            step="any"
                            value={persediaanAwal}
                            onChange={(e) => setPersediaanAwal(Number(e.target.value) || 0)}
                            className="h-8 text-xs font-mono"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">
                            Rentang Tanggal Penerimaan
                        </Label>
                        <Input
                            value={penerimaanRange}
                            onChange={(e) => setPenerimaanRange(e.target.value)}
                            placeholder="Contoh: Tgl 01 s/d 31 Agustus 2026"
                            className="h-8 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">
                            B. Jumlah Penerimaan (Liter)
                        </Label>
                        <Input
                            type="number"
                            step="any"
                            value={penerimaanTotal}
                            onChange={(e) => setPenerimaanTotal(Number(e.target.value) || 0)}
                            className="h-8 text-xs font-mono font-semibold"
                        />
                    </div>
                </div>

                {/* Subtotal Box C */}
                <div className="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3 text-xs">
                    <span className="font-semibold text-foreground">
                        C. Jumlah Stock (Persediaan Awal + Penerimaan)
                    </span>
                    <span className="text-sm font-bold font-mono text-primary">
                        {formatNum(jumlahStock)} Liter
                    </span>
                </div>
            </div>

            {/* Section 2: Pemakaian Bahan Bakar */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                    <div>
                        <h4 className="text-sm font-semibold text-foreground">
                            D. Pemakaian Bahan Bakar Mesin Pembangkit
                        </h4>
                        <p className="text-[12px] text-muted-foreground">
                            Rincian konsumsi bahan bakar masing-masing unit mesin pembangkit selama periode operasional.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onAddPemakaian}
                        className="h-7 text-xs gap-1"
                    >
                        <Plus className="size-3.5" />
                        Tambah Mesin
                    </Button>
                </div>

                <div className="space-y-2">
                    {pemakaianList.length === 0 ? (
                        <div className="p-4 text-center text-xs text-muted-foreground">
                            Belum ada rincian mesin. Klik &ldquo;Tambah Mesin&rdquo; untuk menambahkan.
                        </div>
                    ) : (
                        pemakaianList.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-3">
                                <span className="text-xs font-medium text-muted-foreground w-6 text-center">
                                    {idx + 1}.
                                </span>
                                <Input
                                    value={item.mesin}
                                    onChange={(e) => onUpdatePemakaian(idx, 'mesin', e.target.value)}
                                    placeholder="Nama Mesin (misal: Mesin MAK #1)"
                                    className="h-8 text-xs flex-1"
                                />
                                <div className="flex items-center gap-1.5 w-44">
                                    <Input
                                        type="number"
                                        step="any"
                                        value={item.liter}
                                        onChange={(e) => onUpdatePemakaian(idx, 'liter', e.target.value)}
                                        placeholder="0"
                                        className="h-8 text-xs font-mono text-right"
                                    />
                                    <span className="text-xs text-muted-foreground">L</span>
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => onRemovePemakaian(idx)}
                                    className="size-8 text-destructive hover:bg-destructive/10"
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </div>
                        ))
                    )}
                </div>

                {/* Subtotal Box D */}
                <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 p-3 text-xs">
                    <span className="font-semibold text-foreground">
                        Total Pemakaian Mesin (D)
                    </span>
                    <span className="text-sm font-bold font-mono">
                        {formatNum(pemakaianTotal)} Liter
                    </span>
                </div>
            </div>

            {/* Section 3: Pengiriman & Administrasi */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="border-b border-border pb-2">
                    <h4 className="text-sm font-semibold text-foreground">
                        E. Pengiriman Bahan Bakar &amp; Saldo Administrasi
                    </h4>
                    <p className="text-[12px] text-muted-foreground">
                        Bahan bakar yang ditransfer keluar unit (jika ada) dan saldo akhir buku administrasi.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">
                            E. Pengiriman ke Unit Lain (Liter)
                        </Label>
                        <Input
                            type="number"
                            step="any"
                            value={pengirimanTotal}
                            onChange={(e) => setPengirimanTotal(Number(e.target.value) || 0)}
                            className="h-8 text-xs font-mono"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">
                            F. Persediaan Menurut Administrasi (C - D - E)
                        </Label>
                        <div className="h-8 rounded-md border border-input bg-muted/50 px-3 flex items-center font-mono font-bold text-xs text-foreground">
                            {formatNum(administrasiTotal)} Liter
                        </div>
                    </div>
                </div>
            </div>

            {/* Section 4: Pemeriksaan Fisik / Sounding Tangki */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                    <div>
                        <h4 className="text-sm font-semibold text-foreground">
                            G. Pemeriksaan Fisik (Sounding Tangki BBM)
                        </h4>
                        <p className="text-[12px] text-muted-foreground">
                            Hasil sounding fisik tangki harian/storage pada akhir periode berita acara.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onAddFisik}
                        className="h-7 text-xs gap-1"
                    >
                        <Plus className="size-3.5" />
                        Tambah Tangki
                    </Button>
                </div>

                <div className="space-y-2">
                    {fisikList.length === 0 ? (
                        <div className="p-4 text-center text-xs text-muted-foreground">
                            Belum ada data tangki sounding. Klik &ldquo;Tambah Tangki&rdquo; untuk menambahkan.
                        </div>
                    ) : (
                        fisikList.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-3">
                                <span className="text-xs font-medium text-muted-foreground w-6 text-center">
                                    {idx + 1}.
                                </span>
                                <Input
                                    value={item.tangki}
                                    onChange={(e) => onUpdateFisik(idx, 'tangki', e.target.value)}
                                    placeholder="Nama Tangki (misal: Tangki Storage 1)"
                                    className="h-8 text-xs flex-1"
                                />
                                <div className="flex items-center gap-1.5 w-44">
                                    <Input
                                        type="number"
                                        step="any"
                                        value={item.liter}
                                        onChange={(e) => onUpdateFisik(idx, 'liter', e.target.value)}
                                        placeholder="0"
                                        className="h-8 text-xs font-mono text-right"
                                    />
                                    <span className="text-xs text-muted-foreground">L</span>
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => onRemoveFisik(idx)}
                                    className="size-8 text-destructive hover:bg-destructive/10"
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </div>
                        ))
                    )}
                </div>

                {/* Subtotal Box G */}
                <div className="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3 text-xs">
                    <span className="font-semibold text-foreground">
                        Jumlah Persediaan menurut Fisik (G)
                    </span>
                    <span className="text-sm font-bold font-mono text-primary">
                        {formatNum(fisikTotal)} Liter
                    </span>
                </div>
            </div>

            {/* Section 5: Selisih & Catatan */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="border-b border-border pb-2">
                    <h4 className="text-sm font-semibold text-foreground">
                        H. Selisih Administrasi vs Fisik &amp; Catatan
                    </h4>
                    <p className="text-[12px] text-muted-foreground">
                        Perhitungan selisih stok fisik terhadap buku administrasi dan alasan teknis selisih.
                    </p>
                </div>

                {/* Selisih Result Card */}
                <div className={`p-4 rounded-md border flex items-center justify-between ${
                    selisihTotal === 0
                        ? 'bg-muted/30 border-border text-foreground'
                        : selisihTotal > 0
                          ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-900 dark:text-emerald-200'
                          : 'bg-destructive/10 border-destructive/30 text-destructive'
                }`}>
                    <div className="space-y-0.5">
                        <span className="text-xs font-semibold">Selisih Fisik vs Administrasi (G - F)</span>
                        <Badge
                            variant="outline"
                            className={`ml-2 text-[10px] ${
                                selisihTotal === 0
                                    ? 'bg-muted'
                                    : selisihTotal > 0
                                      ? 'border-emerald-600/40 text-emerald-700 dark:text-emerald-300'
                                      : 'border-destructive/40 text-destructive'
                            }`}
                        >
                            {selisihTotal === 0 ? 'Nihil / Sesuai' : selisihTotal > 0 ? 'Lebih Fisik (+)' : 'Kurang Fisik (-)'}
                        </Badge>
                    </div>
                    <span className="text-base font-extrabold font-mono">
                        {selisihTotal > 0 ? `+${formatNum(selisihTotal)}` : formatNum(selisihTotal)} Liter
                    </span>
                </div>

                {/* Catatan Selisih */}
                <div className="space-y-1.5 text-xs">
                    <Label className="font-semibold text-foreground">
                        Catatan: * Selisih disebabkan karena:
                    </Label>
                    <textarea
                        rows={3}
                        value={catatan}
                        onChange={(e) => setCatatan(e.target.value)}
                        placeholder="Tuliskan alasan teknis selisih BBM bila ada (misal: penguapan, kalibrasi sounding tangki, dsb.)..."
                        className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
            </div>
        </div>
    );
}
