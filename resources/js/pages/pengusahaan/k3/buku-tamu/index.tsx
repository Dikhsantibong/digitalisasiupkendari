import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BookUser,
    CheckCircle2,
    Eye,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Sparkles,
    Trash2,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanBukuTamu from '@/routes/k3/pengusahaan/buku-tamu';
import type { IdName } from '@/types';

export type BukuTamuItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    tanggal: string;
    jumlah_kehadiran_tamu: number;
    tamu_pln: number;
    instansi: number;
    kontraktor: number;
    lainnya: number;
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
        items: Array<Omit<BukuTamuItem, '_key'>>;
    };
    sample_items?: Array<Omit<BukuTamuItem, '_key'>>;
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
        font-size: 10.5px !important; 
    }
    .no-print { display: none !important; }
    .buku-tamu-table { width: 100% !important; border-collapse: collapse !important; }
    .buku-tamu-table th, .buku-tamu-table td { border: 1px solid #000 !important; padding: 5px 6px !important; }
    .buku-tamu-header-box { border: 2px solid #000 !important; }
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

export default function PengusahaanBukuTamuPage(props: Props) {
    const {
        unit,
        filters,
        options,
        record: initialRecord,
        sample_items = [],
        has_saved,
        can_write,
    } = props;

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const [noDokumen, setNoDokumen] = useState(
        initialRecord.no_dokumen || 'SMT-FM-AK3-06.04'
    );
    const [revisi, setRevisi] = useState(initialRecord.revisi || '01');
    const [tanggalDokumen, setTanggalDokumen] = useState(
        initialRecord.tanggal_dokumen || '23 SEPTEMBER 2019'
    );
    const [catatan, setCatatan] = useState(initialRecord.catatan || '');

    const [items, setItems] = useState<BukuTamuItem[]>(() => {
        let counter = 1;

        return (initialRecord.items || []).map((it) => ({
            ...it,
            _key: counter++,
        }));
    });

    const monthName = useMemo(() => {
        return OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    }, [filters.month]);

    const totals = useMemo(() => {
        return items.reduce(
            (acc, it) => {
                acc.jumlah += Number(it.jumlah_kehadiran_tamu) || 0;
                acc.pln += Number(it.tamu_pln) || 0;
                acc.instansi += Number(it.instansi) || 0;
                acc.kontraktor += Number(it.kontraktor) || 0;
                acc.lainnya += Number(it.lainnya) || 0;

                return acc;
            },
            { jumlah: 0, pln: 0, instansi: 0, kontraktor: 0, lainnya: 0 }
        );
    }, [items]);

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: number) => {
        if (dirty && !confirm('Perubahan yang belum disimpan akan hilang. Lanjutkan ganti periode/unit?')) {
            return;
        }

        router.get(
            k3PengusahaanBukuTamu.index().url,
            {
                unit_id: key === 'unit_id' ? val : filters.unit_id,
                month: key === 'month' ? val : filters.month,
                year: key === 'year' ? val : filters.year,
            },
            { preserveState: false }
        );
    };

    const updateItem = <K extends keyof BukuTamuItem>(
        key: number,
        field: K,
        value: BukuTamuItem[K]
    ) => {
        setItems((prev) =>
            prev.map((it) => {
                if (it._key !== key) {
return it;
}

                const updated = { ...it, [field]: value };

                // Auto-calculate jumlah_kehadiran_tamu if one of the 4 visitor types was updated
                if (
                    field === 'tamu_pln' ||
                    field === 'instansi' ||
                    field === 'kontraktor' ||
                    field === 'lainnya'
                ) {
                    const pln = Number(field === 'tamu_pln' ? value : it.tamu_pln) || 0;
                    const instansi = Number(field === 'instansi' ? value : it.instansi) || 0;
                    const kontraktor = Number(field === 'kontraktor' ? value : it.kontraktor) || 0;
                    const lainnya = Number(field === 'lainnya' ? value : it.lainnya) || 0;
                    updated.jumlah_kehadiran_tamu = pln + instansi + kontraktor + lainnya;
                }

                return updated;
            })
        );
        setDirty(true);
    };

    const addItem = () => {
        const nextNo = items.length > 0 ? Math.max(...items.map((i) => i.no_urut)) + 1 : 1;
        const newKey = Date.now() + Math.random();
        const defaultDate = `${Math.min(nextNo, 28)}/${filters.month}/${filters.year}`;

        setItems((prev) => [
            ...prev,
            {
                _key: newKey,
                id: null,
                no_urut: nextNo,
                tanggal: defaultDate,
                jumlah_kehadiran_tamu: 0,
                tamu_pln: 0,
                instansi: 0,
                kontraktor: 0,
                lainnya: 0,
                keterangan: '',
                sort_order: prev.length,
            },
        ]);
        setDirty(true);
    };

    const removeItem = (key: number) => {
        if (items.length <= 1) {
            alert('Minimal harus ada 1 baris entri.');

            return;
        }

        setItems((prev) => {
            const next = prev.filter((i) => i._key !== key);

            return next.map((it, idx) => ({ ...it, no_urut: idx + 1, sort_order: idx }));
        });
        setDirty(true);
    };

    const loadSampleRows = () => {
        if (!confirm('Muat contoh data mutasi buku tamu sesuai dokumen resmi SMT-FM-AK3-06.04? Baris yang belum disimpan akan digantikan.')) {
            return;
        }

        let counter = 1;
        setItems(
            sample_items.map((it) => ({
                ...it,
                _key: counter++,
            }))
        );
        setDirty(true);
    };

    const recalculateAllTotals = () => {
        setItems((prev) =>
            prev.map((it) => {
                const pln = Number(it.tamu_pln) || 0;
                const instansi = Number(it.instansi) || 0;
                const kontraktor = Number(it.kontraktor) || 0;
                const lainnya = Number(it.lainnya) || 0;

                return {
                    ...it,
                    jumlah_kehadiran_tamu: pln + instansi + kontraktor + lainnya,
                };
            })
        );
        setDirty(true);
    };

    const handleSave = () => {
        if (!can_write) {
return;
}

        setSaving(true);
        router.post(
            k3PengusahaanBukuTamu.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: it.no_urut || idx + 1,
                    tanggal: it.tanggal || `${idx + 1}/${filters.month}/${filters.year}`,
                    jumlah_kehadiran_tamu: Number(it.jumlah_kehadiran_tamu) || 0,
                    tamu_pln: Number(it.tamu_pln) || 0,
                    instansi: Number(it.instansi) || 0,
                    kontraktor: Number(it.kontraktor) || 0,
                    lainnya: Number(it.lainnya) || 0,
                    keterangan: it.keterangan || '',
                    sort_order: idx,
                })),
            },
            {
                onSuccess: () => {
                    setSaving(false);
                    setDirty(false);
                },
                onError: (err) => {
                    setSaving(false);
                    const msg = Object.values(err)[0] || 'Gagal menyimpan data laporan mutasi buku tamu.';
                    alert(msg);
                },
            }
        );
    };

    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="space-y-6 p-4 sm:p-6 lg:p-8">
            <style>{PRINT_CSS}</style>
            <Head
                title={`Laporan Mutasi Buku Tamu - ${unit.name} (${monthName} ${filters.year})`}
            />

            {/* TOP BAR / NAVIGATION (NO PRINT) */}
            <div className="no-print flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9 shrink-0"
                        onClick={() => router.get(k3Pengusahaan.index('input').url)}
                        title="Kembali ke Menu K3 Input"
                    >
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Laporan Mutasi Buku Tamu
                            </h1>
                            <Badge
                                variant={has_saved ? 'default' : 'outline'}
                                className={
                                    has_saved
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                        : 'border-amber-500 text-amber-600 dark:text-amber-400'
                                }
                            >
                                {has_saved ? (
                                    <>
                                        <CheckCircle2 className="mr-1 size-3" /> Tersimpan
                                    </>
                                ) : (
                                    <>
                                        <Eye className="mr-1 size-3" /> Draft / Template
                                    </>
                                )}
                            </Badge>
                            {dirty && (
                                <Badge variant="secondary" className="bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                    Belum Disimpan
                                </Badge>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground sm:text-sm">
                            Formulir No. {noDokumen} — Rekapitulasi mutasi dan kehadiran tamu berkunjung ke unit pembangkit.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handlePrint}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <Printer className="size-3.5" />
                        Cetak A4
                    </Button>
                    {can_write && (
                        <Button
                            size="sm"
                            onClick={handleSave}
                            disabled={saving}
                            className="gap-1.5 text-xs font-semibold bg-primary hover:bg-primary/90 text-primary-foreground"
                        >
                            <Save className="size-3.5" />
                            {saving ? 'Menyimpan...' : 'Simpan Laporan'}
                        </Button>
                    )}
                </div>
            </div>

            {/* FILTER BAR (NO PRINT) */}
            <Card className="no-print p-4 border border-border shadow-xs">
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <OperasiSelect
                            label="Unit Layanan"
                            value={String(filters.unit_id)}
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            onChange={(val) => handleFilterChange('unit_id', Number(val))}
                        />
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            options={OPERASI_MONTHS.map((label, idx) => ({
                                value: String(idx + 1),
                                label,
                            }))}
                            onChange={(val) => handleFilterChange('month', Number(val))}
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            options={options.years.map((y) => ({
                                value: String(y),
                                label: String(y),
                            }))}
                            onChange={(val) => handleFilterChange('year', Number(val))}
                        />
                    </div>

                    {/* Quick summary badges */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            <Users className="size-3.5 text-sky-600" />
                            <span>Total Tamu: {totals.jumlah} Orang</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <BookUser className="size-3.5 text-emerald-600" />
                            <span>PLN: {totals.pln} | Kontraktor: {totals.kontraktor} | Instansi: {totals.instansi}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <span>Lainnya / Umum: {totals.lainnya} Orang</span>
                        </div>
                    </div>

                    {can_write && (
                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                                className="gap-1.5 text-xs font-medium"
                            >
                                <Plus className="size-3.5" />
                                Tambah Baris Tamu
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={recalculateAllTotals}
                                className="gap-1.5 text-xs font-medium"
                                title="Hitung ulang jumlah total tamu per baris"
                            >
                                <RotateCcw className="size-3.5" />
                                Hitung Total
                            </Button>
                            {sample_items.length > 0 && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={loadSampleRows}
                                    className="gap-1.5 text-xs font-medium"
                                    title="Muat contoh baris sesuai scan fisik dokumen SMT-FM-AK3-06.04"
                                >
                                    <Sparkles className="size-3.5 text-amber-500" />
                                    Muat Contoh Dokumen
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </Card>

            {/* PRINT CONTAINER / REPORT SHEET */}
            <div className="print-container rounded-lg border border-border bg-card p-4 sm:p-6 shadow-xs text-card-foreground">
                {/* KOP RESMI PLN NUSANTARA POWER & LAMBANG K3 */}
                <div className="relative rounded border-2 border-black p-3 dark:border-border">
                    {/* Logo PLN Kiri */}
                    <div className="absolute left-3 top-3 flex items-center">
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
                    <div className="absolute right-3 top-3 flex items-center">
                        <img
                            src="/logo/k3.png"
                            alt="Logo K3"
                            className="max-h-12 object-contain"
                            onError={(e) => {
                                (e.target as HTMLElement).style.display = 'none';
                            }}
                        />
                    </div>

                    {/* Header Text Tengah */}
                    <div className="text-center px-16">
                        <div className="text-xs font-black tracking-wide sm:text-sm text-foreground">
                            PT. PLN NUSANTARA POWER
                        </div>
                        <div className="text-[11px] font-bold tracking-wider sm:text-xs text-foreground">
                            UNIT PEMBANGKITAN KENDARI
                        </div>
                        <div className="text-[10px] font-semibold uppercase sm:text-[11px] text-foreground">
                            UNIT LAYANAN {unit.name}
                        </div>
                    </div>
                </div>

                {/* FORM TITLE & METADATA BOX */}
                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_260px] sm:items-stretch">
                    {/* Left: Document Title */}
                    <div className="flex items-center justify-center border-2 border-black p-2.5 text-center dark:border-border">
                        <h2 className="text-xs font-black uppercase tracking-wide sm:text-sm text-foreground">
                            LAPORAN MUTASI BUKU TAMU PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                        </h2>
                    </div>

                    {/* Right: Document Control Box */}
                    <div className="border-2 border-black text-[10px] dark:border-border sm:text-[11px]">
                        <div className="grid grid-cols-[85px_1fr] border-b border-black dark:border-border">
                            <div className="border-r border-black p-1 font-bold dark:border-border">
                                No.Formulir
                            </div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input
                                        type="text"
                                        value={noDokumen}
                                        onChange={(e) => {
                                            setNoDokumen(e.target.value);
                                            setDirty(true);
                                        }}
                                        className="w-full bg-transparent font-semibold focus:outline-none"
                                    />
                                ) : (
                                    noDokumen
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-[85px_1fr] border-b border-black dark:border-border">
                            <div className="border-r border-black p-1 font-bold dark:border-border">
                                Revisi
                            </div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input
                                        type="text"
                                        value={revisi}
                                        onChange={(e) => {
                                            setRevisi(e.target.value);
                                            setDirty(true);
                                        }}
                                        className="w-full bg-transparent font-semibold focus:outline-none"
                                    />
                                ) : (
                                    revisi
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-[85px_1fr]">
                            <div className="border-r border-black p-1 font-bold dark:border-border">
                                Tanggal
                            </div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input
                                        type="text"
                                        value={tanggalDokumen}
                                        onChange={(e) => {
                                            setTanggalDokumen(e.target.value);
                                            setDirty(true);
                                        }}
                                        className="w-full bg-transparent font-semibold focus:outline-none"
                                    />
                                ) : (
                                    tanggalDokumen
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* VISITOR LOG TABLE */}
                <div className="mt-3 overflow-x-auto">
                    <table className="buku-tamu-table w-full border-collapse border-2 border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th rowSpan={2} className="w-10 border border-black px-1.5 py-2 text-center dark:border-border">
                                    NO
                                </th>
                                <th rowSpan={2} className="w-24 border border-black px-2 py-2 text-center dark:border-border">
                                    TANGGAL
                                </th>
                                <th rowSpan={2} className="w-24 border border-black px-2 py-2 text-center dark:border-border">
                                    JUMLAH<br />KEHADIRAN<br />TAMU
                                </th>
                                <th colSpan={4} className="border border-black px-2 py-1.5 text-center dark:border-border">
                                    JENIS TAMU
                                </th>
                                <th rowSpan={2} className="border border-black px-3 py-2 text-center dark:border-border">
                                    KETERANGAN
                                </th>
                                {can_write && (
                                    <th rowSpan={2} className="no-print w-10 border border-black px-1 py-1.5 text-center dark:border-border">
                                        #
                                    </th>
                                )}
                            </tr>
                            <tr className="bg-muted/30 font-bold uppercase text-foreground">
                                <th className="w-20 border border-black px-1.5 py-1 text-center dark:border-border">
                                    TAMU PLN
                                </th>
                                <th className="w-20 border border-black px-1.5 py-1 text-center dark:border-border">
                                    INSTANSI
                                </th>
                                <th className="w-20 border border-black px-1.5 py-1 text-center dark:border-border">
                                    KONTRAKTOR
                                </th>
                                <th className="w-20 border border-black px-1.5 py-1 text-center dark:border-border">
                                    LAINNYA
                                </th>
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
                                                placeholder={`${it.no_urut}/${filters.month}/${filters.year}`}
                                            />
                                        ) : (
                                            it.tanggal
                                        )}
                                    </td>

                                    {/* JUMLAH KEHADIRAN TAMU */}
                                    <td className="border border-black px-2 py-2 text-center font-bold align-middle dark:border-border bg-muted/10">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min={0}
                                                value={it.jumlah_kehadiran_tamu}
                                                onChange={(e) => updateItem(it._key, 'jumlah_kehadiran_tamu', Number(e.target.value))}
                                                className="w-full text-center font-bold bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.jumlah_kehadiran_tamu || '—'
                                        )}
                                    </td>

                                    {/* TAMU PLN */}
                                    <td className="border border-black px-1.5 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min={0}
                                                value={it.tamu_pln === 0 ? '' : it.tamu_pln}
                                                placeholder="-"
                                                onChange={(e) => updateItem(it._key, 'tamu_pln', e.target.value === '' ? 0 : Number(e.target.value))}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.tamu_pln > 0 ? it.tamu_pln : '-'
                                        )}
                                    </td>

                                    {/* INSTANSI */}
                                    <td className="border border-black px-1.5 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min={0}
                                                value={it.instansi === 0 ? '' : it.instansi}
                                                placeholder="-"
                                                onChange={(e) => updateItem(it._key, 'instansi', e.target.value === '' ? 0 : Number(e.target.value))}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.instansi > 0 ? it.instansi : '-'
                                        )}
                                    </td>

                                    {/* KONTRAKTOR */}
                                    <td className="border border-black px-1.5 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min={0}
                                                value={it.kontraktor === 0 ? '' : it.kontraktor}
                                                placeholder="-"
                                                onChange={(e) => updateItem(it._key, 'kontraktor', e.target.value === '' ? 0 : Number(e.target.value))}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.kontraktor > 0 ? it.kontraktor : '-'
                                        )}
                                    </td>

                                    {/* LAINNYA */}
                                    <td className="border border-black px-1.5 py-2 text-center align-middle dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min={0}
                                                value={it.lainnya === 0 ? '' : it.lainnya}
                                                placeholder="-"
                                                onChange={(e) => updateItem(it._key, 'lainnya', e.target.value === '' ? 0 : Number(e.target.value))}
                                                className="w-full text-center bg-transparent focus:bg-background focus:outline-none"
                                            />
                                        ) : (
                                            it.lainnya > 0 ? it.lainnya : '-'
                                        )}
                                    </td>

                                    {/* KETERANGAN */}
                                    <td className="border border-black px-2.5 py-2 text-left align-middle dark:border-border">
                                        {can_write ? (
                                            <textarea
                                                rows={it.keterangan && it.keterangan.includes('\n') ? 2 : 1}
                                                value={it.keterangan}
                                                onChange={(e) => updateItem(it._key, 'keterangan', e.target.value)}
                                                className="w-full bg-transparent focus:bg-background focus:outline-none text-[10px] leading-snug sm:text-[11px]"
                                                placeholder="Instansi / Perusahaan Tamu..."
                                            />
                                        ) : (
                                            <div className="whitespace-pre-wrap leading-snug">
                                                {it.keterangan || '—'}
                                            </div>
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
                        <tfoot>
                            <tr className="bg-muted/40 font-black text-foreground">
                                <td colSpan={2} className="border border-black px-2 py-2 text-center uppercase tracking-wider dark:border-border">
                                    TOTAL KEHADIRAN TAMU
                                </td>
                                <td className="border border-black px-2 py-2 text-center font-black dark:border-border bg-muted/20">
                                    {totals.jumlah}
                                </td>
                                <td className="border border-black px-1.5 py-2 text-center dark:border-border">
                                    {totals.pln > 0 ? totals.pln : '-'}
                                </td>
                                <td className="border border-black px-1.5 py-2 text-center dark:border-border">
                                    {totals.instansi > 0 ? totals.instansi : '-'}
                                </td>
                                <td className="border border-black px-1.5 py-2 text-center dark:border-border">
                                    {totals.kontraktor > 0 ? totals.kontraktor : '-'}
                                </td>
                                <td className="border border-black px-1.5 py-2 text-center dark:border-border">
                                    {totals.lainnya > 0 ? totals.lainnya : '-'}
                                </td>
                                <td className="border border-black px-2 py-2 text-center text-muted-foreground dark:border-border">
                                    -
                                </td>
                                {can_write && (
                                    <td className="no-print border border-black px-1 py-1 text-center dark:border-border">
                                        -
                                    </td>
                                )}
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* Additional Notes Box */}
                <div className="mt-4 border border-black p-3 text-[10px] dark:border-border sm:text-[11px]">
                    <div className="font-bold uppercase text-foreground mb-1">
                        Catatan Khusus Mutasi Buku Tamu:
                    </div>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan rombongan dinas, tamu VIP, surat izin khusus, atau kendala keamanan selama kunjungan tamu..."
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
