import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanKecelakaanMasyarakat from '@/routes/k3/pengusahaan/kecelakaan-masyarakat';
import type { IdName } from '@/types';

export type KecelakaanMasyarakatItem = {
    _key: number;
    id?: number;
    no_urut: number;
    tanggal_kejadian: string;
    fungsi: string;
    lokasi_kejadian: string;
    luka_ringan: number;
    luka_berat: number;
    meninggal: number;
    kerugian_material: number;
    keterangan: string;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    record: {
        is_nihil: boolean;
        lampiran_teks: string;
        nomor_keputusan: string;
        catatan: string;
        items: Array<Omit<KecelakaanMasyarakatItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 10mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 11px !important; }
    .no-print { display: none !important; }
    .km-table th, .km-table td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    .km-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

const formatCurrency = (val: number): string =>
    new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);

export default function PengusahaanKecelakaanMasyarakatPage(props: Props) {
    const {
        unit,
        filters,
        options,
        record: initialRecord,
        has_saved,
        can_write,
    } = props;

    const [isNihil, setIsNihil] = useState<boolean>(initialRecord.is_nihil);
    const [lampiranTeks, setLampiranTeks] = useState(initialRecord.lampiran_teks);
    const [nomorKeputusan, setNomorKeputusan] = useState(initialRecord.nomor_keputusan);
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<KecelakaanMasyarakatItem[]>(() =>
        initialRecord.items.map((it, idx) => ({ ...it, _key: idx + 1 })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 5);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanKecelakaanMasyarakat.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const handleToggleNihil = (val: boolean) => {
        setIsNihil(val);
        setDirty(true);
    };

    const addItem = () => {
        const newItem: KecelakaanMasyarakatItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            tanggal_kejadian: '',
            fungsi: 'Operasi',
            lokasi_kejadian: '',
            luka_ringan: 0,
            luka_berat: 0,
            meninggal: 0,
            kerugian_material: 0,
            keterangan: '',
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setIsNihil(false);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof KecelakaanMasyarakatItem, value: string | number) => {
        setItems((prev) =>
            prev.map((item) => {
                if (item._key !== key) {
                    return item;
                }

                return { ...item, [field]: value };
            }),
        );
        setDirty(true);
    };

    const removeItem = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    const resetChanges = () => {
        setIsNihil(initialRecord.is_nihil);
        setLampiranTeks(initialRecord.lampiran_teks);
        setNomorKeputusan(initialRecord.nomor_keputusan);
        setCatatan(initialRecord.catatan);
        setItems(initialRecord.items.map((it, idx) => ({ ...it, _key: idx + 1 })));
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanKecelakaanMasyarakat.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                is_nihil: isNihil,
                lampiran_teks: lampiranTeks,
                nomor_keputusan: nomorKeputusan,
                catatan,
                items: isNihil
                    ? []
                    : items.map((it, idx) => ({
                        no_urut: idx + 1,
                        tanggal_kejadian: it.tanggal_kejadian,
                        fungsi: it.fungsi,
                        lokasi_kejadian: it.lokasi_kejadian,
                        luka_ringan: Number(it.luka_ringan) || 0,
                        luka_berat: Number(it.luka_berat) || 0,
                        meninggal: Number(it.meninggal) || 0,
                        kerugian_material: Number(it.kerugian_material) || 0,
                        keterangan: it.keterangan,
                    })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={`Laporan Bulanan Kecelakaan Masyarakat Umum — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(k3Pengusahaan.index('input').url)}
                            title="Kembali ke Input Pengusahaan K3"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Laporan Bulanan Kecelakaan Masyarakat Umum
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant={isNihil ? 'secondary' : 'destructive'} className="font-semibold">
                                    {isNihil ? 'NIHIL' : `${items.length} Kejadian`}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {monthName} {filters.year}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Laporan bulanan kecelakaan masyarakat umum dan kerugian material (Kepdir PT PLN No. 0252.P/DIR/2016).
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={resetChanges}
                                disabled={saving}
                                className="gap-1.5 text-xs text-muted-foreground"
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <Button
                                size="sm"
                                onClick={handleSubmit}
                                disabled={saving}
                                className="gap-1.5 text-xs"
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan...' : has_saved ? 'Simpan Perubahan' : 'Simpan Data'}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                        >
                            <Printer className="size-3.5" />
                            Cetak (A4 Landscape)
                        </Button>
                    </div>
                </div>

                {/* Filter and Status Switcher */}
                <Card className="no-print p-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div className="flex flex-wrap items-end gap-3">
                            <OperasiSelect
                                label="Unit Layanan"
                                value={String(filters.unit_id)}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                                onChange={(val) => handleFilterChange('unit_id', val)}
                            />

                            <OperasiSelect
                                label="Bulan"
                                value={String(filters.month)}
                                options={OPERASI_MONTHS.map((label, idx) => ({ value: String(idx + 1), label }))}
                                onChange={(val) => handleFilterChange('month', val)}
                            />

                            <OperasiSelect
                                label="Tahun"
                                value={String(filters.year)}
                                options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                                onChange={(val) => handleFilterChange('year', val)}
                            />
                        </div>

                        {/* Status Toggle Buttons */}
                        {can_write && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-muted-foreground">Kondisi Bulan Ini:</span>
                                <div className="inline-flex rounded-lg border border-border bg-muted/40 p-1">
                                    <button
                                        type="button"
                                        onClick={() => handleToggleNihil(true)}
                                        className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold transition-all ${
                                            isNihil
                                                ? 'bg-emerald-600 text-white shadow-xs'
                                                : 'text-muted-foreground hover:bg-background/80 hover:text-foreground'
                                        }`}
                                    >
                                        <CheckCircle2 className="size-3.5" />
                                        NIHIL (Tidak Ada Kejadian)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            handleToggleNihil(false);

                                            if (items.length === 0) {
                                                addItem();
                                            }
                                        }}
                                        className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold transition-all ${
                                            !isNihil
                                                ? 'bg-destructive text-destructive-foreground shadow-xs'
                                                : 'text-muted-foreground hover:bg-background/80 hover:text-foreground'
                                        }`}
                                    >
                                        <AlertTriangle className="size-3.5" />
                                        Ada Kejadian Kecelakaan
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Printable container (A4 Landscape) */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                    <div className="km-header-box relative rounded border-2 border-black p-4 dark:border-border">
                        {/* Logo PLN Kiri */}
                        <div className="absolute left-4 top-4 flex items-center">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="max-h-14 object-contain"
                                onError={(e) => {
                                    (e.target as HTMLElement).style.display = 'none';
                                }}
                            />
                        </div>

                        {/* Lambang K3 Kanan */}
                        <div className="absolute right-4 top-4 flex items-center">
                            <img
                                src="/logo/k3.png"
                                alt="Logo K3"
                                className="max-h-14 object-contain"
                                onError={(e) => {
                                    (e.target as HTMLElement).style.display = 'none';
                                }}
                            />
                        </div>

                        {/* Teks Instansi Tengah */}
                        <div className="text-center px-24">
                            <div className="text-sm font-extrabold tracking-wide text-foreground uppercase">
                                PT. PLN NUSANTARA POWER
                            </div>
                            <div className="text-xs font-bold text-foreground uppercase">
                                UNIT PEMBANGKITAN KENDARI
                            </div>
                            <div className="text-xs font-bold text-foreground uppercase">
                                UNIT LAYANAN {unit.name.toUpperCase().startsWith('ULPLTD') || unit.name.toUpperCase().startsWith('UP') ? unit.name.toUpperCase() : `PUSAT LISTRIK TENAGA DIESEL ${unit.name.toUpperCase()}`}
                            </div>
                        </div>

                        {/* Garis Ganda Pemisah */}
                        <div className="my-3 border-b-2 border-black dark:border-border" />

                        {/* Judul Dokumen & Referensi Regulasi */}
                        <div className="flex flex-col items-center gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex-1 text-center sm:pl-32">
                                <div className="text-xs font-bold tracking-wider text-foreground uppercase">
                                    LAPORAN BULANAN
                                </div>
                                <div className="text-sm font-black tracking-wide text-foreground uppercase">
                                    KECELAKAAN MASYARAKAT UMUM
                                </div>
                                <div className="text-xs font-bold text-foreground uppercase">
                                    BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                                </div>
                            </div>
                            <div className="text-center text-[10px] text-muted-foreground italic leading-tight sm:text-right">
                                <div>{lampiranTeks}</div>
                                <div>{nomorKeputusan}</div>
                            </div>
                        </div>
                    </div>

                    {/* Tabel Laporan Kecelakaan Masyarakat Umum */}
                    <div className="mt-4 overflow-x-auto">
                        <table className="km-table w-full border-collapse border-2 border-black text-xs dark:border-border">
                            <thead>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th rowSpan={2} className="w-12 border border-black p-2 dark:border-border">
                                        NO
                                    </th>
                                    <th rowSpan={2} className="w-40 border border-black p-2 dark:border-border">
                                        TANGGAL KEJADIAN
                                    </th>
                                    <th rowSpan={2} className="w-36 border border-black p-2 dark:border-border">
                                        FUNGSI
                                    </th>
                                    <th rowSpan={2} className="w-52 border border-black p-2 dark:border-border">
                                        LOKASI KEJADIAN
                                    </th>
                                    <th colSpan={4} className="border border-black p-2 uppercase dark:border-border">
                                        AKIBAT YANG DITIMBULKAN
                                    </th>
                                    {!isNihil && can_write && (
                                        <th rowSpan={2} className="no-print w-12 border border-black p-2 dark:border-border">
                                            AKSI
                                        </th>
                                    )}
                                </tr>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th className="w-28 border border-black p-1.5 dark:border-border">LUKA RINGAN</th>
                                    <th className="w-28 border border-black p-1.5 dark:border-border">LUKA BERAT</th>
                                    <th className="w-28 border border-black p-1.5 dark:border-border">MENINGGAL</th>
                                    <th className="w-36 border border-black p-1.5 dark:border-border">KERUGIAN MATERIAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                {isNihil ? (
                                    /* Tampilan Baris NIHIL Resmi PLN (sesuai format fisik media_1790456635522.png) */
                                    <tr className="border-b border-black text-center font-bold dark:border-border h-48">
                                        <td className="border-r border-black p-4 align-middle dark:border-border">-</td>
                                        <td className="border-r border-black p-4 align-middle dark:border-border">-</td>
                                        <td className="border-r border-black p-4 align-middle dark:border-border">-</td>
                                        <td className="border-r border-black p-4 align-middle dark:border-border">-</td>
                                        <td className="border-r border-black p-4 align-middle text-emerald-700 dark:border-border dark:text-emerald-400">
                                            NIHIL
                                        </td>
                                        <td className="border-r border-black p-4 align-middle text-emerald-700 dark:border-border dark:text-emerald-400">
                                            NIHIL
                                        </td>
                                        <td className="border-r border-black p-4 align-middle text-emerald-700 dark:border-border dark:text-emerald-400">
                                            NIHIL
                                        </td>
                                        <td className="border-r border-black p-4 align-middle text-emerald-700 dark:border-border dark:text-emerald-400">
                                            NIHIL
                                        </td>
                                    </tr>
                                ) : items.length === 0 ? (
                                    <tr>
                                        <td colSpan={can_write ? 9 : 8} className="p-6 text-center text-muted-foreground italic">
                                            Belum ada kejadian tercatat. Klik tombol di bawah untuk menambahkan rincian kecelakaan.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item, index) => (
                                        <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                                            {/* NO */}
                                            <td className="border-r border-black p-2 text-center font-mono dark:border-border">
                                                {index + 1}
                                            </td>

                                            {/* TANGGAL KEJADIAN */}
                                            <td className="border-r border-black p-1 dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.tanggal_kejadian}
                                                        onChange={(e) => updateItem(item._key, 'tanggal_kejadian', e.target.value)}
                                                        placeholder="DD/MM/YYYY"
                                                        className="w-full text-center bg-transparent focus:outline-none"
                                                    />
                                                ) : (
                                                    <span>{item.tanggal_kejadian || '-'}</span>
                                                )}
                                            </td>

                                            {/* FUNGSI */}
                                            <td className="border-r border-black p-1 dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.fungsi}
                                                        onChange={(e) => updateItem(item._key, 'fungsi', e.target.value)}
                                                        placeholder="Fungsi..."
                                                        className="w-full bg-transparent focus:outline-none"
                                                    />
                                                ) : (
                                                    <span>{item.fungsi || '-'}</span>
                                                )}
                                            </td>

                                            {/* LOKASI KEJADIAN */}
                                            <td className="border-r border-black p-1 dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.lokasi_kejadian}
                                                        onChange={(e) => updateItem(item._key, 'lokasi_kejadian', e.target.value)}
                                                        placeholder="Lokasi kejadian..."
                                                        className="w-full bg-transparent focus:outline-none"
                                                    />
                                                ) : (
                                                    <span>{item.lokasi_kejadian || '-'}</span>
                                                )}
                                            </td>

                                            {/* LUKA RINGAN */}
                                            <td className="border-r border-black p-1 text-center dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.luka_ringan}
                                                        onChange={(e) => updateItem(item._key, 'luka_ringan', parseInt(e.target.value) || 0)}
                                                        className="w-full text-center bg-transparent focus:outline-none font-semibold"
                                                    />
                                                ) : (
                                                    <span>{item.luka_ringan} Orang</span>
                                                )}
                                            </td>

                                            {/* LUKA BERAT */}
                                            <td className="border-r border-black p-1 text-center dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.luka_berat}
                                                        onChange={(e) => updateItem(item._key, 'luka_berat', parseInt(e.target.value) || 0)}
                                                        className="w-full text-center bg-transparent focus:outline-none font-semibold text-amber-600"
                                                    />
                                                ) : (
                                                    <span>{item.luka_berat} Orang</span>
                                                )}
                                            </td>

                                            {/* MENINGGAL */}
                                            <td className="border-r border-black p-1 text-center dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.meninggal}
                                                        onChange={(e) => updateItem(item._key, 'meninggal', parseInt(e.target.value) || 0)}
                                                        className="w-full text-center bg-transparent focus:outline-none font-bold text-destructive"
                                                    />
                                                ) : (
                                                    <span>{item.meninggal} Orang</span>
                                                )}
                                            </td>

                                            {/* KERUGIAN MATERIAL */}
                                            <td className="border-r border-black p-1 text-right dark:border-border">
                                                {can_write ? (
                                                    <div className="flex items-center gap-1">
                                                        <span className="text-muted-foreground text-[10px]">Rp</span>
                                                        <input
                                                            type="number"
                                                            min={0}
                                                            step="any"
                                                            value={item.kerugian_material}
                                                            onChange={(e) => updateItem(item._key, 'kerugian_material', parseFloat(e.target.value) || 0)}
                                                            className="w-full text-right bg-transparent focus:outline-none font-mono"
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="font-mono">
                                                        {item.kerugian_material > 0 ? `Rp ${formatCurrency(item.kerugian_material)}` : 'NIHIL'}
                                                    </span>
                                                )}
                                            </td>

                                            {/* AKSI */}
                                            {can_write && (
                                                <td className="no-print border border-black p-1 text-center dark:border-border">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeItem(item._key)}
                                                        className="size-6 p-0 text-destructive hover:bg-destructive/10"
                                                        title="Hapus baris ini"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Tombol Tambah Baris (jika non-NIHIL) */}
                    {!isNihil && can_write && (
                        <div className="no-print mt-3">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                                className="gap-1.5 text-xs text-primary"
                            >
                                <Plus className="size-3.5" />
                                Tambah Rincian Kejadian
                            </Button>
                        </div>
                    )}

                    {/* Catatan / Keterangan Tambahan */}
                    <div className="mt-4">
                        <div className="text-xs font-semibold text-foreground">Catatan Tambahan:</div>
                        {can_write ? (
                            <textarea
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                placeholder="Tambahkan catatan khusus laporan kecelakaan masyarakat umum jika ada..."
                                rows={2}
                                className="mt-1 w-full rounded border border-border bg-background p-2 text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                            />
                        ) : (
                            <p className="mt-1 text-xs text-muted-foreground italic">
                                {catatan ? catatan : 'Tidak ada catatan.'}
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
