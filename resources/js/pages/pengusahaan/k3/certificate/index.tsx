import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Plus, Printer, RotateCcw, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanCertificate from '@/routes/k3/pengusahaan/certificate';
import type { IdName } from '@/types';

type Code = { code: string; name: string };

type RowData = {
    category_code: string | null;
    jenis: string | null;
    kapasitas: string | null;
    lokasi: string | null;
    merk_manufacture: string | null;
    no_seri: string | null;
    regulasi: string | null;
    ijin_awal_nomor: string | null;
    ijin_awal_tanggal: string | null;
    uji_terakhir_nomor: string | null;
    uji_terakhir_tanggal: string | null;
    uji_ulang_tanggal: string | null;
    batasan_uji: string | null;
    masa_berlaku_tahun: string | number | null;
    keterangan: string | null;
};

type Row = RowData & { _key: number };

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number };
    rows: RowData[];
    options: { units: IdName[]; categories: Code[] };
    can_write: boolean;
};

type TextField =
    | 'jenis'
    | 'kapasitas'
    | 'lokasi'
    | 'merk_manufacture'
    | 'no_seri'
    | 'regulasi'
    | 'ijin_awal_nomor'
    | 'ijin_awal_tanggal'
    | 'uji_terakhir_nomor'
    | 'uji_terakhir_tanggal'
    | 'uji_ulang_tanggal'
    | 'batasan_uji'
    | 'keterangan';

const blank = (key: number): Row => ({
    _key: key,
    category_code: null,
    jenis: '',
    kapasitas: null,
    lokasi: null,
    merk_manufacture: null,
    no_seri: null,
    regulasi: null,
    ijin_awal_nomor: null,
    ijin_awal_tanggal: null,
    uji_terakhir_nomor: null,
    uji_terakhir_tanggal: null,
    uji_ulang_tanggal: null,
    batasan_uji: null,
    masa_berlaku_tahun: null,
    keterangan: null,
});

