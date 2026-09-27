import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    HardHat,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Shield,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanInventarisApd from '@/routes/k3/pengusahaan/inventaris-apd';
import type { IdName } from '@/types';

export type ApdInventoryItem = {
    _key: number;
    id?: number | null;
    kategori: string;
    no_grup: number;
    nama_grup: string | null;
    nama_alat: string;
    jumlah: number | string;
    lokasi: string;
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
        catatan: string;
        items: Array<Omit<ApdInventoryItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 portrait; margin: 8mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 10px !important; }
    .no-print { display: none !important; }
    .apd-table { width: 100% !important; border-collapse: collapse !important; }
    .apd-table th, .apd-table td { border: 1px solid #000 !important; padding: 3px 6px !important; }
    .apd-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; padding: 0 !important; font-size: inherit !important; }
    .page-break { page-break-before: always; }
}
`;

export default function PengusahaanInventarisApdPage(props: Props) {
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
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<ApdInventoryItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            jumlah: it.jumlah ?? '',
            lokasi: it.lokasi ?? '',
            keterangan: it.keterangan ?? '',
        })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanInventarisApd.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const addItem = (kategori: 'I' | 'II') => {
        const catItems = items.filter((it) => it.kategori === kategori);
        const lastNoGrup = catItems.length > 0 ? Math.max(...catItems.map((it) => it.no_grup)) : 0;

        const newItem: ApdInventoryItem = {
            _key: nextKey,
            kategori,
            no_grup: lastNoGrup + 1,
            nama_grup: null,
            nama_alat: '',
            jumlah: '',
            lokasi: '',
            keterangan: '',
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof ApdInventoryItem, value: string | number) => {
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
        setNoDokumen(initialRecord.no_dokumen);
        setRevisi(initialRecord.revisi);
        setTanggalDokumen(initialRecord.tanggal_dokumen);
        setCatatan(initialRecord.catatan);
        setItems(
            initialRecord.items.map((it, idx) => ({
                ...it,
                _key: idx + 1,
                jumlah: it.jumlah ?? '',
                lokasi: it.lokasi ?? '',
                keterangan: it.keterangan ?? '',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanInventarisApd.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                catatan,
                items: items.map((it, idx) => ({
                    kategori: it.kategori,
                    no_grup: it.no_grup,
                    nama_grup: it.nama_grup,
                    nama_alat: it.nama_alat,
                    jumlah: it.jumlah === '' ? null : Number(it.jumlah),
                    lokasi: it.lokasi,
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

    // Summary calculations
    const summary = useMemo(() => {
        let totalUtama = 0;
        let totalPelengkap = 0;
        let countUtama = 0;
        let countPelengkap = 0;

        for (const item of items) {
            const qty = item.jumlah !== '' && !isNaN(Number(item.jumlah)) ? Number(item.jumlah) : 0;

            if (item.kategori === 'I') {
                totalUtama += qty;
                countUtama++;
            } else {
                totalPelengkap += qty;
                countPelengkap++;
            }
        }

        return {
            totalUtama,
            totalPelengkap,
            totalAll: totalUtama + totalPelengkap,
            countUtama,
            countPelengkap,
        };
    }, [items]);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Inventaris APD — ${unit.name}`} />

            {/* Back Button & Header Bar */}
            <div className="no-print flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => router.get(k3Pengusahaan.index({ section: 'input' }).url)}
                        title="Kembali ke Menu Input K3"
                    >
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Inventaris Alat Pelindung Diri
                            </h1>
                            {has_saved ? (
                                <Badge variant="secondary" className="border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    Tersimpan
                                </Badge>
                            ) : (
                                <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    Draft
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Inventaris dan pemantauan kondisi kesiapan alat pelindung diri utama dan pelengkap (SMT-FM-AK3-01.01).
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => window.print()}
                        className="gap-1.5"
                    >
                        <Printer className="size-4" />
                        <span>Cetak Dokumen</span>
                    </Button>

                    {dirty && can_write && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={resetChanges}
                            className="gap-1.5 text-muted-foreground"
                        >
                            <RotateCcw className="size-4" />
                            <span>Reset</span>
                        </Button>
                    )}

                    {can_write && (
                        <Button
                            size="sm"
                            onClick={handleSubmit}
                            disabled={saving}
                            className="gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            <Save className="size-4" />
                            <span>{saving ? 'Menyimpan...' : 'Simpan Data'}</span>
                        </Button>
                    )}
                </div>
            </div>

            {/* Filter Card & Badges */}
            <Card className="no-print p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

                    {/* Quick summary badges */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            <HardHat className="size-3.5 text-sky-600" />
                            <span>APD Utama: {summary.totalUtama} Item ({summary.countUtama} Baris)</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <Shield className="size-3.5 text-indigo-600" />
                            <span>APD Pelengkap: {summary.totalPelengkap} Item ({summary.countPelengkap} Baris)</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <span>Total APD: {summary.totalAll} Unit</span>
                        </div>
                    </div>
                </div>
            </Card>

            {/* Printable container (A4 Portrait) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="apd-header-box relative rounded border-2 border-black p-4 dark:border-border">
                    {/* Logo PLN Kiri */}
                    <div className="absolute left-4 top-4 flex items-center">
                        <img
                            src="/logo/sidebar-logo.png"
                            alt="PLN Nusantara Power"
                            className="max-h-12 object-contain"
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
                            className="max-h-12 object-contain"
                            onError={(e) => {
                                (e.target as HTMLElement).style.display = 'none';
                            }}
                        />
                    </div>

                    {/* Teks Instansi Tengah */}
                    <div className="px-20 text-center">
                        <div className="text-xs font-extrabold tracking-wide text-foreground uppercase sm:text-sm">
                            PT. PLN NUSANTARA POWER
                        </div>
                        <div className="text-[11px] font-bold text-foreground uppercase sm:text-xs">
                            UNIT PEMBANGKITAN KENDARI
                        </div>
                        <div className="text-[11px] font-bold text-foreground uppercase sm:text-xs">
                            UNIT LAYANAN {unit.name.toUpperCase().startsWith('ULPLTD') || unit.name.toUpperCase().startsWith('UP') ? unit.name.toUpperCase() : `PUSAT LISTRIK TENAGA DIESEL ${unit.name.toUpperCase()}`}
                        </div>
                    </div>

                    {/* Garis Pemisah */}
                    <div className="my-2.5 border-b-2 border-black dark:border-border" />

                    {/* Judul Dokumen & Kotak Metadata Formulir */}
                    <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <div className="flex-1 text-center sm:pl-24">
                            <h2 className="text-sm font-black tracking-wider text-foreground uppercase sm:text-base">
                                INVENTARIS ALAT PELINDUNG DIRI
                            </h2>
                            <div className="text-[10px] font-bold text-muted-foreground uppercase sm:text-[11px]">
                                PERIODE: BULAN {monthName.toUpperCase()} {filters.year}
                            </div>
                        </div>

                        {/* Kotak Metadata SMT-FM-AK3-01.01 */}
                        <div className="w-52 border border-black text-[9px] dark:border-border sm:w-56 sm:text-[10px]">
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

                {/* Table Content */}
                <div className="mt-4 overflow-x-auto">
                    <table className="apd-table w-full border-collapse border border-black text-[11px] dark:border-border">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-12 border border-black px-2 py-1.5 text-center dark:border-border">
                                    No
                                </th>
                                <th className="border border-black px-3 py-1.5 text-left dark:border-border">
                                    Alat Pelindung Diri
                                </th>
                                <th className="w-20 border border-black px-2 py-1.5 text-center dark:border-border">
                                    Jumlah
                                </th>
                                <th className="w-40 border border-black px-3 py-1.5 text-left dark:border-border">
                                    Lokasi
                                </th>
                                <th className="w-60 border border-black px-3 py-1.5 text-left dark:border-border">
                                    Keterangan
                                </th>
                                {can_write && (
                                    <th className="no-print w-12 border border-black px-1 py-1.5 text-center dark:border-border">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {/* Render rows grouped by Kategori I and II */}
                            {(['I', 'II'] as const).map((kategoriKey) => {
                                const catItems = items.filter((it) => it.kategori === kategoriKey);
                                const isUtama = kategoriKey === 'I';
                                const catTitle = isUtama
                                    ? 'Peralatan Keselamatan Kerja Utama'
                                    : 'Peralatan Keselamatan Kerja Pelengkap';

                                return (
                                    <Fragment key={kategoriKey}>
                                        {/* Category Header Row */}
                                        <tr className="bg-muted/60 font-extrabold text-foreground">
                                            <td className="border border-black px-2 py-1.5 text-center dark:border-border">
                                                {kategoriKey}
                                            </td>
                                            <td
                                                colSpan={can_write ? 4 : 4}
                                                className="border border-black px-3 py-1.5 tracking-wide uppercase dark:border-border"
                                            >
                                                {catTitle}
                                            </td>
                                            {can_write && (
                                                <td className="no-print border border-black px-1 py-1.5 text-center dark:border-border">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-6 text-primary hover:bg-primary/20"
                                                        onClick={() => addItem(kategoriKey)}
                                                        title={`Tambah Baris ${catTitle}`}
                                                    >
                                                        <Plus className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>

                                        {/* Category Items */}
                                        {catItems.map((item, idx) => {
                                            const hasGroup = !!item.nama_grup;
                                            // Check if this is the first occurrence of this group
                                            const isFirstInGroup =
                                                hasGroup &&
                                                (idx === 0 || catItems[idx - 1].nama_grup !== item.nama_grup);

                                            return (
                                                <Fragment key={item._key}>
                                                    {/* Group sub-header row if applicable */}
                                                    {isFirstInGroup && (
                                                        <tr className="bg-muted/20 font-bold text-foreground">
                                                            <td className="border border-black px-2 py-1 text-center font-bold dark:border-border">
                                                                {item.no_grup}
                                                            </td>
                                                            <td
                                                                colSpan={can_write ? 4 : 4}
                                                                className="border border-black px-3 py-1 font-semibold dark:border-border"
                                                            >
                                                                {item.nama_grup}
                                                            </td>
                                                            {can_write && (
                                                                <td className="no-print border border-black px-1 py-1 text-center dark:border-border" />
                                                            )}
                                                        </tr>
                                                    )}

                                                    <tr className="hover:bg-muted/10">
                                                        {/* Number cell: only shown if no group */}
                                                        <td className="border border-black px-2 py-1 text-center font-medium dark:border-border">
                                                            {!hasGroup ? item.no_grup : ''}
                                                        </td>

                                                        {/* Nama Alat Pelindung Diri */}
                                                        <td
                                                            className={`border border-black px-3 py-1 dark:border-border ${
                                                                hasGroup ? 'pl-6' : ''
                                                            }`}
                                                        >
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={item.nama_alat}
                                                                    onChange={(e) =>
                                                                        updateItem(item._key, 'nama_alat', e.target.value)
                                                                    }
                                                                    placeholder="Nama peralatan..."
                                                                    className="w-full bg-transparent focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span>{item.nama_alat}</span>
                                                            )}
                                                        </td>

                                                        {/* Jumlah */}
                                                        <td className="border border-black px-2 py-1 text-center dark:border-border">
                                                            {can_write ? (
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    value={item.jumlah}
                                                                    onChange={(e) =>
                                                                        updateItem(item._key, 'jumlah', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="w-full bg-transparent text-center focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span>{item.jumlah !== '' ? item.jumlah : '-'}</span>
                                                            )}
                                                        </td>

                                                        {/* Lokasi */}
                                                        <td className="border border-black px-2 py-1 dark:border-border">
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={item.lokasi}
                                                                    onChange={(e) =>
                                                                        updateItem(item._key, 'lokasi', e.target.value)
                                                                    }
                                                                    placeholder="Lokasi..."
                                                                    className="w-full bg-transparent focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span>{item.lokasi || '-'}</span>
                                                            )}
                                                        </td>

                                                        {/* Keterangan */}
                                                        <td className="border border-black px-2 py-1 dark:border-border">
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={item.keterangan}
                                                                    onChange={(e) =>
                                                                        updateItem(item._key, 'keterangan', e.target.value)
                                                                    }
                                                                    placeholder="Keterangan..."
                                                                    className="w-full bg-transparent focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span>{item.keterangan || '-'}</span>
                                                            )}
                                                        </td>

                                                        {/* Delete Row button */}
                                                        {can_write && (
                                                            <td className="no-print border border-black px-1 py-1 text-center dark:border-border">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-6 text-rose-500 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950"
                                                                    onClick={() => removeItem(item._key)}
                                                                    title="Hapus Baris"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                </Fragment>
                                            );
                                        })}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Catatan / Keterangan Tambahan */}
                <div className="mt-4">
                    <label className="text-[11px] font-bold text-foreground">
                        Catatan / Tindak Lanjut:
                    </label>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Tuliskan catatan kondisi atau pengadaan APD di sini..."
                            className="mt-1 w-full rounded border border-black p-2 text-[11px] dark:border-border"
                        />
                    ) : (
                        <p className="mt-1 text-[11px] text-muted-foreground whitespace-pre-wrap">
                            {catatan || '-'}
                        </p>
                    )}
                </div>

            </div>
        </div>
    );
}
