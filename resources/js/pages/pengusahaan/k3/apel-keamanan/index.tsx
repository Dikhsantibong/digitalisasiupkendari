import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Filter,
    Printer,
    RotateCcw,
    Save,
    Shield,
    Sparkles,
    UserCheck,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanApelKeamanan from '@/routes/k3/pengusahaan/apel-keamanan';
import type { IdName } from '@/types';

export type ApelKeamananItem = {
    _key: number;
    id?: number | null;
    tanggal: string;
    hari_ke: number;
    tim_regu: string;
    shift: string;
    waktu_apel: string;
    jumlah_personil: number;
    kelengkapan_atribut: string;
    paraf_komandan_regu: string;
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
        items: Array<Omit<ApelKeamananItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 portrait; margin: 8mm; }
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
        font-size: 10px !important; 
    }
    .no-print { display: none !important; }
    .apel-table { width: 100% !important; border-collapse: collapse !important; }
    .apel-table th, .apel-table td { border: 1px solid #000 !important; padding: 3px 5px !important; }
    .apel-header-box { border: 2px solid #000 !important; }
    tr.day-group { page-break-inside: avoid !important; break-inside: avoid !important; }
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

export default function PengusahaanApelKeamananPage(props: Props) {
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

    const [items, setItems] = useState<ApelKeamananItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            kelengkapan_atribut: it.kelengkapan_atribut || 'Lengkap',
            paraf_komandan_regu: it.paraf_komandan_regu || '',
            keterangan: it.keterangan || '',
        })),
    );

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [dayRangeFilter, setDayRangeFilter] = useState<'all' | '1-10' | '11-20' | '21-end'>('all');

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: string) => {
        router.get(
            k3PengusahaanApelKeamanan.index().url,
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

    const updateItem = (key: number, field: keyof ApelKeamananItem, value: unknown) => {
        setItems((prev) =>
            prev.map((it) => (it._key === key ? { ...it, [field]: value } : it)),
        );
        setDirty(true);
    };

    // Quick fill all personil to 2 and atribut to Lengkap
    const setAllStandard = () => {
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                jumlah_personil: 2,
                kelengkapan_atribut: 'Lengkap',
            })),
        );
        setDirty(true);
    };

    // Quick fill standard shift rotation (A: Pagi, B: Sore, C: Malam)
    const setStandardRegu = () => {
        setItems((prev) =>
            prev.map((it) => {
                let regu = it.tim_regu;

                if (it.shift.toLowerCase().includes('pagi')) {
                    regu = 'A';
                } else if (it.shift.toLowerCase().includes('sore') || it.shift.toLowerCase().includes('siang')) {
                    regu = 'B';
                } else if (it.shift.toLowerCase().includes('malam')) {
                    regu = 'C';
                }

                return { ...it, tim_regu: regu };
            }),
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
                kelengkapan_atribut: it.kelengkapan_atribut || 'Lengkap',
                paraf_komandan_regu: it.paraf_komandan_regu || '',
                keterangan: it.keterangan || '',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanApelKeamanan.store().url,
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
                    tanggal: it.tanggal,
                    hari_ke: it.hari_ke,
                    tim_regu: it.tim_regu,
                    shift: it.shift,
                    waktu_apel: it.waktu_apel,
                    jumlah_personil: Number(it.jumlah_personil) || 0,
                    kelengkapan_atribut: it.kelengkapan_atribut,
                    paraf_komandan_regu: it.paraf_komandan_regu,
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

    // Calculate security attendance stats
    const stats = useMemo(() => {
        const totalItems = items.length;

        if (totalItems === 0) {
            return {
                totalDays: 0,
                totalApel: 0,
                totalPersonil: 0,
                avgPersonil: '0.0',
                lengkapCount: 0,
                lengkapPercent: 100,
            };
        }

        const distinctDays = new Set(items.map((it) => it.hari_ke)).size;
        let sumPersonil = 0;
        let lengkapCount = 0;

        for (const item of items) {
            sumPersonil += Number(item.jumlah_personil) || 0;

            if (item.kelengkapan_atribut.trim().toLowerCase() === 'lengkap') {
                lengkapCount++;
            }

        }

        const avgPersonil = (sumPersonil / totalItems).toFixed(1);
        const lengkapPercent = Math.round((lengkapCount / totalItems) * 100);

        return {
            totalDays: distinctDays,
            totalApel: totalItems,
            totalPersonil: sumPersonil,
            avgPersonil,
            lengkapCount,
            lengkapPercent,
        };
    }, [items]);

    // Screen view filter (print view always prints all items)
    const filteredItems = useMemo(() => {
        return items.filter((it) => {
            if (dayRangeFilter === '1-10' && (it.hari_ke < 1 || it.hari_ke > 10)) {
                return false;
            }

            if (dayRangeFilter === '11-20' && (it.hari_ke < 11 || it.hari_ke > 20)) {
                return false;
            }

            if (dayRangeFilter === '21-end' && it.hari_ke < 21) {
                return false;
            }

            return true;
        });
    }, [items, dayRangeFilter]);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Laporan Apel Satuan Keamanan — ${unit.name}`} />

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
                                Laporan Apel Satuan Keamanan
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
                            Laporan apel harian satuan pengamanan per shift dan pemantauan kelengkapan atribut (SMT-FM-AK3-13.13).
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
                            <Shield className="size-3.5 text-sky-600" />
                            <span>{stats.totalDays} Hari ({stats.totalApel} Sesi Apel)</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Atribut: {stats.lengkapPercent}% Lengkap</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <Users className="size-3.5 text-indigo-600" />
                            <span>Rerata: {stats.avgPersonil} Personil/Shift</span>
                        </div>
                    </div>
                </div>

                {/* Quick actions for Security Commander / Safety Officer */}
                {can_write && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={setAllStandard}
                            className="gap-1.5 text-xs"
                            title="Setel personil 2 dan atribut lengkap untuk seluruh shift dalam satu klik"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel 2 Personil & Lengkap</span>
                        </Button>


                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={setStandardRegu}
                            className="gap-1.5 text-xs"
                            title="Setel tim regu standar (Pagi: A, Sore: B, Malam: C)"
                        >
                            <UserCheck className="size-3.5 text-sky-600" />
                            <span>Setel Regu Standar (A-B-C)</span>
                        </Button>

                        {/* Page / Range Navigation Tabs for Screen View */}
                        <div className="ml-auto flex items-center gap-1.5 text-xs">
                            <span className="text-muted-foreground flex items-center gap-1">
                                <Filter className="size-3" /> Tampilkan:
                            </span>
                            <div className="inline-flex rounded-md border p-0.5 bg-muted/30">
                                <button
                                    type="button"
                                    onClick={() => setDayRangeFilter('all')}
                                    className={`px-2 py-0.5 rounded text-xs transition-colors ${
                                        dayRangeFilter === 'all'
                                            ? 'bg-background font-bold text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Semua ({stats.totalDays} Tgl)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDayRangeFilter('1-10')}
                                    className={`px-2 py-0.5 rounded text-xs transition-colors ${
                                        dayRangeFilter === '1-10'
                                            ? 'bg-background font-bold text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Tgl 1–10
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDayRangeFilter('11-20')}
                                    className={`px-2 py-0.5 rounded text-xs transition-colors ${
                                        dayRangeFilter === '11-20'
                                            ? 'bg-background font-bold text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Tgl 11–20
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDayRangeFilter('21-end')}
                                    className={`px-2 py-0.5 rounded text-xs transition-colors ${
                                        dayRangeFilter === '21-end'
                                            ? 'bg-background font-bold text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Tgl 21–{stats.totalDays}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </Card>

            {/* Printable Container (A4 Portrait matches original scan) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* Header KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="apel-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                    {/* Judul Dokumen & Kotak Metadata SMT-FM-AK3-13.13 */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex-1 text-left sm:pr-4">
                            <h2 className="text-xs font-black tracking-wider text-foreground uppercase sm:text-sm">
                                LAPORAN APEL SATUAN KEAMANAN PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
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

                {/* Security Apel Table */}
                <div className="mt-4 overflow-x-auto">
                    <table className="apel-table w-full border-collapse border border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-10 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    NO
                                </th>
                                <th className="w-28 min-w-[110px] border border-black px-2 py-1.5 text-center dark:border-border">
                                    TANGGAL
                                </th>
                                <th className="w-16 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    TIM REGU
                                </th>
                                <th className="w-16 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    SHIFT
                                </th>
                                <th className="w-20 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    WAKTU APEL
                                </th>
                                <th className="w-20 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    JUMLAH PERSONIL
                                </th>
                                <th className="w-28 border border-black px-2 py-1.5 text-center dark:border-border">
                                    KELENGKAPAN ATRIBUT
                                </th>
                                <th className="min-w-[100px] border border-black px-2 py-1.5 text-center dark:border-border">
                                    KET.
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {/* In screen view, show filteredItems. In print, print all items */}
                            {items.map((it, idx) => {
                                const isVisibleOnScreen = filteredItems.some((f) => f._key === it._key);

                                // Check if this item is the first row for this hari_ke
                                const isFirstOfHari = idx === 0 || items[idx - 1].hari_ke !== it.hari_ke;
                                const hariRows = items.filter((x) => x.hari_ke === it.hari_ke);
                                const rowSpan = hariRows.length;

                                // Date label formatted e.g. "1 Agustus 2026"
                                const dateLabel = `${it.hari_ke} ${monthName} ${filters.year}`;

                                return (
                                    <tr
                                        key={it._key}
                                        className={`day-group hover:bg-muted/15 transition-colors ${
                                            !isVisibleOnScreen ? 'no-screen hidden print:table-row' : ''
                                        }`}
                                    >
                                        {/* NO & TANGGAL with rowSpan */}
                                        {isFirstOfHari && (
                                            <>
                                                <td
                                                    rowSpan={rowSpan}
                                                    className="border border-black px-1.5 py-1 text-center font-bold align-middle bg-card dark:border-border"
                                                >
                                                    {it.hari_ke}
                                                </td>
                                                <td
                                                    rowSpan={rowSpan}
                                                    className="border border-black px-2 py-1 text-center font-medium align-middle bg-card whitespace-nowrap dark:border-border"
                                                >
                                                    {dateLabel}
                                                </td>
                                            </>
                                        )}

                                        {/* TIM REGU */}
                                        <td className="border border-black px-1 py-0.5 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.tim_regu}
                                                    onChange={(e) => updateItem(it._key, 'tim_regu', e.target.value)}
                                                    className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                                    placeholder="A/B/C"
                                                />
                                            ) : (
                                                <span className="font-bold">{it.tim_regu}</span>
                                            )}
                                        </td>

                                        {/* SHIFT */}
                                        <td className="border border-black px-1 py-0.5 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.shift}
                                                    onChange={(e) => updateItem(it._key, 'shift', e.target.value)}
                                                    className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                                    placeholder="Pagi/Sore/Malam"
                                                />
                                            ) : (
                                                it.shift
                                            )}
                                        </td>

                                        {/* WAKTU APEL */}
                                        <td className="border border-black px-1 py-0.5 text-center font-mono align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.waktu_apel}
                                                    onChange={(e) => updateItem(it._key, 'waktu_apel', e.target.value)}
                                                    className="w-full text-center bg-transparent font-mono focus:bg-background focus:outline-none"
                                                    placeholder="08:00"
                                                />
                                            ) : (
                                                it.waktu_apel
                                            )}
                                        </td>

                                        {/* JUMLAH PERSONIL */}
                                        <td className="border border-black px-1 py-0.5 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="50"
                                                    value={it.jumlah_personil}
                                                    onChange={(e) => updateItem(it._key, 'jumlah_personil', Number(e.target.value))}
                                                    className="w-full text-center font-semibold bg-transparent focus:bg-background focus:outline-none"
                                                />
                                            ) : (
                                                <span className="font-semibold">{it.jumlah_personil}</span>
                                            )}
                                        </td>

                                        {/* KELENGKAPAN ATRIBUT */}
                                        <td className="border border-black px-1 py-0.5 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <select
                                                    value={it.kelengkapan_atribut}
                                                    onChange={(e) => updateItem(it._key, 'kelengkapan_atribut', e.target.value)}
                                                    className="w-full text-center bg-transparent text-[10px] focus:bg-background focus:outline-none sm:text-[11px]"
                                                >
                                                    <option value="Lengkap">Lengkap</option>
                                                    <option value="Tidak Lengkap">Tidak Lengkap</option>
                                                </select>
                                            ) : (
                                                <span className={it.kelengkapan_atribut === 'Lengkap' ? 'text-emerald-700 font-medium dark:text-emerald-400' : 'text-amber-700 font-semibold dark:text-amber-400'}>
                                                    {it.kelengkapan_atribut}
                                                </span>
                                            )}
                                        </td>

                                        {/* KETERANGAN */}
                                        <td className="border border-black px-1.5 py-0.5 text-left align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.keterangan}
                                                    onChange={(e) => updateItem(it._key, 'keterangan', e.target.value)}
                                                    className="w-full bg-transparent focus:bg-background focus:outline-none"
                                                    placeholder="Keterangan / Catatan..."
                                                />
                                            ) : (
                                                <span>{it.keterangan || '—'}</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Additional Notes Box */}
                <div className="mt-4 border border-black p-3 text-[10px] dark:border-border sm:text-[11px]">
                    <div className="font-bold uppercase text-foreground mb-1">
                        Catatan Khusus / Informasi Keamanan:
                    </div>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan koordinasi tim keamanan, kejadian menonjol, atau arahan khusus komandan regu..."
                            className="w-full rounded border border-input bg-transparent p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    ) : (
                        <div className="text-muted-foreground whitespace-pre-wrap">
                            {catatan || 'Tidak ada catatan khusus.'}
                        </div>
                    )}
                </div>

                {/* Footer Signatures */}
                <div className="mt-6 grid grid-cols-2 gap-8 text-center text-[10px] sm:text-xs">
                    <div>
                        <div className="text-muted-foreground">Mengetahui,</div>
                        <div className="font-bold text-foreground">Team Leader K3 & Keamanan</div>
                        <div className="h-14" />
                        <div className="font-bold underline text-foreground">( ........................................ )</div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Kendari, {monthName} {filters.year}</div>
                        <div className="font-bold text-foreground">Komandan Regu Satuan Pengamanan</div>
                        <div className="h-14" />
                        <div className="font-bold underline text-foreground">( ........................................ )</div>
                    </div>
                </div>
            </div>
        </div>
    );
}
