import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, Download, FileSpreadsheet, Plus, Printer, RotateCcw, Save, Trash2 } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { buildDocumentHeader, createSheet, downloadWorkbook, mergeCells, paintSheet, setColWidths, SPECS, XLSX_COLORS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import program from '@/routes/operasi/jadwal/program-5s-5r';
import type { IdName } from '@/types';

type ServerRow = { id: number | null; pelaksana: string; rencana: number[]; realisasi: number[]; target: number };
type Row = ServerRow & { _key: number };
type Cat = 'rencana' | 'realisasi';

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    days_in_month: number;
    rows: ServerRow[];
    can_write: boolean;
};

const PRINT_CSS = `@media print { @page { size:A4 landscape; margin:6mm; } body *{visibility:hidden!important;} .print-container,.print-container *{visibility:visible!important;} .print-container{position:absolute!important;left:0!important;top:0!important;width:100%!important;} .no-print{display:none!important;} .p-table th,.p-table td{border:1px solid #000!important;font-size:7px!important;} .p-th{background:#ea7315!important;color:#fff!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} .p-done{background:#cbd5e1!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} }`;

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

export default function Program5s5rPage({ unit, filters, options, days_in_month, rows: initial, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initial));
    const [nextKey, setNextKey] = useState(initial.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const sig = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [last, setLast] = useState(sig);
    if (last !== sig) { setLast(sig); setRows(hydrate(initial)); setNextKey(initial.length); setDirty(false); }

    const monthName = useMemo(() => OPERASI_MONTHS[filters.month - 1] ?? '', [filters.month]);
    const days = useMemo(() => Array.from({ length: days_in_month }, (_, i) => i + 1), [days_in_month]);

    const visit = (p: Partial<Props['filters']>) => router.get(program.index().url, { ...filters, ...p }, { preserveState: true, preserveScroll: true, replace: true });
    const toggle = (key: number, cat: Cat, day: number) => {
        if (!can_write) return;
        setRows((prev) => prev.map((r) => { if (r._key !== key) return r; const s = new Set(r[cat]); s.has(day) ? s.delete(day) : s.add(day); return { ...r, [cat]: Array.from(s).sort((a, b) => a - b) }; }));
        setDirty(true);
    };
    const setField = (key: number, f: 'pelaksana' | 'target', v: string) => { setRows((p) => p.map((r) => (r._key === key ? { ...r, [f]: f === 'target' ? Math.max(0, parseInt(v, 10) || 0) : v } : r))); setDirty(true); };
    const addRow = () => { setRows((p) => [...p, { _key: nextKey, id: null, pelaksana: '', rencana: [], realisasi: [], target: 4 }]); setNextKey((k) => k + 1); setDirty(true); };
    const remove = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };
    const markPlan = () => { if (!can_write) return; setRows((p) => p.map((r) => ({ ...r, rencana: days.slice() }))); setDirty(true); };
    const reset = () => { setRows(hydrate(initial)); setDirty(false); };
    const save = () => {
        if (!can_write) return; setSaving(true);
        router.post(program.store().url, { unit_id: filters.unit_id, month: filters.month, year: filters.year, rows: rows.map((r) => ({ id: r.id, pelaksana: r.pelaksana, rencana: r.rencana, realisasi: r.realisasi, target: r.target })) }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) });
    };

    const handleExcel = async () => {
        const H: (string | number)[][] = [[], [], [], []];
        H.push(['PELAKSANA', 'STATUS', ...days.map(String), 'RENCANA', 'TARGET', 'REALISASI', 'A. KINERJA']);
        rows.forEach((r) => {
            const kin = r.target > 0 ? Math.round((r.realisasi.length / r.target) * 100) : 0;
            H.push([r.pelaksana, 'RENCANA', ...days.map((d) => (r.rencana.includes(d) ? 1 : '')), r.rencana.length, r.target, r.realisasi.length, `${kin}%`]);
            H.push(['', 'REALISASI', ...days.map((d) => (r.realisasi.includes(d) ? 1 : '')), '', '', '', '']);
        });
        const { workbook, worksheet: ws } = createSheet('5S5R', H);
        const dayStart = 2; const dayEnd = dayStart + days.length - 1; const colRenc = dayStart + days.length; const colKinerja = colRenc + 3; const totalCols = colKinerja + 1;
        const done: StyleSpec = { fill: XLSX_COLORS.slate, bold: true, align: 'center', valign: 'center', border: true };
        paintSheet(ws, H.length, totalCols, (r, c) => {
            if (r < 4) return null;
            if (r === 4) return SPECS.headerOrange;
            if (c === 0) return SPECS.cellLeft;
            if (c === 1) return SPECS.labelCenter;
            if (c >= dayStart && c <= dayEnd) return H[r]?.[c] === 1 ? done : SPECS.cell;
            return { ...SPECS.cell, bold: true };
        });
        rows.forEach((_, i) => { const top = 5 + i * 2; mergeCells(ws, top, 0, top + 1, 0); for (let c = colRenc; c <= colKinerja; c++) mergeCells(ws, top, c, top + 1, c); });
        const colWidths = [26, 12, ...Array(days.length).fill(3.2), 9, 8, 9, 11];
        setColWidths(ws, colWidths);
        await buildDocumentHeader({ workbook, worksheet: ws }, { totalCols, colWidths, titleLines: ['JASA PENDUKUNG TEKNIS 11 & 6 SITE - KIT', `LAPORAN PROJECT — ${unit.name.toUpperCase()}`, 'JADWAL PELAKSANAAN 5S5R'], barTitle: `JADWAL PELAKSANAAN 5S5R — ${monthName.toUpperCase()} ${filters.year}` });
        await downloadWorkbook(workbook, `Jadwal_5S5R_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
    };

    const th = 'p-th border border-black bg-[#ea7315] p-1 text-center text-[10px] font-bold text-white';
    const dayCells = (r: Row, cat: Cat) => days.map((d) => {
        const on = r[cat].includes(d);
        return <td key={`${cat}-${d}`} onClick={() => toggle(r._key, cat, d)} className={`border border-black text-center text-[11px] font-bold select-none ${on ? 'p-done bg-slate-300 dark:bg-slate-600' : 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}>{on ? '1' : ''}</td>;
    });

    return (
        <>
            <Head title={`Jadwal 5S5R — ${unit.name}`} />
            <style>{PRINT_CSS}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" onClick={() => router.get(jadwal.index().url)}><ArrowLeft className="size-4" /></Button>
                        <div>
                            <div className="flex items-center gap-2"><h1 className="text-xl font-bold text-foreground">Jadwal Pelaksanaan 5S5R Pembangkit</h1><Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">{unit.name}</Badge></div>
                            <p className="text-xs text-muted-foreground">Klik sel RENCANA / REALISASI untuk mengisi 1. Kinerja = realisasi ÷ target.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && <Button variant="outline" size="sm" onClick={reset} disabled={saving} className="gap-1.5 text-xs text-muted-foreground"><RotateCcw className="size-3.5" />Reset</Button>}
                        {can_write && <><Button variant="outline" size="sm" onClick={markPlan} className="gap-1.5 text-xs"><CheckCheck className="size-3.5 text-primary" />Tandai Rencana Semua</Button><Button variant="outline" size="sm" onClick={addRow} className="gap-1.5 text-xs"><Plus className="size-3.5" />Tambah</Button><Button size="sm" onClick={save} disabled={saving || !dirty} className="gap-1.5 text-xs"><Save className="size-3.5" />{saving ? 'Menyimpan…' : 'Simpan'}</Button></>}
                        <Button variant="outline" size="sm" onClick={handleExcel} className="gap-1.5 text-xs"><FileSpreadsheet className="size-3.5 text-emerald-600" />Excel</Button>
                        <a href={`${program.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`} target="_blank" rel="noreferrer"><Button variant="outline" size="sm" className="gap-1.5 text-xs"><Download className="size-3.5 text-rose-600" />PDF</Button></a>
                        <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 text-xs"><Printer className="size-3.5" />Cetak</Button>
                    </div>
                </div>

                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((l, i) => ({ value: String(i + 1), label: l }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                </div>

                <div className="print-container rounded-md border border-border bg-card">
                    <div className="border-b border-border p-3">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img src="/logo/sidebar-logo.png" alt="PLN" className="max-h-11 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold text-foreground uppercase">JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</div>
                                <div className="text-[11px] font-semibold text-foreground uppercase">LAPORAN PROJECT — {unit.name.toUpperCase()}</div>
                                <div className="text-[11px] font-bold text-foreground uppercase">JADWAL PELAKSANAAN 5S5R — {monthName.toUpperCase()} {filters.year}</div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img src="/logo/mkp.jpg" alt="MKP" className="max-h-11 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                    <table className="p-table w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th rowSpan={2} className={`${th} min-w-40 text-left`}>Pelaksana</th>
                                <th rowSpan={2} className={th}>Rencana / Realisasi</th>
                                <th colSpan={days.length} className={th}>Bulan {monthName}</th>
                                <th rowSpan={2} className={th}>Rencana</th>
                                <th rowSpan={2} className={th}>Target</th>
                                <th rowSpan={2} className={th}>Realisasi</th>
                                <th rowSpan={2} className={th}>A. Kinerja</th>
                                {can_write && <th rowSpan={2} className={`${th} no-print`}>Aksi</th>}
                            </tr>
                            <tr>{days.map((d) => <th key={d} className={`${th} w-6`}>{d}</th>)}</tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => {
                                const rc = r.rencana.length; const re = r.realisasi.length; const kin = r.target > 0 ? Math.round((re / r.target) * 100) : 0;
                                return (
                                    <Fragment key={r._key}>
                                        <tr className="hover:bg-muted/10">
                                            <td rowSpan={2} className="border border-black p-1 text-left align-middle">
                                                {can_write ? <input value={r.pelaksana} onChange={(e) => setField(r._key, 'pelaksana', e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-xs font-semibold focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" /> : <span className="text-xs font-semibold">{r.pelaksana}</span>}
                                            </td>
                                            <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold">RENCANA</td>
                                            {dayCells(r, 'rencana')}
                                            <td rowSpan={2} className="border border-black p-1 text-center align-middle font-bold">{rc}</td>
                                            <td rowSpan={2} className="border border-black p-0 text-center align-middle">{can_write ? <input type="number" min="0" value={r.target || ''} onChange={(e) => setField(r._key, 'target', e.target.value)} className="h-7 w-12 bg-transparent text-center text-xs font-bold focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary" placeholder="0" /> : r.target}</td>
                                            <td rowSpan={2} className="border border-black p-1 text-center align-middle font-extrabold">{re}</td>
                                            <td rowSpan={2} className={`border border-black p-1 text-center align-middle font-extrabold ${kin >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{kin}%</td>
                                            {can_write && <td rowSpan={2} className="no-print border border-black p-0 text-center align-middle"><Button variant="ghost" size="icon" className="size-6 text-muted-foreground hover:text-destructive" onClick={() => remove(r._key)}><Trash2 className="size-3.5" /></Button></td>}
                                        </tr>
                                        <tr className="hover:bg-muted/10">
                                            <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold">REALISASI</td>
                                            {dayCells(r, 'realisasi')}
                                        </tr>
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </>
    );
}

Program5s5rPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Jadwal 5S5R', href: program.index() },
    ],
};