const hydrate = (rows: RowData[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; }
    .no-print { display: none !important; }
    .cert-table { width: 100% !important; border-collapse: collapse !important; font-size: 7px !important; }
    .cert-table th, .cert-table td { border: 1px solid #000 !important; padding: 1.5px !important; vertical-align: middle !important; }
    .cert-th { background-color: #dbe9f7 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

type StatusKey = 'AKTIF' | 'EXPIRED' | 'BELUM';

const statusOf = (r: Row): StatusKey => {
    const certified = r.ijin_awal_tanggal || r.uji_terakhir_tanggal || r.uji_ulang_tanggal;

    if (!certified) {
        return 'BELUM';
    }

    if (r.uji_ulang_tanggal) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return new Date(r.uji_ulang_tanggal) >= today ? 'AKTIF' : 'EXPIRED';
    }

    return 'AKTIF';
};

const STATUS_CLASS: Record<StatusKey, string> = {
    AKTIF: 'text-emerald-700 dark:text-emerald-400 font-bold',
    EXPIRED: 'text-rose-700 dark:text-rose-400 font-bold',
    BELUM: 'text-amber-700 dark:text-amber-400 font-bold',
};

export default function PengusahaanCertificateIndex({
    unit,
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = String(filters.unit_id);
    const [sig, setSig] = useState(signature);

    if (sig !== signature) {
        setSig(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
        setDirty(false);
    }

    const update = (key: number, field: TextField, value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: value } : r)));
        setDirty(true);
    };

    const updateCategory = (key: number, value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, category_code: value || null } : r)));
        setDirty(true);
    };

    const addRow = () => {
        setRows((p) => [...p, blank(nextKey)]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const removeRow = (key: number) => {
        setRows((p) => p.filter((r) => r._key !== key));
        setDirty(true);
    };

    const handleReset = () => {
        setRows(hydrate(initialRows));
        setDirty(false);
    };

    const save = () => {
        if (!can_write) {
            return;
        }

        setSaving(true);
        router.post(
            k3PengusahaanCertificate.store().url,
            {
                unit_id: filters.unit_id,
                rows: rows.map((r) => ({
                    category_code: r.category_code,
                    jenis: r.jenis,
                    kapasitas: r.kapasitas,
                    lokasi: r.lokasi,
                    merk_manufacture: r.merk_manufacture,
                    no_seri: r.no_seri,
                    regulasi: r.regulasi,
                    ijin_awal_nomor: r.ijin_awal_nomor,
                    ijin_awal_tanggal: r.ijin_awal_tanggal || null,
                    uji_terakhir_nomor: r.uji_terakhir_nomor,
                    uji_terakhir_tanggal: r.uji_terakhir_tanggal || null,
                    uji_ulang_tanggal: r.uji_ulang_tanggal || null,
                    batasan_uji: r.batasan_uji,
                    masa_berlaku_tahun:
                        r.masa_berlaku_tahun === '' || r.masa_berlaku_tahun === null
                            ? null
                            : Number(r.masa_berlaku_tahun),
                    keterangan: r.keterangan,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const txt = (r: Row, field: TextField, type = 'text') =>
        can_write ? (
            <input
                type={type}
                value={(r[field] as string) ?? ''}
                onChange={(e) => update(r._key, field, e.target.value)}
                className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
            />
        ) : (
            <span className="text-xs">{(r[field] as string) || '-'}</span>
        );

    const thBase =
        'cert-th border border-black bg-[#dbe9f7] p-1 text-center text-[10px] font-bold text-slate-900';

    return (
        <>
            <Head title={`Daftar Monitoring Sertifikasi Peralatan — ${unit.name}`} />
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
                                    Daftar Monitoring Sertifikasi Peralatan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Data sertifikat &amp; pengujian alat (crane, tangki timbun, instalasi penyalur petir, dll.). Status AKTIF/EXPIRED/BELUM dihitung otomatis dari tanggal pengujian ulang.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
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

                {/* Filter Unit */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            router.get(
                                k3PengusahaanCertificate.index().url,
                                { unit_id: Number(value) },
                                { preserveState: true, replace: true },
                            )
                        }
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Printable container */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* KOP Dokumen Pengusahaan K3 */}
                    <div className="border-b border-border p-3">
                        <table className="w-full border-collapse border border-black dark:border-border">
                            <tbody>
                                <tr>
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
                                    <td className="p-2 text-center align-middle">
                                        <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                            PT. PLN NUSANTARA POWER
                                        </div>
                                        <div className="text-xs font-bold text-foreground uppercase">
                                            UNIT PEMBANGKITAN KENDARI
                                        </div>
                                        <div className="text-[11px] font-semibold text-foreground uppercase">
                                            UNIT LAYANAN PUSAT LISTRIK TENAGA DIESEL {unit.name.toUpperCase()}
                                        </div>
                                    </td>
                                    <td className="w-40 border-l border-black p-2 text-center align-middle dark:border-border">
                                        <img
                                            src="/logo/k3.png"
                                            alt="Logo K3"
                                            className="mx-auto max-h-12 object-contain"
                                            onError={(e) => {
                                                (e.target as HTMLElement).style.display = 'none';
                                            }}
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {/* Title Bar */}
                        <div className="mt-2 border border-black p-1.5 text-center dark:border-border">
                            <h2 className="text-xs font-bold tracking-wider text-foreground uppercase md:text-sm">
                                DAFTAR MONITORING SERTIFIKASI PERALATAN
                            </h2>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="cert-table w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th rowSpan={2} className={`${thBase} w-8`}>
                                        NO
                                    </th>
                                    <th rowSpan={2} className={`${thBase} min-w-40 text-left`}>
                                        NAMA KATEGORI ALAT
                                    </th>
                                    <th rowSpan={2} className={`${thBase} min-w-32`}>
                                        JENIS
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        KAPASITAS
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        LOKASI
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        MERK MANUFACTURE
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        NO. SERI ALAT
                                    </th>
                                    <th rowSpan={2} className={`${thBase} min-w-40`}>
                                        REGULASI PERATURAN
                                    </th>
                                    <th colSpan={2} className={thBase}>
                                        IJIN PEMAKAIAN AWAL
                                    </th>
                                    <th colSpan={2} className={thBase}>
                                        PEMERIKSAAN PENGUJIAN TERAKHIR
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        PENGUJIAN ULANG (TANGGAL)
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        STATUS (AKTIF/ EXPIRED/ BELUM)
                                    </th>
                                    <th rowSpan={2} className={thBase}>
                                        BATASAN UJI
                                    </th>
                                    <th rowSpan={2} className={`${thBase} min-w-32`}>
                                        KETERANGAN
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className={`${thBase} no-print w-10`}>
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    <th className={thBase}>NOMOR</th>
                                    <th className={thBase}>TANGGAL</th>
                                    <th className={thBase}>NOMOR</th>
                                    <th className={thBase}>TANGGAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={can_write ? 17 : 16}
                                            className="border border-black p-4 text-center text-muted-foreground"
                                        >
                                            Belum ada data. Klik &quot;Tambah Baris&quot; untuk menambahkan.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((r, i) => {
                                        const st = statusOf(r);

                                        return (
                                            <tr
                                                key={r._key}
                                                className="transition-colors hover:bg-muted/10 [&>td]:border [&>td]:border-black [&>td]:p-1"
                                            >
                                                <td className="text-center font-bold">{i + 1}</td>
                                                <td className="min-w-40">
                                                    {can_write ? (
                                                        <select
                                                            value={r.category_code ?? ''}
                                                            onChange={(e) => updateCategory(r._key, e.target.value)}
                                                            className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                        >
                                                            <option value="">— pilih —</option>
                                                            {options.categories.map((c) => (
                                                                <option key={c.code} value={c.code}>
                                                                    {c.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <span className="text-xs">
                                                            {options.categories.find((c) => c.code === r.category_code)
                                                                ?.name ??
                                                                r.category_code ??
                                                                '-'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="min-w-32">{txt(r, 'jenis')}</td>
                                                <td className="min-w-24">{txt(r, 'kapasitas')}</td>
                                                <td className="min-w-24">{txt(r, 'lokasi')}</td>
                                                <td className="min-w-28">{txt(r, 'merk_manufacture')}</td>
                                                <td className="min-w-28">{txt(r, 'no_seri')}</td>
                                                <td className="min-w-40">{txt(r, 'regulasi')}</td>
                                                <td className="min-w-24">{txt(r, 'ijin_awal_nomor')}</td>
                                                <td className="min-w-32">{txt(r, 'ijin_awal_tanggal', 'date')}</td>
                                                <td className="min-w-24">{txt(r, 'uji_terakhir_nomor')}</td>
                                                <td className="min-w-32">{txt(r, 'uji_terakhir_tanggal', 'date')}</td>
                                                <td className="min-w-32">{txt(r, 'uji_ulang_tanggal', 'date')}</td>
                                                <td className={`text-center text-xs ${STATUS_CLASS[st]}`}>{st}</td>
                                                <td className="min-w-24">{txt(r, 'batasan_uji')}</td>
                                                <td className="min-w-32">{txt(r, 'keterangan')}</td>
                                                {can_write && (
                                                    <td className="no-print text-center">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-muted-foreground hover:text-destructive"
                                                            onClick={() => removeRow(r._key)}
                                                            title="Hapus baris ini"
                                                        >
                                                            <Trash2 className="size-4" />
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
                </div>

                {/* NB legend */}
                <div className="rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                    <p className="font-semibold text-foreground">NB : Keterangan Status</p>
                    <ul className="mt-1 space-y-0.5">
                        <li>
                            <span className="font-bold text-emerald-700 dark:text-emerald-400">AKTIF</span> — Sudah tersertifikasi dan sertifikat masih berlaku.
                        </li>
                        <li>
                            <span className="font-bold text-rose-700 dark:text-rose-400">EXPIRED</span> — Sudah tersertifikasi namun sertifikat sudah tidak berlaku.
                        </li>
                        <li>
                            <span className="font-bold text-amber-700 dark:text-amber-400">BELUM</span> — Peralatan belum sama sekali dilakukan sertifikasi.
                        </li>
                    </ul>
                    {options.categories.length > 0 && (
                        <p className="mt-2 text-xs">
                            Kategori alat diambil dari master kategori. Tanggal berformat YYYY-MM-DD; status dihitung otomatis dari tanggal Pengujian Ulang.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

PengusahaanCertificateIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Pengusahaan.index('input').url },
        { title: 'Daftar Sertifikasi Peralatan', href: k3PengusahaanCertificate.index().url },
    ],
};
