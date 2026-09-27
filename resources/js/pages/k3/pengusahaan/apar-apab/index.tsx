import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Flame,
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
import k3PengusahaanAparApab from '@/routes/k3/pengusahaan/apar-apab';
import type { IdName } from '@/types';

export type AparApabItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    no_rfid: string;
    lokasi: string;
    tgl_periksa: string;
    merk_apar: string;
    jenis_apar: string;
    berat_kg: number | string;
    kondisi_tabung: string;
    kondisi_nozzle_selang: string;
    indikator_tekanan: string;
    kondisi_pin_segel: string;
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
        items: Array<Omit<AparApabItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 10px !important; }
    .no-print { display: none !important; }
    .apar-table th, .apar-table td { border: 1px solid #000 !important; padding: 4px 5px !important; }
    .apar-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

/**
 * Check if the expiration string looks expired compared to the active period
 */
function isExpiredString(keterangan: string, filterYear: number, filterMonth: number): boolean {
    if (!keterangan) {
        return false;
    }

    const cleaned = keterangan.trim();
    // Match date formats like "Ex. DD/MM/YYYY" or "DD/MM/YYYY" or "YYYY-MM-DD"
    const match = cleaned.match(/(?:Ex\.?\s*)?(\d{1,2})[-/](\d{1,2})[-/](\d{4})/i);

    if (match) {
        const itemMonth = parseInt(match[2], 10);
        const itemYear = parseInt(match[3], 10);

        if (itemYear < filterYear) {
            return true;
        }

        if (itemYear === filterYear && itemMonth < filterMonth) {
            return true;
        }
    }

    return false;
}

export default function PengusahaanAparApabPage(props: Props) {
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
    const [items, setItems] = useState<AparApabItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            berat_kg: it.berat_kg ?? '',
            kondisi_tabung: it.kondisi_tabung ?? 'baik',
            kondisi_nozzle_selang: it.kondisi_nozzle_selang ?? 'baik',
            indikator_tekanan: it.indikator_tekanan ?? 'ok',
            kondisi_pin_segel: it.kondisi_pin_segel ?? 'baik',
        })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);
    const [bulkDate, setBulkDate] = useState(`12/${filters.month}/${filters.year}`);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanAparApab.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const addItem = () => {
        const newItem: AparApabItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            no_rfid: '',
            lokasi: '',
            tgl_periksa: bulkDate,
            merk_apar: 'Fire Venom',
            jenis_apar: 'Gas Cair',
            berat_kg: 6,
            kondisi_tabung: 'baik',
            kondisi_nozzle_selang: 'baik',
            indikator_tekanan: 'ok',
            kondisi_pin_segel: 'baik',
            keterangan: '',
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof AparApabItem, value: string | number) => {
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
                kondisi_tabung: 'baik',
                kondisi_nozzle_selang: 'baik',
                indikator_tekanan: 'ok',
                kondisi_pin_segel: 'baik',
            })),
        );
        setDirty(true);
    };

    const handleApplyBulkDate = () => {
        if (!bulkDate) {
            return;
        }

        setItems((prev) =>
            prev.map((item) => ({
                ...item,
                tgl_periksa: bulkDate,
            })),
        );
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
                berat_kg: it.berat_kg ?? '',
                kondisi_tabung: it.kondisi_tabung ?? 'baik',
                kondisi_nozzle_selang: it.kondisi_nozzle_selang ?? 'baik',
                indikator_tekanan: it.indikator_tekanan ?? 'ok',
                kondisi_pin_segel: it.kondisi_pin_segel ?? 'baik',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanAparApab.store().url,
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
                    no_rfid: it.no_rfid,
                    lokasi: it.lokasi,
                    tgl_periksa: it.tgl_periksa,
                    merk_apar: it.merk_apar,
                    jenis_apar: it.jenis_apar,
                    berat_kg: it.berat_kg === '' ? null : Number(it.berat_kg),
                    kondisi_tabung: it.kondisi_tabung,
                    kondisi_nozzle_selang: it.kondisi_nozzle_selang,
                    indikator_tekanan: it.indikator_tekanan,
                    kondisi_pin_segel: it.kondisi_pin_segel,
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
        let tabungBaik = 0;
        let tekananOk = 0;
        let pinBaik = 0;
        let expiredCount = 0;

        for (const item of items) {
            if (item.kondisi_tabung?.toLowerCase() === 'baik') {
                tabungBaik++;
            }

            if (item.indikator_tekanan?.toLowerCase() === 'ok') {
                tekananOk++;
            }

            if (item.kondisi_pin_segel?.toLowerCase() === 'baik') {
                pinBaik++;
            }

            if (isExpiredString(item.keterangan, filters.year, filters.month)) {
                expiredCount++;
            }
        }

        return { totalItems, tabungBaik, tekananOk, pinBaik, expiredCount };
    }, [items, filters.year, filters.month]);

    return (
        <>
            <Head title={`Kondisi APAR dan APAB — ${unit.name}`} />
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
                                    Kondisi APAR dan APAB
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
                                Akses 2 — Pengusahaan: Pemeriksaan fisik, tekanan, pin segel, dan masa kadaluarsa APAR & APAB (SMT-FM-AK3-12.03).
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
                                        value={bulkDate}
                                        onChange={(e) => setBulkDate(e.target.value)}
                                        placeholder="DD/MM/YYYY"
                                        className="w-28 rounded px-2 py-1 text-xs bg-background border border-border font-mono text-center focus:outline-none"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleApplyBulkDate}
                                        className="h-7 text-xs px-2.5"
                                        title="Terapkan tanggal ini ke seluruh baris"
                                    >
                                        Isi Tanggal Semua
                                    </Button>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSetAllBaik}
                                    className="h-8 gap-1.5 text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
                                    title="Setel tabung, nozzle, tekanan, dan pin segel ke kondisi 'baik' & 'ok'"
                                >
                                    <CheckCircle2 className="size-3.5" />
                                    Setel Semua Baik & OK
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Summary badges */}
                    <div className="mt-3 flex flex-wrap items-center gap-3 pt-3 border-t border-border">
                        <div className="flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 text-xs font-semibold text-primary">
                            <Flame className="size-3.5" />
                            <span>Total Tabung: {summary.totalItems} Unit</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <span>Tabung Baik: {summary.tabungBaik} / {summary.totalItems}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            <span>Tekanan OK: {summary.tekananOk} / {summary.totalItems}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <span>Pin/Segel Baik: {summary.pinBaik} / {summary.totalItems}</span>
                        </div>
                        {summary.expiredCount > 0 && (
                            <div className="flex items-center gap-1.5 rounded-lg border border-destructive/30 bg-destructive/10 px-2.5 py-1 text-xs font-bold text-destructive">
                                <Sparkles className="size-3.5" />
                                <span>{summary.expiredCount} Tabung Kadaluarsa!</span>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Printable container (A4 Landscape) */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                    <div className="apar-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                        {/* Judul Dokumen & Kotak Metadata Formulir SMT-FM-AK3-12.03 */}
                        <div className="flex items-center justify-between">
                            <div className="flex-1 text-center pl-28">
                                <h2 className="text-sm font-black tracking-wider text-foreground uppercase">
                                    KONDISI ALAT PEMADAM API RINGAN DAN BESAR (APAR-APAB)
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

                    {/* Tabel Inspeksi APAR / APAB */}
                    <div className="mt-4 overflow-x-auto">
                        <table className="apar-table w-full border-collapse border-2 border-black text-xs dark:border-border">
                            <thead>
                                <tr className="bg-muted/40 text-center font-bold text-foreground">
                                    <th className="w-10 border border-black p-1.5 dark:border-border">NO.</th>
                                    <th className="w-16 border border-black p-1.5 dark:border-border">NO RFID</th>
                                    <th className="w-44 border border-black p-1.5 text-left dark:border-border">LOKASI</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">TGL PERIKSA</th>
                                    <th className="w-28 border border-black p-1.5 dark:border-border">MERK APAR</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">JENIS APAR</th>
                                    <th className="w-16 border border-black p-1.5 dark:border-border">BERAT (KG)</th>
                                    <th className="w-20 border border-black p-1.5 dark:border-border">KONDISI TABUNG</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border leading-tight">
                                        KONDISI NOZZLE/ SELANG
                                    </th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border leading-tight">
                                        INDIKATOR TEKANAN TABUNG
                                    </th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border leading-tight">
                                        KONDISI PIN/SEGEL
                                    </th>
                                    <th className="w-32 border border-black p-1.5 dark:border-border">KETERANGAN</th>
                                    {can_write && (
                                        <th className="no-print w-10 border border-black p-1.5 dark:border-border">AKSI</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={can_write ? 13 : 12} className="p-6 text-center text-muted-foreground italic">
                                            Belum ada data tabung APAR/APAB. Klik tombol di bawah untuk menambahkan item.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item, index) => {
                                        const isExpired = isExpiredString(item.keterangan, filters.year, filters.month);

                                        return (
                                            <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                                                {/* NO */}
                                                <td className="border-r border-black p-1 text-center font-mono dark:border-border">
                                                    {index + 1}
                                                </td>

                                                {/* NO RFID */}
                                                <td className="border-r border-black p-1 text-center font-mono dark:border-border">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.no_rfid}
                                                            onChange={(e) => updateItem(item._key, 'no_rfid', e.target.value)}
                                                            placeholder="-"
                                                            className="w-full text-center bg-transparent focus:outline-none font-mono"
                                                        />
                                                    ) : (
                                                        item.no_rfid || '-'
                                                    )}
                                                </td>

                                                {/* LOKASI */}
                                                <td className="border-r border-black p-1 dark:border-border font-medium">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.lokasi}
                                                            onChange={(e) => updateItem(item._key, 'lokasi', e.target.value)}
                                                            placeholder="Lokasi penempatan..."
                                                            className="w-full bg-transparent focus:outline-none"
                                                        />
                                                    ) : (
                                                        item.lokasi
                                                    )}
                                                </td>

                                                {/* TGL PERIKSA */}
                                                <td className="border-r border-black p-1 text-center dark:border-border font-mono text-[11px]">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.tgl_periksa}
                                                            onChange={(e) => updateItem(item._key, 'tgl_periksa', e.target.value)}
                                                            placeholder="DD/MM/YYYY"
                                                            className="w-full text-center bg-transparent focus:outline-none font-mono"
                                                        />
                                                    ) : (
                                                        item.tgl_periksa || '-'
                                                    )}
                                                </td>

                                                {/* MERK APAR */}
                                                <td className="border-r border-black p-1 text-center dark:border-border font-semibold">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.merk_apar}
                                                            onChange={(e) => updateItem(item._key, 'merk_apar', e.target.value)}
                                                            placeholder="Merk..."
                                                            className="w-full text-center bg-transparent focus:outline-none"
                                                        />
                                                    ) : (
                                                        item.merk_apar || '-'
                                                    )}
                                                </td>

                                                {/* JENIS APAR */}
                                                <td className="border-r border-black p-1 text-center dark:border-border">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.jenis_apar}
                                                            onChange={(e) => updateItem(item._key, 'jenis_apar', e.target.value)}
                                                            placeholder="Jenis..."
                                                            className="w-full text-center bg-transparent focus:outline-none"
                                                        />
                                                    ) : (
                                                        item.jenis_apar || '-'
                                                    )}
                                                </td>

                                                {/* BERAT (KG) */}
                                                <td className="border-r border-black p-1 text-center dark:border-border font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            min={0}
                                                            value={item.berat_kg}
                                                            onChange={(e) => updateItem(item._key, 'berat_kg', e.target.value)}
                                                            placeholder="-"
                                                            className="w-full text-center bg-transparent focus:outline-none font-mono"
                                                        />
                                                    ) : (
                                                        item.berat_kg !== '' && item.berat_kg !== null ? item.berat_kg : '-'
                                                    )}
                                                </td>

                                                {/* KONDISI TABUNG */}
                                                <td className="border-r border-black p-1 text-center dark:border-border lowercase">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.kondisi_tabung}
                                                            onChange={(e) => updateItem(item._key, 'kondisi_tabung', e.target.value)}
                                                            className={`w-full text-center bg-transparent focus:outline-none ${item.kondisi_tabung?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : 'text-emerald-700 dark:text-emerald-400'}`}
                                                        />
                                                    ) : (
                                                        <span className={item.kondisi_tabung?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : ''}>
                                                            {item.kondisi_tabung || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* KONDISI NOZZLE/SELANG */}
                                                <td className="border-r border-black p-1 text-center dark:border-border lowercase">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.kondisi_nozzle_selang}
                                                            onChange={(e) => updateItem(item._key, 'kondisi_nozzle_selang', e.target.value)}
                                                            className={`w-full text-center bg-transparent focus:outline-none ${item.kondisi_nozzle_selang?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : 'text-emerald-700 dark:text-emerald-400'}`}
                                                        />
                                                    ) : (
                                                        <span className={item.kondisi_nozzle_selang?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : ''}>
                                                            {item.kondisi_nozzle_selang || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* INDIKATOR TEKANAN TABUNG */}
                                                <td className="border-r border-black p-1 text-center dark:border-border lowercase">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.indikator_tekanan}
                                                            onChange={(e) => updateItem(item._key, 'indikator_tekanan', e.target.value)}
                                                            className={`w-full text-center bg-transparent focus:outline-none ${item.indikator_tekanan?.toLowerCase() !== 'ok' ? 'font-bold text-destructive' : 'text-sky-700 dark:text-sky-400'}`}
                                                        />
                                                    ) : (
                                                        <span className={item.indikator_tekanan?.toLowerCase() !== 'ok' ? 'font-bold text-destructive' : ''}>
                                                            {item.indikator_tekanan || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* KONDISI PIN/SEGEL */}
                                                <td className="border-r border-black p-1 text-center dark:border-border lowercase">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.kondisi_pin_segel}
                                                            onChange={(e) => updateItem(item._key, 'kondisi_pin_segel', e.target.value)}
                                                            className={`w-full text-center bg-transparent focus:outline-none ${item.kondisi_pin_segel?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : 'text-emerald-700 dark:text-emerald-400'}`}
                                                        />
                                                    ) : (
                                                        <span className={item.kondisi_pin_segel?.toLowerCase() !== 'baik' ? 'font-bold text-destructive' : ''}>
                                                            {item.kondisi_pin_segel || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* KETERANGAN (HIGHLIGHT MERAH JIKA EXPIRED) */}
                                                <td className={`border-r border-black p-1 text-center dark:border-border font-mono text-[11px] ${isExpired ? 'text-destructive font-bold' : ''}`}>
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={item.keterangan}
                                                            onChange={(e) => updateItem(item._key, 'keterangan', e.target.value)}
                                                            placeholder="Ex. DD/MM/YYYY"
                                                            className={`w-full text-center bg-transparent focus:outline-none font-mono ${isExpired ? 'text-destructive font-bold' : ''}`}
                                                        />
                                                    ) : (
                                                        <span>{item.keterangan || '-'}</span>
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
                                        );
                                    })
                                )}
                            </tbody>
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
                                Tambah Tabung APAR / APAB
                            </Button>
                        </div>
                    )}

                    {/* Catatan / Keterangan Tambahan */}
                    <div className="mt-4">
                        <div className="text-xs font-semibold text-foreground">Catatan / Tindak Lanjut Pemeliharaan APAR:</div>
                        {can_write ? (
                            <textarea
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                placeholder="Tambahkan catatan khusus kondisi tabung atau jadwal isi ulang APAR/APAB jika ada..."
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
