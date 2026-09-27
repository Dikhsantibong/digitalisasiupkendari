import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Cctv,
    CheckCircle2,
    Eye,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanLaporanCctv from '@/routes/k3/pengusahaan/laporan-cctv';
import type { IdName } from '@/types';

export type LaporanCctvItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    tanggal: string;
    lokasi_cctv: string;
    waktu_pantau: string;
    kondisi_pantau: string;
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
        items: Array<Omit<LaporanCctvItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { 
        position: absolute !important; 
        left: 0 !important; 
        top: 0 !important; 
        width: 100% !important; 
        background: #fff !important; 
        color: #000 !important; 
        padding: 0 !important; 
        margin: 0 !important; 
        font-size: 11px !important; 
    }
    .no-print { display: none !important; }
    .cctv-table { width: 100% !important; border-collapse: collapse !important; }
    .cctv-table th, .cctv-table td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    .cctv-header-box { border: 2px solid #000 !important; }
    tr { page-break-inside: avoid !important; break-inside: avoid !important; }
    input, textarea, select { 
        border: none !important; 
        background: transparent !important; 
        padding: 0 !important; 
        font-size: inherit !important; 
        appearance: none !important; 
        -webkit-appearance: none !important;
    }
}
`;

const DEFAULT_8_POINTS = `1. Ruang Pembangkit (Lokal)
2. Depan Kantor Unit
3. Depan Pos Satpam
4. PLNT (eks)
5. Tangki Timbun HSD
6. Oil Trap
7. Pos BBM & Gudang
8. Area Pembongkaran 2 BBM`;

export default function PengusahaanLaporanCctvPage(props: Props) {
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

    const [items, setItems] = useState<LaporanCctvItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            no_urut: it.no_urut ?? (idx + 1),
            lokasi_cctv: it.lokasi_cctv || DEFAULT_8_POINTS,
            waktu_pantau: it.waktu_pantau || 'Setiap Saat',
            kondisi_pantau: it.kondisi_pantau || 'Aman',
            keterangan: it.keterangan || '',
        })),
    );

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: string) => {
        router.get(
            k3PengusahaanLaporanCctv.index().url,
            {
                ...filters,
                [key]: Number(val),
            },
            {
                preserveState: false,
                preserveScroll: true,
            },
        );
    };

    const updateItem = (key: number, field: keyof LaporanCctvItem, value: unknown) => {
        setItems((prev) =>
            prev.map((it) => (it._key === key ? { ...it, [field]: value } : it)),
        );
        setDirty(true);
    };

    const addItem = () => {
        const nextNo = items.length > 0 ? Math.max(...items.map((i) => i.no_urut)) + 1 : 1;
        const newItem: LaporanCctvItem = {
            _key: nextKey,
            id: null,
            no_urut: nextNo,
            tanggal: `31/${filters.month}/${filters.year}`,
            lokasi_cctv: DEFAULT_8_POINTS,
            waktu_pantau: 'Setiap Saat',
            kondisi_pantau: 'Aman',
            keterangan: '',
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const removeItem = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    // Quick fill all to 'Aman'
    const setAllAman = () => {
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                kondisi_pantau: 'Aman',
            })),
        );
        setDirty(true);
    };

    // Reset points to default 8 CCTV cameras
    const fillDefaultPoints = (key: number) => {
        updateItem(key, 'lokasi_cctv', DEFAULT_8_POINTS);
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
                no_urut: it.no_urut ?? (idx + 1),
                lokasi_cctv: it.lokasi_cctv || DEFAULT_8_POINTS,
                waktu_pantau: it.waktu_pantau || 'Setiap Saat',
                kondisi_pantau: it.kondisi_pantau || 'Aman',
                keterangan: it.keterangan || '',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanLaporanCctv.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                catatan,
                items: items.map((it, idx) => ({
                    id: it.id,
                    no_urut: it.no_urut,
                    tanggal: it.tanggal,
                    lokasi_cctv: it.lokasi_cctv,
                    waktu_pantau: it.waktu_pantau,
                    kondisi_pantau: it.kondisi_pantau,
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

    // Calculate CCTV summary statistics
    const stats = useMemo(() => {
        const totalEntries = items.length;
        let amanCount = 0;

        for (const item of items) {
            if (item.kondisi_pantau.trim().toLowerCase() === 'aman' || item.kondisi_pantau.trim().toLowerCase() === 'baik') {
                amanCount++;
            }
        }

        const amanPercent = totalEntries > 0 ? Math.round((amanCount / totalEntries) * 100) : 100;

        return {
            totalEntries,
            amanCount,
            amanPercent,
            cctvPointsCount: 8,
        };
    }, [items]);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Laporan Kondisi CCTV — ${unit.name}`} />

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
                                Laporan Kondisi CCTV
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
                            Laporan kondisi fisik, visual, dan pemantauan kamera pengawas CCTV di area unit pembangkit (SMT-FM-AK3-14.01).
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

            {/* Filter Card & Summary Stats */}
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
                            <Cctv className="size-3.5 text-sky-600" />
                            <span>8 Titik CCTV Terpantau</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Kondisi: {stats.amanPercent}% Aman / Normal</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <Eye className="size-3.5 text-indigo-600" />
                            <span>Pemantauan: Setiap Saat</span>
                        </div>
                    </div>
                </div>

                {/* Quick actions for Security Commander */}
                {can_write && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={setAllAman}
                            className="gap-1.5 text-xs"
                            title="Setel kondisi pantau ke 'Aman'"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel Kondisi Aman</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addItem}
                            className="gap-1.5 text-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Tambah Catatan Pantau</span>
                        </Button>
                    </div>
                )}
            </Card>

            {/* Printable Container (A4 Portrait matches original scan) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* Header KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="cctv-header-box relative rounded border-2 border-black p-4 dark:border-border">
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
                    <div className="px-24 text-center">
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

                    {/* Judul Dokumen & Kotak Metadata SMT-FM-AK3-14.01 */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex-1 text-left sm:pr-4">
                            <h2 className="text-xs font-black tracking-wider text-foreground uppercase sm:text-sm">
                                LAPORAN KONDISI CCTV PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                            </h2>
                        </div>

                        {/* Kotak Metadata Dokumen */}
                        <div className="w-full sm:w-64 border border-black text-[9px] dark:border-border sm:text-[10px]">
                            <div className="flex border-b border-black dark:border-border">
                                <div className="w-24 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                    No. dokumen
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
                                    No. revisi
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
                    <table className="cctv-table w-full border-collapse border border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-10 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    NO
                                </th>
                                <th className="w-28 border border-black px-2 py-1.5 text-center dark:border-border">
                                    TANGGAL
                                </th>
                                <th className="min-w-[220px] border border-black px-2 py-1.5 text-left dark:border-border">
                                    LOKASI CCTV
                                </th>
                                <th className="w-32 border border-black px-2 py-1.5 text-center dark:border-border">
                                    WAKTU PANTAU
                                </th>
                                <th className="w-28 border border-black px-2 py-1.5 text-center dark:border-border">
                                    KONDISI PANTAU
                                </th>
                                <th className="min-w-[120px] border border-black px-2 py-1.5 text-center dark:border-border">
                                    KETERANGAN
                                </th>
                                {can_write && (
                                    <th className="no-print w-10 border border-black px-1 py-1.5 text-center dark:border-border">
                                        #
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((it) => (
                                <tr key={it._key} className="hover:bg-muted/15 transition-colors">
                                    {/* NO */}
                                    <td className="border border-black px-1.5 py-2 text-center font-bold align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                value={it.no_urut}
                                                onChange={(e) => updateItem(it._key, 'no_urut', Number(e.target.value))}
                                                className="w-full text-center font-bold bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.no_urut
                                        )}
                                    </td>

                                    {/* TANGGAL */}
                                    <td className="border border-black px-2 py-2 text-center align-middle font-medium dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={it.tanggal}
                                                onChange={(e) => updateItem(it._key, 'tanggal', e.target.value)}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                                placeholder="31/8/2026"
                                            />
                                        ) : (
                                            it.tanggal
                                        )}
                                    </td>

                                    {/* LOKASI CCTV */}
                                    <td className="border border-black px-2.5 py-2 text-left align-top dark:border-border">
                                        {can_write ? (
                                            <div>
                                                <textarea
                                                    rows={8}
                                                    value={it.lokasi_cctv}
                                                    onChange={(e) => updateItem(it._key, 'lokasi_cctv', e.target.value)}
                                                    className="w-full bg-transparent focus:bg-background focus:outline-none text-[10px] leading-relaxed sm:text-[11px]"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => fillDefaultPoints(it._key)}
                                                    className="no-print text-[9px] text-primary hover:underline mt-0.5"
                                                >
                                                    Muat Ulang 8 Titik Standar
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="whitespace-pre-wrap leading-relaxed">
                                                {it.lokasi_cctv}
                                            </div>
                                        )}
                                    </td>

                                    {/* WAKTU PANTAU */}
                                    <td className="border border-black px-2 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={it.waktu_pantau}
                                                onChange={(e) => updateItem(it._key, 'waktu_pantau', e.target.value)}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                                placeholder="Setiap Saat"
                                            />
                                        ) : (
                                            it.waktu_pantau
                                        )}
                                    </td>

                                    {/* KONDISI PANTAU */}
                                    <td className="border border-black px-2 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={it.kondisi_pantau}
                                                onChange={(e) => updateItem(it._key, 'kondisi_pantau', e.target.value)}
                                                className="w-full text-center font-bold bg-transparent focus:bg-background focus:outline-none"
                                                placeholder="Aman"
                                            />
                                        ) : (
                                            <span className={it.kondisi_pantau.toLowerCase() === 'aman' || it.kondisi_pantau.toLowerCase() === 'baik' ? 'text-emerald-700 font-bold dark:text-emerald-400' : 'text-amber-700 font-bold dark:text-amber-400'}>
                                                {it.kondisi_pantau}
                                            </span>
                                        )}
                                    </td>

                                    {/* KETERANGAN */}
                                    <td className="border border-black px-2 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={it.keterangan}
                                                onChange={(e) => updateItem(it._key, 'keterangan', e.target.value)}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                                placeholder="Keterangan..."
                                            />
                                        ) : (
                                            <span>{it.keterangan || '—'}</span>
                                        )}
                                    </td>

                                    {/* Action */}
                                    {can_write && (
                                        <td className="no-print border border-black px-1 py-1 text-center align-middle dark:border-border">
                                            <button
                                                type="button"
                                                onClick={() => removeItem(it._key)}
                                                className="text-muted-foreground hover:text-red-600 transition-colors"
                                                title="Hapus baris ini"
                                            >
                                                <Trash2 className="size-3.5 mx-auto" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Additional Notes Box */}
                <div className="mt-4 border border-black p-3 text-[10px] dark:border-border sm:text-[11px]">
                    <div className="font-bold uppercase text-foreground mb-1">
                        Catatan Khusus Pemantauan / Gangguan Perangkat CCTV:
                    </div>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan kamera offline, kabel terputus, sudut pandang terhalang dahan/pohon, atau usulan relokasi..."
                            className="w-full rounded border border-input bg-transparent p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    ) : (
                        <div className="text-muted-foreground whitespace-pre-wrap">
                            {catatan || 'Tidak ada catatan khusus.'}
                        </div>
                    )}
                </div>

            </div>
        </div>
    );
}
