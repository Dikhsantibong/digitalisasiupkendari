import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanEvaluasiPengujian from '@/routes/k3/pengusahaan/evaluasi-pengujian';
import type { IdName } from '@/types';

export type RowData = {
    id?: number | null;
    no_urut: string | null;
    nama_kategori_alat: string;
    jenis: string;
    kapasitas: string | null;
    temuan_sertifikat: string | null;
    progres_bulan: number[];
    keterangan: string | null;
    sort_order?: number;
};

type Row = RowData & { _key: number };

type MetaData = {
    nomor_dokumen: string;
    tanggal_terbit: string;
    revisi: string;
    halaman: string;
    catatan: string;
};

type HistoryItem = {
    year: number;
    items: number;
    updated_at: string | null;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    rows: RowData[];
    meta: MetaData;
    has_saved: boolean;
    history: HistoryItem[];
    can_write: boolean;
};

const MONTH_NAMES = [
    'JAN',
    'FEB',
    'MAR',
    'APR',
    'MEI',
    'JUN',
    'JUL',
    'AGS',
    'SEP',
    'OKT',
    'NOV',
    'DES',
];

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; }
    .no-print { display: none !important; }
    .evaluasi-table { width: 100% !important; border-collapse: collapse !important; font-size: 8px !important; }
    .evaluasi-table th, .evaluasi-table td { border: 1px solid #000 !important; padding: 2.5px 3px !important; vertical-align: middle !important; }
    .evaluasi-th { background-color: #f1f5f9 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; font-weight: bold !important; text-align: center !important; }
    .evaluasi-month-active { background-color: #fef08a !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .evaluasi-header-box { border: 1px solid #000 !important; }
}
`;

export default function PengusahaanEvaluasiPengujianPage(props: Props) {
    const { unit, filters, options, rows: initialRows, meta: initialMeta, can_write } = props;

    const [keyCounter, setKeyCounter] = useState<number>(() => initialRows.length + 10);
    const [rows, setRows] = useState<Row[]>(() =>
        initialRows.map((r, i) => ({ ...r, _key: i + 1 })),
    );
    const [meta, setMeta] = useState<MetaData>(() => initialMeta);
    const [dirty, setDirty] = useState<boolean>(false);
    const [saving, setSaving] = useState<boolean>(false);

    const navigatePeriod = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah halaman?')) {
            return;
        }

        router.get(
            k3PengusahaanEvaluasiPengujian.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    const updateRow = <K extends keyof RowData>(key: number, field: K, val: RowData[K]) => {
        setRows((prev) =>
            prev.map((r) => (r._key === key ? { ...r, [field]: val } : r)),
        );
        setDirty(true);
    };

    const toggleMonth = (key: number, monthNum: number) => {
        if (!can_write) {
            return;
        }

        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== key) {
                    return r;
                }

                const current = r.progres_bulan ?? [];
                const next = current.includes(monthNum)
                    ? current.filter((m) => m !== monthNum)
                    : [...current, monthNum].sort((a, b) => a - b);

                return { ...r, progres_bulan: next };
            }),
        );
        setDirty(true);
    };

    const updateMeta = <K extends keyof MetaData>(field: K, val: MetaData[K]) => {
        setMeta((prev) => ({ ...prev, [field]: val }));
        setDirty(true);
    };

    const addRow = () => {
        const nextKey = keyCounter + 1;

        setKeyCounter(nextKey);
        const lastRow = rows[rows.length - 1];
        const newRow: Row = {
            id: null,
            _key: nextKey,
            no_urut: lastRow ? String(Number(lastRow.no_urut || '0') + 1) : '1',
            nama_kategori_alat: '',
            jenis: '',
            kapasitas: '',
            temuan_sertifikat: '',
            progres_bulan: [],
            keterangan: '',
            sort_order: rows.length,
        };

        setRows((prev) => [...prev, newRow]);
        setDirty(true);
        toast.info('Baris evaluasi baru ditambahkan.');
    };

    const deleteRow = (key: number) => {
        if (rows.length <= 1) {
            toast.error('Minimal harus ada satu baris data.');

            return;
        }

        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
        toast.success('Baris berhasil dihapus.');
    };

    const resetChanges = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setRows(initialRows.map((r, i) => ({ ...r, _key: i + 1 })));
            setMeta(initialMeta);
            setDirty(false);
            toast.info('Perubahan dibatalkan.');
        }
    };

    const save = () => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            k3PengusahaanEvaluasiPengujian.store().url,
            {
                unit_id: filters.unit_id,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    no_urut: r.no_urut || String(idx + 1),
                    nama_kategori_alat: r.nama_kategori_alat.trim() || `Kategori ${idx + 1}`,
                    jenis: r.jenis.trim() || `Alat ${idx + 1}`,
                    kapasitas: r.kapasitas || null,
                    temuan_sertifikat: r.temuan_sertifikat || null,
                    progres_bulan: r.progres_bulan ?? [],
                    keterangan: r.keterangan || null,
                    sort_order: idx,
                })),
                meta,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const stats = useMemo(() => {
        let totalTemuan = 0;
        let closedTemuan = 0;
        let onProgress = 0;

        rows.forEach((r) => {
            const hasTemuan = Boolean(r.temuan_sertifikat && r.temuan_sertifikat.trim() !== '');
            const ket = (r.keterangan ?? '').toLowerCase();
            const isClosed = ket.includes('close') || ket.includes('selesai');

            if (hasTemuan) {
                totalTemuan++;

                if (isClosed) {
                    closedTemuan++;
                } else if ((r.progres_bulan ?? []).length > 0) {
                    onProgress++;
                }
            }
        });

        return {
            totalPeralatan: rows.length,
            totalTemuan,
            closedTemuan,
            onProgress,
        };
    }, [rows]);

    return (
        <>
            <Head title={`Formulir Evaluasi Hasil Pengujian Peralatan — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(k3Pengusahaan.index('formulir').url)}
                            title="Kembali ke Formulir Pengusahaan K3"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Formulir Evaluasi Hasil Pengujian Peralatan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="secondary" className="text-xs">
                                    Tahun {filters.year}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Evaluasi temuan dalam sertifikat pengujian peralatan dan pemantauan progres tindak lanjut tahunan.
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
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={addRow}
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={save}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                            title="Cetak Dokumen Lanskap"
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
                        label="Tahun Periode"
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
                        icon={<FileSpreadsheet className="size-4" />}
                        tone="bg-primary/10 text-primary"
                        label="Total Peralatan"
                        value={`${stats.totalPeralatan} Alat`}
                    />
                    <StatTile
                        icon={<AlertCircle className="size-4" />}
                        tone="bg-amber-500/10 text-amber-600"
                        label="Temuan Sertifikat"
                        value={`${stats.totalTemuan} Temuan`}
                    />
                    <StatTile
                        icon={<Clock className="size-4" />}
                        tone="bg-blue-500/10 text-blue-600"
                        label="Dalam Progres"
                        value={`${stats.onProgress} Alat`}
                    />
                    <StatTile
                        icon={<CheckCircle2 className="size-4" />}
                        tone="bg-emerald-500/10 text-emerald-600"
                        label="Close Temuan"
                        value={`${stats.closedTemuan} Selesai`}
                    />
                </div>

                {/* Printable container */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* KOP Dokumen Pengusahaan K3 */}
                    <div className="border-b border-border p-3">
                        <table className="evaluasi-header-box w-full border-collapse border border-black dark:border-border">
                            <tbody>
                                <tr>
                                    {/* Logo Kiri PLN */}
                                    <td className="w-52 border-r border-black p-2 text-center align-middle dark:border-border">
                                        <img
                                            src="/logo/sidebar-logo.png"
                                            alt="PLN Nusantara Power"
                                            className="mx-auto max-h-12 object-contain"
                                            onError={(e) => {
                                                (e.target as HTMLElement).style.display = 'none';
                                            }}
                                        />
                                    </td>

                                    {/* Judul Dokumen Tengah */}
                                    <td className="p-2 text-center align-middle">
                                        <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                            PT PLN NUSANTARA POWER
                                        </div>
                                        <div className="text-xs font-bold text-foreground uppercase">
                                            UNIT LAYANAN PUSAT LISTRIK TENAGA DIESEL {unit.name.toUpperCase()}
                                        </div>
                                        <div className="mt-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            FORMULIR
                                        </div>
                                        <div className="text-sm font-extrabold text-foreground uppercase">
                                            EVALUASI HASIL PENGUJIAN PERALATAN
                                        </div>
                                    </td>

                                    {/* Metadata Dokumen Kanan */}
                                    <td className="w-64 border-l border-black p-1.5 text-left text-[11px] align-middle dark:border-border">
                                        <table className="w-full text-xs">
                                            <tbody>
                                                <tr>
                                                    <td className="w-28 py-0.5 font-semibold text-muted-foreground">Nomor Dokumen</td>
                                                    <td className="py-0.5">:</td>
                                                    <td className="py-0.5 pl-1 font-mono">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={meta.nomor_dokumen}
                                                                onChange={(e) => updateMeta('nomor_dokumen', e.target.value)}
                                                                placeholder="FMG-08-2.3.60"
                                                                className="w-full bg-transparent text-xs focus:outline-none"
                                                            />
                                                        ) : (
                                                            meta.nomor_dokumen || '-'
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="py-0.5 font-semibold text-muted-foreground">Tanggal Terbit</td>
                                                    <td className="py-0.5">:</td>
                                                    <td className="py-0.5 pl-1 font-mono">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={meta.tanggal_terbit}
                                                                onChange={(e) => updateMeta('tanggal_terbit', e.target.value)}
                                                                placeholder="21 Mei 2018"
                                                                className="w-full bg-transparent text-xs focus:outline-none"
                                                            />
                                                        ) : (
                                                            meta.tanggal_terbit || '-'
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
                                                                value={meta.revisi}
                                                                onChange={(e) => updateMeta('revisi', e.target.value)}
                                                                placeholder="00"
                                                                className="w-full bg-transparent text-xs focus:outline-none"
                                                            />
                                                        ) : (
                                                            meta.revisi || '-'
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
                                                                value={meta.halaman}
                                                                onChange={(e) => updateMeta('halaman', e.target.value)}
                                                                placeholder="1 dari 1"
                                                                className="w-full bg-transparent text-xs focus:outline-none"
                                                            />
                                                        ) : (
                                                            meta.halaman || '-'
                                                        )}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Evaluasi Table */}
                    <div className="overflow-x-auto">
                        <table className="evaluasi-table w-full min-w-[1400px] border-collapse text-xs">
                            <thead>
                                <tr className="evaluasi-th bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:font-bold">
                                    <th rowSpan={2} className="w-10">No</th>
                                    <th rowSpan={2} className="min-w-[170px] text-left">Nama Kategori Alat</th>
                                    <th rowSpan={2} className="min-w-[190px] text-left">Jenis</th>
                                    <th rowSpan={2} className="min-w-[110px] text-left">Kapasitas</th>
                                    <th rowSpan={2} className="min-w-[220px] text-left">Temuan yang Ada di Dalam Sertifikat</th>
                                    <th colSpan={12} className="bg-amber-50/80 text-amber-950 dark:bg-amber-950/40 dark:text-amber-200">
                                        Progres Tindak Lanjut {filters.year}
                                    </th>
                                    <th rowSpan={2} className="min-w-[190px] text-left">Keterangan</th>
                                    {can_write && <th rowSpan={2} className="no-print w-12 text-center">Aksi</th>}
                                </tr>
                                <tr className="evaluasi-th bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-1 [&>th]:text-center [&>th]:text-[11px] [&>th]:font-semibold">
                                    {MONTH_NAMES.map((name) => (
                                        <th key={name} className="w-9 min-w-[36px] bg-amber-50/40 text-center dark:bg-amber-950/20">
                                            {name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, idx) => (
                                    <tr
                                        key={row._key}
                                        className="transition-colors hover:bg-muted/40 [&>td]:border [&>td]:border-border [&>td]:p-1.5 [&>td]:align-middle"
                                    >
                                        <td className="text-center font-medium text-muted-foreground">
                                            {can_write ? (
                                                <input
                                                    type="text"
                                                    value={row.no_urut ?? String(idx + 1)}
                                                    onChange={(e) => updateRow(row._key, 'no_urut', e.target.value)}
                                                    className="w-8 text-center text-xs focus:outline-none"
                                                />
                                            ) : (
                                                row.no_urut ?? idx + 1
                                            )}
                                        </td>
                                        <td>
                                            <CellInput
                                                value={row.nama_kategori_alat}
                                                onChange={(v) => updateRow(row._key, 'nama_kategori_alat', v)}
                                                placeholder="Kategori alat..."
                                                canWrite={can_write}
                                                className="font-semibold"
                                            />
                                        </td>
                                        <td>
                                            <CellInput
                                                value={row.jenis}
                                                onChange={(v) => updateRow(row._key, 'jenis', v)}
                                                placeholder="Jenis alat..."
                                                canWrite={can_write}
                                            />
                                        </td>
                                        <td>
                                            <CellInput
                                                value={row.kapasitas ?? ''}
                                                onChange={(v) => updateRow(row._key, 'kapasitas', v)}
                                                placeholder="-"
                                                canWrite={can_write}
                                            />
                                        </td>
                                        <td>
                                            <CellInput
                                                value={row.temuan_sertifikat ?? ''}
                                                onChange={(v) => updateRow(row._key, 'temuan_sertifikat', v)}
                                                placeholder="Temuan di sertifikat..."
                                                canWrite={can_write}
                                            />
                                        </td>

                                        {/* 12 Months interactive cells */}
                                        {MONTH_NAMES.map((_, mIdx) => {
                                            const monthNum = mIdx + 1;
                                            const isActive = (row.progres_bulan ?? []).includes(monthNum);

                                            return (
                                                <td
                                                    key={monthNum}
                                                    onClick={() => toggleMonth(row._key, monthNum)}
                                                    className={`w-9 text-center select-none transition-colors ${
                                                        isActive
                                                            ? 'evaluasi-month-active bg-amber-200 text-amber-900 font-bold dark:bg-amber-500/30 dark:text-amber-200'
                                                            : 'bg-background hover:bg-muted/50'
                                                    } ${can_write ? 'cursor-pointer' : 'cursor-default'}`}
                                                    title={
                                                        can_write
                                                            ? `Klik untuk ${isActive ? 'menghapus tanda' : 'menandai'} progres bulan ${MONTH_NAMES[mIdx]}`
                                                            : undefined
                                                    }
                                                >
                                                    {isActive ? '✓' : ''}
                                                </td>
                                            );
                                        })}

                                        <td>
                                            <CellInput
                                                value={row.keterangan ?? ''}
                                                onChange={(v) => updateRow(row._key, 'keterangan', v)}
                                                placeholder="Keterangan tindak lanjut..."
                                                canWrite={can_write}
                                            />
                                        </td>

                                        {can_write && (
                                            <td className="no-print text-center">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => deleteRow(row._key)}
                                                    className="size-7 text-muted-foreground hover:text-destructive"
                                                    title="Hapus baris"
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

                    {/* Catatan & Keterangan Tambahan */}
                    <div className="grid gap-3 border-t border-border p-3 md:grid-cols-2">
                        <div className="space-y-1.5 rounded-md border border-border bg-muted/20 p-3 text-xs">
                            <span className="font-semibold text-foreground">Petunjuk Pengisian:</span>
                            <ul className="list-inside list-disc space-y-1 text-muted-foreground">
                                <li>
                                    Isi kolom <strong>Temuan yang Ada di Dalam Sertifikat</strong> sesuai laporan hasil riksa uji.
                                </li>
                                <li>
                                    Klik sel bulan <strong>JAN s.d. DES</strong> untuk menandai periode tindak lanjut perbaikan alat yang sedang berjalan.
                                </li>
                                <li>
                                    Tuliskan status penyelesaian (contoh: <em>&quot;Mei {filters.year} Close Temuan&quot;</em>) pada kolom <strong>Keterangan</strong>.
                                </li>
                            </ul>
                        </div>

                        <div className="space-y-1.5 text-xs">
                            <Label htmlFor="catatan" className="font-semibold">
                                Catatan Khusus &amp; Rekomendasi:
                            </Label>
                            {can_write ? (
                                <Textarea
                                    id="catatan"
                                    value={meta.catatan}
                                    onChange={(e) => updateMeta('catatan', e.target.value)}
                                    placeholder="Tuliskan catatan evaluasi atau rekomendasi tindak lanjut pemeliharaan/K3..."
                                    rows={3}
                                    className="text-xs"
                                />
                            ) : (
                                <p className="italic text-muted-foreground">
                                    {meta.catatan || 'Tidak ada catatan tambahan.'}
                                </p>
                            )}
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

function CellInput({
    value,
    onChange,
    placeholder,
    canWrite,
    className = '',
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    canWrite: boolean;
    className?: string;
}) {
    if (!canWrite) {
        return <div className={`px-1 py-0.5 text-xs ${className}`}>{value || '-'}</div>;
    }

    return (
        <Input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            className={`h-7 text-xs ${className}`}
        />
    );
}

PengusahaanEvaluasiPengujianPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan K3', href: k3Pengusahaan.index('formulir') },
        { title: 'Evaluasi Hasil Pengujian Peralatan', href: k3PengusahaanEvaluasiPengujian.index() },
    ],
};
