import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    MapPin,
    Plus,
    Printer,
    RotateCcw,
    Save,
    ShieldCheck,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { MobileMatrixForm } from '@/components/mobile/matrix-form';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanPatrolSecurity from '@/routes/k3/pengusahaan/patrol-security';
import type { IdName } from '@/types';

export type PatrolSecurityItem = {
    _key: number;
    id?: number | null;
    lokasi_kode: string;
    lokasi_nama?: string;
    scans: Record<string, number>;
    total: number;
    persentase: number;
    sort_order?: number;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    days_in_month: number;
    sundays: number[];
    options: {
        units: IdName[];
        years: number[];
    };
    record: {
        judul: string;
        catatan: string;
        items: Array<Omit<PatrolSecurityItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
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
        font-size: 8.5px !important; 
    }
    .no-print { display: none !important; }
    .patrol-table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed !important; }
    .patrol-table th, .patrol-table td { border: 1px solid #000 !important; padding: 2px 1.5px !important; text-align: center !important; }
    .patrol-header-box { border: 2px solid #000 !important; }
    .sunday-col { background-color: #fef08a !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    input, textarea { 
        border: none !important; 
        background: transparent !important; 
        padding: 0 !important; 
        font-size: inherit !important; 
        text-align: center !important;
        appearance: none !important; 
        -webkit-appearance: none !important;
    }
}
`;

export default function PengusahaanPatrolSecurityPage(props: Props) {
    const {
        unit,
        filters,
        days_in_month,
        sundays,
        options,
        record: initialRecord,
        has_saved,
        can_write,
    } = props;

    const [judul, setJudul] = useState(initialRecord.judul || 'PATROL CHECK SECURITY');
    const [catatan, setCatatan] = useState(initialRecord.catatan || '');

    const [items, setItems] = useState<PatrolSecurityItem[]>(() =>
        initialRecord.items.map((it, idx) => {
            const rawScans = it.scans || {};
            const cleanScans: Record<string, number> = {};

            for (let d = 1; d <= days_in_month; d++) {
                cleanScans[String(d)] = Number(rawScans[String(d)]) || 0;
            }

            const rowTotal = Object.values(cleanScans).reduce((sum, v) => sum + v, 0);

            return {
                ...it,
                _key: idx + 1,
                scans: cleanScans,
                total: rowTotal,
                persentase: it.persentase || 0,
            };
        }),
    );

    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: string) => {
        router.get(
            k3PengusahaanPatrolSecurity.index().url,
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

    // Update single cell scan value for a checkpoint and day
    const updateScan = (key: number, day: number, value: string) => {
        const numVal = Math.max(0, parseInt(value, 10) || 0);

        setItems((prev) =>
            prev.map((it) => {
                if (it._key !== key) {
                    return it;
                }

                const nextScans = { ...it.scans, [String(day)]: numVal };
                const nextTotal = Object.values(nextScans).reduce((s, v) => s + v, 0);

                return {
                    ...it,
                    scans: nextScans,
                    total: nextTotal,
                };
            }),
        );
        setDirty(true);
    };

    const updateLokasiKode = (key: number, kode: string) => {
        setItems((prev) =>
            prev.map((it) => (it._key === key ? { ...it, lokasi_kode: kode, lokasi_nama: kode } : it)),
        );
        setDirty(true);
    };

    const addCheckpoint = () => {
        const nextNum = items.length + 1;
        const prefix = unit.name.substring(0, 3).toUpperCase() || 'POA';
        const kode = `${prefix}${nextNum}`;

        const cleanScans: Record<string, number> = {};

        for (let d = 1; d <= days_in_month; d++) {
            cleanScans[String(d)] = 0;
        }

        const newItem: PatrolSecurityItem = {
            _key: nextKey,
            id: null,
            lokasi_kode: kode,
            lokasi_nama: kode,
            scans: cleanScans,
            total: 0,
            persentase: 0,
            sort_order: items.length,
        };

        setItems((prev) => [...prev, newItem]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const removeCheckpoint = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    // Quick Action: Reset all cells to 0
    const setAllZero = () => {
        setItems((prev) =>
            prev.map((it) => {
                const zeroScans: Record<string, number> = {};

                for (let d = 1; d <= days_in_month; d++) {
                    zeroScans[String(d)] = 0;
                }

                return {
                    ...it,
                    scans: zeroScans,
                    total: 0,
                    persentase: 0,
                };
            }),
        );
        setDirty(true);
    };

    const resetChanges = () => {
        setJudul(initialRecord.judul || 'PATROL CHECK SECURITY');
        setCatatan(initialRecord.catatan || '');
        setItems(
            initialRecord.items.map((it, idx) => {
                const rawScans = it.scans || {};
                const cleanScans: Record<string, number> = {};

                for (let d = 1; d <= days_in_month; d++) {
                    cleanScans[String(d)] = Number(rawScans[String(d)]) || 0;
                }

                const rowTotal = Object.values(cleanScans).reduce((sum, v) => sum + v, 0);

                return {
                    ...it,
                    _key: idx + 1,
                    scans: cleanScans,
                    total: rowTotal,
                    persentase: it.persentase || 0,
                };
            }),
        );
        setDirty(false);
    };

    // Calculate real-time grand totals and percentages
    const calculation = useMemo(() => {
        const grandTotal = items.reduce((acc, it) => acc + it.total, 0);

        // Daily column sums
        const dailyTotals: Record<number, number> = {};

        for (let d = 1; d <= days_in_month; d++) {
            let daySum = 0;

            for (const item of items) {
                daySum += Number(item.scans[String(d)]) || 0;
            }

            dailyTotals[d] = daySum;
        }

        // Active checkpoints with > 0 scans
        const activeCheckpoints = items.filter((it) => it.total > 0).length;
        const avgDaily = (grandTotal / days_in_month).toFixed(1);

        // Find most frequent checkpoint
        let maxRow: PatrolSecurityItem | null = null;

        for (const item of items) {
            if (!maxRow || item.total > maxRow.total) {
                maxRow = item;
            }
        }

        return {
            grandTotal,
            dailyTotals,
            activeCheckpoints,
            avgDaily,
            topCheckpoint: maxRow && maxRow.total > 0 ? `${maxRow.lokasi_kode} (${maxRow.total})` : '—',
        };
    }, [items, days_in_month]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const grandTotal = calculation.grandTotal;

        router.post(
            k3PengusahaanPatrolSecurity.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                judul,
                catatan,
                items: items.map((it, idx) => {
                    const rowPercentage = grandTotal > 0 ? Number(((it.total / grandTotal) * 100).toFixed(2)) : 0;

                    return {
                        id: it.id,
                        lokasi_kode: it.lokasi_kode,
                        lokasi_nama: it.lokasi_nama || it.lokasi_kode,
                        scans: it.scans,
                        total: it.total,
                        persentase: rowPercentage,
                        sort_order: idx,
                    };
                }),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const daysArray = Array.from({ length: days_in_month }, (_, i) => i + 1);

    return (
        <div className="space-y-6 p-6">
            <style>{PRINT_CSS}</style>

            <Head title={`Patrol Check Security — ${unit.name}`} />

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
                                Patrol Check Security
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
                            Rekapitulasi dan pemantauan matriks scan patroli harian satuan pengamanan per checkpoint.
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
                            <MapPin className="size-3.5 text-sky-600" />
                            <span>{items.length} Checkpoint ({calculation.activeCheckpoints} Aktif)</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <ShieldCheck className="size-3.5 text-emerald-600" />
                            <span>Total Scan: {calculation.grandTotal} Kali</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <Activity className="size-3.5 text-indigo-600" />
                            <span>Rerata: {calculation.avgDaily} Scan/Hari</span>
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
                            onClick={setAllZero}
                            className="gap-1.5 text-xs"
                            title="Reset seluruh angka scan menjadi 0"
                        >
                            <Sparkles className="size-3.5 text-amber-500" />
                            <span>Setel Semua 0</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addCheckpoint}
                            className="gap-1.5 text-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Tambah Checkpoint</span>
                        </Button>

                        <div className="ml-auto text-xs text-muted-foreground flex items-center gap-2">
                            <span className="inline-flex items-center gap-1">
                                <span className="inline-block size-3 rounded border border-amber-400 bg-amber-200" />
                                <span>Hari Minggu</span>
                            </span>
                            <span>Checkpoint Terbanyak: <strong className="text-foreground">{calculation.topCheckpoint}</strong></span>
                        </div>
                    </div>
                )}
            </Card>

            {/* Printable Container (A4 Landscape matches original form) */}
            <div className="print-container overflow-hidden rounded-md border border-border bg-card p-3 shadow-xs sm:p-6">
                {/* Header KOP Dokumen Resmi Kembar Logo PLN & Lambang K3 */}
                <div className="patrol-header-box relative rounded border-2 border-black p-3.5 dark:border-border">
                    {/* Logo PLN Kiri */}
                    <div className="absolute left-3 top-3.5 flex items-center">
                        <img
                            src="/logo/sidebar-logo.png"
                            alt="PLN Nusantara Power"
                            className="max-h-7 max-w-14 object-contain sm:max-h-11 sm:max-w-none"
                            onError={(e) => {
                                (e.target as HTMLElement).style.display = 'none';
                            }}
                        />
                    </div>

                    {/* Lambang K3 Kanan */}
                    <div className="absolute right-3 top-3.5 flex items-center">
                        <img
                            src="/logo/k3.png"
                            alt="Logo K3"
                            className="max-h-7 object-contain sm:max-h-11"
                            onError={(e) => {
                                (e.target as HTMLElement).style.display = 'none';
                            }}
                        />
                    </div>

                    {/* Teks Instansi Tengah */}
                    <div className="px-16 text-center sm:px-24">
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
                    <div className="my-2 border-b-2 border-black dark:border-border" />

                    {/* Judul Dokumen & Info Bulan */}
                    <div className="flex flex-col items-center justify-between gap-1 px-2 sm:flex-row">
                        <div className="hidden w-24 sm:block" />
                        <h2 className="text-sm font-black tracking-wider text-foreground uppercase sm:text-base text-center">
                            PATROL CHECK SECURITY
                        </h2>
                        <div className="text-xs font-bold text-foreground text-right">
                            Bulan {monthName} {filters.year}
                        </div>
                    </div>
                </div>

                {/* Patrol Check Matrix Table */}
                {compact ? (
                    <div className="mt-3">
                        <MobileMatrixForm<PatrolSecurityItem>
                            columns={daysArray.map((d) => ({ key: String(d), label: String(d), isRed: sundays.includes(d), done: items.some((it) => (it.scans[String(d)] ?? 0) > 0) }))}
                            rows={items}
                            rowKey={(it) => it._key}
                            rowLabel={(it) =>
                                can_write ? (
                                    <div className="flex items-center gap-2">
                                        <Input value={it.lokasi_kode} onChange={(e) => updateLokasiKode(it._key, e.target.value)} placeholder="Kode lokasi" className="h-10 text-sm font-semibold" />
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeCheckpoint(it._key)} className="size-10 shrink-0 text-muted-foreground hover:text-destructive" aria-label="Hapus lokasi">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ) : (
                                    it.lokasi_kode
                                )
                            }
                            rowSub={(it) => `Total bulan ini: ${it.total} scan · ${calculation.grandTotal > 0 ? ((it.total / calculation.grandTotal) * 100).toFixed(1) : '0.0'}%`}
                            value={(it, day) => String(it.scans[day] ?? 0)}
                            onChange={(index, day, value) => updateScan(items[index]._key, Number(day), value)}
                            readOnly={!can_write}
                            empty="Belum ada lokasi patroli."
                            footer={(day) => (
                                <p className="px-1 text-[12px] text-muted-foreground">
                                    Total tanggal {day}: <span className="font-semibold text-foreground">{calculation.dailyTotals[Number(day)] ?? 0}</span> scan · bulan ini {calculation.grandTotal} scan
                                </p>
                            )}
                        />
                    </div>
                ) : (
                    <div className="mt-3 overflow-x-auto">
                        <table className="patrol-table w-full border-collapse border border-black text-[9px] dark:border-border sm:text-[10px]">
                            <thead>
                                <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                    <th rowSpan={2} className="w-16 min-w-[55px] border border-black px-1 py-1 text-center dark:border-border">
                                        LOKASI
                                    </th>
                                    <th colSpan={days_in_month} className="border border-black px-1 py-0.5 text-center dark:border-border">
                                        TANGGAL
                                    </th>
                                    <th rowSpan={2} className="w-11 min-w-[38px] border border-black px-1 py-1 text-center dark:border-border">
                                        TOTAL
                                    </th>
                                    <th rowSpan={2} className="w-14 min-w-[48px] border border-black px-1 py-1 text-center dark:border-border">
                                        PERSENTASE
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="no-print w-7 border border-black px-0.5 py-1 text-center dark:border-border">
                                            #
                                        </th>
                                    )}
                                </tr>
                                <tr className="bg-muted/30 font-bold text-foreground">
                                    {daysArray.map((d) => {
                                        const isSunday = sundays.includes(d);

                                        return (
                                            <th
                                                key={d}
                                                className={`border border-black px-0.5 py-0.5 text-center dark:border-border min-w-[20px] ${
                                                    isSunday ? 'sunday-col bg-amber-200 text-amber-950 font-black dark:bg-amber-900/60 dark:text-amber-200' : ''
                                                }`}
                                            >
                                                {d}
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((it) => {
                                    const grandTotal = calculation.grandTotal;
                                    const rowPercentage = grandTotal > 0 ? ((it.total / grandTotal) * 100).toFixed(1) : '0.0';

                                    return (
                                        <tr key={it._key} className="hover:bg-muted/15 transition-colors">
                                            {/* LOKASI Kode */}
                                            <td className="border border-black px-1 py-0.5 font-bold text-center align-middle bg-card dark:border-border">
                                                {can_write ? (
                                                    <input
                                                        type="text"
                                                        value={it.lokasi_kode}
                                                        onChange={(e) => updateLokasiKode(it._key, e.target.value)}
                                                        className="w-full text-center font-bold bg-transparent focus:bg-background focus:outline-none"
                                                        placeholder="POA..."
                                                    />
                                                ) : (
                                                    it.lokasi_kode
                                                )}
                                            </td>

                                            {/* Day 1..days_in_month Scans */}
                                            {daysArray.map((d) => {
                                                const isSunday = sundays.includes(d);
                                                const scanVal = it.scans[String(d)] ?? 0;

                                                return (
                                                    <td
                                                        key={d}
                                                        className={`border border-black p-0 text-center align-middle dark:border-border ${
                                                            isSunday ? 'sunday-col bg-amber-100/70 dark:bg-amber-950/40 font-medium' : ''
                                                        }`}
                                                    >
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={scanVal === 0 ? '0' : String(scanVal)}
                                                                onChange={(e) => updateScan(it._key, d, e.target.value)}
                                                                className={`w-full h-6 text-center text-[9px] bg-transparent focus:bg-background focus:outline-none sm:text-[10px] ${
                                                                    scanVal > 0 ? 'font-bold text-foreground' : 'text-muted-foreground/70'
                                                                }`}
                                                            />
                                                        ) : (
                                                            <span className={scanVal > 0 ? 'font-bold' : 'text-muted-foreground/60'}>
                                                                {scanVal}
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}

                                            {/* Row TOTAL */}
                                            <td className="border border-black px-1 py-0.5 font-bold text-center align-middle bg-muted/20 dark:border-border">
                                                {it.total}
                                            </td>

                                            {/* Row PERSENTASE */}
                                            <td className="border border-black px-1 py-0.5 text-center font-semibold align-middle bg-muted/10 dark:border-border">
                                                {rowPercentage}%
                                            </td>

                                            {/* Action Button */}
                                            {can_write && (
                                                <td className="no-print border border-black p-0.5 text-center align-middle dark:border-border">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeCheckpoint(it._key)}
                                                        className="text-muted-foreground hover:text-red-600 transition-colors"
                                                        title="Hapus baris ini"
                                                    >
                                                        <Trash2 className="size-3 mx-auto" />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                {/* Summary Footer Row */}
                                <tr className="bg-muted/40 font-black text-foreground border-t-2 border-black dark:border-border">
                                    <td className="border border-black px-1 py-1 text-center dark:border-border uppercase">
                                        TOTAL
                                    </td>
                                    {daysArray.map((d) => {
                                        const isSunday = sundays.includes(d);
                                        const daySum = calculation.dailyTotals[d] ?? 0;

                                        return (
                                            <td
                                                key={d}
                                                className={`border border-black px-0.5 py-1 text-center font-bold dark:border-border ${
                                                    isSunday ? 'sunday-col bg-amber-200 text-amber-950 dark:bg-amber-900/60 dark:text-amber-200' : ''
                                                }`}
                                            >
                                                {daySum}
                                            </td>
                                        );
                                    })}
                                    <td className="border border-black px-1 py-1 text-center font-black dark:border-border text-emerald-800 dark:text-emerald-300">
                                        {calculation.grandTotal}
                                    </td>
                                    <td className="border border-black px-1 py-1 text-center font-black dark:border-border">
                                        {calculation.grandTotal > 0 ? '100%' : '0%'}
                                    </td>
                                    {can_write && <td className="no-print border border-black dark:border-border" />}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                {/* Additional Notes Box */}
                <div className="mt-3 border border-black p-2.5 text-[9px] dark:border-border sm:text-[10px]">
                    <div className="font-bold uppercase text-foreground mb-1">
                        Catatan Kejadian / Temuan Patroli Satuan Pengamanan:
                    </div>
                    {can_write ? (
                        <textarea
                            rows={2}
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            placeholder="Catatan kondisi rute patroli, temuan pintu/pagar/instalasi, gangguan keamanan, atau catatan shift..."
                            className="w-full rounded border border-input bg-transparent p-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
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
