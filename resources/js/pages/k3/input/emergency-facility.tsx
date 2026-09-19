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
import emergencyFacility from '@/routes/k3/input/emergency-facility';
import type { IdName } from '@/types';

type ServerRow = {
    id: number | null;
    grup: string;
    nama_peralatan: string;
    jml_total: number;
    jml_ready: number;
    jml_not_ready: number;
    lokasi: string;
    kendala: string;
    tindak_lanjut: string;
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

export default function EmergencyFacilityInput({ unit, filters, groups, rows: initial, options, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initial));
    const [nextKey, setNextKey] = useState(initial.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [sig, setSig] = useState(signature);
    if (sig !== signature) { setSig(signature); setRows(hydrate(initial)); setNextKey(initial.length); setDirty(false); }

    // Groups present in data but not in the default order still get rendered.
    const orderedGroups = [...groups, ...rows.map((r) => r.grup).filter((g) => !groups.includes(g))].filter((g, i, a) => a.indexOf(g) === i);

    const visit = (patch: Partial<Props['filters']>) => router.get(emergencyFacility.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    const updateNum = (key: number, field: 'jml_total' | 'jml_ready', value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: Math.max(0, parseInt(value, 10) || 0) } : r)));
        setDirty(true);
    };
    const updateText = (key: number, field: 'nama_peralatan' | 'lokasi' | 'kendala' | 'tindak_lanjut', value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: value } : r)));
        setDirty(true);
    };
    const addItem = (grup: string) => {
        setRows((p) => [...p, { _key: nextKey, id: null, grup, nama_peralatan: '', jml_total: 0, jml_ready: 0, jml_not_ready: 0, lokasi: '', kendala: '', tindak_lanjut: '' }]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };
    const remove = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };

    const save = () => {
        setSaving(true);
        const ordered = orderedGroups.flatMap((g) => rows.filter((r) => r.grup === g));
        router.post(
            emergencyFacility.store().url,
            {
                unit_id: filters.unit_id, month: filters.month, year: filters.year,
                rows: ordered.map((r) => ({
                    id: r.id, grup: r.grup, nama_peralatan: r.nama_peralatan,
                    jml_total: r.jml_total, jml_ready: r.jml_ready,
                    jml_not_ready: Math.max(0, r.jml_total - r.jml_ready),
                    lokasi: r.lokasi, kendala: r.kendala, tindak_lanjut: r.tindak_lanjut,
                })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const totalCols = can_write ? 9 : 8;

    return (
        <>
            <Head title="Pemeriksaan Emergency Facility" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Pemeriksaan Emergency Facility"
                    description="Matriks kesiapan peralatan darurat per kelompok. % Kesiapan dihitung otomatis dari Jml Ready ÷ Jml Total."
                    actions={(
                        <div className="flex flex-wrap gap-2">
                            <K3InputExportButtons input="emergency-facility" query={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }} />
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
                                <th className="text-left">Nama Peralatan</th>
                                <th className="w-16">Jml Total</th>
                                <th className="w-16">Jml Ready</th>
                                <th className="w-16">Not Ready</th>
                                <th className="w-16">% Kesiapan</th>
                                <th className="text-left">Lokasi Penempatan</th>
                                <th className="text-left">Kendala</th>
                                <th className="text-left">Tindak Lanjut</th>
                                {can_write && <th className="w-12">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {orderedGroups.map((grup) => {
                                const items = rows.filter((r) => r.grup === grup);
                                return (
                                    <Fragment key={grup}>
                                        <tr className="bg-muted/70">
                                            <td colSpan={totalCols} className="border border-border p-1.5 text-left text-xs font-bold tracking-wide text-foreground uppercase">{grup}</td>
                                        </tr>
                                        {items.map((row, li) => {
                                            const notReady = Math.max(0, row.jml_total - row.jml_ready);
                                            const pct = row.jml_total > 0 ? Math.round((row.jml_ready / row.jml_total) * 100) : 0;
                                            return (
                                                <tr key={row._key} className="[&>td]:border [&>td]:border-border [&>td]:p-1 hover:bg-muted/20">
                                                    <td className="text-center">{li + 1}</td>
                                                    <td className="min-w-[220px]">
                                                        {can_write ? <Input value={row.nama_peralatan} onChange={(e) => updateText(row._key, 'nama_peralatan', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.nama_peralatan}</span>}
                                                    </td>
                                                    <td className="text-center">
                                                        {can_write ? <Input type="number" min="0" value={row.jml_total || ''} onChange={(e) => updateNum(row._key, 'jml_total', e.target.value)} className="h-8 w-14 text-center text-xs" placeholder="0" /> : row.jml_total}
                                                    </td>
                                                    <td className="text-center">
                                                        {can_write ? <Input type="number" min="0" value={row.jml_ready || ''} onChange={(e) => updateNum(row._key, 'jml_ready', e.target.value)} className="h-8 w-14 text-center text-xs" placeholder="0" /> : row.jml_ready}
                                                    </td>
                                                    <td className="text-center font-medium">{notReady}</td>
                                                    <td className={`text-center font-bold ${pct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{row.jml_total > 0 ? `${pct}%` : '-'}</td>
                                                    <td className="min-w-[160px]">{can_write ? <Input value={row.lokasi} onChange={(e) => updateText(row._key, 'lokasi', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.lokasi || '-'}</span>}</td>
                                                    <td className="min-w-[140px]">{can_write ? <Input value={row.kendala} onChange={(e) => updateText(row._key, 'kendala', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.kendala || '-'}</span>}</td>
                                                    <td className="min-w-[140px]">{can_write ? <Input value={row.tindak_lanjut} onChange={(e) => updateText(row._key, 'tindak_lanjut', e.target.value)} className="h-8 text-xs" /> : <span className="text-xs">{row.tindak_lanjut || '-'}</span>}</td>
                                                    {can_write && <td className="text-center"><Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => remove(row._key)}><Trash2 className="size-4" /></Button></td>}
                                                </tr>
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
            </div>
        </>
    );
}

EmergencyFacilityInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Pemeriksaan Emergency Facility', href: emergencyFacility.index() },
    ],
};
