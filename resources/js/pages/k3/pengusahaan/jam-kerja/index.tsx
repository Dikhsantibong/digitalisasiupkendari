import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Clock,
    Printer,
    RotateCcw,
    Save,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard } from '@/routes';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanJamKerja from '@/routes/k3/pengusahaan/jam-kerja';
import type { IdName } from '@/types';

export type JamKerjaData = {
    id?: number | null;
    no_dokumen: string;
    tgl_berlaku: string;
    revisi: string;
    halaman: string;
    karyawan_tetap: number;
    karyawan_tetap_shift: number;
    karyawan_tidak_tetap: number;
    karyawan_tidak_tetap_shift: number;
    hari_tetap: number;
    jam_tetap: number;
    lembur_tetap: number;
    hari_tetap_shift: number;
    jam_tetap_shift: number;
    lembur_tetap_shift: number;
    hari_tidak_tetap: number;
    jam_tidak_tetap: number;
    lembur_tidak_tetap: number;
    hari_tidak_tetap_shift: number;
    jam_tidak_tetap_shift: number;
    lembur_tidak_tetap_shift: number;
    cuti_orang: number;
    cuti_hari: number;
    cuti_jam: number;
    ijin_orang: number;
    ijin_hari: number;
    ijin_jam: number;
    sakit_orang: number;
    sakit_hari: number;
    sakit_jam: number;
    total_jam_kerja_orang?: number;
    total_lembur?: number;
    total_absensi_jam?: number;
    total_jam_kerja_seluruh?: number;
    catatan?: string | null;
};

