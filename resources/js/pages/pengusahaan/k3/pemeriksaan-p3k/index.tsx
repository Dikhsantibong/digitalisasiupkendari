import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseMedical,
    CheckCheck,
    CheckCircle2,
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
import k3PengusahaanPemeriksaanP3k from '@/routes/k3/pengusahaan/pemeriksaan-p3k';
import type { IdName } from '@/types';

export type P3kItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    nama_isi: string;
    standar_jumlah: number;
    satuan: string;
    kondisi_lokasi: Record<string, number | string>;
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
        locations: string[];
        catatan: string;
        items: Array<Omit<P3kItem, '_key'>>;
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
    .p3k-table { width: 100% !important; border-collapse: collapse !important; }
    .p3k-table th, .p3k-table td { border: 1px solid #000 !important; padding: 3px 5px !important; }
    .p3k-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; padding: 0 !important; font-size: inherit !important; }
}
`;

export default function PengusahaanPemeriksaanP3kPage(props: Props) {
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
    const [locations, setLocations] = useState<string[]>(initialRecord.locations);
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [newLocationName, setNewLocationName] = useState('');
    const [showAddLocation, setShowAddLocation] = useState(false);

    const [items, setItems] = useState<P3kItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            keterangan: it.keterangan ?? 'Lengkap',
            kondisi_lokasi: { ...(it.kondisi_lokasi || {}) },
        })),
    );
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanPemeriksaanP3k.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const updateItemValue = (key: number, location: string, value: string) => {
        setItems((prev) =>
            prev.map((item) => {
                if (item._key !== key) {
                    return item;
                }

                return {
                    ...item,
                    kondisi_lokasi: {
                        ...item.kondisi_lokasi,
                        [location]: value === '' ? '' : Number(value),
                    },
                };
            }),
        );
        setDirty(true);
    };

    const updateItemField = (key: number, field: 'nama_isi' | 'standar_jumlah' | 'satuan' | 'keterangan', value: string | number) => {
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

    const addItem = () => {
        const newItem: P3kItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            nama_isi: '',
            standar_jumlah: 1,
            satuan: 'buah',
            kondisi_lokasi: locations.reduce((acc, loc) => ({ ...acc, [loc]: 1 }), {}),
            keterangan: 'Lengkap',
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

    const addLocation = () => {
        const trimmed = newLocationName.trim();

        if (!trimmed || locations.includes(trimmed)) {
            return;
        }

        setLocations((prev) => [...prev, trimmed]);
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                kondisi_lokasi: {
                    ...it.kondisi_lokasi,
                    [trimmed]: it.standar_jumlah,
                },
            })),
        );
        setNewLocationName('');
        setShowAddLocation(false);
        setDirty(true);
    };

    const removeLocation = (locToRemove: string) => {
        if (locations.length <= 1) {
            return;
        }

        setLocations((prev) => prev.filter((loc) => loc !== locToRemove));
        setItems((prev) =>
            prev.map((it) => {
                const nextKondisi = { ...it.kondisi_lokasi };
                delete nextKondisi[locToRemove];

                return { ...it, kondisi_lokasi: nextKondisi };
            }),
        );
        setDirty(true);
    };

    const fillAllStandard = () => {
        setItems((prev) =>
            prev.map((it) => {
                const standardVal = it.standar_jumlah;
                const nextKondisi: Record<string, number> = {};

                for (const loc of locations) {
                    nextKondisi[loc] = standardVal;
                }

                return {
                    ...it,
                    kondisi_lokasi: nextKondisi,
                    keterangan: 'Lengkap',
                };
            }),
        );
        setDirty(true);
    };

    const setAllLengkap = () => {
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                keterangan: 'Lengkap',
            })),
        );
        setDirty(true);
    };

    const resetChanges = () => {
        setNoDokumen(initialRecord.no_dokumen);
        setRevisi(initialRecord.revisi);
        setTanggalDokumen(initialRecord.tanggal_dokumen);
        setLocations(initialRecord.locations);
        setCatatan(initialRecord.catatan);
        setItems(
            initialRecord.items.map((it, idx) => ({
                ...it,
                _key: idx + 1,
                keterangan: it.keterangan ?? 'Lengkap',
                kondisi_lokasi: { ...(it.kondisi_lokasi || {}) },
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanPemeriksaanP3k.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                locations,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    nama_isi: it.nama_isi,
                    standar_jumlah: Number(it.standar_jumlah) || 1,
                    satuan: it.satuan,
                    kondisi_lokasi: it.kondisi_lokasi,
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

    // Calculate completion / shortage stats
    const stats = useMemo(() => {
        let totalCells = 0;
        let completeCells = 0;
        let shortCells = 0;

        for (const item of items) {
            const standard = Number(item.standar_jumlah) || 0;

            for (const loc of locations) {
                totalCells++;
                const actual = item.kondisi_lokasi[loc];
                const qty = actual !== undefined && actual !== '' && !isNaN(Number(actual)) ? Number(actual) : 0;

                if (qty >= standard) {
                    completeCells++;
                } else {
                    shortCells++;
                }
            }
        }

        const percentage = totalCells > 0 ? Math.round((completeCells / totalCells) * 100) : 100;

        return {
            totalBoxes: locations.length,
            totalRows: items.length,
            completeCells,
            shortCells,
            percentage,
        };
    }, [items, locations]);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Pemeriksaan P3K — ${unit.name}`} />

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
                                Pemeriksaan Peralatan P3K
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
                            Pemeriksaan dan monitoring kelengkapan isi kotak P3K di seluruh lokasi unit (SMT-FM-AK3-10.01).
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
                            <BriefcaseMedical className="size-3.5 text-sky-600" />
                            <span>{stats.totalBoxes} Titik Kotak P3K</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Kelengkapan: {stats.percentage}%</span>
                        </div>
                        {stats.shortCells > 0 && (
                            <div className="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                <span>{stats.shortCells} Item Kurang / Habis</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick actions for Safety Officer */}
                {can_write && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={fillAllStandard}
                            className="gap-1.5 text-xs"
                            title="Isi seluruh kotak P3K dengan jumlah standar dalam satu klik"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel Sesuai Standar</span>
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={setAllLengkap}
                            className="gap-1.5 text-xs"
                        >
                            <CheckCheck className="size-3.5 text-emerald-600" />
                            <span>Setel Keterangan Lengkap</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addItem}
                            className="gap-1.5 text-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Tambah Item P3K</span>
                        </Button>

                        {!showAddLocation ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setShowAddLocation(true)}
                                className="gap-1.5 text-xs"
                            >
                                <Plus className="size-3.5" />
                                <span>Tambah Titik Kotak</span>
                            </Button>
                        ) : (
                            <div className="flex items-center gap-1.5">
                                <input
                                    type="text"
                                    value={newLocationName}
                                    onChange={(e) => setNewLocationName(e.target.value)}
                                    placeholder="Nama lokasi baru..."
                                    className="h-8 rounded border px-2 text-xs"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            addLocation();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={addLocation}
                                >
                                    Tambah
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => setShowAddLocation(false)}
                                >
                                    Batal
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </Card>

            {/* Printable container (A4 Landscape) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="p3k-header-box relative rounded border-2 border-black p-4 dark:border-border">
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

                    {/* Judul Dokumen & Kotak Metadata Formulir */}
                    <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <div className="flex-1 text-center sm:pl-24">
                            <h2 className="text-sm font-black tracking-wider text-foreground uppercase sm:text-base">
                                PEMERIKSAAN PERALATAN P3K PERIODE BULAN {monthName.toUpperCase()}
                            </h2>
                            <div className="text-xs font-black tracking-wider text-foreground uppercase">
                                TAHUN {filters.year}
                            </div>
                        </div>

                        {/* Kotak Metadata SMT-FM-AK3-10.01 */}
                        <div className="w-56 border border-black text-[9px] dark:border-border sm:text-[10px]">
                            <div className="flex border-b border-black dark:border-border">
                                <div className="w-20 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                    Nomor
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
                                <div className="w-20 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
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
                                <div className="w-20 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
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
                    <table className="p3k-table w-full border-collapse border border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-10 border border-black px-1.5 py-1.5 text-center dark:border-border">
                                    NO
                                </th>
                                <th className="min-w-[150px] border border-black px-2 py-1.5 text-left dark:border-border">
                                    ISI KOTAK P3K
                                </th>
                                <th className="w-16 border border-black px-1 py-1.5 text-center dark:border-border">
                                    STANDAR JUMLAH
                                </th>
                                <th className="w-14 border border-black px-1 py-1.5 text-center dark:border-border">
                                    SATUAN
                                </th>

                                {/* Dynamic Location Columns */}
                                {locations.map((loc) => (
                                    <th
                                        key={loc}
                                        className="min-w-[65px] border border-black px-1 py-1.5 text-center dark:border-border"
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            <span>{loc}</span>
                                            {can_write && locations.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeLocation(loc)}
                                                    className="no-print text-muted-foreground hover:text-rose-500"
                                                    title={`Hapus kolom ${loc}`}
                                                >
                                                    ×
                                                </button>
                                            )}
                                        </div>
                                    </th>
                                ))}

                                <th className="min-w-[100px] border border-black px-2 py-1.5 text-center dark:border-border">
                                    Keterangan
                                </th>
                                {can_write && (
                                    <th className="no-print w-10 border border-black px-1 py-1.5 text-center dark:border-border">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item._key} className="hover:bg-muted/10">
                                    <td className="border border-black px-1 text-center font-medium dark:border-border">
                                        {item.no_urut}
                                    </td>

                                    {/* Isi Kotak P3K */}
                                    <td className="border border-black px-2 py-1 dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.nama_isi}
                                                onChange={(e) =>
                                                    updateItemField(item._key, 'nama_isi', e.target.value)
                                                }
                                                className="w-full bg-transparent focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.nama_isi}</span>
                                        )}
                                    </td>

                                    {/* Standar Jumlah */}
                                    <td className="border border-black px-1 py-1 text-center font-semibold dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="number"
                                                min="0"
                                                value={item.standar_jumlah}
                                                onChange={(e) =>
                                                    updateItemField(item._key, 'standar_jumlah', Number(e.target.value))
                                                }
                                                className="w-full bg-transparent text-center font-semibold focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.standar_jumlah}</span>
                                        )}
                                    </td>

                                    {/* Satuan */}
                                    <td className="border border-black px-1 py-1 text-center text-muted-foreground dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.satuan}
                                                onChange={(e) =>
                                                    updateItemField(item._key, 'satuan', e.target.value)
                                                }
                                                className="w-full bg-transparent text-center focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.satuan}</span>
                                        )}
                                    </td>

                                    {/* Cells for each Kotak Location */}
                                    {locations.map((loc) => {
                                        const val = item.kondisi_lokasi[loc];
                                        const numericVal = val !== undefined && val !== '' && !isNaN(Number(val)) ? Number(val) : 0;
                                        const isShortage = numericVal < Number(item.standar_jumlah);

                                        return (
                                            <td
                                                key={loc}
                                                className={`border border-black px-1 py-1 text-center dark:border-border ${
                                                    isShortage ? 'bg-amber-100/50 text-amber-950 font-bold dark:bg-amber-950/40 dark:text-amber-300' : ''
                                                }`}
                                            >
                                                {can_write ? (
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={val !== undefined ? val : ''}
                                                        onChange={(e) =>
                                                            updateItemValue(item._key, loc, e.target.value)
                                                        }
                                                        placeholder="0"
                                                        className="w-full bg-transparent text-center focus:outline-none"
                                                    />
                                                ) : (
                                                    <span>{val !== undefined ? val : '-'}</span>
                                                )}
                                            </td>
                                        );
                                    })}

                                    {/* Keterangan */}
                                    <td className="border border-black px-2 py-1 text-center dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.keterangan}
                                                onChange={(e) =>
                                                    updateItemField(item._key, 'keterangan', e.target.value)
                                                }
                                                placeholder="Keterangan..."
                                                className="w-full bg-transparent text-center focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.keterangan || '-'}</span>
                                        )}
                                    </td>

                                    {/* Delete Action */}
                                    {can_write && (
                                        <td className="no-print border border-black px-1 py-1 text-center dark:border-border">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-6 text-rose-500 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950"
                                                onClick={() => removeItem(item._key)}
                                                title="Hapus Item"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Catatan / Tindak Lanjut */}
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
                            placeholder="Tuliskan catatan pemeriksaan kotak P3K atau kebutuhan obat pengisian ulang di sini..."
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
