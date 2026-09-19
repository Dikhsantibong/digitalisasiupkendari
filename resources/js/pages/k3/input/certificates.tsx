import { Head, router } from '@inertiajs/react';
import { Plus, Printer, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { K3InputExportButtons } from '@/components/k3/input-export-buttons';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import certificate from '@/routes/k3/input/certificate';
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
    filters: { unit_id: number };
    rows: RowData[];
    options: { units: IdName[]; categories: Code[] };
    can_write: boolean;
};

type TextField =
    | 'jenis' | 'kapasitas' | 'lokasi' | 'merk_manufacture' | 'no_seri' | 'regulasi'
    | 'ijin_awal_nomor' | 'ijin_awal_tanggal' | 'uji_terakhir_nomor' | 'uji_terakhir_tanggal'
    | 'uji_ulang_tanggal' | 'batasan_uji' | 'keterangan';

const blank = (key: number): Row => ({
    _key: key, category_code: null, jenis: '', kapasitas: null, lokasi: null,
    merk_manufacture: null, no_seri: null, regulasi: null, ijin_awal_nomor: null,
    ijin_awal_tanggal: null, uji_terakhir_nomor: null, uji_terakhir_tanggal: null,
    uji_ulang_tanggal: null, batasan_uji: null, masa_berlaku_tahun: null, keterangan: null,
});