type HistoryItem = {
    year: number;
    month: number;
    total_jam_kerja_seluruh: number;
    updated_at: string | null;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    record: JamKerjaData;
    has_saved: boolean;
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
    .jk-table th, .jk-table td { border: 1px solid #000 !important; }
    .jk-header-box { border: 1px solid #000 !important; }
    .jk-box { border: 2px solid #000 !important; }
    input { border: none !important; background: transparent !important; }
}
`;

const formatDec = (val: number): string =>
    new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);

export default function PengusahaanJamKerjaPage(props: Props) {
    const { unit, filters, options, record: initialRecord, can_write } = props;

    const [form, setForm] = useState<JamKerjaData>(() => initialRecord);
    const [dirty, setDirty] = useState<boolean>(false);
    const [saving, setSaving] = useState<boolean>(false);

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const updateField = <K extends keyof JamKerjaData>(key: K, value: JamKerjaData[K]) => {
        setForm((prev) => ({ ...prev, [key]: value }));
        setDirty(true);
    };

    const updateNum = (key: keyof JamKerjaData, rawValue: string) => {
        const parsed = rawValue === '' ? 0 : Number(rawValue);

        updateField(key, parsed);
    };

    const navigatePeriod = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah halaman?')) {
            return;
        }

        router.get(
            k3PengusahaanJamKerja.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    const resetChanges = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setForm(initialRecord);
            setDirty(false);
        }
    };

    const save = () => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            k3PengusahaanJamKerja.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...form,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Live Calculations matching physical form:
    const calcs = useMemo(() => {
        const totalKaryawan =
            (form.karyawan_tetap || 0) +
            (form.karyawan_tetap_shift || 0) +
            (form.karyawan_tidak_tetap || 0) +
            (form.karyawan_tidak_tetap_shift || 0);

        const jamTetap = (form.karyawan_tetap || 0) * (form.hari_tetap || 0) * (form.jam_tetap || 0);
        const jamTetapShift = (form.karyawan_tetap_shift || 0) * (form.hari_tetap_shift || 0) * (form.jam_tetap_shift || 0);
        const jamTidakTetap = (form.karyawan_tidak_tetap || 0) * (form.hari_tidak_tetap || 0) * (form.jam_tidak_tetap || 0);
        const jamTidakTetapShift = (form.karyawan_tidak_tetap_shift || 0) * (form.hari_tidak_tetap_shift || 0) * (form.jam_tidak_tetap_shift || 0);

        const totalJamOrang = jamTetap + jamTetapShift + jamTidakTetap + jamTidakTetapShift;

        const totalLembur =
            (form.lembur_tetap || 0) +
            (form.lembur_tetap_shift || 0) +
            (form.lembur_tidak_tetap || 0) +
            (form.lembur_tidak_tetap_shift || 0);

        const totalAbsensiJam =
            (form.cuti_jam || 0) +
            (form.ijin_jam || 0) +
            (form.sakit_jam || 0);

        const totalJamSeluruh = totalJamOrang + totalLembur - totalAbsensiJam;

        return {
            totalKaryawan,
            jamTetap,
            jamTetapShift,
            jamTidakTetap,
            jamTidakTetapShift,
            totalJamOrang,
            totalLembur,
            totalAbsensiJam,
            totalJamSeluruh,
        };
    }, [form]);

    return (
        <>
            <Head title={`Laporan Hari Kerja & Jam Kerja Karyawan — ${unit.name}`} />
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
                                    Laporan Hari Kerja dan Jam Kerja Karyawan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="secondary" className="text-xs">
                                    {monthName} {filters.year}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Perhitungan hari kerja, jam reguler, lembur, dan absensi seluruh karyawan unit (Formulir FMZ-08.4.4.10).
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
                                onClick={save}
                                disabled={saving || !dirty}
                                className="gap-1.5 text-xs"
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                            title="Cetak Dokumen Potret A4"
                        >
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(value) => navigatePeriod({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => navigatePeriod({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, idx) => ({ value: String(idx + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => navigatePeriod({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* KPI Summary Tiles */}
                <div className="no-print grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatTile
                        icon={<Users className="size-4" />}
                        tone="bg-primary/10 text-primary"
                        label="Jumlah Karyawan"
                        value={`${calcs.totalKaryawan} Orang`}
                    />
                    <StatTile
                        icon={<Clock className="size-4" />}
                        tone="bg-blue-500/10 text-blue-600"
                        label="Jam Kerja Orang"
                        value={`${formatDec(calcs.totalJamOrang)} Jam`}
                    />
                    <StatTile
                        icon={<Calendar className="size-4" />}
                        tone="bg-amber-500/10 text-amber-600"
                        label="Total Lembur"
                        value={`${formatDec(calcs.totalLembur)} Jam`}
                    />
                    <StatTile
                        icon={<Building2 className="size-4" />}
                        tone="bg-emerald-500/10 text-emerald-600"
                        label="Total Jam Seluruh"
                        value={`${formatDec(calcs.totalJamSeluruh)} Jam`}
                    />
                </div>

                {/* Printable container (A4 Portrait) */}
                <div className="print-container mx-auto max-w-[850px] overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    {/* KOP Dokumen Resmi FMZ-08.4.4.10 */}
                    <table className="jk-header-box w-full border-collapse border border-black dark:border-border">
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
                                        LAPORAN HARI KERJA DAN JAM KERJA KARYAWAN
                                    </div>
                                    <div className="text-xs font-semibold text-foreground uppercase">
                                        UNIT : ULPLTD {unit.name.toUpperCase()}
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

                    {/* Section Body */}
                    <div className="mt-6 space-y-6 text-xs text-foreground">
                        {/* A. Karyawan */}
                        <div>
                            <div className="font-bold text-sm">A. Karyawan:</div>
                            <div className="mt-2 space-y-1 pl-4">
                                <div className="grid grid-cols-[180px_20px_100px_60px] items-center">
                                    <span>Tetap</span>
                                    <span>:</span>
                                    <NumInput
                                        value={form.karyawan_tetap}
                                        onChange={(v) => updateNum('karyawan_tetap', v)}
                                        canWrite={can_write}
                                    />
                                    <span className="pl-2">Orang</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_100px_60px] items-center">
                                    <span>Tetap (Shift)</span>
                                    <span>:</span>
                                    <NumInput
                                        value={form.karyawan_tetap_shift}
                                        onChange={(v) => updateNum('karyawan_tetap_shift', v)}
                                        canWrite={can_write}
                                    />
                                    <span className="pl-2">Orang</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_100px_60px] items-center">
                                    <span>Tidak Tetap</span>
                                    <span>:</span>
                                    <NumInput
                                        value={form.karyawan_tidak_tetap}
                                        onChange={(v) => updateNum('karyawan_tidak_tetap', v)}
                                        canWrite={can_write}
                                    />
                                    <span className="pl-2">Orang</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_100px_60px] items-center">
                                    <span>Tidak Tetap (Shift)</span>
                                    <span>:</span>
                                    <NumInput
                                        value={form.karyawan_tidak_tetap_shift}
                                        onChange={(v) => updateNum('karyawan_tidak_tetap_shift', v)}
                                        canWrite={can_write}
                                    />
                                    <span className="pl-2">Orang</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_100px_60px] items-center border-t border-black pt-1 font-bold dark:border-border">
                                    <span>Jumlah Karyawan</span>
                                    <span>:</span>
                                    <span className="text-right font-bold pr-2">{calcs.totalKaryawan}</span>
                                    <span className="pl-2">Orang</span>
                                </div>
                            </div>
                        </div>

                        {/* B. Hari Kerja dan Jam Kerja / Orang / Bulan */}
                        <div>
                            <div className="font-bold text-sm">B. Hari Kerja dan Jam Kerja / Orang / Bulan:</div>
                            <div className="mt-2 pl-4">
                                <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center font-semibold text-muted-foreground pb-1">
                                    <span />
                                    <span />
                                    <span className="text-center underline">Hari Kerja</span>
                                    <span className="text-center underline">Jam Kerja</span>
                                    <span className="text-center underline">Lembur</span>
                                </div>

                                <div className="space-y-1">
                                    {/* Tetap */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Tetap</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.hari_tetap} onChange={(v) => updateNum('hari_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.jam_tetap} onChange={(v) => updateNum('jam_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.lembur_tetap} onChange={(v) => updateNum('lembur_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>

                                    {/* Tetap (Shift) */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Tetap (Shift)</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.hari_tetap_shift} onChange={(v) => updateNum('hari_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.jam_tetap_shift} onChange={(v) => updateNum('jam_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.lembur_tetap_shift} onChange={(v) => updateNum('lembur_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>

                                    {/* Tidak Tetap */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Tidak Tetap</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.hari_tidak_tetap} onChange={(v) => updateNum('hari_tidak_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.jam_tidak_tetap} onChange={(v) => updateNum('jam_tidak_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.lembur_tidak_tetap} onChange={(v) => updateNum('lembur_tidak_tetap', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>

                                    {/* Tidak Tetap (Shift) */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Tidak Tetap (Shift)</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.hari_tidak_tetap_shift} onChange={(v) => updateNum('hari_tidak_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.jam_tidak_tetap_shift} onChange={(v) => updateNum('jam_tidak_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.lembur_tidak_tetap_shift} onChange={(v) => updateNum('lembur_tidak_tetap_shift', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* C. Jumlah Jam Kerja Orang */}
                        <div>
                            <div className="font-bold text-sm">C. Jumlah Jam Kerja Orang:</div>
                            <div className="mt-2 space-y-1 pl-4">
                                <div className="grid grid-cols-[180px_20px_120px_60px] items-center">
                                    <span>Tetap</span>
                                    <span>:</span>
                                    <span className="text-right font-mono pr-2">{formatDec(calcs.jamTetap)}</span>
                                    <span className="pl-2">Jam</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_120px_60px] items-center">
                                    <span>Tetap (Shift)</span>
                                    <span>:</span>
                                    <span className="text-right font-mono pr-2">{calcs.jamTetapShift > 0 ? formatDec(calcs.jamTetapShift) : '-'}</span>
                                    <span className="pl-2">Jam</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_120px_60px] items-center">
                                    <span>Tidak Tetap</span>
                                    <span>:</span>
                                    <span className="text-right font-mono pr-2">{formatDec(calcs.jamTidakTetap)}</span>
                                    <span className="pl-2">Jam</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_120px_60px] items-center">
                                    <span>Tidak Tetap (Shift)</span>
                                    <span>:</span>
                                    <span className="text-right font-mono pr-2">{formatDec(calcs.jamTidakTetapShift)}</span>
                                    <span className="pl-2">Jam</span>
                                </div>
                                <div className="grid grid-cols-[180px_20px_120px_60px] items-center border-t border-black pt-1 font-bold dark:border-border">
                                    <span>Jumlah Jam</span>
                                    <span>:</span>
                                    <span className="text-right font-mono font-bold pr-2">{formatDec(calcs.totalJamOrang)}</span>
                                    <span className="pl-2">Jam</span>
                                </div>
                            </div>
                        </div>

                        {/* D. Lembur */}
                        <div className="flex items-center pl-4 font-bold">
                            <span className="w-[180px] text-sm">D. Lembur</span>
                            <span className="w-[20px]">:</span>
                            <span className="w-[120px] text-right font-mono pr-2">{formatDec(calcs.totalLembur)}</span>
                            <span className="w-[60px] pl-2 font-normal">Jam</span>
                        </div>

                        {/* E. Jumlah Absensi Karyawan */}
                        <div>
                            <div className="flex items-center pl-4 font-bold">
                                <span className="w-[180px] text-sm">E. Jumlah Absensi Karyawan</span>
                                <span className="w-[20px]">:</span>
                                <span className="w-[120px] text-right font-mono pr-2">{formatDec(calcs.totalAbsensiJam)}</span>
                                <span className="w-[60px] pl-2 font-normal">Jam</span>
                            </div>

                            <div className="mt-2 pl-4">
                                <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center font-semibold text-muted-foreground pb-1">
                                    <span />
                                    <span />
                                    <span className="text-center underline">Jumlah</span>
                                    <span className="text-center underline">Jumlah</span>
                                    <span className="text-center underline">Jumlah</span>
                                </div>

                                <div className="space-y-1">
                                    {/* Cuti */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Cuti</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.cuti_orang} onChange={(v) => updateNum('cuti_orang', v)} canWrite={can_write} className="w-16" />
                                            <span>Orang</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.cuti_hari} onChange={(v) => updateNum('cuti_hari', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.cuti_jam} onChange={(v) => updateNum('cuti_jam', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>

                                    {/* Ijin */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Ijin</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.ijin_orang} onChange={(v) => updateNum('ijin_orang', v)} canWrite={can_write} className="w-16" />
                                            <span>Orang</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.ijin_hari} onChange={(v) => updateNum('ijin_hari', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.ijin_jam} onChange={(v) => updateNum('ijin_jam', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>

                                    {/* Sakit */}
                                    <div className="grid grid-cols-[180px_20px_120px_120px_120px] items-center">
                                        <span>Sakit</span>
                                        <span>:</span>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.sakit_orang} onChange={(v) => updateNum('sakit_orang', v)} canWrite={can_write} className="w-16" />
                                            <span>Orang</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.sakit_hari} onChange={(v) => updateNum('sakit_hari', v)} canWrite={can_write} className="w-16" />
                                            <span>Hari</span>
                                        </div>
                                        <div className="flex items-center justify-center gap-1">
                                            <NumInput value={form.sakit_jam} onChange={(v) => updateNum('sakit_jam', v)} canWrite={can_write} className="w-16" />
                                            <span>Jam</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* F. Jumlah Jam Kerja Seluruh Karyawan (Boxed) */}
                        <div className="border-t-2 border-black pt-4 dark:border-border">
                            <div className="font-bold text-sm">F. Jumlah Jam Kerja Seluruh Karyawan:</div>
                            <div className="mt-2 flex flex-wrap items-center justify-between gap-3 pl-4">
                                <span className="italic text-muted-foreground">
                                    = Jumlah Jam Kerja Karyawan + Lembur – Jumlah Absensi Karyawan =
                                </span>
                                <div className="flex items-center gap-2">
                                    <div className="jk-box flex items-center justify-center border-2 border-black bg-muted/20 px-6 py-2 text-base font-extrabold font-mono dark:border-border">
                                        {formatDec(calcs.totalJamSeluruh)}
                                    </div>
                                    <span className="font-bold text-sm">Jam</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
        <Card className="flex flex-row items-center gap-3 p-3 py-3 shadow-none">
            <div className={`flex size-8 items-center justify-center rounded-lg ${tone}`}>{icon}</div>
            <div className="min-w-0">
                <div className="text-[10px] font-medium uppercase text-muted-foreground">{label}</div>
                <div className="truncate text-sm font-bold text-foreground">{value}</div>
            </div>
        </Card>
    );
}

function NumInput({
    value,
    onChange,
    canWrite,
    className = 'w-20',
}: {
    value: number | string;
    onChange: (val: string) => void;
    canWrite: boolean;
    className?: string;
}) {
    if (!canWrite) {
        return <span className={`text-right font-mono text-xs ${className}`}>{value}</span>;
    }

    return (
        <input
            type="number"
            min={0}
            step="any"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={`h-7 border border-input rounded px-1.5 text-right font-mono text-xs focus:outline-none focus:ring-1 focus:ring-primary ${className}`}
        />
    );
}

PengusahaanJamKerjaPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pengusahaan K3', href: k3Pengusahaan.index('input') },
        { title: 'Hari & Jam Kerja Karyawan', href: k3PengusahaanJamKerja.index() },
    ],
};
