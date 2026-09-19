import { Head, router } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { K3InputExportButtons } from '@/components/k3/input-export-buttons';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import apdInventory from '@/routes/k3/input/apd-inventory';
import type { IdName } from '@/types';

type ServerRow = {
    id: number | null;
    grup: string;
    subkategori: string;
    nama: string;
    jumlah: number;
    satuan: string;
    lokasi: string;
    keterangan: string;
    foto: string;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    groups: string[];
    rows: ServerRow[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

export default function ApdInventoryInput({ unit, filters, groups, rows: initial, options, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initial));
    const [nextKey, setNextKey] = useState(initial.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [sig, setSig] = useState(signature);
    if (sig !== signature) { setSig(signature); setRows(hydrate(initial)); setNextKey(initial.length); setDirty(false); }

    const orderedGroups = [...groups, ...rows.map((r) => r.grup).filter((g) => !groups.includes(g))].filter((g, i, a) => a.indexOf(g) === i);

    const visit = (patch: Partial<Props['filters']>) => router.get(apdInventory.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    const updateText = (key: number, field: 'nama' | 'subkategori' | 'satuan' | 'lokasi' | 'keterangan' | 'foto', value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: value } : r)));
        setDirty(true);
    };
    const updateNum = (key: number, value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, jumlah: Math.max(0, parseInt(value, 10) || 0) } : r)));
        setDirty(true);
    };
    const addItem = (grup: string) => {
        setRows((p) => [...p, { _key: nextKey, id: null, grup, subkategori: '', nama: '', jumlah: 0, satuan: '', lokasi: '', keterangan: '', foto: '' }]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };
    const remove = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };

    const save = () => {
        setSaving(true);
        const ordered = orderedGroups.flatMap((g) => rows.filter((r) => r.grup === g));
        router.post(
            apdInventory.store().url,
            {
                unit_id: filters.unit_id, month: filters.month, year: filters.year,
                rows: ordered.map((r) => ({ id: r.id, grup: r.grup, subkategori: r.subkategori, nama: r.nama, jumlah: r.jumlah, satuan: r.satuan, lokasi: r.lokasi, keterangan: r.keterangan, foto: r.foto })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const totalCols = can_write ? 8 : 7;

    return (
        <>
            <Head title="Daftar Inventaris Alat Pelindung Diri" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Daftar Inventaris Alat Pelindung Diri (APD)"
                    description="Pendataan stok APD per kelompok & subkategori: jumlah, satuan, lokasi penyimpanan, dan dokumentasi foto."
                    actions={(
                        <div className="flex flex-wrap gap-2">
                            <K3InputExportButtons input="apd-inventory" query={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }} />
                            {can_write && (
                                <>
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
                                <th className="w-10">No</th>
                                <th className="text-left">Alat Pelindung Diri</th>
                                <th className="w-20">Jumlah</th>
                                <th className="w-24">Satuan</th>
                                <th className="text-left">Lokasi</th>
                                <th className="text-left">Keterangan</th>
                                <th className="text-left">Foto (URL)</th>
                                {can_write && <th className="w-12">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {orderedGroups.map((grup) => {
                                const items = rows.filter((r) => r.grup === grup);
                                let lastSub = '__none__';
                                let n = 0;
                                return (
                                    <Fragment key={grup}>
                                        <tr className="bg-muted/70">
                                            <td colSpan={totalCols} className="border border-border p-1.5 text-left text-xs font-bold tracking-wide text-foreground uppercase">{grup}</td>
                                        </tr>
                                        {items.map((row) => {
                                            const showSub = row.subkategori && row.subkategori !== lastSub;
                                            if (row.subkategori !== lastSub) lastSub = row.subkategori;
                                            n += 1;
                                            return (
                                                <Fragment key={row._key}>
                                                    {showSub && (
                                                        <tr className="bg-muted/30">
                                                            <td className="border border-border p-1 text-center text-[11px] font-semibold text-muted-foreground" />
                                                            <td colSpan={totalCols - 1} className="border border-border p-1 text-left text-[11px] font-semibold text-foreground">{row.subkategori}</td>
                                                        </tr>
                                                    )}
                                                    <tr className="[&>td]:border [&>td]:border-border [&>td]:p-1 hover:bg-muted/20">
                                                        <td className="text-center">{n}</td>
                                                        <td className="min-w-[220px] pl-3">
                                                            {can_write ? <Input value={row.nama} onChange={(e) => updateText(row._key, 'nama', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.nama}</span>}
                                                        </td>
                                                        <td className="text-center">
                                                            {can_write ? <Input type="number" min="0" value={row.jumlah || ''} onChange={(e) => updateNum(row._key, e.target.value)} className="h-8 w-16 text-center text-xs" placeholder="0" /> : row.jumlah}
                                                        </td>
                                                        <td className="min-w-[80px]">{can_write ? <Input value={row.satuan} onChange={(e) => updateText(row._key, 'satuan', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.satuan}</span>}</td>
                                                        <td className="min-w-[140px]">{can_write ? <Input value={row.lokasi} onChange={(e) => updateText(row._key, 'lokasi', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.lokasi || '-'}</span>}</td>
                                                        <td className="min-w-[140px]">{can_write ? <Input value={row.keterangan} onChange={(e) => updateText(row._key, 'keterangan', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.keterangan || '-'}</span>}</td>
                                                        <td className="min-w-[140px]">{can_write ? <Input value={row.foto} onChange={(e) => updateText(row._key, 'foto', e.target.value)} className="h-8 text-xs" placeholder="https://…" /> : <span className="text-xs">{row.foto || '-'}</span>}</td>
                                                        {can_write && <td className="text-center"><Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => remove(row._key)}><Trash2 className="size-4" /></Button></td>}
                                                    </tr>
                                                </Fragment>
                                            );
                                        })}
                                        {can_write && (
                                            <tr>
                                                <td colSpan={totalCols} className="border border-border p-1.5">
                                                    <Button variant="outline" size="sm" onClick={() => addItem(grup)} className="gap-1.5 text-xs"><Plus className="size-3.5" />Tambah Item — {grup}</Button>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <p className="text-[12px] text-muted-foreground">* Untuk item dengan subkategori, isi kolom subkategori lewat data awal; item tambahan baru masuk tanpa subkategori (dapat dikelompokkan manual).</p>
            </div>
        </>
    );
}

ApdInventoryInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Daftar Inventaris APD', href: apdInventory.index() },
    ],
};