const hydrate = (rows: RowData[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; }
    .no-print { display: none !important; }
    .cert-table { font-size: 7px !important; }
    .cert-table th, .cert-table td { border: 1px solid #000 !important; }
    .cert-th { background-color: #dbe9f7 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

type StatusKey = 'AKTIF' | 'EXPIRED' | 'BELUM';

const statusOf = (r: Row): StatusKey => {
    const certified = r.ijin_awal_tanggal || r.uji_terakhir_tanggal || r.uji_ulang_tanggal;
    if (!certified) return 'BELUM';
    if (r.uji_ulang_tanggal) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return new Date(r.uji_ulang_tanggal) >= today ? 'AKTIF' : 'EXPIRED';
    }
    return 'AKTIF';
};

const STATUS_CLASS: Record<StatusKey, string> = {
    AKTIF: 'text-emerald-700 dark:text-emerald-400',
    EXPIRED: 'text-rose-700 dark:text-rose-400',
    BELUM: 'text-amber-700 dark:text-amber-400',
};

export default function CertificateInput({ filters, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = String(filters.unit_id);
    const [sig, setSig] = useState(signature);
    if (sig !== signature) { setSig(signature); setRows(hydrate(initialRows)); setNextKey(initialRows.length); setDirty(false); }

    const update = (key: number, field: TextField, value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: value } : r)));
        setDirty(true);
    };
    const updateCategory = (key: number, value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, category_code: value || null } : r)));
        setDirty(true);
    };
    const addRow = () => { setRows((p) => [...p, blank(nextKey)]); setNextKey((k) => k + 1); setDirty(true); };
    const removeRow = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };

    const save = () => {
        setSaving(true);
        router.post(
            certificate.store().url,
            {
                unit_id: filters.unit_id,
                rows: rows.map((r) => ({
                    category_code: r.category_code, jenis: r.jenis, kapasitas: r.kapasitas, lokasi: r.lokasi,
                    merk_manufacture: r.merk_manufacture, no_seri: r.no_seri, regulasi: r.regulasi,
                    ijin_awal_nomor: r.ijin_awal_nomor, ijin_awal_tanggal: r.ijin_awal_tanggal || null,
                    uji_terakhir_nomor: r.uji_terakhir_nomor, uji_terakhir_tanggal: r.uji_terakhir_tanggal || null,
                    uji_ulang_tanggal: r.uji_ulang_tanggal || null, batasan_uji: r.batasan_uji,
                    masa_berlaku_tahun: r.masa_berlaku_tahun === '' || r.masa_berlaku_tahun === null ? null : Number(r.masa_berlaku_tahun),
                    keterangan: r.keterangan,
                })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const txt = (r: Row, field: TextField, type = 'text') =>
        can_write ? (
            <input type={type} value={(r[field] as string) ?? ''} onChange={(e) => update(r._key, field, e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" />
        ) : (
            <span className="text-xs">{(r[field] as string) || '-'}</span>
        );

    const thBase = 'cert-th border border-black bg-[#dbe9f7] p-1.5 text-center text-[11px] font-bold text-slate-900';

    return (
        <>
            <Head title="Daftar Monitoring Sertifikasi Peralatan" />
            <style>{PRINT_CSS}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Daftar Monitoring Sertifikasi Peralatan"
                    description="Data sertifikat & pengujian alat (crane, tangki timbun, instalasi penyalur petir, dll.). Status AKTIF/EXPIRED/BELUM dihitung otomatis dari tanggal pengujian ulang."
                    actions={
                        <div className="no-print flex flex-wrap gap-2">
                            {can_write && <Button variant="secondary" onClick={addRow} className="gap-1.5"><Plus className="size-4" />Tambah Baris</Button>}
                            {can_write && <Button onClick={save} disabled={saving || !dirty} className="gap-2"><Save className="size-4" />{saving ? 'Menyimpan…' : 'Simpan'}</Button>}
                            <K3InputExportButtons input="certificates" query={{ unit_id: filters.unit_id }} />
                            <Button variant="outline" onClick={() => window.print()} className="gap-1.5"><Printer className="size-4" />Cetak</Button>
                        </div>
                    }
                />

                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => router.get(certificate.index().url, { unit_id: Number(value) }, { preserveState: true, replace: true })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    {dirty && !saving && <span className="pb-1 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                </div>

                <div className="print-container rounded-md border border-border bg-card">
                    <div className="border-b border-border p-3">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img src="/logo/sidebar-logo.png" alt="PLN" className="max-h-11 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold text-foreground uppercase">JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</div>
                                <div className="text-[11px] font-semibold text-foreground uppercase">LAPORAN PROJECT</div>
                                <div className="text-[11px] font-bold text-foreground uppercase">DAFTAR MONITORING SERTIFIKASI PERALATAN</div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img src="/logo/mkp.jpg" alt="MKP" className="max-h-11 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                    <table className="cert-table w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th rowSpan={2} className={`${thBase} w-8`}>NO</th>
                                <th rowSpan={2} className={`${thBase} min-w-40 text-left`}>NAMA KATEGORI ALAT</th>
                                <th rowSpan={2} className={`${thBase} min-w-32`}>JENIS</th>
                                <th rowSpan={2} className={thBase}>KAPASITAS</th>
                                <th rowSpan={2} className={thBase}>LOKASI</th>
                                <th rowSpan={2} className={thBase}>MERK MANUFACTURE</th>
                                <th rowSpan={2} className={thBase}>NO. SERI ALAT</th>
                                <th rowSpan={2} className={`${thBase} min-w-40`}>REGULASI PERATURAN</th>
                                <th colSpan={2} className={thBase}>IJIN PEMAKAIAN AWAL</th>
                                <th colSpan={2} className={thBase}>PEMERIKSAAN PENGUJIAN TERAKHIR</th>
                                <th rowSpan={2} className={thBase}>PENGUJIAN ULANG (TANGGAL)</th>
                                <th rowSpan={2} className={thBase}>STATUS (AKTIF/ EXPIRED/ BELUM)</th>
                                <th rowSpan={2} className={thBase}>BATASAN UJI</th>
                                <th rowSpan={2} className={`${thBase} min-w-32`}>KETERANGAN</th>
                                {can_write && <th rowSpan={2} className={`${thBase} no-print w-10`}>Aksi</th>}
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
                                <tr><td colSpan={can_write ? 17 : 16} className="border border-black p-4 text-center text-muted-foreground">Belum ada data. Klik “Tambah Baris”.</td></tr>
                            ) : rows.map((r, i) => {
                                const st = statusOf(r);
                                return (
                                    <tr key={r._key} className="[&>td]:border [&>td]:border-black [&>td]:p-1 hover:bg-muted/20">
                                        <td className="text-center">{i + 1}</td>
                                        <td className="min-w-40">
                                            {can_write ? (
                                                <select value={r.category_code ?? ''} onChange={(e) => updateCategory(r._key, e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none">
                                                    <option value="">— pilih —</option>
                                                    {options.categories.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                                                </select>
                                            ) : (
                                                <span className="text-xs">{options.categories.find((c) => c.code === r.category_code)?.name ?? r.category_code ?? '-'}</span>
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
                                        <td className={`text-center text-xs font-bold ${STATUS_CLASS[st]}`}>{st}</td>
                                        <td className="min-w-24">{txt(r, 'batasan_uji')}</td>
                                        <td className="min-w-32">{txt(r, 'keterangan')}</td>
                                        {can_write && <td className="no-print text-center"><Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(r._key)}><Trash2 className="size-4" /></Button></td>}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    </div>
                </div>

                {/* NB legend */}
                <div className="rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                    <p className="font-semibold text-foreground">NB : Keterangan Status</p>
                    <ul className="mt-1 space-y-0.5">
                        <li><span className="font-bold text-emerald-700 dark:text-emerald-400">AKTIF</span> — Sudah tersertifikasi dan sertifikat masih berlaku.</li>
                        <li><span className="font-bold text-rose-700 dark:text-rose-400">EXPIRED</span> — Sudah tersertifikasi namun sertifikat sudah tidak berlaku.</li>
                        <li><span className="font-bold text-amber-700 dark:text-amber-400">BELUM</span> — Peralatan belum sama sekali dilakukan sertifikasi.</li>
                    </ul>
                    {options.categories.length > 0 && (
                        <p className="mt-2">Kategori alat diambil dari master. Tanggal format YYYY-MM-DD; status dihitung dari tanggal Pengujian Ulang.</p>
                    )}
                </div>
            </div>
        </>
    );
}

CertificateInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sertifikasi Peralatan', href: certificate.index() },
    ],
};
