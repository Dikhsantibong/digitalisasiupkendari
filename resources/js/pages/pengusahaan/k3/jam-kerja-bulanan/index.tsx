import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CalendarRange,
    Clock,
    ExternalLink,
    Info,
    Printer,
    RefreshCw,
    RotateCcw,
    Save,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanJamKerja from '@/routes/k3/pengusahaan/jam-kerja';
import k3PengusahaanJamKerjaBulanan from '@/routes/k3/pengusahaan/jam-kerja-bulanan';
import type { IdName } from '@/types';

export type JamKerjaBulananData = {
    id?: number | null;
    unit_id?: number;
    year?: number;
    month?: number;
    no_dokumen: string;
    tgl_berlaku: string;
    revisi: string;
    halaman: string;
    jam_kerja_komulatif_bulan_lalu: number;
    karyawan_tetap: number;
    karyawan_tetap_shift: number;
    karyawan_tidak_tetap: number;
    karyawan_tidak_tetap_shift: number;
    jumlah_karyawan?: number;
    hari_kerja: number;
    jam_kerja_standart?: number;
    jam_kerja_standart_karyawan: number;
    jam_kerja_lembur_karyawan: number;
    jam_kerja_seluruh_karyawan?: number;
    jam_absensi_karyawan: number;
    jam_kerja_realisasi_karyawan?: number;
    jam_kerja_komulatif_bulan_ini?: number;
    catatan?: string | null;
};

