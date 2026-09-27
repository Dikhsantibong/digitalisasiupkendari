import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Boxes,
    CheckCircle2,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanApat from '@/routes/k3/pengusahaan/apat';
import type { IdName } from '@/types';

export type ApatItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    nama_alat: string;
    tanggal_inspeksi: string;
    kondisi: string;
    jumlah: number | string;
    keterangan: string;
    sort_order?: number;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    record: {
        no_dokumen: string;
        revisi: string;
        tanggal_dokumen: string;
        tanggal_inspeksi: string;
        catatan: string;
        items: Array<Omit<ApatItem, '_key'>>;
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
    .apat-table th, .apat-table td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    .apat-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

export default function PengusahaanApatPage(props: Props) {
    const {
        unit,
        filters,
        options,
        record: initialRecord,
        has_saved,
        can_write,
    } = props;

    const [noDokumen, setNoDokumen] = useState(initialRecord.no_dokumen);
    const [revisi, setRevisi] = useState(initialRecord.revisi);
    const [tanggalDokumen, setTanggalDokumen] = useState(initialRecord.tanggal_dokumen);
    const [tanggalInspeksi, setTanggalInspeksi] = useState(
        initialRecord.tanggal_inspeksi || `12/${filters.month}/${filters.year}`,
    );
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<ApatItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            jumlah: it.jumlah ?? 0,
            kondisi: it.kondisi ?? 'Baik',
            tanggal_inspeksi: it.tanggal_inspeksi || initialRecord.tanggal_inspeksi || `12/${filters.month}/${filters.year}`,
        })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanApat.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const addItem = () => {
        const newItem: ApatItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            nama_alat: '',
            tanggal_inspeksi: tanggalInspeksi,
            kondisi: 'Baik',
            jumlah: 1,
            keterangan: '',
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof ApatItem, value: string | number) => {
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

    const handleSetAllBaik = () => {
        setItems((prev) =>
            prev.map((item) => ({
                ...item,
                kondisi: 'Baik',
            })),
        );
        setDirty(true);
    };

    const handleApplyDateToAll = () => {
        if (!tanggalInspeksi) {
            return;
        }

        setItems((prev) =>
            prev.map((item) => ({
                ...item,
                tanggal_inspeksi: tanggalInspeksi,
            })),
        );
        setDirty(true);
    };

    const resetChanges = () => {
        setNoDokumen(initialRecord.no_dokumen);
        setRevisi(initialRecord.revisi);
        setTanggalDokumen(initialRecord.tanggal_dokumen);
        setTanggalInspeksi(initialRecord.tanggal_inspeksi || `12/${filters.month}/${filters.year}`);
        setCatatan(initialRecord.catatan);
        setItems(
            initialRecord.items.map((it, idx) => ({
                ...it,
                _key: idx + 1,
                jumlah: it.jumlah ?? 0,
                kondisi: it.kondisi ?? 'Baik',
                tanggal_inspeksi: it.tanggal_inspeksi || initialRecord.tanggal_inspeksi || `12/${filters.month}/${filters.year}`,
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanApat.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                tanggal_inspeksi: tanggalInspeksi,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    nama_alat: it.nama_alat,
                    tanggal_inspeksi: it.tanggal_inspeksi || tanggalInspeksi,
                    kondisi: it.kondisi,
                    jumlah: it.jumlah === '' ? 0 : Number(it.jumlah),
                    keterangan: it.keterangan,
                    sort_order: idx,
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

    // Calculate totals
    const summary = useMemo(() => {
        const totalItems = items.length;
        let totalJumlah = 0;
        let kondisiBaik = 0;

        for (const item of items) {
            if (item.jumlah !== '' && !isNaN(Number(item.jumlah))) {
                totalJumlah += Number(item.jumlah);
            }

            if (item.kondisi?.toLowerCase() === 'baik') {
                kondisiBaik++;
            }
        }

        return { totalItems, totalJumlah, kondisiBaik };
    }, [items]);

    return (
        <>
            <Head title={`Kondisi Alat Pemadam Api Tradisional — ${unit.name}`} />
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
                                    Kondisi Alat Pemadam Api Tradisional (APAT)
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {monthName} {filters.year}
                                </Badge>
                                {has_saved ? (
                                    <Badge variant="secondary" className="text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        Tersimpan
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="text-xs text-amber-600 border-amber-300">
                                        Draf Baru
                                    </Badge>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Pemeriksaan dan monitoring kesiapan alat pemadam api tradisional (SMT-FM-AK3-12.04).
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

                {/* Filter and stats Card */}
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

                        {/* Quick action tools */}
                        {can_write && (
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1.5 rounded-md border border-border bg-muted/40 p-1">
                                    <input
                                        type="text"
                                        value={tanggalInspeksi}
                                        onChange={(e) => setTanggalInspeksi(e.target.value)}
                                        placeholder="DD/MM/YYYY"
                                        className="w-28 rounded px-2 py-1 text-xs bg-background border border-border font-mono text-center focus:outline-none"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleApplyDateToAll}
                                        className="h-7 text-xs px-2.5"
                                        title="Terapkan tanggal inspeksi ini ke seluruh item"
                                    >
                                        Terapkan Tgl
                                    </Button>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSetAllBaik}
                                    className="h-8 gap-1.5 text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
                                    title="Setel kondisi semua alat ke 'Baik'"
                                >
                                    <CheckCircle2 className="size-3.5" />
                                    Setel Semua Baik
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Summary badges */}
                    <div className="mt-3 flex flex-wrap items-center gap-3 pt-3 border-t border-border">
                        <div className="flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 text-xs font-semibold text-primary">
                            <Boxes className="size-3.5" />
                            <span>Jenis Alat: {summary.totalItems} Jenis</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <span>Total Jumlah: {summary.totalJumlah} Unit/Buah</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            <span>Kondisi Baik: {summary.kondisiBaik} / {summary.totalItems} Jenis</span>
                        </div>
                    </div>
                </Card>

                {/* Printable container (A4 Landscape) */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                    <div className="apat-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                        {/* Garis Pemisah */}
                        <div className="my-3 border-b-2 border-black dark:border-border" />

                        {/* Judul Dokumen & Kotak Metadata Formulir SMT-FM-AK3-12.04 */}
                        <div className="flex items-center justify-between">
                            <div className="flex-1 text-center pl-28">
                                <h2 className="text-sm font-black tracking-wider text-foreground uppercase">
                                    KONDISI ALAT PEMADAM API TRADISIONAL
                                </h2>
                                <div className="text-xs font-bold text-foreground uppercase">
                                    {unit.name.toUpperCase()}
                                </div>
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                                </div>
                            </div>

                            {/* Kotak Metadata Dokumen */}
                            <div className="w-56 border border-black text-[10px] dark:border-border">
                                <div className="flex border-b border-black dark:border-border">
                                    <div className="w-24 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                        No. Dokumen
                                    </div>
                                    <div className="flex-1 px-2 py-0.5 font-mono">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={noDokumen}
                                                onChange={(e) => {
                                                    setNoDokumen(e.target.value);
                                                    setDirty(true);
                                                }}
                                                className="w-full bg-transparent focus:outline-none"
                                            />
                                        ) : (
                                            noDokumen
                                        )}
                                    </div>
                                </div>
                                <div className="flex border-b border-black dark:border-border">
                                    <div className="w-24 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                        Revisi
                                    </div>
                                    <div className="flex-1 px-2 py-0.5 font-mono">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={revisi}
                                                onChange={(e) => {
                                                    setRevisi(e.target.value);
                                                    setDirty(true);
                                                }}
                                                className="w-full bg-transparent focus:outline-none"
                                            />
                                        ) : (
                                            revisi
                                        )}
                                    </div>
                                </div>
                                <div className="flex">
                                    <div className="w-24 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                        Tanggal
                                    </div>
                                    <div className="flex-1 px-2 py-0.5 font-mono">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={tanggalDokumen}
                                                onChange={(e) => {
                                                    setTanggalDokumen(e.target.value);
                                                    setDirty(true);
                                                }}
                                                className="w-full bg-transparent focus:outline-none"
                                            />
                                        ) : (
                                            tanggalDokumen
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tabel Inspeksi APAT */}
                    <div className="mt-4 overflow-x-auto">
                        <table className="apat-table w-full border-collapse border-2 border-black text-xs dark:border-border">
                            <thead>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th className="w-12 border border-black p-2 dark:border-border">NO</th>
                                    <th className="w-72 border border-black p-2 text-left dark:border-border">NAMA ALAT</th>
                                    <th className="w-44 border border-black p-2 dark:border-border">TANGGAL INSPEKSI</th>
                                    <th className="w-36 border border-black p-2 dark:border-border">KONDISI</th>
                                    <th className="w-28 border border-black p-2 dark:border-border">JUMLAH</th>
                                    <th className="border border-black p-2 text-left dark:border-border">KET</th>
                                    {can_write && (
                                        <th className="no-print w-12 border border-black p-2 dark:border-border">AKSI</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={can_write ? 7 : 6} className="p-6 text-center text-muted-foreground italic">
                                            Belum ada data alat pemadam api tradisional. Klik tombol di bawah untuk menambahkan item.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item, index) => (
                                        <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                                            {/* NO */}
                                            <td className="border-r border-black p-2 text-center font-mono dark:border-border">
                                                {index + 1}
                                            </td>

                                            {/* NAMA ALAT */}
                                            <td className="border-r border-black p-2 dark:border-border font-bold">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.nama_alat}
                                                        onChange={(e) => updateItem(item._key, 'nama_alat', e.target.value)}
                                                        placeholder="Nama peralatan..."
                                                        className="w-full bg-transparent focus:outline-none font-bold uppercase"
                                                    />
                                                ) : (
                                                    <span className="uppercase">{item.nama_alat}</span>
                                                )}
                                            </td>

                                            {/* TANGGAL INSPEKSI */}
                                            <td className="border-r border-black p-2 text-center font-mono text-[11px] dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.tanggal_inspeksi}
                                                        onChange={(e) => updateItem(item._key, 'tanggal_inspeksi', e.target.value)}
                                                        placeholder="DD/MM/YYYY"
                                                        className="w-full text-center bg-transparent focus:outline-none font-mono"
                                                    />
                                                ) : (
                                                    item.tanggal_inspeksi || '-'
                                                )}
                                            </td>

                                            {/* KONDISI */}
                                            <td className="border-r border-black p-2 text-center dark:border-border font-semibold">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.kondisi}
                                                        onChange={(e) => updateItem(item._key, 'kondisi', e.target.value)}
                                                        className={`w-full text-center bg-transparent focus:outline-none font-semibold ${item.kondisi?.toLowerCase() === 'baik' ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive font-bold'}`}
                                                    />
                                                ) : (
                                                    <span className={item.kondisi?.toLowerCase() === 'baik' ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive font-bold'}>
                                                        {item.kondisi || '-'}
                                                    </span>
                                                )}
                                            </td>

                                            {/* JUMLAH */}
                                            <td className="border-r border-black p-2 text-center font-mono font-bold dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.jumlah}
                                                        onChange={(e) => updateItem(item._key, 'jumlah', parseInt(e.target.value, 10) || 0)}
                                                        className="w-full text-center bg-transparent focus:outline-none font-mono font-bold"
                                                    />
                                                ) : (
                                                    item.jumlah
                                                )}
                                            </td>

                                            {/* KET */}
                                            <td className="border-r border-black p-2 dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.keterangan}
                                                        onChange={(e) => updateItem(item._key, 'keterangan', e.target.value)}
                                                        placeholder="Keterangan..."
                                                        className="w-full bg-transparent focus:outline-none"
                                                    />
                                                ) : (
                                                    item.keterangan || '-'
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
                            <tfoot>
                                <tr className="border-t-2 border-black bg-muted/40 font-bold dark:border-border">
                                    <td colSpan={4} className="border-r border-black p-2 text-right uppercase dark:border-border">
                                        TOTAL PERALATAN TRADISIONAL
                                    </td>
                                    <td className="border-r border-black p-2 text-center font-mono font-bold text-primary dark:border-border">
                                        {summary.totalJumlah}
                                    </td>
                                    <td colSpan={can_write ? 2 : 1} className="border-black p-2 text-muted-foreground italic text-[11px] dark:border-border">
                                        Kondisi Baik: {summary.kondisiBaik} dari {summary.totalItems} jenis alat
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {/* Tombol Tambah Baris */}
                    {can_write && (
                        <div className="no-print mt-3 flex items-center justify-between">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                                className="gap-1.5 text-xs text-primary"
                            >
                                <Plus className="size-3.5" />
                                Tambah Jenis Alat
                            </Button>
                        </div>
                    )}

                    {/* Catatan / Keterangan Tambahan */}
                    <div className="mt-4">
                        <div className="text-xs font-semibold text-foreground">Catatan / Tindak Lanjut:</div>
                        {can_write ? (
                            <textarea
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                placeholder="Tambahkan catatan khusus kondisi alat pemadam api tradisional jika ada..."
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
