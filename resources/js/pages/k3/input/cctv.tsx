import { Head, router } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { K3InputExportButtons } from '@/components/k3/input-export-buttons';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import cctv from '@/routes/k3/input/cctv';
import type { IdName } from '@/types';

type Row = {
    id: number | null;
    tanggal: string;
    no_cctv: string;
    titik_lokasi: string;
    status: string;
    foto_terpasang: string;
    foto_tampilan: string;
    keterangan: string;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    rows: Array<Partial<Row> & { id: number }>;
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const empty = (): Row => ({ id: null, tanggal: '', no_cctv: '', titik_lokasi: '', status: 'on', foto_terpasang: '', foto_tampilan: '', keterangan: '' });
const toRow = (r: Partial<Row> & { id: number }): Row => ({ ...empty(), ...r, id: r.id, tanggal: (r.tanggal as string) ?? '', status: (r.status as string) || 'on' });

export default function CctvInput({ unit, filters, rows: initial, options, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(initial.map(toRow));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [sig, setSig] = useState(signature);
    if (sig !== signature) { setSig(signature); setRows(initial.map(toRow)); setDirty(false); }

    const visit = (patch: Partial<Props['filters']>) => router.get(cctv.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    const update = (i: number, k: keyof Row, v: string) => { setRows((p) => p.map((r, idx) => (idx === i ? { ...r, [k]: v } : r))); setDirty(true); };
    const addRow = () => { setRows((p) => [...p, empty()]); setDirty(true); };
    const removeRow = (i: number) => { setRows((p) => p.filter((_, idx) => idx !== i)); setDirty(true); };
    const save = () => { setSaving(true); router.post(cctv.store().url, { unit_id: filters.unit_id, month: filters.month, year: filters.year, rows }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) }); };

    const cell = (i: number, k: keyof Row, type = 'text', placeholder = '') =>
        can_write ? <Input type={type} value={rows[i][k] as string} onChange={(e) => update(i, k, e.target.value)} className="h-8 text-xs" placeholder={placeholder} /> : <span className="text-xs">{(rows[i][k] as string) || '-'}</span>;

    const cols = can_write ? 9 : 8;

    return (
        <>
            <Head title="Daftar CCTV" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Daftar CCTV"
                    description="Pendataan titik CCTV terpasang beserta status (on/off/rusak) dan dokumentasi foto."
                    actions={(
                        <div className="flex flex-wrap gap-2">
                            <K3InputExportButtons input="cctv" query={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }} />
                            {can_write && (
                                <>
                                    <Button variant="outline" onClick={addRow} className="gap-1.5"><Plus className="size-4" />Tambah Baris</Button>
                                    <Button onClick={save} disabled={saving || !dirty} className="gap-2"><Save className="size-4" />{saving ? 'Menyimpan…' : 'Simpan'}</Button>
                                </>
                            )}
                        </div>
                    )}
                />
                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((l, i) => ({ value: String(i + 1), label: l }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    {dirty && <span className="pb-1 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                </div>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full border-collapse text-xs">
                        <thead className="bg-muted/50">
                            <tr className="[&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:font-bold [&>th]:text-foreground">
                                <th className="w-10">No</th><th className="w-32">Tanggal</th><th>No CCTV</th><th className="text-left">Titik Lokasi CCTV</th><th>Status</th><th>Foto Terpasang (URL)</th><th>Foto Tampilan (URL)</th><th className="text-left">Keterangan</th>{can_write && <th className="w-12">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr><td colSpan={cols} className="border border-border p-4 text-center text-muted-foreground">Belum ada data. Klik “Tambah Baris”.</td></tr>
                            ) : rows.map((_, i) => (
                                <tr key={i} className="[&>td]:border [&>td]:border-border [&>td]:p-1 hover:bg-muted/20">
                                    <td className="text-center">{i + 1}</td>
                                    <td>{cell(i, 'tanggal', 'date')}</td>
                                    <td className="min-w-[80px]">{cell(i, 'no_cctv')}</td>
                                    <td className="min-w-[200px]">{cell(i, 'titik_lokasi')}</td>
                                    <td className="min-w-[90px]">
                                        {can_write ? (
                                            <select value={rows[i].status} onChange={(e) => update(i, 'status', e.target.value)} className="h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                                <option value="on">ON</option>
                                                <option value="off">OFF</option>
                                                <option value="rusak">RUSAK</option>
                                            </select>
                                        ) : (
                                            <span className="text-xs uppercase">{rows[i].status}</span>
                                        )}
                                    </td>
                                    <td className="min-w-[140px]">{cell(i, 'foto_terpasang', 'text', 'https://…')}</td>
                                    <td className="min-w-[140px]">{cell(i, 'foto_tampilan', 'text', 'https://…')}</td>
                                    <td className="min-w-[160px]">{cell(i, 'keterangan')}</td>
                                    {can_write && <td className="text-center"><Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(i)}><Trash2 className="size-4" /></Button></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

CctvInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Daftar CCTV', href: cctv.index() },
    ],
};
