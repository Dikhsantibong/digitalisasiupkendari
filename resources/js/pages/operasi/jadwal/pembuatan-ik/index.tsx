import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileSpreadsheet, Plus, Printer, RotateCcw, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { buildDocumentHeader, createSheet, downloadWorkbook, paintSheet, setColWidths, SPECS, XLSX_COLORS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import pembuatanIk from '@/routes/operasi/jadwal/pembuatan-ik';
import type { IdName } from '@/types';

type ServerRow = { id: number | null; nama: string; pic: string; months: Record<string, string> };
type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; year: number };
    options: { units: IdName[]; years: number[] };
    rows: ServerRow[];
    can_write: boolean;
};

const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
const PRINT_CSS = `@media print { @page { size:A4 landscape; margin:8mm; } body *{visibility:hidden!important;} .print-container,.print-container *{visibility:visible!important;} .print-container{position:absolute!important;left:0!important;top:0!important;width:100%!important;} .no-print{display:none!important;} .p-table th,.p-table td{border:1px solid #000!important;font-size:8px!important;} .p-th{background:#ea7315!important;color:#fff!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} .p-done{background:#cbd5e1!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;} }`;
const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i, months: { ...r.months } }));

export default function PembuatanIkPage({ unit, filters, options, rows: initial, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initial));
    const [nextKey, setNextKey] = useState(initial.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const sig = `${filters.unit_id}-${filters.year}`;
    const [last, setLast] = useState(sig);
    if (last !== sig) { setLast(sig); setRows(hydrate(initial)); setNextKey(initial.length); setDirty(false); }

    const visit = (p: Partial<Props['filters']>) => router.get(pembuatanIk.index().url, { ...filters, ...p }, { preserveState: true, preserveScroll: true, replace: true });
    const toggle = (key: number, m: number) => { if (!can_write) return; setRows((p) => p.map((r) => { if (r._key !== key) return r; const mm = { ...r.months }; if (mm[String(m)]) delete mm[String(m)]; else mm[String(m)] = '1'; return { ...r, months: mm }; })); setDirty(true); };
    const setField = (key: number, f: 'nama' | 'pic', v: string) => { setRows((p) => p.map((r) => (r._key === key ? { ...r, [f]: v } : r))); setDirty(true); };
    const addRow = () => { setRows((p) => [...p, { _key: nextKey, id: null, nama: '', pic: '', months: {} }]); setNextKey((k) => k + 1); setDirty(true); };
    const remove = (key: number) => { setRows((p) => p.filter((r) => r._key !== key)); setDirty(true); };
    const reset = () => { setRows(hydrate(initial)); setDirty(false); };
    const jumlahOf = (r: Row) => Object.values(r.months).filter((v) => String(v).trim() !== '').length;
    const save = () => { if (!can_write) return; setSaving(true); router.post(pembuatanIk.store().url, { unit_id: filters.unit_id, year: filters.year, rows: rows.map((r) => ({ id: r.id, nama: r.nama, pic: r.pic, months: r.months })) }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) }); };

    const handleExcel = async () => {
        const H: (string | number)[][] = [[], [], [], []];
        H.push(['NO', 'INSTRUKSI KERJA', 'PIC PEMBUAT', ...MONTHS, 'JUMLAH']);
        rows.forEach((r, i) => H.push([i + 1, r.nama, r.pic, ...MONTHS.map((_, mi) => (r.months[String(mi + 1)] ? 1 : '')), jumlahOf(r)]));
        const { workbook, worksheet: ws } = createSheet('Pembuatan_IK', H);
        const mStart = 3; const mEnd = 14; const totalCols = 16;
        const done: StyleSpec = { fill: XLSX_COLORS.slate, bold: true, align: 'center', valign: 'center', border: true };
        paintSheet(ws, H.length, totalCols, (r, c) => {
            if (r < 4) return null;
            if (r === 4) return SPECS.headerOrange;
            if (c === 1 || c === 2) return SPECS.cellLeft;
            if (c >= mStart && c <= mEnd) return H[r]?.[c] === 1 ? done : SPECS.cell;
            return { ...SPECS.cell, bold: true };
        });
        const colWidths = [5, 42, 16, ...Array(12).fill(4), 8];
        setColWidths(ws, colWidths);
        await buildDocumentHeader({ workbook, worksheet: ws }, { totalCols, colWidths, titleLines: ['JASA PENDUKUNG TEKNIS 11 & 6 SITE - KIT', `LAPORAN PROJECT — ${unit.name.toUpperCase()}`, 'JADWAL PEMBUATAN IK'], barTitle: `JADWAL PEMBUATAN IK — TAHUN ${filters.year}` });
        await downloadWorkbook(workbook, `Jadwal_Pembuatan_IK_${unit.name.replace(/\s+/g, '_')}_${filters.year}.xlsx`);
    };

    const th = 'p-th border border-black bg-[#ea7315] p-1 text-center text-[10px] font-bold text-white';

    return (
        <>
            <Head title={`Jadwal Pembuatan IK — ${unit.name}`} />
            <style>{PRINT_CSS}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" onClick={() => router.get(jadwal.index().url)}><ArrowLeft className="size-4" /></Button>
                        <div><div className="flex items-center gap-2"><h1 className="text-xl font-bold text-foreground">Jadwal Pembuatan IK</h1><Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">{unit.name}</Badge></div><p className="text-xs text-muted-foreground">Klik sel bulan untuk menandai target pembuatan IK (1). JUMLAH = total bulan terisi.</p></div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && <Button variant="outline" size="sm" onClick={reset} disabled={saving} className="gap-1.5 text-xs text-muted-foreground"><RotateCcw className="size-3.5" />Reset</Button>}
                        {can_write && <><Button variant="outline" size="sm" onClick={addRow} className="gap-1.5 text-xs"><Plus className="size-3.5" />Tambah IK</Button><Button size="sm" onClick={save} disabled={saving || !dirty} className="gap-1.5 text-xs"><Save className="size-3.5" />{saving ? 'Menyimpan…' : 'Simpan'}</Button></>}
                        <Button variant="outline" size="sm" onClick={handleExcel} className="gap-1.5 text-xs"><FileSpreadsheet className="size-3.5 text-emerald-600" />Excel</Button>
                        <a href={`${pembuatanIk.pdf().url}?unit_id=${filters.unit_id}&year=${filters.year}`} target="_blank" rel="noreferrer"><Button variant="outline" size="sm" className="gap-1.5 text-xs"><Download className="size-3.5 text-rose-600" />PDF</Button></a>
                        <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 text-xs"><Printer className="size-3.5" />Cetak</Button>
                    </div>
                </div>

                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
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
                                <div className="text-[11px] font-bold text-foreground uppercase">JADWAL PEMBUATAN IK — TAHUN {filters.year}</div>
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
                                <th rowSpan={2} className={`${th} w-10`}>No</th>
                                <th rowSpan={2} className={`${th} min-w-64 text-left`}>Instruksi Kerja</th>
                                <th rowSpan={2} className={`${th} min-w-32`}>PIC Pembuat</th>
                                <th colSpan={12} className={th}>Bulan</th>
                                <th rowSpan={2} className={th}>Jumlah</th>
                                {can_write && <th rowSpan={2} className={`${th} no-print`}>Aksi</th>}
                            </tr>
                            <tr>{MONTHS.map((m) => <th key={m} className={`${th} w-8`}>{m}</th>)}</tr>
                        </thead>
                        <tbody>
                            <tr className="bg-[#ea7315]/10"><td colSpan={can_write ? 16 : 15} className="border border-black p-1 text-left text-xs font-bold uppercase">A. Pembuatan Instruksi Kerja</td></tr>
                            {rows.map((r, i) => (
                                <tr key={r._key} className="hover:bg-muted/10">
                                    <td className="border border-black p-1 text-center">{i + 1}</td>
                                    <td className="border border-black p-1 text-left">{can_write ? <input value={r.nama} onChange={(e) => setField(r._key, 'nama', e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" placeholder="Nama IK…" /> : <span className="text-xs">{r.nama}</span>}</td>
                                    <td className="border border-black p-1 text-center">{can_write ? <input value={r.pic} onChange={(e) => setField(r._key, 'pic', e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-center text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" /> : <span className="text-xs">{r.pic}</span>}</td>
                                    {MONTHS.map((_, mi) => { const m = mi + 1; const on = !!r.months[String(m)]; return <td key={m} onClick={() => toggle(r._key, m)} className={`border border-black text-center text-[11px] font-bold select-none ${on ? 'p-done bg-slate-300 dark:bg-slate-600' : 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}>{on ? '1' : ''}</td>; })}
                                    <td className="border border-black p-1 text-center font-extrabold">{jumlahOf(r)}</td>
                                    {can_write && <td className="no-print border border-black p-0 text-center"><Button variant="ghost" size="icon" className="size-6 text-muted-foreground hover:text-destructive" onClick={() => remove(r._key)}><Trash2 className="size-3.5" /></Button></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </>
    );
}

PembuatanIkPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
        { title: 'Jadwal Pembuatan IK', href: pembuatanIk.index() },
    ],
};
