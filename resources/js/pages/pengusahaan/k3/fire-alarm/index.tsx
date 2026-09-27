import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BellRing,
    Calendar,
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
import k3PengusahaanFireAlarm from '@/routes/k3/pengusahaan/fire-alarm';
import type { IdName } from '@/types';

export type FireAlarmItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    lokasi: string;
    tanggal_periksa: string;
    kondisi: string;
    panel_indikator: string;
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
        tanggal_periksa: string;
        catatan: string;
        items: Array<Omit<FireAlarmItem, '_key'>>;
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
    .fa-table { width: 100% !important; border-collapse: collapse !important; }
    .fa-table th, .fa-table td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    .fa-header-box { border: 2px solid #000 !important; }
    input, textarea, select { border: none !important; background: transparent !important; padding: 0 !important; font-size: inherit !important; appearance: none !important; }
}
`;

export default function PengusahaanFireAlarmPage(props: Props) {
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
    const [batchDate, setBatchDate] = useState(initialRecord.tanggal_periksa || '12/08/2026');
    const [catatan, setCatatan] = useState(initialRecord.catatan);

    const [items, setItems] = useState<FireAlarmItem[]>(() =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            tanggal_periksa: it.tanggal_periksa || initialRecord.tanggal_periksa || '12/08/2026',
            kondisi: it.kondisi || 'Baik',
            panel_indikator: it.panel_indikator || 'Baik',
            keterangan: it.keterangan || '',
        })),
    );

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanFireAlarm.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const updateItem = (key: number, field: keyof FireAlarmItem, value: string | number) => {
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
        const newItem: FireAlarmItem = {
            _key: nextKey,
            no_urut: items.length + 1,
            lokasi: '',
            tanggal_periksa: batchDate || '',
            kondisi: 'Baik',
            panel_indikator: 'Baik',
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

    const applyBatchDate = () => {
        if (!batchDate) {
            return;
        }

        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                tanggal_periksa: batchDate,
            })),
        );
        setDirty(true);
    };

    const setAllBaik = () => {
        setItems((prev) =>
            prev.map((it) => ({
                ...it,
                kondisi: 'Baik',
                panel_indikator: 'Baik',
            })),
        );
        setDirty(true);
    };

    const resetChanges = () => {
        setNoDokumen(initialRecord.no_dokumen);
        setRevisi(initialRecord.revisi);
        setTanggalDokumen(initialRecord.tanggal_dokumen);
        setBatchDate(initialRecord.tanggal_periksa || '12/08/2026');
        setCatatan(initialRecord.catatan);
        setItems(
            initialRecord.items.map((it, idx) => ({
                ...it,
                _key: idx + 1,
                tanggal_periksa: it.tanggal_periksa || initialRecord.tanggal_periksa || '12/08/2026',
                kondisi: it.kondisi || 'Baik',
                panel_indikator: it.panel_indikator || 'Baik',
                keterangan: it.keterangan || '',
            })),
        );
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanFireAlarm.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                tanggal_periksa: batchDate,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    lokasi: it.lokasi,
                    tanggal_periksa: it.tanggal_periksa,
                    kondisi: it.kondisi,
                    panel_indikator: it.panel_indikator,
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

    // Calculate condition stats
    const stats = useMemo(() => {
        let countBaik = 0;
        let countRusak = 0;

        for (const item of items) {
            if (item.kondisi.toLowerCase().includes('rusak') || item.panel_indikator.toLowerCase().includes('rusak') || item.panel_indikator.toLowerCase().includes('trouble')) {
                countRusak++;
            } else {
                countBaik++;
            }
        }

        return {
            total: items.length,
            countBaik,
            countRusak,
        };
    }, [items]);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Pemeriksaan Fire Alarm — ${unit.name}`} />

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
                                Pemeriksaan Fire Alarm
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
                            Pemeriksaan berkala kondisi fisik panel dan detektor fire alarm unit pembangkit (SMT-FM-AK3-12.06).
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
                            <BellRing className="size-3.5 text-sky-600" />
                            <span>{stats.total} Titik Fire Alarm</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Kondisi Baik: {stats.countBaik}</span>
                        </div>
                        {stats.countRusak > 0 && (
                            <div className="flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                <span>Perlu Perbaikan: {stats.countRusak}</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick actions bar */}
                {can_write && (
                    <div className="mt-4 flex flex-wrap items-center gap-3 border-t pt-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <label className="text-xs font-semibold text-muted-foreground whitespace-nowrap">
                                Tanggal Periksa:
                            </label>
                            <input
                                type="text"
                                value={batchDate}
                                onChange={(e) => setBatchDate(e.target.value)}
                                placeholder="DD/MM/YYYY"
                                className="h-8 w-32 rounded border px-2 text-xs"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={applyBatchDate}
                                className="gap-1 h-8 text-xs"
                                title="Terapkan tanggal ini ke seluruh baris lokasi"
                            >
                                <Calendar className="size-3.5" />
                                <span>Terapkan Tgl</span>
                            </Button>
                        </div>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={setAllBaik}
                            className="gap-1.5 h-8 text-xs"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel Semua Baik / Normal</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addItem}
                            className="gap-1.5 h-8 text-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Tambah Baris</span>
                        </Button>
                    </div>
                )}
            </Card>

            {/* Printable container (A4 Landscape) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                {/* KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="fa-header-box relative rounded border-2 border-black p-4 dark:border-border">
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
                        <div className="flex-1 text-center sm:pl-28">
                            <h2 className="text-sm font-black tracking-wider text-foreground uppercase sm:text-base">
                                PEMERIKSAAN FIRE ALARM PERIODE BULAN {monthName.toUpperCase()}
                            </h2>
                            <div className="text-xs font-black tracking-wider text-foreground uppercase">
                                TAHUN {filters.year}
                            </div>
                        </div>

                        {/* Kotak Metadata SMT-FM-AK3-12.06 */}
                        <div className="w-56 border border-black text-[9px] dark:border-border sm:text-[10px]">
                            <div className="flex border-b border-black dark:border-border">
                                <div className="w-24 bg-muted/30 px-2 py-0.5 font-semibold border-r border-black dark:border-border">
                                    No Dokumen
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
                    <table className="fa-table w-full border-collapse border border-black text-[11px] dark:border-border">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-12 border border-black px-2 py-2 text-center dark:border-border">
                                    NO
                                </th>
                                <th className="min-w-[180px] border border-black px-3 py-2 text-left dark:border-border">
                                    LOKASI
                                </th>
                                <th className="w-36 border border-black px-2 py-2 text-center dark:border-border">
                                    TANGGAL PERIKSA
                                </th>
                                <th className="w-32 border border-black px-2 py-2 text-center dark:border-border">
                                    KONDISI
                                </th>
                                <th className="w-36 border border-black px-2 py-2 text-center dark:border-border">
                                    PANEL INDIKATOR
                                </th>
                                <th className="border border-black px-3 py-2 text-left dark:border-border">
                                    Keterangan
                                </th>
                                {can_write && (
                                    <th className="no-print w-12 border border-black px-1 py-2 text-center dark:border-border">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item._key} className="hover:bg-muted/10">
                                    <td className="border border-black px-2 py-2 text-center font-medium dark:border-border">
                                        {item.no_urut}
                                    </td>

                                    {/* Lokasi */}
                                    <td className="border border-black px-3 py-2 font-semibold uppercase dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.lokasi}
                                                onChange={(e) =>
                                                    updateItem(item._key, 'lokasi', e.target.value)
                                                }
                                                className="w-full bg-transparent font-semibold uppercase focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.lokasi}</span>
                                        )}
                                    </td>

                                    {/* Tanggal Periksa */}
                                    <td className="border border-black px-2 py-2 text-center dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.tanggal_periksa}
                                                onChange={(e) =>
                                                    updateItem(item._key, 'tanggal_periksa', e.target.value)
                                                }
                                                placeholder="DD/MM/YYYY"
                                                className="w-full bg-transparent text-center focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.tanggal_periksa || '-'}</span>
                                        )}
                                    </td>

                                    {/* Kondisi */}
                                    <td className="border border-black px-2 py-2 text-center dark:border-border">
                                        {can_write ? (
                                            <select
                                                value={item.kondisi}
                                                onChange={(e) =>
                                                    updateItem(item._key, 'kondisi', e.target.value)
                                                }
                                                className="w-full bg-transparent text-center font-medium focus:outline-none"
                                            >
                                                <option value="Baik">Baik</option>
                                                <option value="Rusak">Rusak</option>
                                                <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                                            </select>
                                        ) : (
                                            <span>{item.kondisi}</span>
                                        )}
                                    </td>

                                    {/* Panel Indikator */}
                                    <td className="border border-black px-2 py-2 text-center dark:border-border">
                                        {can_write ? (
                                            <select
                                                value={item.panel_indikator}
                                                onChange={(e) =>
                                                    updateItem(item._key, 'panel_indikator', e.target.value)
                                                }
                                                className="w-full bg-transparent text-center font-medium focus:outline-none"
                                            >
                                                <option value="Baik">Baik</option>
                                                <option value="Normal">Normal</option>
                                                <option value="Alarm">Alarm</option>
                                                <option value="Trouble">Trouble</option>
                                                <option value="Rusak">Rusak</option>
                                            </select>
                                        ) : (
                                            <span>{item.panel_indikator}</span>
                                        )}
                                    </td>

                                    {/* Keterangan */}
                                    <td className="border border-black px-3 py-2 dark:border-border">
                                        {can_write ? (
                                            <input
                                                type="text"
                                                value={item.keterangan}
                                                onChange={(e) =>
                                                    updateItem(item._key, 'keterangan', e.target.value)
                                                }
                                                placeholder="Keterangan kondisi..."
                                                className="w-full bg-transparent focus:outline-none"
                                            />
                                        ) : (
                                            <span>{item.keterangan || '-'}</span>
                                        )}
                                    </td>

                                    {/* Delete Action */}
                                    {can_write && (
                                        <td className="no-print border border-black px-1 py-2 text-center dark:border-border">
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
                            placeholder="Tuliskan catatan pemeriksaan panel fire alarm atau perbaikan di sini..."
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
