import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Copy, ListPlus, Plus, Printer, RotateCcw, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout, useInMobileShell } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import laporanKegiatan from '@/routes/har/pengusahaan/laporan-kegiatan';
import type { IdName } from '@/types';

type Category = 'mesin' | 'listrik';

type ServerGroup = { mesin: string; judul: string; jenis_har: string; uraian: string[] };

type TextField = 'hasil_pekerjaan' | 'material_nama' | 'material_no_part' | 'jumlah' | 'data' | 'no_lh05' | 'no_sr_ba' | 'no_tug9' | 'no_wo_spki';

type ServerEntry = { id?: number; activity_date: string; groups: ServerGroup[] } & Record<TextField, string>;

/** On screen the activity lines are edited as one text, a line each. */
type Group = { mesin: string; judul: string; jenis_har: string; uraian: string };

type Entry = { key: number; activity_date: string; groups: Group[] } & Record<TextField, string>;

type Props = {
    filters: { unit_id: number; month: number; year: number };
    reports: Record<Category, ServerEntry[]>;
    machines: string[];
    document: { number: string; revision: string; date: string };
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const TABS: { key: Category; label: string; title: string; srLabel: string; woLabel: string }[] = [
    { key: 'mesin', label: 'Pemeliharaan Mesin', title: 'LAPORAN KEGIATAN PEMELIHARAAN MESIN PEMBANGKIT', srLabel: 'NO. SR', woLabel: 'NO. WO' },
    { key: 'listrik', label: 'Pemeliharaan Listrik & Kontrol', title: 'LAPORAN KEGIATAN PEMELIHARAAN LISTRIK & KONTROL PEMBANGKIT', srLabel: 'NO. BA', woLabel: 'NO. SPKI' },
];

const JENIS_HAR = ['PREVENTIV', 'P0', 'P1', 'P2', 'P3', 'P4', 'P5', 'K', '-'];

const JUDUL = ['PREVENTIV MAINTENANCE P1', 'PREVENTIV MAINTENANCE P2', 'PEMELIHARAAN P3', 'PEMELIHARAAN P4', 'PEMELIHARAAN P5', 'CORRECTIVE MAINTENANCE', 'UMUM'];

const KETERANGAN_FIELDS: { key: TextField; label: (tab: (typeof TABS)[number]) => string; wide?: boolean }[] = [
    { key: 'data', label: () => 'DATA' },
    { key: 'no_lh05', label: () => 'NO. LH 05' },
    { key: 'no_sr_ba', label: (tab) => tab.srLabel },
    { key: 'no_tug9', label: () => 'NO. TUG 9' },
    { key: 'no_wo_spki', label: (tab) => tab.woLabel, wide: true },
];

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
}
`;

const cell = 'border border-black align-middle dark:border-neutral-500';
const head = 'border border-black px-1 py-1 text-center text-[10px] font-bold dark:border-neutral-500';
const field = 'w-full bg-transparent text-[11px] focus:bg-background focus:outline-none print:hidden';

let nextKey = 1;

const blankGroup = (): Group => ({ mesin: '', judul: '', jenis_har: '', uraian: '' });

const blankEntry = (): Entry => ({
    key: nextKey++,
    activity_date: '',
    groups: [blankGroup()],
    hasil_pekerjaan: 'Baik',
    material_nama: '',
    material_no_part: '',
    jumlah: '',
    data: '',
    no_lh05: '',
    no_sr_ba: '',
    no_tug9: '',
    no_wo_spki: '',
});

const toEntries = (entries: ServerEntry[]): Entry[] =>
    entries.map((entry) => ({
        ...blankEntry(),
        ...entry,
        key: nextKey++,
        groups: (entry.groups.length ? entry.groups : [{ mesin: '', judul: '', jenis_har: '', uraian: [] }]).map((g) => ({
            mesin: g.mesin ?? '',
            judul: g.judul ?? '',
            jenis_har: g.jenis_har ?? '',
            uraian: (g.uraian ?? []).join('\n'),
        })),
    }));

const lines = (text: string): string[] =>
    text
        .split('\n')
        .map((line) => line.replace(/^\s*-\s*/, '').trim())
        .filter(Boolean);

const printDate = (iso: string): string => {
    if (!iso) {
        return '';
    }

    const [y, m, d] = iso.split('-').map(Number);

    return `${String(d).padStart(2, '0')}-${OPERASI_MONTHS[m - 1] ?? ''}-${String(y).slice(-2)}`;
};

/**
 * Akses 2 — Pengusahaan: Laporan Kegiatan Pemeliharaan Mesin Pembangkit and
 * Listrik & Kontrol Pembangkit. Each numbered entry has one or more machine
 * groups (title, jenis HAR and activity lines) and shared result, material and
 * keterangan columns — laid out like the paper form, printed A4 landscape.
 */
export default function LaporanKegiatanPage({ filters, reports, machines, document, options, can_write }: Props) {
    const compact = useCompactLayout();
    const inShell = useInMobileShell();
    const [category, setCategory] = useState<Category>('mesin');
    const [entries, setEntries] = useState<Record<Category, Entry[]>>(() => ({ mesin: toEntries(reports.mesin), listrik: toEntries(reports.listrik) }));
    const [dirty, setDirty] = useState<Record<Category, boolean>>({ mesin: false, listrik: false });
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setEntries({ mesin: toEntries(reports.mesin), listrik: toEntries(reports.listrik) });
        setDirty({ mesin: false, listrik: false });
    }

    const tab = TABS.find((t) => t.key === category) ?? TABS[0];
    const rows = entries[category];
    const anyDirty = dirty.mesin || dirty.listrik;
    const monthTitle = `${(OPERASI_MONTHS[filters.month - 1] ?? '').toUpperCase()} ${filters.year}`;

    const visit = (patch: Partial<Props['filters']>) => {
        if (anyDirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah periode?')) {
            return;
        }

        router.get(laporanKegiatan.index().url, { ...filters, ...patch }, { preserveScroll: true, replace: true });
    };

    const change = (next: (current: Entry[]) => Entry[]) => {
        setEntries((prev) => ({ ...prev, [category]: next(prev[category]) }));
        setDirty((prev) => ({ ...prev, [category]: true }));
    };

    const setEntry = (key: number, patch: Partial<Entry>) => change((current) => current.map((e) => (e.key === key ? { ...e, ...patch } : e)));

    const setGroup = (key: number, index: number, patch: Partial<Group>) =>
        change((current) => current.map((e) => (e.key === key ? { ...e, groups: e.groups.map((g, i) => (i === index ? { ...g, ...patch } : g)) } : e)));

    const addEntry = () => change((current) => [...current, blankEntry()]);

    const duplicate = (key: number) =>
        change((current) => current.flatMap((e) => (e.key === key ? [e, { ...e, key: nextKey++, activity_date: '', groups: e.groups.map((g) => ({ ...g })), no_wo_spki: '' }] : [e])));

    const remove = (key: number) => {
        if (window.confirm('Hapus kegiatan ini?')) {
            change((current) => current.filter((e) => e.key !== key));
        }
    };

    const addGroup = (key: number) => change((current) => current.map((e) => (e.key === key ? { ...e, groups: [...e.groups, blankGroup()] } : e)));

    const removeGroup = (key: number, index: number) =>
        change((current) => current.map((e) => (e.key === key && e.groups.length > 1 ? { ...e, groups: e.groups.filter((_, i) => i !== index) } : e)));

    const reset = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setEntries({ mesin: toEntries(reports.mesin), listrik: toEntries(reports.listrik) });
            setDirty({ mesin: false, listrik: false });
        }
    };

    const post = (cat: Category, onDone: () => void) =>
        router.post(
            laporanKegiatan.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                category: cat,
                entries: entries[cat].map((e) => ({
                    activity_date: e.activity_date || null,
                    groups: e.groups.map((g) => ({ mesin: g.mesin, judul: g.judul, jenis_har: g.jenis_har, uraian: lines(g.uraian) })),
                    hasil_pekerjaan: e.hasil_pekerjaan,
                    material_nama: e.material_nama,
                    material_no_part: e.material_no_part,
                    jumlah: e.jumlah,
                    data: e.data,
                    no_lh05: e.no_lh05,
                    no_sr_ba: e.no_sr_ba,
                    no_tug9: e.no_tug9,
                    no_wo_spki: e.no_wo_spki,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty((prev) => ({ ...prev, [cat]: false })),
                onFinish: onDone,
            },
        );

    // Saves every changed report (Mesin and/or Listrik & Kontrol), one after the other.
    const save = () => {
        const pending = (['mesin', 'listrik'] as Category[]).filter((c) => dirty[c]);

        if (pending.length === 0) {
            return;
        }

        setSaving(true);
        const next = (index: number) => {
            if (index >= pending.length) {
                setSaving(false);

                return;
            }

            post(pending[index], () => next(index + 1));
        };

        next(0);
    };

    const textCell = (entry: Entry, key: TextField, rowSpan: number, className = '') => (
        <td rowSpan={rowSpan} className={cn(cell, 'p-1 text-center text-[11px]', className)}>
            {can_write && (
                <textarea
                    value={entry[key]}
                    onChange={(e) => setEntry(entry.key, { [key]: e.target.value })}
                    rows={Math.max(1, entry[key].split('\n').length)}
                    className={cn(field, 'resize-none text-center')}
                    aria-label={key}
                />
            )}
            <span className={cn('whitespace-pre-line', can_write && 'hidden print:inline')}>{entry[key]}</span>
        </td>
    );

    const actions = (
        <>
            {anyDirty && (
                <Button variant="outline" size={compact ? 'default' : 'sm'} onClick={reset} disabled={saving} className="gap-1.5">
                    <RotateCcw className="size-4" />
                    Reset
                </Button>
            )}
            {can_write && (
                <Button variant="outline" size={compact ? 'default' : 'sm'} onClick={addEntry} className="gap-1.5">
                    <Plus className="size-4" />
                    {compact ? 'Tambah' : 'Tambah Kegiatan'}
                </Button>
            )}
            {!compact && (
                <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5">
                    <Printer className="size-4" />
                    Cetak
                </Button>
            )}
            {can_write && (
                <Button size={compact ? 'default' : 'sm'} onClick={save} disabled={saving || !anyDirty} className="gap-1.5">
                    <Save className="size-4" />
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            )}
        </>
    );

    const datalists = (
        <>
            <datalist id="lk-mesin">
                {['UMUM', ...machines].map((m) => (
                    <option key={m} value={m} />
                ))}
            </datalist>
            <datalist id="lk-jenis">
                {JENIS_HAR.map((j) => (
                    <option key={j} value={j} />
                ))}
            </datalist>
            <datalist id="lk-judul">
                {JUDUL.map((j) => (
                    <option key={j} value={j} />
                ))}
            </datalist>
            <datalist id="lk-hasil">
                {['Baik', 'Belum Selesai', 'Menunggu Material'].map((h) => (
                    <option key={h} value={h} />
                ))}
            </datalist>
        </>
    );

    return (
        <>
            <Head title={`Laporan Kegiatan Pemeliharaan — ${tab.label}`} />
            <style>{PRINT_CSS}</style>
            {datalists}

            <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', compact && 'pb-28')}>
                <div className="no-print flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        {!inShell && (
                            <Button variant="outline" size="icon" onClick={() => router.get(harPengusahaan.index('input').url)} title="Kembali ke Input Pengusahaan Pemeliharaan" className="shrink-0">
                                <ArrowLeft className="size-4" />
                            </Button>
                        )}
                        <div className="min-w-0">
                            <h1 className="text-lg font-bold text-foreground md:text-xl">Laporan Kegiatan Pemeliharaan</h1>
                            <p className="text-xs text-muted-foreground">
                                Kegiatan pemeliharaan mesin serta listrik &amp; kontrol pembangkit per bulan: uraian per mesin, jenis HAR, material dan keterangan.
                            </p>
                        </div>
                    </div>
                    {!compact && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
                </div>

                <div className="no-print flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(v) => visit({ unit_id: Number(v) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className="w-full sm:w-48"
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                        className="w-full sm:w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                        className="w-full sm:w-32"
                    />
                    {anyDirty && (
                        <div className="flex items-center gap-1.5 pb-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                <div className="no-print flex gap-1.5 overflow-x-auto pb-1">
                    {TABS.map((t, index) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setCategory(t.key)}
                            className={cn(
                                'shrink-0 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                t.key === category ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            {index + 1}. {t.label} ({entries[t.key].length}){dirty[t.key] ? ' •' : ''}
                        </button>
                    ))}
                </div>

                {compact ? (
                    <div className="flex flex-col gap-3">
                        <p className="rounded-lg bg-muted/50 px-3 py-2 text-[12px] font-semibold text-foreground">
                            {tab.title} BULAN {monthTitle}
                        </p>
                        {rows.length === 0 && <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">Belum ada kegiatan. Tekan Tambah Kegiatan.</p>}
                        {rows.map((entry, index) => (
                            <div key={entry.key} className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3">
                                <div className="flex items-center gap-2">
                                    <span className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-[12px] font-bold text-primary">{index + 1}</span>
                                    <Input type="date" value={entry.activity_date} onChange={(e) => setEntry(entry.key, { activity_date: e.target.value })} disabled={!can_write} className="h-10 flex-1 text-sm" />
                                    {can_write && (
                                        <>
                                            <Button variant="ghost" size="icon" onClick={() => duplicate(entry.key)} className="size-9" aria-label="Duplikat kegiatan">
                                                <Copy className="size-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => remove(entry.key)} className="size-9 text-muted-foreground hover:text-destructive" aria-label="Hapus kegiatan">
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </div>
                                {entry.groups.map((group, g) => (
                                    <div key={g} className="flex flex-col gap-2 rounded-lg border border-dashed border-border p-2.5">
                                        <div className="grid grid-cols-2 gap-2">
                                            <MobileField label="Mesin">
                                                <Input list="lk-mesin" value={group.mesin} onChange={(e) => setGroup(entry.key, g, { mesin: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                            </MobileField>
                                            <MobileField label="Jenis HAR">
                                                <Input list="lk-jenis" value={group.jenis_har} onChange={(e) => setGroup(entry.key, g, { jenis_har: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                            </MobileField>
                                        </div>
                                        <MobileField label="Judul (mis. PREVENTIV MAINTENANCE P2)">
                                            <Input list="lk-judul" value={group.judul} onChange={(e) => setGroup(entry.key, g, { judul: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                        </MobileField>
                                        <MobileField label="Uraian kegiatan (satu baris per kegiatan)">
                                            <Textarea value={group.uraian} onChange={(e) => setGroup(entry.key, g, { uraian: e.target.value })} disabled={!can_write} rows={4} className="text-sm" />
                                        </MobileField>
                                        {can_write && entry.groups.length > 1 && (
                                            <Button variant="ghost" size="sm" onClick={() => removeGroup(entry.key, g)} className="self-start text-destructive">
                                                <Trash2 className="size-4" />
                                                Hapus mesin ini
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {can_write && (
                                    <Button variant="outline" size="sm" onClick={() => addGroup(entry.key)} className="gap-1.5 self-start">
                                        <ListPlus className="size-4" />
                                        Tambah Mesin
                                    </Button>
                                )}
                                <div className="grid grid-cols-2 gap-2">
                                    <MobileField label="Hasil Pekerjaan">
                                        <Input list="lk-hasil" value={entry.hasil_pekerjaan} onChange={(e) => setEntry(entry.key, { hasil_pekerjaan: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                    </MobileField>
                                    <MobileField label="Jumlah">
                                        <Input value={entry.jumlah} onChange={(e) => setEntry(entry.key, { jumlah: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                    </MobileField>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <MobileField label="Material (Nama)">
                                        <Input value={entry.material_nama} onChange={(e) => setEntry(entry.key, { material_nama: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                    </MobileField>
                                    <MobileField label="No. Part">
                                        <Input value={entry.material_no_part} onChange={(e) => setEntry(entry.key, { material_no_part: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                    </MobileField>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    {KETERANGAN_FIELDS.map((f) => (
                                        <div key={f.key} className={f.wide ? 'col-span-2' : ''}>
                                            <MobileField label={f.label(tab)}>
                                                {f.wide ? (
                                                    <Textarea value={entry[f.key]} onChange={(e) => setEntry(entry.key, { [f.key]: e.target.value })} disabled={!can_write} rows={2} className="text-sm" placeholder="Satu nomor per baris" />
                                                ) : (
                                                    <Input value={entry[f.key]} onChange={(e) => setEntry(entry.key, { [f.key]: e.target.value })} disabled={!can_write} className="h-10 text-sm" />
                                                )}
                                            </MobileField>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="print-container overflow-x-auto rounded-md border border-border bg-white p-2 text-black">
                        <div className="min-w-[1200px]">
                            {/* Kop */}
                            <table className="w-full border-collapse">
                                <tbody>
                                    <tr>
                                        <td className="w-48 border border-black p-2">
                                            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-10 object-contain" />
                                        </td>
                                        <td className="border border-black p-2 text-center">
                                            <div className="text-sm font-bold">PT. PLN NUSANTARA POWER</div>
                                            <div className="text-sm font-bold">UNIT PEMBANGKITAN KENDARI</div>
                                        </td>
                                        <td className="w-48 border border-black p-2">
                                            <img src="/logo/k3.png" alt="K3" className="ml-auto max-h-10 object-contain" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-black p-3 text-center text-[13px] font-bold">
                                            {tab.title} BULAN {monthTitle}
                                        </td>
                                        <td className="border border-black p-1.5 text-[10px]">
                                            <dl className="grid grid-cols-[76px_1fr]">
                                                <dt>No. Dokumen</dt>
                                                <dd>: {document.number}</dd>
                                                <dt>Revisi</dt>
                                                <dd>: {document.revision}</dd>
                                                <dt>Tanggal</dt>
                                                <dd>: {document.date}</dd>
                                            </dl>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table className="mt-1 w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th rowSpan={2} className={cn(head, 'w-8')}>
                                            NO
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'w-28')}>
                                            TANGGAL
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'w-24')}>
                                            MESIN
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'min-w-80')}>
                                            URAIAN KEGIATAN
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'w-20')}>
                                            JENIS HAR
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'w-16')}>
                                            HSL. PEK.
                                        </th>
                                        <th colSpan={2} className={head}>
                                            MATERIAL TERPAKAI
                                        </th>
                                        <th rowSpan={2} className={cn(head, 'w-24')}>
                                            JUMLAH
                                        </th>
                                        <th colSpan={KETERANGAN_FIELDS.length} className={head}>
                                            KETERANGAN
                                        </th>
                                        {can_write && <th rowSpan={2} className={cn(head, 'no-print w-20')} aria-label="Aksi" />}
                                    </tr>
                                    <tr>
                                        <th className={cn(head, 'w-32')}>NAMA</th>
                                        <th className={cn(head, 'w-20')}>NO. PART</th>
                                        {KETERANGAN_FIELDS.map((f) => (
                                            <th key={f.key} className={cn(head, f.wide ? 'w-36' : 'w-16')}>
                                                {f.label(tab)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 && (
                                        <tr>
                                            <td colSpan={9 + KETERANGAN_FIELDS.length + (can_write ? 1 : 0)} className={cn(cell, 'p-6 text-center text-xs text-neutral-500')}>
                                                Belum ada kegiatan bulan ini. Klik &quot;Tambah Kegiatan&quot;.
                                            </td>
                                        </tr>
                                    )}
                                    {rows.map((entry, index) => {
                                        const span = entry.groups.length;

                                        return (
                                            <Fragment key={entry.key}>
                                                {entry.groups.map((group, g) => (
                                                    <tr key={g}>
                                                        {g === 0 && (
                                                            <>
                                                                <td rowSpan={span} className={cn(cell, 'text-center text-[11px]')}>
                                                                    {index + 1}
                                                                </td>
                                                                <td rowSpan={span} className={cn(cell, 'p-1 text-center text-[11px]')}>
                                                                    {can_write && (
                                                                        <input
                                                                            type="date"
                                                                            value={entry.activity_date}
                                                                            onChange={(e) => setEntry(entry.key, { activity_date: e.target.value })}
                                                                            className={field}
                                                                            aria-label={`Tanggal kegiatan ${index + 1}`}
                                                                        />
                                                                    )}
                                                                    <span className={can_write ? 'hidden print:inline' : ''}>{printDate(entry.activity_date)}</span>
                                                                </td>
                                                            </>
                                                        )}
                                                        <td className={cn(cell, 'p-1 text-center text-[11px]')}>
                                                            {can_write && (
                                                                <input
                                                                    list="lk-mesin"
                                                                    value={group.mesin}
                                                                    onChange={(e) => setGroup(entry.key, g, { mesin: e.target.value })}
                                                                    placeholder="Mesin"
                                                                    className={cn(field, 'text-center')}
                                                                />
                                                            )}
                                                            <span className={can_write ? 'hidden print:inline' : ''}>{group.mesin}</span>
                                                        </td>
                                                        <td className={cn(cell, 'p-0 text-[11px]')}>
                                                            {can_write && (
                                                                <div className="flex flex-col gap-0.5 p-1 print:hidden">
                                                                    <input
                                                                        list="lk-judul"
                                                                        value={group.judul}
                                                                        onChange={(e) => setGroup(entry.key, g, { judul: e.target.value })}
                                                                        placeholder="Judul, mis. PREVENTIV MAINTENANCE P2"
                                                                        className={cn(field, 'font-bold underline')}
                                                                    />
                                                                    <textarea
                                                                        value={group.uraian}
                                                                        onChange={(e) => setGroup(entry.key, g, { uraian: e.target.value })}
                                                                        rows={Math.max(3, group.uraian.split('\n').length)}
                                                                        placeholder="Satu kegiatan per baris"
                                                                        className={cn(field, 'resize-y leading-5')}
                                                                    />
                                                                    {entry.groups.length > 1 && (
                                                                        <button type="button" onClick={() => removeGroup(entry.key, g)} className="no-print self-start text-[10px] text-destructive hover:underline">
                                                                            Hapus mesin ini
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            )}
                                                            <div className={can_write ? 'hidden print:block' : ''}>
                                                                {group.judul && <div className="border-b border-black px-1 py-0.5 font-bold underline">{group.judul}</div>}
                                                                {lines(group.uraian).map((line, i) => (
                                                                    <div key={i} className="border-b border-black px-1 py-0.5 last:border-b-0">
                                                                        - {line}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </td>
                                                        <td className={cn(cell, 'p-1 text-center text-[11px]')}>
                                                            {can_write && (
                                                                <input
                                                                    list="lk-jenis"
                                                                    value={group.jenis_har}
                                                                    onChange={(e) => setGroup(entry.key, g, { jenis_har: e.target.value })}
                                                                    placeholder="-"
                                                                    className={cn(field, 'text-center')}
                                                                />
                                                            )}
                                                            <span className={can_write ? 'hidden print:inline' : ''}>{group.jenis_har}</span>
                                                        </td>
                                                        {g === 0 && (
                                                            <>
                                                                {textCell(entry, 'hasil_pekerjaan', span)}
                                                                {textCell(entry, 'material_nama', span)}
                                                                {textCell(entry, 'material_no_part', span)}
                                                                {textCell(entry, 'jumlah', span)}
                                                                {KETERANGAN_FIELDS.map((f) => (
                                                                    <Fragment key={f.key}>{textCell(entry, f.key, span, f.wide ? 'font-bold' : '')}</Fragment>
                                                                ))}
                                                                {can_write && (
                                                                    <td rowSpan={span} className={cn(cell, 'no-print p-1')}>
                                                                        <div className="flex flex-col items-center gap-1">
                                                                            <IconButton label="Tambah mesin" onClick={() => addGroup(entry.key)}>
                                                                                <ListPlus className="size-3.5" />
                                                                            </IconButton>
                                                                            <IconButton label="Duplikat kegiatan" onClick={() => duplicate(entry.key)}>
                                                                                <Copy className="size-3.5" />
                                                                            </IconButton>
                                                                            <IconButton label="Hapus kegiatan" onClick={() => remove(entry.key)} danger>
                                                                                <Trash2 className="size-3.5" />
                                                                            </IconButton>
                                                                        </div>
                                                                    </td>
                                                                )}
                                                            </>
                                                        )}
                                                    </tr>
                                                ))}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {compact && can_write && <StickyActionBar>{actions}</StickyActionBar>}
        </>
    );
}

function MobileField({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
            {label}
            {children}
        </label>
    );
}

function IconButton({ label, onClick, danger = false, children }: { label: string; onClick: () => void; danger?: boolean; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            aria-label={label}
            className={cn('rounded p-1 text-muted-foreground transition hover:bg-muted', danger ? 'hover:text-destructive' : 'hover:text-primary')}
        >
            {children}
        </button>
    );
}

LaporanKegiatanPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pengusahaan Pemeliharaan', href: harPengusahaan.index('input') },
        { title: 'Laporan Kegiatan Pemeliharaan', href: laporanKegiatan.index() },
    ],
};
