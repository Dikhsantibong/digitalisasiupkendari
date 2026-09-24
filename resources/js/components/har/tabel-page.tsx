import { Head, router } from '@inertiajs/react';
import { Check, Download, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import type { IdName } from '@/types';

type Column = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'date' | 'number' | 'select' | 'check';
    options?: string[];
    group?: string;
    exclusive?: string;
    width: number;
    align: 'l' | 'c';
};
type Value = string | number | null;
type Row = Record<string, Value>;

export type HarTabelPageProps = {
    tabel: { key: string; title: string; description: string; columns: Column[]; totals: string[]; notes: string[]; blank_rows: number };
    kop_lines: string[];
    unit: IdName;
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    rows: Row[];
    summary: { title: string; columns: string[]; rows: (string | number)[][] } | null;
    urls: { index: string; store: string; pdf: string };
    has_saved: boolean;
    can_write: boolean;
};

const th = 'border border-slate-400 px-1 py-1';
const td = 'border border-border';
const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const cellTextarea =
    'block min-h-14 w-full resize-y rounded-none border-0 bg-transparent px-1 py-1 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

const blankRow = (columns: Column[]): Row => Object.fromEntries(columns.map((c) => [c.key, null]));
const numeric = (value: Value) => (value !== null && value !== '' && Number.isFinite(Number(value)) ? Number(value) : 0);

/**
 * Shared page of the HAR free tables (App\Support\HarTabel) — Rekap Laporan
 * Gangguan and Laporan Kondisi Abnormal & Gangguan Pembangkit. Each has its own
 * thin page at resources/js/pages/har/input/{key}/index.tsx rendering this.
 */
