import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Search,
    Signpost,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanInspeksiRambu from '@/routes/k3/pengusahaan/inspeksi-rambu';
import type { IdName } from '@/types';

export type InspeksiRambuItem = {
    _key: number;
    id?: number | null;
    no_id: number;
    rambu_k3: string;
    lokasi: string;
    tingkat_pelanggaran: string;
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
        items: Array<Omit<InspeksiRambuItem, '_key'>>;
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
    .rambu-table { width: 100% !important; border-collapse: collapse !important; }
    .rambu-table th, .rambu-table td { border: 1px solid #000 !important; padding: 4px 6px !important; }
    .rambu-header-box { border: 2px solid #000 !important; }
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

const KETERANGAN_OPTIONS = ['Perhatian', 'Bahaya', 'Waspada', 'Awas', 'Penting'];
const TINGKAT_PELANGGARAN_OPTIONS = ['Nihil', 'Ringan', 'Sedang', 'Berat'];

export default function PengusahaanInspeksiRambuPage(props: Props) {
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
    const [tanggalInspeksi, setTanggalInspeksi] = useState(initialRecord.tanggal_inspeksi || '01/09/2026');
    const [catatan, setCatatan] = useState(initialRecord.catatan);

    const [items, setItems] = useState<InspeksiRambuItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            no_id: it.no_id ?? (idx + 1),
            tingkat_pelanggaran: it.tingkat_pelanggaran || 'Nihil',
            keterangan: it.keterangan || 'Perhatian',
        })),
    );

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [kategoriFilter, setKategoriFilter] = useState('all');
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: string) => {
        router.get(
            k3PengusahaanInspeksiRambu.index().url,
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

    const updateItem = (key: number, field: keyof InspeksiRambuItem, value: unknown) => {
        setItems((prev) =>
            prev.map((it) => (it._key === key ? { ...it, [field]: value } : it)),
        );
        setDirty(true);
    };

    const addItem = () => {
        const newNoId = items.length > 0 ? Math.max(...items.map((i) => i.no_id)) + 1 : 1;
        const newItem: InspeksiRambuItem = {
            _key: nextKey,
            id: null,
            no_id: newNoId,
            rambu_k3: '',
            lokasi: '',
            tingkat_pelanggaran: 'Nihil',
            keterangan: 'Perhatian',
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

    // Quick action: set all tingkat_pelanggaran to 'Nihil'
    const setAllNihil = () => {
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                tingkat_pelanggaran: 'Nihil',
            })),
        );
        setDirty(true);
    };

    const resetChanges = () => {
        setNoDokumen(initialRecord.no_dokumen);
        setRevisi(initialRecord.revisi);
        setTanggalDokumen(initialRecord.tanggal_dokumen);
        setTanggalInspeksi(initialRecord.tanggal_inspeksi || '01/09/2026');
        setCatatan(initialRecord.catatan);
        setItems(
            initialRecord.items.map((it, idx) => ({
                ...it,
                _key: idx + 1,
                no_id: it.no_id ?? (idx + 1),
                tingkat_pelanggaran: it.tingkat_pelanggaran || 'Nihil',
                keterangan: it.keterangan || 'Perhatian',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanInspeksiRambu.store().url,
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
                    id: it.id,
                    no_id: it.no_id,
                    rambu_k3: it.rambu_k3,
                    lokasi: it.lokasi,
                    tingkat_pelanggaran: it.tingkat_pelanggaran,
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

    // Calculate inspection statistics
    const stats = useMemo(() => {
        const totalItems = items.length;

        if (totalItems === 0) {
            return {
                totalSigns: 0,
                nihilCount: 0,
                nihilPercent: 100,
                distinctLocations: 0,
                hazardCount: 0,
            };
        }

        let nihilCount = 0;
        let hazardCount = 0;
        const locations = new Set<string>();

        for (const item of items) {
            if (item.tingkat_pelanggaran.trim().toLowerCase() === 'nihil') {
                nihilCount++;
            }

            const ketLower = item.keterangan.trim().toLowerCase();

            if (ketLower === 'bahaya' || ketLower === 'waspada' || ketLower === 'awas') {
                hazardCount++;
            }

            if (item.lokasi.trim() !== '') {
                locations.add(item.lokasi.trim());
            }
        }

        const nihilPercent = Math.round((nihilCount / totalItems) * 100);

        return {
            totalSigns: totalItems,
            nihilCount,
            nihilPercent,
            distinctLocations: locations.size,
            hazardCount,
        };
    }, [items]);

    // Screen view filter (print view renders all items)
    const filteredItems = useMemo(() => {
        return items.filter((it) => {
            if (kategoriFilter !== 'all' && it.keterangan.trim().toLowerCase() !== kategoriFilter.toLowerCase()) {
                return false;
            }

            if (searchQuery.trim() !== '') {
                const q = searchQuery.toLowerCase();
                const matchName = it.rambu_k3.toLowerCase().includes(q);
                const matchLoc = it.lokasi.toLowerCase().includes(q);
                const matchNo = String(it.no_id).includes(q);

                if (!matchName && !matchLoc && !matchNo) {
                    return false;
                }
            }

            return true;
        });
    }, [items, kategoriFilter, searchQuery]);

    const getKeteranganBadgeClass = (ket: string) => {
        switch (ket.toLowerCase()) {
            case 'bahaya':
                return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 border-red-300';
            case 'waspada':
                return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-300';
            case 'awas':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300 border-orange-300';
            case 'penting':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border-blue-300';
            default:
                return 'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border-sky-300';
        }
    };

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Inspeksi Rambu Rambu K3 — ${unit.name}`} />

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
                                Inspeksi Rambu Rambu K3
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
                            Pemeriksaan rutin kepatuhan, kondisi fisik, dan tingkat pelanggaran rambu-rambu K3 (SMT-FM-AK3-07.01).
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
                            <Signpost className="size-3.5 text-sky-600" />
                            <span>{stats.totalSigns} Titik Rambu ({stats.distinctLocations} Area)</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Kepatuhan: {stats.nihilPercent}% Nihil Pelanggaran</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            <AlertTriangle className="size-3.5 text-amber-600" />
                            <span>{stats.hazardCount} Rambu Bahaya / Waspada</span>
                        </div>
                    </div>
                </div>

                {/* Quick actions for Safety Officer */}
                {can_write && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={setAllNihil}
                            className="gap-1.5 text-xs"
                            title="Setel seluruh tingkat pelanggaran menjadi 'Nihil' dalam satu klik"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel Semua Nihil</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addItem}
                            className="gap-1.5 text-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Tambah Rambu Baru</span>
                        </Button>

                        <div className="flex w-full flex-wrap items-center gap-1.5 sm:ml-auto sm:w-auto sm:flex-nowrap">
                            {/* Search filter */}
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Cari rambu / lokasi..."
                                    className="h-8 w-44 rounded-md border pl-8 pr-2 text-xs focus:outline-none sm:w-56"
                                />
                            </div>

                            {/* Category filter */}
                            <Select value={kategoriFilter} onValueChange={setKategoriFilter}>
                                <SelectTrigger className="h-8 w-40 text-xs">
                                    <SelectValue placeholder="Semua Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all" className="text-xs">Semua Kategori</SelectItem>
                                    <SelectItem value="Perhatian" className="text-xs">Perhatian</SelectItem>
                                    <SelectItem value="Bahaya" className="text-xs">Bahaya</SelectItem>
                                    <SelectItem value="Waspada" className="text-xs">Waspada</SelectItem>
                                    <SelectItem value="Awas" className="text-xs">Awas</SelectItem>
                                    <SelectItem value="Penting" className="text-xs">Penting</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}
            </Card>

            {/* Printable Container (A4 Portrait matches original scan) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* Header KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="rambu-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                    {/* Judul Dokumen & Kotak Metadata SMT-FM-AK3-07.01 */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex-1 text-left sm:pr-4">
                            <h2 className="text-sm font-black tracking-wider text-foreground uppercase sm:text-base">
                                INSPEKSI RAMBU RAMBU K3
                            </h2>
                            <div className="text-xs font-bold text-foreground uppercase">
                                PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                            </div>
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
                    <table className="rambu-table w-full border-collapse border border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-12 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    No Id
                                </th>
                                <th className="min-w-[180px] border border-black px-2 py-1.5 text-left dark:border-border">
                                    Rambu Rambu K3
                                </th>
                                <th className="min-w-[150px] border border-black px-2 py-1.5 text-left dark:border-border">
                                    Lokasi
                                </th>
                                <th className="w-36 border border-black px-2 py-1.5 text-center dark:border-border">
                                    Tingkat Pelanggaran
                                </th>
                                <th className="w-32 border border-black px-2 py-1.5 text-center dark:border-border">
                                    Keterangan
                                </th>
                                {can_write && (
                                    <th className="no-print w-10 border border-black px-1 py-1.5 text-center dark:border-border">
                                        #
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((it) => {
                                const isVisibleOnScreen = filteredItems.some((f) => f._key === it._key);
                                const tingkatChoices = TINGKAT_PELANGGARAN_OPTIONS.includes(it.tingkat_pelanggaran)
                                    ? TINGKAT_PELANGGARAN_OPTIONS
                                    : [it.tingkat_pelanggaran, ...TINGKAT_PELANGGARAN_OPTIONS].filter(Boolean);
                                const keteranganChoices = KETERANGAN_OPTIONS.includes(it.keterangan)
                                    ? KETERANGAN_OPTIONS
                                    : [it.keterangan, ...KETERANGAN_OPTIONS].filter(Boolean);

                                return (
                                    <tr
                                        key={it._key}
                                        className={`hover:bg-muted/15 transition-colors ${
                                            !isVisibleOnScreen ? 'no-screen hidden print:table-row' : ''
                                        }`}
                                    >
                                        {/* No Id */}
                                        <td className="border border-black px-1.5 py-1 text-center font-bold align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="number"
                                                    value={it.no_id}
                                                    onChange={(e) => updateItem(it._key, 'no_id', Number(e.target.value))}
                                                    className="w-full text-center font-bold bg-transparent focus:bg-background focus:outline-none"
                                                />
                                            ) : (
                                                it.no_id
                                            )}
                                        </td>

                                        {/* Rambu Rambu K3 */}
                                        <td className="border border-black px-2 py-1 text-left align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.rambu_k3}
                                                    onChange={(e) => updateItem(it._key, 'rambu_k3', e.target.value)}
                                                    className="w-full bg-transparent focus:bg-background focus:outline-none font-medium"
                                                    placeholder="Nama rambu K3..."
                                                />
                                            ) : (
                                                <span className="font-medium">{it.rambu_k3}</span>
                                            )}
                                        </td>

                                        {/* Lokasi */}
                                        <td className="border border-black px-2 py-1 text-left align-middle dark:border-border">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={it.lokasi}
                                                    onChange={(e) => updateItem(it._key, 'lokasi', e.target.value)}
                                                    className="w-full bg-transparent focus:bg-background focus:outline-none"
                                                    placeholder="Lokasi penempatan..."
                                                />
                                            ) : (
                                                it.lokasi
                                            )}
                                        </td>

                                        {/* Tingkat Pelanggaran */}
                                        <td className="border border-black px-1.5 py-1 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <>
                                                    <div className="no-print flex justify-center">
                                                        <Select
                                                            value={it.tingkat_pelanggaran || 'Nihil'}
                                                            onValueChange={(val) => updateItem(it._key, 'tingkat_pelanggaran', val)}
                                                        >
                                                            <SelectTrigger size="sm" className="h-7 w-full min-w-[90px] text-xs font-medium justify-between bg-transparent">
                                                                <SelectValue placeholder="Pilih...">
                                                                    <span className={it.tingkat_pelanggaran.toLowerCase() === 'nihil' ? 'text-emerald-700 font-semibold dark:text-emerald-400' : 'text-red-700 font-bold dark:text-red-400'}>
                                                                        {it.tingkat_pelanggaran}
                                                                    </span>
                                                                </SelectValue>
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {tingkatChoices.map((opt) => (
                                                                    <SelectItem key={opt} value={opt} className="text-xs">
                                                                        <span className={opt.toLowerCase() === 'nihil' ? 'text-emerald-700 font-semibold dark:text-emerald-400' : 'text-red-700 font-bold dark:text-red-400'}>
                                                                            {opt}
                                                                        </span>
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <span className="hidden print:inline-block">
                                                        <span className={it.tingkat_pelanggaran.toLowerCase() === 'nihil' ? 'text-emerald-700 font-semibold' : 'text-red-700 font-bold'}>
                                                            {it.tingkat_pelanggaran}
                                                        </span>
                                                    </span>
                                                </>
                                            ) : (
                                                <span className={it.tingkat_pelanggaran.toLowerCase() === 'nihil' ? 'text-emerald-700 font-semibold dark:text-emerald-400' : 'text-red-700 font-bold dark:text-red-400'}>
                                                    {it.tingkat_pelanggaran}
                                                </span>
                                            )}
                                        </td>

                                        {/* Keterangan */}
                                        <td className="border border-black px-1.5 py-1 text-center align-middle dark:border-border">
                                            {can_write ? (
                                                <>
                                                    <div className="no-print flex justify-center">
                                                        <Select
                                                            value={it.keterangan || 'Perhatian'}
                                                            onValueChange={(val) => updateItem(it._key, 'keterangan', val)}
                                                        >
                                                            <SelectTrigger size="sm" className="h-7 w-full min-w-[105px] text-xs font-medium justify-between bg-transparent">
                                                                <SelectValue placeholder="Pilih...">
                                                                    <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-bold border ${getKeteranganBadgeClass(it.keterangan)}`}>
                                                                        {it.keterangan}
                                                                    </span>
                                                                </SelectValue>
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {keteranganChoices.map((opt) => (
                                                                    <SelectItem key={opt} value={opt} className="text-xs">
                                                                        <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-bold border ${getKeteranganBadgeClass(opt)}`}>
                                                                            {opt}
                                                                        </span>
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <span className="hidden print:inline-block">
                                                        <span className={`inline-block rounded px-2 py-0.5 text-[9px] font-bold border ${getKeteranganBadgeClass(it.keterangan)}`}>
                                                            {it.keterangan}
                                                        </span>
                                                    </span>
                                                </>
                                            ) : (
                                                <span className={`inline-block rounded px-2 py-0.5 text-[9px] font-bold border ${getKeteranganBadgeClass(it.keterangan)}`}>
                                                    {it.keterangan}
                                                </span>
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
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Additional Notes Box */}
                <div className="mt-4 border border-black p-3 text-[10px] dark:border-border sm:text-[11px]">
                    <div className="font-bold uppercase text-foreground mb-1">
                        Catatan Khusus / Temuan Inspeksi Rambu K3:
                    </div>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan kondisi fisik rambu yang rusak, tertutup debu/oli, pudar, atau usulan penambahan rambu baru..."
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
