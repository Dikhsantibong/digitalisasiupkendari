import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileSpreadsheet, Plus, Printer, RotateCcw, Save, Trash2 } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { buildDocumentHeader, createSheet, downloadWorkbook, mergeCells, paintSheet, setColWidths, SPECS, XLSX_COLORS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import flm from '@/routes/operasi/jadwal/flm';
import type { IdName } from '@/types';

type Section = 'rutin' | 'non_rutin';
type RowType = 'mark' | 'minutes' | 'shift';

type ServerRow = {
    id: number | null;
    section: Section;
    shift: string;
    label: string;
    row_type: RowType;
    days: Record<string, string>;
    target: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    days_in_month: number;
    rows: ServerRow[];
    can_write: boolean;
};

const SECTIONS: { key: Section; label: string }[] = [
    { key: 'rutin', label: 'FLM Rutin' },
    { key: 'non_rutin', label: 'FLM Non Rutin' },
];

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; }
    .no-print { display: none !important; }
    .flm-table { font-size: 7px !important; }
    .flm-table th, .flm-table td { border: 1px solid #000 !important; }
    .flm-th { background-color: #ea7315 !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .flm-done { background-color: #cbd5e1 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i, days: { ...r.days } }));

export default function OperasiFlmPage({ unit, filters, options, days_in_month, rows: initialRows, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [isAddOpen, setIsAddOpen] = useState(false);
    const [form, setForm] = useState<{ section: Section; shift: string; label: string; row_type: RowType; target: number }>({ section: 'non_rutin', shift: 'A', label: 'REALISASI', row_type: 'mark', target: 15 });

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [sig, setSig] = useState(signature);
    if (sig !== signature) { setSig(signature); setRows(hydrate(initialRows)); setNextKey(initialRows.length); setDirty(false); }

    const monthName = useMemo(() => OPERASI_MONTHS[filters.month - 1] ?? '', [filters.month]);
    const days = useMemo(() => Array.from({ length: days_in_month }, (_, i) => i + 1), [days_in_month]);

    const visit = (patch: Partial<Props['filters']>) => router.get(flm.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    const setDay = (key: number, day: number, value: string) => {
        setRows((p) => p.map((r) => {
            if (r._key !== key) return r;
            const d = { ...r.days };
            if (value.trim() === '') delete d[String(day)]; else d[String(day)] = value;
            return { ...r, days: d };
        }));
        setDirty(true);
    };
    const toggleMark = (key: number, day: number) => {
        if (!can_write) return;
        setRows((p) => p.map((r) => {
            if (r._key !== key) return r;
            const d = { ...r.days };
            if (d[String(day)]) delete d[String(day)]; else d[String(day)] = '1';
            return { ...r, days: d };
        }));
        setDirty(true);
    };
    const updateMeta = (key: number, field: 'label' | 'shift' | 'target', value: string) => {
        setRows((p) => p.map((r) => (r._key === key ? { ...r, [field]: field === 'target' ? Math.max(0, parseInt(value, 10) || 0) : value } : r)));
        setDirty(true);
    };
    const remove = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };

    const addRow = () => {
        setRows((p) => [...p, { _key: nextKey, id: null, section: form.section, shift: form.shift, label: form.label, row_type: form.row_type, days: {}, target: Number(form.target) || 0 }]);
        setNextKey((k) => k + 1);
        setDirty(true);
        setIsAddOpen(false);
    };

    const realisasiOf = (r: Row): number | null => {
        if (r.row_type === 'shift') return null;
        const vals = Object.values(r.days).filter((v) => String(v).trim() !== '');
        if (r.row_type === 'minutes') return vals.reduce((s, v) => s + (parseInt(v, 10) || 0), 0);
        return vals.length;
    };

    const handleReset = () => { setRows(hydrate(initialRows)); setDirty(false); };
    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        const ordered = SECTIONS.flatMap((s) => rows.filter((r) => r.section === s.key));
        router.post(
            flm.store().url,
            {
                unit_id: filters.unit_id, month: filters.month, year: filters.year,
                rows: ordered.map((r) => ({ section: r.section, shift: r.shift, label: r.label, row_type: r.row_type, days: r.days, target: r.target })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const thBase = 'flm-th border border-black bg-[#ea7315] p-1 text-center text-[10px] font-bold text-white';

    const handleExcel = async () => {
        const ordered = SECTIONS.flatMap((s) => rows.filter((r) => r.section === s.key));
        const H: (string | number)[][] = [[], [], [], []];
        H.push(['KATEGORI', 'URAIAN', 'SHIFT', ...days.map(String), 'REALISASI', 'TARGET', '%']);
        ordered.forEach((r) => {
            const realisasi = realisasiOf(r) ?? '';
            const pct = r.row_type === 'shift' ? '' : (r.target > 0 ? `${Math.round(((realisasiOf(r) ?? 0) / r.target) * 100)}%` : '0%');
            const label = SECTIONS.find((s) => s.key === r.section)?.label ?? '';
            H.push([label, r.label, r.shift, ...days.map((d) => { const v = r.days[String(d)] ?? ''; if (r.row_type === 'mark') return v !== '' ? 1 : ''; if (r.row_type === 'minutes') return v !== '' ? Number(v) : ''; return v; }), r.row_type === 'shift' ? '' : realisasi, r.row_type === 'shift' ? '' : r.target, pct]);
        });
        const { workbook, worksheet: ws } = createSheet('FLM', H);
        const dayStart = 3; const dayEnd = dayStart + days.length - 1; const totalCols = 3 + days.length + 3;
        const done: StyleSpec = { fill: XLSX_COLORS.slate, bold: true, align: 'center', valign: 'center', border: true };
        paintSheet(ws, H.length, totalCols, (r, c) => {
            if (r < 4) return null;
            if (r === 4) return SPECS.headerOrange;
            if (c === 0) return SPECS.label;
            if (c === 1) return SPECS.cellLeft;
            if (c >= dayStart && c <= dayEnd) return H[r]?.[c] === 1 ? done : SPECS.cell;
            return { ...SPECS.cell, bold: true };
        });
        // Merge the category column across each section's rows.
        let cursor = 5;
        SECTIONS.forEach((s) => {
            const n = ordered.filter((r) => r.section === s.key).length;
            if (n > 1) mergeCells(ws, cursor, 0, cursor + n - 1, 0);
            cursor += n;
        });
        const colWidths = [16, 26, 8, ...Array(days.length).fill(3.2), 9, 8, 7];
        setColWidths(ws, colWidths);
        await buildDocumentHeader({ workbook, worksheet: ws }, { totalCols, colWidths, titleLines: ['JASA PENDUKUNG TEKNIS 11 SITE KIT', `LAPORAN PROJECT — ${unit.name.toUpperCase()}`, 'JADWAL FIRST LINE MAINTENANCE (FLM) OPERATOR'], barTitle: `JADWAL FLM OPERATOR — ${monthName.toUpperCase()} ${filters.year}` });
        await downloadWorkbook(workbook, `Jadwal_FLM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
    };

    const dayCell = (r: Row, day: number) => {
        const val = r.days[String(day)] ?? '';
        if (r.row_type === 'mark') {
            const on = val !== '';
            return (
                <td key={day} onClick={() => toggleMark(r._key, day)} className={`border border-black text-center text-[11px] font-bold select-none ${on ? 'flm-done bg-slate-300 dark:bg-slate-600' : 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}>
                    {on ? '1' : ''}
                </td>
            );
        }
        if (r.row_type === 'minutes') {
            return (
                <td key={day} className="border border-black p-0 text-center">
                    {can_write ? <input type="number" min="0" value={val} onChange={(e) => setDay(r._key, day, e.target.value)} className="h-6 w-9 bg-transparent text-center text-[10px] focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary" /> : <span className="text-[10px]">{val}</span>}
                </td>
            );
        }
        return (
            <td key={day} className="border border-black p-0 text-center">
                {can_write ? <input type="text" maxLength={2} value={val} onChange={(e) => setDay(r._key, day, e.target.value.toUpperCase())} className="h-6 w-7 bg-transparent text-center text-[10px] font-semibold uppercase focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary" /> : <span className="text-[10px] font-semibold">{val}</span>}
            </td>
        );
    };

    return (
        <>
            <Head title={`Jadwal FLM Operator — ${unit.name}`} />
            <style>{PRINT_CSS}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" onClick={() => router.get(jadwal.index().url)} title="Kembali"><ArrowLeft className="size-4" /></Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">Jadwal First Line Maintenance (FLM) Operator</h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">{unit.name}</Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">Klik sel pada baris REALISASI untuk mengisi 1; baris "Waktu tentatif" diisi menit; baris jadwal diisi huruf shift. Realisasi &amp; persentase otomatis.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && <Button variant="outline" size="sm" onClick={handleReset} disabled={saving} className="gap-1.5 text-xs text-muted-foreground"><RotateCcw className="size-3.5" />Reset</Button>}
                        {can_write && (
                            <>
                                <Button variant="outline" size="sm" onClick={() => setIsAddOpen(true)} className="gap-1.5 text-xs"><Plus className="size-3.5" />Tambah Baris</Button>
                                <Button size="sm" onClick={handleSave} disabled={saving || !dirty} className="gap-1.5 text-xs"><Save className="size-3.5" />{saving ? 'Menyimpan…' : 'Simpan'}</Button>
                            </>
                        )}
                        <Button variant="outline" size="sm" onClick={handleExcel} className="gap-1.5 text-xs"><FileSpreadsheet className="size-3.5 text-emerald-600" />Excel</Button>
                        <a href={`${flm.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`} target="_blank" rel="noreferrer">
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs"><Download className="size-3.5 text-rose-600" />PDF</Button>
                        </a>
                        <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 text-xs"><Printer className="size-3.5" />Cetak</Button>
                    </div>
                </div>

                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect label="Unit Pembangkit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((l, i) => ({ value: String(i + 1), label: l }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    {dirty && <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400"><span className="size-2 animate-pulse rounded-full bg-amber-500" />Belum disimpan</div>}
                </div>

                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border"><img src="/logo/sidebar-logo.png" alt="PLN" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} /></div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold text-foreground uppercase">JASA PENDUKUNG TEKNIS 11 SITE KIT</div>
                                <div className="text-[11px] font-semibold text-foreground uppercase">LAPORAN PROJECT — {unit.name.toUpperCase()}</div>
                                <div className="text-[11px] font-bold text-foreground uppercase">JADWAL FIRST LINE MAINTENANCE (FLM) OPERATOR — {monthName.toUpperCase()} {filters.year}</div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border"><img src="/logo/mkp.jpg" alt="MKP" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} /></div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="flm-table w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th rowSpan={2} className={`${thBase} w-24`}>Kategori</th>
                                    <th rowSpan={2} className={`${thBase} min-w-32 text-left`}>Uraian</th>
                                    <th rowSpan={2} className={`${thBase} w-16`}>Shift</th>
                                    <th colSpan={days.length} className={thBase}>Bulan {monthName}</th>
                                    <th colSpan={3} className={thBase}>Analisa Kinerja</th>
                                    {can_write && <th rowSpan={2} className={`${thBase} no-print w-10`}>Aksi</th>}
                                </tr>
                                <tr>
                                    {days.map((d) => <th key={d} className={`${thBase} w-6`}>{d}</th>)}
                                    <th className={thBase}>Realisasi</th>
                                    <th className={thBase}>Target</th>
                                    <th className={thBase}>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                {SECTIONS.map((section) => {
                                    const items = rows.filter((r) => r.section === section.key);
                                    if (items.length === 0) return null;
                                    return (
                                        <Fragment key={section.key}>
                                            {items.map((row, li) => {
                                                const realisasi = realisasiOf(row);
                                                const pct = row.row_type === 'shift' ? null : (row.target > 0 ? Math.round(((realisasi ?? 0) / row.target) * 100) : 0);
                                                return (
                                                    <tr key={row._key} className="hover:bg-muted/10">
                                                        {li === 0 && (
                                                            <td rowSpan={items.length} className="border border-black bg-[#ea7315]/10 p-1 text-center align-middle text-xs font-bold text-foreground uppercase">
                                                                {section.label}
                                                            </td>
                                                        )}
                                                        <td className="border border-black p-1 text-left">
                                                            {can_write ? <input value={row.label} onChange={(e) => updateMeta(row._key, 'label', e.target.value)} className={`w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none ${row.row_type === 'minutes' ? 'text-rose-600 dark:text-rose-400' : ''}`} /> : <span className={`text-xs ${row.row_type === 'minutes' ? 'text-rose-600 dark:text-rose-400' : ''}`}>{row.label}</span>}
                                                        </td>
                                                        <td className="border border-black p-0 text-center">
                                                            {can_write ? <input value={row.shift} onChange={(e) => updateMeta(row._key, 'shift', e.target.value)} className="h-7 w-14 bg-transparent text-center text-xs font-semibold focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary" /> : <span className="text-xs font-semibold">{row.shift}</span>}
                                                        </td>
                                                        {days.map((d) => dayCell(row, d))}
                                                        <td className="border border-black p-1 text-center font-bold text-foreground">{realisasi ?? ''}</td>
                                                        <td className="border border-black p-0 text-center">
                                                            {row.row_type === 'shift' ? '' : (can_write ? <input type="number" min="0" value={row.target || ''} onChange={(e) => updateMeta(row._key, 'target', e.target.value)} className="h-7 w-14 bg-transparent text-center text-xs font-bold focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary" placeholder="0" /> : row.target)}
                                                        </td>
                                                        <td className={`border border-black p-1 text-center font-bold ${pct === null ? '' : pct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{pct === null ? '' : `${pct}%`}</td>
                                                        {can_write && (
                                                            <td className="no-print border border-black p-0 text-center">
                                                                <Button variant="ghost" size="icon" className="size-6 text-muted-foreground hover:text-destructive" onClick={() => remove(row._key)}><Trash2 className="size-3.5" /></Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                );
                                            })}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tambah Baris FLM</DialogTitle>
                        <DialogDescription>Tambahkan baris jadwal FLM untuk unit {unit.name}.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label>Kategori</Label>
                            <select value={form.section} onChange={(e) => setForm((f) => ({ ...f, section: e.target.value as Section }))} className="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="rutin">FLM Rutin</option>
                                <option value="non_rutin">FLM Non Rutin</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Tipe Baris</Label>
                            <select value={form.row_type} onChange={(e) => setForm((f) => ({ ...f, row_type: e.target.value as RowType }))} className="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="mark">Realisasi (klik isi 1)</option>
                                <option value="minutes">Waktu tentatif (menit)</option>
                                <option value="shift">Jadwal shift (huruf)</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5"><Label>Uraian</Label><Input value={form.label} onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))} /></div>
                        <div className="grid gap-1.5"><Label>Shift</Label><Input value={form.shift} onChange={(e) => setForm((f) => ({ ...f, shift: e.target.value }))} placeholder="A / B / C / D / JADWAL" /></div>
                        <div className="grid gap-1.5"><Label>Target</Label><Input type="number" min="0" value={form.target} onChange={(e) => setForm((f) => ({ ...f, target: Number(e.target.value) }))} /></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAddOpen(false)}>Batal</Button>
                        <Button onClick={addRow}>Tambahkan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

OperasiFlmPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Jadwal FLM', href: flm.index() },
    ],
};