type HistoryItem = {
    year: number;
    month: number;
    jam_kerja_komulatif_bulan_ini: number;
    updated_at: string | null;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    record: JamKerjaBulananData;
    has_saved: boolean;
    has_detail_record: boolean;
    komulatif_lalu_suggested: number | null;
    history: HistoryItem[];
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 11px !important; }
    .no-print { display: none !important; }
    .jkb-table th, .jkb-table td { border: 1px solid #000 !important; }
    .jkb-header-box { border: 1px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

const formatNumber = (val: number): string =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(val);

export default function PengusahaanJamKerjaBulananPage(props: Props) {
    const {
        unit,
        filters,
        options,
        record: initialRecord,
        has_saved,
        has_detail_record,
        komulatif_lalu_suggested,
        history,
        can_write,
    } = props;

    const [form, setForm] = useState<JamKerjaBulananData>(() => initialRecord);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const updateField = <K extends keyof JamKerjaBulananData>(key: K, value: JamKerjaBulananData[K]) => {
        setForm((prev) => ({ ...prev, [key]: value }));
        setDirty(true);
    };

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: string | number) => {
        const nextFilters = { ...filters, [key]: Number(value) };
        router.get(k3PengusahaanJamKerjaBulanan.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const resetChanges = () => {
        setForm(initialRecord);
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanJamKerjaBulanan.store().url,
            {
                ...form,
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Live Calculations according to FMZ-08.4.4.11 formula:
    const calcs = useMemo(() => {
        const tetap = Number(form.karyawan_tetap) || 0;
        const tetapShift = Number(form.karyawan_tetap_shift) || 0;
        const tidakTetap = Number(form.karyawan_tidak_tetap) || 0;
        const tidakTetapShift = Number(form.karyawan_tidak_tetap_shift) || 0;
        const jumlahKaryawan = tetap + tetapShift + tidakTetap + tidakTetapShift;

        const hariKerja = Number(form.hari_kerja) || 0;
        const jamStandart = hariKerja * 8;

        const jamStandartKaryawan = Number(form.jam_kerja_standart_karyawan) || 0;
        const jamLembur = Number(form.jam_kerja_lembur_karyawan) || 0;
        const jamSeluruh = jamStandartKaryawan + jamLembur;

        const jamAbsensi = Number(form.jam_absensi_karyawan) || 0;
        const jamRealisasi = jamSeluruh - jamAbsensi;

        const komulatifLalu = Number(form.jam_kerja_komulatif_bulan_lalu) || 0;
        const komulatifIni = komulatifLalu + jamRealisasi;

        return {
            jumlahKaryawan,
            jamStandart,
            jamSeluruh,
            jamRealisasi,
            komulatifIni,
        };
    }, [form]);

    return (
        <>
            <Head title={`Laporan Bulanan Jumlah Jam Kerja Karyawan — ${unit.name}`} />
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
                                    Laporan Bulanan Jumlah Jam Kerja Karyawan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="secondary" className="text-xs">
                                    {monthName} {filters.year}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Ringkasan 14 uraian jam kerja standar, lembur, absensi, realisasi, dan kumulatif bulanan (Formulir FMZ-08.4.4.11).
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
                            Cetak (A4)
                        </Button>
                    </div>
                </div>

                {/* Filter and Synchronization Bar */}
                <Card className="no-print p-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
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

                        <div className="flex flex-wrap items-center gap-2">
                            {has_detail_record ? (
                                <div className="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                                    <Info className="size-4 shrink-0" />
                                    <span>Terkoneksi dengan data detail <strong>FMZ-08.4.4.10</strong></span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 gap-1 px-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-100"
                                        onClick={() => {
                                            router.get(k3PengusahaanJamKerja.index().url, {
                                                unit_id: filters.unit_id,
                                                month: filters.month,
                                                year: filters.year,
                                            });
                                        }}
                                    >
                                        Buka Detail <ExternalLink className="size-3" />
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-300">
                                    <Info className="size-4 shrink-0" />
                                    <span>Belum ada lembar detail FMZ-08.4.4.10 bulan ini.</span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 gap-1 px-1.5 text-xs font-semibold text-amber-700 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
                                        onClick={() => {
                                            router.get(k3PengusahaanJamKerja.index().url, {
                                                unit_id: filters.unit_id,
                                                month: filters.month,
                                                year: filters.year,
                                            });
                                        }}
                                    >
                                        Isi FMZ-08.4.4.10 <ExternalLink className="size-3" />
                                    </Button>
                                </div>
                            )}

                            {komulatif_lalu_suggested !== null &&
                                form.jam_kerja_komulatif_bulan_lalu !== komulatif_lalu_suggested &&
                                can_write && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => updateField('jam_kerja_komulatif_bulan_lalu', komulatif_lalu_suggested)}
                                        className="gap-1.5 text-xs text-blue-600 hover:text-blue-700"
                                        title="Gunakan nilai jam kerja kumulatif bulan lalu dari arsip sistem"
                                    >
                                        <RefreshCw className="size-3.5" />
                                        Gunakan Kumulatif Bulan Lalu ({formatNumber(komulatif_lalu_suggested)})
                                    </Button>
                                )}
                        </div>
                    </div>
                </Card>

                {/* KPI Summary Tiles */}
                <div className="no-print grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatTile
                        icon={<Users className="size-4" />}
                        tone="bg-primary/10 text-primary"
                        label="Jumlah Karyawan (Baris 6)"
                        value={`${calcs.jumlahKaryawan} Orang`}
                    />
                    <StatTile
                        icon={<Clock className="size-4" />}
                        tone="bg-blue-500/10 text-blue-600"
                        label="Jam Kerja Seluruh (Baris 11)"
                        value={`${formatNumber(calcs.jamSeluruh)} Jam`}
                    />
                    <StatTile
                        icon={<Calendar className="size-4" />}
                        tone="bg-emerald-500/10 text-emerald-600"
                        label="Jam Kerja Realisasi (Baris 13)"
                        value={`${formatNumber(calcs.jamRealisasi)} Jam`}
                    />
                    <StatTile
                        icon={<Building2 className="size-4" />}
                        tone="bg-purple-500/10 text-purple-600"
                        label="Kumulatif s/d Bulan Ini (Baris 14)"
                        value={`${formatNumber(calcs.komulatifIni)} Jam`}
                    />
                </div>

                {/* Printable container (A4 Portrait) */}
                <div className="print-container w-full overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi FMZ-08.4.4.11 */}
                    <table className="jkb-header-box w-full border-collapse border border-black dark:border-border">
                        <tbody>
                            <tr>
                                {/* Logo PLN Kiri */}
                                <td className="w-48 border-r border-black p-2 text-center align-middle dark:border-border">
                                    <img
                                        src="/logo/sidebar-logo.png"
                                        alt="PLN Nusantara Power"
                                        className="mx-auto max-h-12 object-contain"
                                        onError={(e) => {
                                            (e.target as HTMLElement).style.display = 'none';
                                        }}
                                    />
                                </td>

                                {/* Judul Tengah */}
                                <td className="p-2 text-center align-middle">
                                    <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                        PT PLN NUSANTARA POWER
                                    </div>
                                    <div className="text-sm font-extrabold text-foreground uppercase">
                                        LAPORAN BULANAN JUMLAH JAM KERJA KARYAWAN
                                    </div>
                                    <div className="text-xs font-semibold text-foreground uppercase">
                                        UNIT : {unit.name.toUpperCase().startsWith('ULPLTD') || unit.name.toUpperCase().startsWith('UP') ? unit.name.toUpperCase() : `ULPLTD ${unit.name.toUpperCase()}`}
                                    </div>
                                    <div className="text-xs font-medium text-foreground">
                                        Periode : {monthName} {filters.year}
                                    </div>
                                </td>

                                {/* Metadata Dokumen Kanan */}
                                <td className="w-56 border-l border-black p-1.5 text-left text-xs align-middle dark:border-border">
                                    <table className="w-full text-xs">
                                        <tbody>
                                            <tr>
                                                <td className="w-24 py-0.5 font-semibold text-muted-foreground">No. Dokumen</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={form.no_dokumen}
                                                            onChange={(e) => updateField('no_dokumen', e.target.value)}
                                                            className="w-full bg-transparent text-xs focus:outline-none"
                                                        />
                                                    ) : (
                                                        form.no_dokumen
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Tgl. Berlaku</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={form.tgl_berlaku}
                                                            onChange={(e) => updateField('tgl_berlaku', e.target.value)}
                                                            className="w-full bg-transparent text-xs focus:outline-none"
                                                        />
                                                    ) : (
                                                        form.tgl_berlaku
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Revisi</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={form.revisi}
                                                            onChange={(e) => updateField('revisi', e.target.value)}
                                                            className="w-full bg-transparent text-xs focus:outline-none"
                                                        />
                                                    ) : (
                                                        form.revisi
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Halaman</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={form.halaman}
                                                            onChange={(e) => updateField('halaman', e.target.value)}
                                                            className="w-full bg-transparent text-xs focus:outline-none"
                                                        />
                                                    ) : (
                                                        form.halaman
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {/* Tabel 14 Baris FMZ-08.4.4.11 */}
                    <table className="jkb-table mt-4 w-full border-collapse border border-black text-xs dark:border-border">
                        <thead>
                            <tr className="bg-[#e8eaf6] text-center font-bold text-foreground dark:bg-muted/60">
                                <th className="w-14 border border-black p-2 dark:border-border">NO.</th>
                                <th className="border border-black p-2 text-left dark:border-border">URAIAN</th>
                                <th className="w-40 border border-black p-2 text-center dark:border-border">JUMLAH</th>
                                <th className="w-44 border border-black p-2 text-left dark:border-border">SATUAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            {/* Baris 1: Jam Kerja Komulatif Bulan Lalu */}
                            <tr className="border-b border-black font-bold bg-muted/20 dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">1.</td>
                                <td className="border-r border-black p-2 dark:border-border">
                                    Jam Kerja Komulatif Bulan Lalu
                                </td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            step="any"
                                            value={form.jam_kerja_komulatif_bulan_lalu}
                                            onChange={(e) => updateField('jam_kerja_komulatif_bulan_lalu', parseFloat(e.target.value) || 0)}
                                            className="w-full text-center font-bold text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{formatNumber(form.jam_kerja_komulatif_bulan_lalu)}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 2: Karyawan Tetap */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">2.</td>
                                <td className="border-r border-black p-2 dark:border-border">Karyawan Tetap</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            value={form.karyawan_tetap}
                                            onChange={(e) => updateField('karyawan_tetap', parseInt(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{form.karyawan_tetap}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Karyawan</td>
                            </tr>

                            {/* Baris 3: Karyawan Tetap (Shift) */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">3.</td>
                                <td className="border-r border-black p-2 dark:border-border">Karyawan Tetap (Shift)</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            value={form.karyawan_tetap_shift}
                                            onChange={(e) => updateField('karyawan_tetap_shift', parseInt(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{form.karyawan_tetap_shift}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Karyawan</td>
                            </tr>

                            {/* Baris 4: Karyawan Tidak Tetap */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">4.</td>
                                <td className="border-r border-black p-2 dark:border-border">Karyawan Tidak Tetap</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            value={form.karyawan_tidak_tetap}
                                            onChange={(e) => updateField('karyawan_tidak_tetap', parseInt(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{form.karyawan_tidak_tetap}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Karyawan</td>
                            </tr>

                            {/* Baris 5: Karyawan Tidak Tetap (Shift) */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">5.</td>
                                <td className="border-r border-black p-2 dark:border-border">Karyawan Tidak Tetap (Shift)</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            value={form.karyawan_tidak_tetap_shift}
                                            onChange={(e) => updateField('karyawan_tidak_tetap_shift', parseInt(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{form.karyawan_tidak_tetap_shift}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Karyawan</td>
                            </tr>

                            {/* Baris 6: Jumlah Karyawan (Bold, computed) */}
                            <tr className="border-b border-black font-bold bg-muted/20 dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">6.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jumlah Karyawan</td>
                                <td className="border-r border-black p-2 text-center dark:border-border">
                                    {formatNumber(calcs.jumlahKaryawan)}
                                </td>
                                <td className="p-2 text-muted-foreground">Karyawan</td>
                            </tr>

                            {/* Baris 7: Hari Kerja */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">7.</td>
                                <td className="border-r border-black p-2 dark:border-border">Hari Kerja</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            value={form.hari_kerja}
                                            onChange={(e) => updateField('hari_kerja', parseInt(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{form.hari_kerja}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Hari</td>
                            </tr>

                            {/* Baris 8: Jam Kerja Standart (Bold, computed: Hari Kerja * 8) */}
                            <tr className="border-b border-black font-bold bg-muted/20 dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">8.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Standart</td>
                                <td className="border-r border-black p-2 text-center dark:border-border">
                                    {formatNumber(calcs.jamStandart)}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja</td>
                            </tr>

                            {/* Baris 9: Jam Kerja Standart Karyawan */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">9.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Standart Karyawan</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            step="any"
                                            value={form.jam_kerja_standart_karyawan}
                                            onChange={(e) => updateField('jam_kerja_standart_karyawan', parseFloat(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{formatNumber(form.jam_kerja_standart_karyawan)}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 10: Jam Kerja Lembur Karyawan */}
                            <tr className="border-b border-black dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">10.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Lembur Karyawan</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            step="any"
                                            value={form.jam_kerja_lembur_karyawan}
                                            onChange={(e) => updateField('jam_kerja_lembur_karyawan', parseFloat(e.target.value) || 0)}
                                            className="w-full text-center text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{formatNumber(form.jam_kerja_lembur_karyawan)}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 11: Jam Kerja Seluruh Karyawan (Bold, computed: 9 + 10) */}
                            <tr className="border-b border-black font-bold bg-muted/20 dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">11.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Seluruh Karyawan</td>
                                <td className="border-r border-black p-2 text-center dark:border-border">
                                    {formatNumber(calcs.jamSeluruh)}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 12: Jam Absensi Karyawan (Bold) */}
                            <tr className="border-b border-black font-bold dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">12.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Absensi Karyawan</td>
                                <td className="border-r border-black p-1 text-center dark:border-border">
                                    {can_write ? (
                                        <input
                                            type="number"
                                            step="any"
                                            value={form.jam_absensi_karyawan}
                                            onChange={(e) => updateField('jam_absensi_karyawan', parseFloat(e.target.value) || 0)}
                                            className="w-full text-center font-bold text-foreground focus:bg-primary/10 focus:outline-none"
                                        />
                                    ) : (
                                        <span>{formatNumber(form.jam_absensi_karyawan)}</span>
                                    )}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 13: Jam Kerja Realisasi Karyawan (Bold, computed: 11 - 12) */}
                            <tr className="border-b border-black font-bold bg-muted/20 dark:border-border">
                                <td className="border-r border-black p-2 text-center dark:border-border">13.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Realisasi Karyawan</td>
                                <td className="border-r border-black p-2 text-center dark:border-border">
                                    {formatNumber(calcs.jamRealisasi)}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>

                            {/* Baris 14: Jam Kerja Komulatif s/d Bulan ini (Bold, computed: 1 + 13) */}
                            <tr className="font-bold bg-muted/30">
                                <td className="border-r border-black p-2 text-center dark:border-border">14.</td>
                                <td className="border-r border-black p-2 dark:border-border">Jam Kerja Komulatif s/d Bulan ini</td>
                                <td className="border-r border-black p-2 text-center text-primary dark:border-border dark:text-primary">
                                    {formatNumber(calcs.komulatifIni)}
                                </td>
                                <td className="p-2 text-muted-foreground">Jam Kerja Karyawan</td>
                            </tr>
                        </tbody>
                    </table>

                    {/* Catatan Tambahan (Bila ada) */}
                    <div className="mt-4">
                        <div className="text-xs font-semibold text-foreground">Catatan / Keterangan:</div>
                        {can_write ? (
                            <textarea
                                value={form.catatan ?? ''}
                                onChange={(e) => updateField('catatan', e.target.value)}
                                placeholder="Tambahkan catatan khusus mengenai realisasi jam kerja jika ada..."
                                rows={2}
                                className="mt-1 w-full rounded border border-border bg-background p-2 text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                            />
                        ) : (
                            <p className="mt-1 text-xs text-muted-foreground italic">
                                {form.catatan ? form.catatan : 'Tidak ada catatan.'}
                            </p>
                        )}
                    </div>
                </div>

                {/* History Riwayat 12 Bulan Terakhir */}
                {history.length > 0 && (
                    <Card className="no-print mt-2 p-4">
                        <div className="flex items-center gap-2 mb-3">
                            <CalendarRange className="size-4 text-primary" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Riwayat Kumulatif Jam Kerja 12 Bulan Terakhir ({unit.name})
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border bg-muted/40 text-left font-semibold text-muted-foreground">
                                        <th className="p-2">Periode</th>
                                        <th className="p-2 text-right">Jam Kumulatif Bulan Ini</th>
                                        <th className="p-2 text-right">Terakhir Diperbarui</th>
                                        <th className="p-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map((h) => {
                                        const mName = OPERASI_MONTHS[h.month - 1] ?? `Bulan ${h.month}`;
                                        const isCurrent = h.year === filters.year && h.month === filters.month;

                                        return (
                                            <tr
                                                key={`${h.year}-${h.month}`}
                                                className={`border-b border-border/50 hover:bg-muted/30 ${isCurrent ? 'bg-primary/5 font-semibold' : ''}`}
                                            >
                                                <td className="p-2">
                                                    {mName} {h.year}
                                                    {isCurrent && (
                                                        <Badge variant="outline" className="ml-2 border-primary/30 text-[10px] text-primary">
                                                            Aktif
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-2 text-right font-mono font-bold">
                                                    {formatNumber(h.jam_kerja_komulatif_bulan_ini)} Jam
                                                </td>
                                                <td className="p-2 text-right text-muted-foreground">
                                                    {h.updated_at ?? '-'}
                                                </td>
                                                <td className="p-2 text-center">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 px-2 text-xs"
                                                        onClick={() => {
                                                            router.get(k3PengusahaanJamKerjaBulanan.index().url, {
                                                                unit_id: filters.unit_id,
                                                                month: h.month,
                                                                year: h.year,
                                                            });
                                                        }}
                                                    >
                                                        Lihat
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

function StatTile({
    icon,
    tone,
    label,
    value,
}: {
    icon: React.ReactNode;
    tone: string;
    label: string;
    value: string;
}) {
    return (
        <Card className="flex items-center gap-3 p-3">
            <div className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${tone}`}>
                {icon}
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-[11px] text-muted-foreground">{label}</p>
                <p className="text-sm font-bold text-foreground">{value}</p>
            </div>
        </Card>
    );
}
