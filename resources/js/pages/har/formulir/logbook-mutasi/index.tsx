import { Head, router } from '@inertiajs/react';
import { BookOpen, Download, Plus, Save, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import logbookRoutes from '@/routes/har/formulir/logbook-mutasi';
import type { IdName } from '@/types';

type Row = Record<string, string | null>;
type ListKey = 'absensi' | 'apd' | 'rutin' | 'non_rutin' | 'kondisi_k3';
type Lists = Record<ListKey, Row[]>;
type Logbook = Lists & { id: number; tanggal: string };

type Props = {
    unit: IdName;
    filters: { unit_id: number; month: number; year: number; tanggal: string | null };
    options: { units: IdName[]; years: number[] };
    logbooks: { id: number; tanggal: string; hadir: number; pekerjaan: number }[];
    logbook: Logbook | null;
    template: Lists;
    can_write: boolean;
};

type Section = { key: ListKey; title: string; columns: { key: string; label: string; width?: string }[] };

const SECTIONS: Section[] = [
    {
        key: 'absensi',
        title: 'A. Absensi',
        columns: [
            { key: 'nama', label: 'Nama' },
            { key: 'jabatan', label: 'Jabatan', width: 'w-48' },
            { key: 'keterangan', label: 'Keterangan', width: 'w-40' },
            { key: 'paraf', label: 'Paraf', width: 'w-24' },
        ],
    },
    { key: 'apd', title: 'B. Kesiapan APD', columns: [{ key: 'item', label: 'Item APD' }, { key: 'keterangan', label: 'Keterangan' }] },
    { key: 'rutin', title: 'C. Job Harian Rutin', columns: [{ key: 'uraian', label: 'Uraian Pekerjaan' }, { key: 'keterangan', label: 'Keterangan' }] },
    { key: 'non_rutin', title: 'D. Job Harian Non Rutin', columns: [{ key: 'uraian', label: 'Uraian Pekerjaan' }, { key: 'keterangan', label: 'Keterangan' }] },
    { key: 'kondisi_k3', title: 'E. Kondisi K3 (Unsafe Action & Unsafe Condition)', columns: [{ key: 'uraian', label: 'Uraian' }, { key: 'keterangan', label: 'Keterangan' }] },
];

const cellInput = 'h-8 rounded-none border-0 bg-transparent px-2 text-xs shadow-none focus-visible:ring-1';
const blankRow = (section: Section): Row => Object.fromEntries(section.columns.map((c) => [c.key, null]));
const formatDate = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);

    return `${d} ${OPERASI_MONTHS[m - 1]} ${y}`;
};
const pickLists = (source: Lists): Lists => ({
    absensi: source.absensi,
    apd: source.apd,
    rutin: source.rutin,
    non_rutin: source.non_rutin,
    kondisi_k3: source.kondisi_k3,
});

/**
 * Formulir Logbook Mutasi Harian Tim Pemeliharaan: satu logbook per tanggal
 * (absensi, APD, job rutin/non rutin, kondisi K3). Masuk Laporan Pemeliharaan.
 */