export function HarTabelPage({ tabel, kop_lines, unit, filters, options, rows: initialRows, summary, urls, has_saved, can_write }: HarTabelPageProps) {
    const fill = (list: Row[]) => (list.length > 0 ? list : Array.from({ length: tabel.blank_rows }, () => blankRow(tabel.columns)));
    const [rows, setRows] = useState<Row[]>(() => fill(initialRows));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const hasGroups = tabel.columns.some((c) => c.group);
    const firstTotal = tabel.columns.findIndex((c) => tabel.totals.includes(c.key));
    const groupCells = tabel.columns.reduce<{ group: string | null; column: Column; span: number }[]>((list, column) => {
        const last = list[list.length - 1];

        if (column.group && last?.group === column.group) {
            last.span++;
        } else {
            list.push({ group: column.group ?? null, column, span: 1 });
        }

        return list;
    }, []);

    const visit = (patch: Partial<HarTabelPageProps['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(urls.index, { ...filters, ...patch }, { preserveScroll: true });
    };

    const setValue = (index: number, column: Column, value: Value) => {
        setRows((current) =>
            current.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                // One tick per exclusive group (e.g. Abnormal / Gangguan).
                const siblings =
                    column.type === 'check' && column.exclusive && value !== null
                        ? Object.fromEntries(tabel.columns.filter((c) => c.exclusive === column.exclusive && c.key !== column.key).map((c) => [c.key, null]))
                        : {};

                return { ...row, ...siblings, [column.key]: value };
            }),
        );
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            urls.store,
            { ...filters, rows },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setRows(fill((page.props as unknown as HarTabelPageProps).rows));
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const editor = (index: number, column: Column, value: Value) => {
        const label = `${column.label} baris ${index + 1}`;
        const align = column.align === 'c' ? 'text-center' : '';

        switch (column.type) {
            case 'textarea':
                return <textarea value={String(value ?? '')} onChange={(e) => setValue(index, column, e.target.value)} className={cellTextarea} disabled={!can_write} aria-label={label} />;
            case 'select':
                return <PdmCellSelect value={String(value ?? '')} onChange={(v) => setValue(index, column, v || null)} options={column.options ?? []} className="justify-center" disabled={!can_write} />;
            case 'check': {
                const checked = String(value ?? '') === '1';

                return (
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked={checked}
                        aria-label={label}
                        onClick={() => setValue(index, column, checked ? null : 1)}
                        disabled={!can_write}
                        className={`mx-auto flex size-5 items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                            checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background hover:border-primary'
                        }`}
                    >
                        {checked && <Check className="size-3.5" />}
                    </button>
                );
            }
            default:
                return (
                    <Input
                        type={column.type === 'date' ? 'date' : column.type === 'number' ? 'number' : 'text'}
                        step={column.type === 'number' ? 'any' : undefined}
                        value={value ?? ''}
                        onChange={(e) => setValue(index, column, e.target.value === '' ? null : column.type === 'number' ? Number(e.target.value) : e.target.value)}
                        className={`${cellInput} ${align}`}
                        disabled={!can_write}
                        aria-label={label}
                    />
                );
        }
    };

    return (
        <>
            <Head title={`${tabel.title} - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={tabel.title}
                    description={`${tabel.description} — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(harInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={() => window.open(`${urls.pdf}?${new URLSearchParams({ unit_id: String(filters.unit_id), month: String(filters.month), year: String(filters.year) })}`, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                        <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, i) => ({ value: String(i + 1), label }))} />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    </div>
                    {dirty ? (
                        <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                    ) : has_saved ? (
                        <StatusBadge tone="success">{initialRows.length} baris tersimpan</StatusBadge>
                    ) : (
                        <StatusBadge tone="neutral">Belum ada data</StatusBadge>
                    )}
                </div>

                <div className="grid grid-cols-[64px_1fr_64px] items-center gap-3 rounded-md border border-border bg-muted/10 p-4 sm:grid-cols-[140px_1fr_140px]">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                    <div className="text-center">
                        {kop_lines.map((line, i) => (
                            <div key={`${i}-${line}`} className={i === kop_lines.length - 1 ? 'text-base font-bold uppercase' : 'text-xs font-semibold uppercase text-muted-foreground'}>
                                {line}
                            </div>
                        ))}
                        <div className="mt-1 text-xs text-muted-foreground">Periode {periodLabel}</div>
                    </div>
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                </div>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full border-collapse text-xs" style={{ minWidth: tabel.columns.reduce((sum, c) => sum + Math.max(c.width, 80), 80) }}>
                        <thead className="bg-[#4f81bd] text-center text-[11px] font-semibold text-white">
                            <tr>
                                <th className={`${th} w-10`} rowSpan={hasGroups ? 2 : 1}>NO</th>
                                {groupCells.map((cell) =>
                                    cell.group ? (
                                        <th key={`${cell.group}-${cell.column.key}`} className={th} colSpan={cell.span}>{cell.group}</th>
                                    ) : (
                                        <th key={cell.column.key} className={th} rowSpan={hasGroups ? 2 : 1} style={{ minWidth: cell.column.width }}>{cell.column.label}</th>
                                    ),
                                )}
                                {can_write && <th className={`${th} w-10`} rowSpan={hasGroups ? 2 : 1} aria-label="Aksi" />}
                            </tr>
                            {hasGroups && (
                                <tr>
                                    {tabel.columns.filter((c) => c.group).map((c) => <th key={c.key} className={th} style={{ minWidth: c.width }}>{c.label}</th>)}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="align-middle hover:bg-muted/30">
                                    <td className={`${td} text-center`}>{index + 1}</td>
                                    {tabel.columns.map((column) => (
                                        <td key={column.key} className={`${td} p-0 ${column.type === 'check' ? 'text-center' : ''}`}>{editor(index, column, row[column.key] ?? null)}</td>
                                    ))}
                                    {can_write && (
                                        <td className={`${td} text-center`}>
                                            <Button variant="ghost" size="icon" onClick={() => {
 setRows((c) => c.filter((_, i) => i !== index)); setDirty(true); 
}} className="size-7 text-muted-foreground hover:text-destructive" aria-label={`Hapus baris ${index + 1}`}>
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {tabel.totals.length > 0 && firstTotal >= 0 && (
                                <tr className="bg-muted/40 font-bold">
                                    <td className={`${td} px-2 py-1 text-right`} colSpan={firstTotal + 1}>TOTAL</td>
                                    {tabel.columns.slice(firstTotal).map((c) => (
                                        <td key={c.key} className={`${td} text-center`}>
                                            {tabel.totals.includes(c.key) ? Math.round(rows.reduce((sum, row) => sum + numeric(row[c.key]), 0) * 100) / 100 : ''}
                                        </td>
                                    ))}
                                    {can_write && <td className={td} />}
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {can_write && (
                    <div>
                        <Button variant="outline" size="sm" onClick={() => {
 setRows((c) => [...c, blankRow(tabel.columns)]); setDirty(true); 
}} className="gap-1 text-xs">
                            <Plus className="size-3.5" />
                            Tambah Baris
                        </Button>
                    </div>
                )}

                {tabel.notes.length > 0 && (
                    <ol className="list-decimal pl-5 text-xs text-muted-foreground">
                        {tabel.notes.map((note) => (
                            <li key={note}>{note}</li>
                        ))}
                    </ol>
                )}

                {summary && (
                    <div className="flex flex-col gap-1.5">
                        <div className="text-sm font-semibold">
                            {summary.title} <span className="text-xs font-normal text-muted-foreground">(dari data tersimpan)</span>
                        </div>
                        <table className="w-fit border-collapse text-xs">
                            <thead className="bg-[#4f81bd] text-white">
                                <tr>
                                    {summary.columns.map((label) => <th key={label} className={`${th} px-3`}>{label}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {summary.rows.map((line, i) => (
                                    <tr key={i}>
                                        {line.map((cell, j) => <td key={j} className={`${td} px-3 py-1 text-center`}>{cell}</td>)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Breadcrumbs of a HAR free-table page.
 */
export function harTabelBreadcrumbs(title: string, href: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title, href },
    ];
}
