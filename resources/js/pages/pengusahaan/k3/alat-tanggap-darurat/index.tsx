import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    LifeBuoy,
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
import k3PengusahaanAlatTanggapDarurat from '@/routes/k3/pengusahaan/alat-tanggap-darurat';
import type { IdName } from '@/types';

export type TanggapDaruratItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    jenis: string;
    siap_pakai: number | string;
    kadaluarsa: number | string;
    kosong: number | string;
    tgl_diisi_kembali: string;
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
        items: Array<Omit<TanggapDaruratItem, '_key'>>;
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
    .atd-table th, .atd-table td { border: 1px solid #000 !important; padding: 5px 7px !important; }
    .atd-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

export default function PengusahaanAlatTanggapDaruratPage(props: Props) {
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
    const [items, setItems] = useState<TanggapDaruratItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            siap_pakai: it.siap_pakai ?? '',
            kadaluarsa: it.kadaluarsa ?? '',
            kosong: it.kosong ?? '',
        })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanAlatTanggapDarurat.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const addItem = () => {
        const newItem: TanggapDaruratItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            jenis: '',
            siap_pakai: '',
            kadaluarsa: '',
            kosong: '',
            tgl_diisi_kembali: '',
            keterangan: 'Baik',
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof TanggapDaruratItem, value: string | number) => {
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
                siap_pakai: it.siap_pakai ?? '',
                kadaluarsa: it.kadaluarsa ?? '',
                kosong: it.kosong ?? '',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanAlatTanggapDarurat.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    jenis: it.jenis,
                    siap_pakai: it.siap_pakai === '' ? null : Number(it.siap_pakai),
                    kadaluarsa: it.kadaluarsa === '' ? null : Number(it.kadaluarsa),
                    kosong: it.kosong === '' ? null : Number(it.kosong),
                    tgl_diisi_kembali: it.tgl_diisi_kembali,
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

    // Calculate totals for quick summary
    const summary = useMemo(() => {
        let totalSiap = 0;
        let totalKadal = 0;
        let totalKosong = 0;

        for (const item of items) {
            if (item.siap_pakai !== '' && !isNaN(Number(item.siap_pakai))) {
                totalSiap += Number(item.siap_pakai);
            }

            if (item.kadaluarsa !== '' && !isNaN(Number(item.kadaluarsa))) {
                totalKadal += Number(item.kadaluarsa);
            }

            if (item.kosong !== '' && !isNaN(Number(item.kosong))) {
                totalKosong += Number(item.kosong);
            }
        }

        return { totalSiap, totalKadal, totalKosong };
    }, [items]);

    return (
        <>
            <Head title={`Daftar Alat Tanggap Darurat — ${unit.name}`} />
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
                                    Daftar Alat Tanggap Darurat
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
                                Akses 2 — Pengusahaan: Daftar inventaris dan pemantauan kondisi kesiapan alat tanggap darurat (SMT-FM-AK3-03.03).
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

                        {/* Quick summary badges */}
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                <LifeBuoy className="size-3.5 text-emerald-600" />
                                <span>Siap Pakai: {summary.totalSiap} Unit</span>
                            </div>
                            <div className="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                <span>Kadaluarsa: {summary.totalKadal} Unit</span>
                            </div>
                            <div className="flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                <span>Kosong: {summary.totalKosong} Unit</span>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Printable container (A4 Landscape) */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                    <div className="atd-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                        {/* Judul Dokumen & Kotak Metadata Formulir */}
                        <div className="flex items-center justify-between">
                            <div className="flex-1 text-center pl-28">
                                <h2 className="text-base font-black tracking-wider text-foreground uppercase">
                                    DAFTAR ALAT TANGGAP DARURAT
                                </h2>
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    PERIODE: BULAN {monthName.toUpperCase()} {filters.year}
                                </div>
                            </div>

                            {/* Kotak Metadata SMT-FM-AK3-03.03 */}
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

                    {/* Tabel Laporan Alat Tanggap Darurat */}
                    <div className="mt-4 overflow-x-auto">
                        <table className="atd-table w-full border-collapse border-2 border-black text-xs dark:border-border">
                            <thead>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th rowSpan={2} className="w-12 border border-black p-2 dark:border-border">
                                        NO.
                                    </th>
                                    <th rowSpan={2} className="border border-black p-2 text-left dark:border-border">
                                        JENIS
                                    </th>
                                    <th colSpan={3} className="border border-black p-2 uppercase dark:border-border">
                                        KONDISI (JUMLAH)
                                    </th>
                                    <th rowSpan={2} className="w-36 border border-black p-2 dark:border-border">
                                        Diisi kembali Tgl
                                    </th>
                                    <th rowSpan={2} className="w-36 border border-black p-2 dark:border-border">
                                        KETERANGAN
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="no-print w-12 border border-black p-2 dark:border-border">
                                            AKSI
                                        </th>
                                    )}
                                </tr>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th className="w-24 border border-black p-1.5 dark:border-border">SIAP PAKAI</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">KADALUARSA</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">KOSONG</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={can_write ? 8 : 7} className="p-6 text-center text-muted-foreground italic">
                                            Belum ada data alat tanggap darurat. Klik tombol di bawah untuk menambahkan item.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item, index) => (
                                        <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                                            {/* NO */}
                                            <td className="border-r border-black p-2 text-center font-mono dark:border-border">
                                                {index + 1}
                                            </td>

                                            {/* JENIS */}
                                            <td className="border-r border-black p-1.5 dark:border-border font-medium">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.jenis}
                                                        onChange={(e) => updateItem(item._key, 'jenis', e.target.value)}
                                                        placeholder="Nama peralatan..."
                                                        className="w-full bg-transparent focus:outline-none"
                                                    />
                                                ) : (
                                                    item.jenis
                                                )}
                                            </td>

                                            {/* SIAP PAKAI */}
                                            <td className="border-r border-black p-1 text-center dark:border-border font-semibold text-emerald-700 dark:text-emerald-400">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.siap_pakai}
                                                        onChange={(e) => updateItem(item._key, 'siap_pakai', e.target.value)}
                                                        placeholder="-"
                                                        className="w-full text-center bg-transparent focus:outline-none font-semibold text-emerald-700 dark:text-emerald-400"
                                                    />
                                                ) : (
                                                    item.siap_pakai !== '' && item.siap_pakai !== null ? item.siap_pakai : '-'
                                                )}
                                            </td>

                                            {/* KADALUARSA */}
                                            <td className="border-r border-black p-1 text-center dark:border-border font-semibold text-amber-600 dark:text-amber-400">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.kadaluarsa}
                                                        onChange={(e) => updateItem(item._key, 'kadaluarsa', e.target.value)}
                                                        placeholder="-"
                                                        className="w-full text-center bg-transparent focus:outline-none font-semibold text-amber-600 dark:text-amber-400"
                                                    />
                                                ) : (
                                                    item.kadaluarsa !== '' && item.kadaluarsa !== null ? item.kadaluarsa : '-'
                                                )}
                                            </td>

                                            {/* KOSONG */}
                                            <td className="border-r border-black p-1 text-center dark:border-border font-semibold text-rose-600 dark:text-rose-400">
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={item.kosong}
                                                        onChange={(e) => updateItem(item._key, 'kosong', e.target.value)}
                                                        placeholder="-"
                                                        className="w-full text-center bg-transparent focus:outline-none font-semibold text-rose-600 dark:text-rose-400"
                                                    />
                                                ) : (
                                                    item.kosong !== '' && item.kosong !== null ? item.kosong : '-'
                                                )}
                                            </td>

                                            {/* DIISI KEMBALI TGL */}
                                            <td className="border-r border-black p-1 text-center dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.tgl_diisi_kembali}
                                                        onChange={(e) => updateItem(item._key, 'tgl_diisi_kembali', e.target.value)}
                                                        placeholder="DD/MM/YYYY"
                                                        className="w-full text-center bg-transparent focus:outline-none font-mono text-[11px]"
                                                    />
                                                ) : (
                                                    <span className="font-mono text-[11px]">{item.tgl_diisi_kembali || '-'}</span>
                                                )}
                                            </td>

                                            {/* KETERANGAN */}
                                            <td className="border-r border-black p-1.5 text-center dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={item.keterangan}
                                                        onChange={(e) => updateItem(item._key, 'keterangan', e.target.value)}
                                                        placeholder="Keterangan..."
                                                        className="w-full text-center bg-transparent focus:outline-none"
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
                                    <td colSpan={2} className="border-r border-black p-2 text-center uppercase dark:border-border">
                                        TOTAL KONDISI
                                    </td>
                                    <td className="border-r border-black p-2 text-center text-emerald-700 dark:border-border dark:text-emerald-400">
                                        {summary.totalSiap}
                                    </td>
                                    <td className="border-r border-black p-2 text-center text-amber-600 dark:border-border dark:text-amber-400">
                                        {summary.totalKadal}
                                    </td>
                                    <td className="border-r border-black p-2 text-center text-rose-600 dark:border-border dark:text-rose-400">
                                        {summary.totalKosong}
                                    </td>
                                    <td colSpan={can_write ? 3 : 2} className="border-black p-2 text-muted-foreground italic text-[11px] dark:border-border">
                                        Total Kesiapan: {summary.totalSiap + summary.totalKadal + summary.totalKosong > 0
                                            ? `${Math.round((summary.totalSiap / (summary.totalSiap + summary.totalKadal + summary.totalKosong)) * 100)}% Siap Pakai`
                                            : '-'}
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
                                Tambah Jenis Peralatan
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
                                placeholder="Tambahkan catatan khusus kondisi alat tanggap darurat jika ada..."
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