export default function HarLogbookMutasiPage({ unit, filters, options, logbooks, logbook, template, can_write }: Props) {
    const compact = useCompactLayout();
    const { can } = usePermissions();
    const today = new Date();
    const sameMonth = today.getFullYear() === filters.year && today.getMonth() + 1 === filters.month;
    const defaultDate = `${filters.year}-${String(filters.month).padStart(2, '0')}-${String(sameMonth ? today.getDate() : 1).padStart(2, '0')}`;
    const [tanggal, setTanggal] = useState(logbook?.tanggal ?? defaultDate);
    const [lists, setLists] = useState<Lists>(() => pickLists(logbook ?? template));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const go = (query: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(logbookRoutes.index().url, { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...query });
    };

    const update = (key: ListKey, rows: Row[]) => {
        setLists((current) => ({ ...current, [key]: rows }));
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            logbookRoutes.store().url,
            { unit_id: filters.unit_id, tanggal, ...lists },
            { onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const remove = () => {
        if (logbook && window.confirm(`Hapus logbook mutasi ${formatDate(logbook.tanggal)}?`)) {
            router.delete(logbookRoutes.destroy(logbook.id).url);
        }
    };

    return (
        <>
            <Head title={`Logbook Mutasi Harian - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Logbook Mutasi Harian Tim Pemeliharaan"
                    description={`Absensi, kesiapan APD, job harian rutin & non rutin, serta kondisi K3 — ${unit.name} · ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}.`}
                    actions={
                        can('har.input.view') && (
                            <Button variant="outline" onClick={() => router.get(harFormulir.index().url, { unit_id: filters.unit_id })}>
                                Kembali
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => go({ unit_id: Number(v), tanggal: null })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => go({ month: Number(v), tanggal: null })} options={OPERASI_MONTHS.map((label, i) => ({ value: String(i + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => go({ year: Number(v), tanggal: null })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                </div>

                <div className="flex flex-col items-start gap-4 lg:flex-row">
                    <aside className="w-full shrink-0 rounded-md border border-border bg-card lg:w-72" aria-label="Daftar logbook">
                        <div className="flex items-center justify-between border-b border-border p-3">
                            <span className="text-sm font-semibold">Logbook {OPERASI_MONTHS[filters.month - 1]}</span>
                            {can_write && (
                                <Button size="sm" variant="outline" onClick={() => go({ tanggal: null })} className="h-7 gap-1 text-xs">
                                    <Plus className="size-3.5" />
                                    Baru
                                </Button>
                            )}
                        </div>
                        {logbooks.length === 0 ? (
                            <p className="p-3 text-xs text-muted-foreground">Belum ada logbook tersimpan bulan ini.</p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {logbooks.map((item) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => go({ tanggal: item.tanggal })}
                                            className={`w-full px-3 py-2 text-left text-xs transition-colors hover:bg-muted/40 ${logbook?.id === item.id ? 'bg-primary/10 font-semibold text-primary' : ''}`}
                                        >
                                            <div>{formatDate(item.tanggal)}</div>
                                            <div className="text-muted-foreground">{item.hadir} hadir · {item.pekerjaan} pekerjaan</div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </aside>

                    <section className="flex w-full min-w-0 flex-1 flex-col gap-4 rounded-md border border-border bg-card p-4" aria-label="Editor logbook">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <BookOpen className="size-4 text-primary" />
                                {logbook ? `Logbook ${formatDate(logbook.tanggal)}` : 'Logbook baru'}
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {logbook && (
                                    <Button variant="outline" size="sm" onClick={() => window.open(logbookRoutes.pdf(logbook.id).url, '_blank')} className="gap-1.5">
                                        <Download className="size-4 text-rose-600" />
                                        PDF
                                    </Button>
                                )}
                                {logbook && can_write && (
                                    <Button variant="outline" size="sm" onClick={remove} className="gap-1.5 text-destructive">
                                        <Trash2 className="size-4" />
                                        Hapus
                                    </Button>
                                )}
                                {can_write && (
                                    <Button size="sm" onClick={save} disabled={saving || (!dirty && logbook !== null)} className="gap-1.5">
                                        <Save className="size-4" />
                                        {saving ? 'Menyimpan…' : 'Simpan'}
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="flex max-w-xs flex-col gap-1.5">
                            <Label htmlFor="logbook-tanggal">Hari / Tanggal</Label>
                            <Input
                                id="logbook-tanggal"
                                type="date"
                                value={tanggal}
                                onChange={(e) => {
                                    setTanggal(e.target.value);
                                    setDirty(true);
                                }}
                                disabled={!can_write || logbook !== null}
                            />
                        </div>

                        {SECTIONS.map((section) => (
                            <div key={section.key} className="flex flex-col gap-2">
                                <h3 className="text-xs font-semibold tracking-wide text-primary uppercase">{section.title}</h3>
                                {compact ? (
<MobileRowEditor
    rows={lists[section.key]}
    canWrite={can_write}
    title={(row, index) => row[section.columns[0].key] || `Baris ${index + 1}`}
    subtitle={(row) => section.columns.slice(1).map((column) => row[column.key]).filter(Boolean).join(' · ')}
    onChange={(index, key, value) => update(section.key, lists[section.key].map((r, i) => (i === index ? { ...r, [key]: value === '' ? null : String(value) } : r)))}
    onRemove={(index) => update(section.key, lists[section.key].filter((_, i) => i !== index))}
    fields={section.columns.map((column) => ({ key: column.key, label: column.label }))}
/>
                                ) : (
                                <div className="overflow-x-auto rounded-md border border-border">
                                    <table className="w-full min-w-[560px] border-collapse text-xs">
                                        <thead className="bg-muted/60 text-center font-semibold">
                                            <tr>
                                                <th className="w-10 border border-border p-1.5">No</th>
                                                {section.columns.map((column) => (
                                                    <th key={column.key} className={`border border-border p-1.5 ${column.width ?? ''}`}>{column.label}</th>
                                                ))}
                                                {can_write && <th className="w-9 border border-border" aria-label="Aksi" />}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lists[section.key].length === 0 && (
                                                <tr>
                                                    <td colSpan={section.columns.length + 2} className="border border-border p-2 text-center text-muted-foreground">
                                                        Belum ada baris.
                                                    </td>
                                                </tr>
                                            )}
                                            {lists[section.key].map((row, index) => (
                                                <tr key={index}>
                                                    <td className="border border-border text-center">{index + 1}</td>
                                                    {section.columns.map((column) => (
                                                        <td key={column.key} className="border border-border p-0">
                                                            <Input
                                                                value={row[column.key] ?? ''}
                                                                onChange={(e) => update(section.key, lists[section.key].map((r, i) => (i === index ? { ...r, [column.key]: e.target.value === '' ? null : e.target.value } : r)))}
                                                                className={cellInput}
                                                                disabled={!can_write}
                                                                aria-label={`${column.label} ${section.title} baris ${index + 1}`}
                                                            />
                                                        </td>
                                                    ))}
                                                    {can_write && (
                                                        <td className="border border-border text-center">
                                                            <button type="button" onClick={() => update(section.key, lists[section.key].filter((_, i) => i !== index))} className="text-muted-foreground hover:text-destructive" aria-label="Hapus baris">
                                                                <X className="size-3.5" />
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                )}
                                {can_write && (
                                    <Button variant="outline" size="sm" onClick={() => update(section.key, [...lists[section.key], blankRow(section)])} className="w-fit gap-1 text-xs">
                                        <Plus className="size-3.5" />
                                        Tambah Baris
                                    </Button>
                                )}
                            </div>
                        ))}

                        <p className="text-xs text-muted-foreground">Baris tanpa nama/uraian tidak disimpan. Menyimpan tanggal yang sudah ada akan memperbarui logbook tanggal tersebut.</p>
                    </section>
                </div>
            </div>
        </>
    );
}

HarLogbookMutasiPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Logbook Mutasi Harian', href: logbookRoutes.index() },
    ],
};
