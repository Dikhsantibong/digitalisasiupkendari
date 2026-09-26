import { Head, router } from '@inertiajs/react';
import { Download, Plus, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import inputLembar from '@/routes/har/input/lembar';
import harJadwal from '@/routes/har/jadwal';
import jadwalLembar from '@/routes/har/jadwal/lembar';
import type { IdName } from '@/types';

type Field = { key: string; label: string; type: 'text' | 'number'; position: 'before' | 'after'; width: number; align: 'l' | 'c' };
type GridColumn = { key: string; label: string; group: string | null; sub: string | null; is_red: boolean };
type Cells = Record<string, Record<string, string>>;
type Row = { section: string | null; fields: Record<string, string | number | null>; cells: Cells };
type Summary = { title: string; columns: string[]; rows: (string | number)[][] } | null;

export type HarLembarPageProps = {
    lembar: {
        key: string;
        title: string;
        description: string;
        menu: 'jadwal' | 'input';
        yearly: boolean;
        per_machine: boolean;
        fields: Field[];
        grid: GridColumn[];
        cell_type: 'code' | 'pair' | 'mark';
        codes: { code: string; label: string }[];
        lines: { key: string; label: string }[];
        sections: { key: string; title: string | null }[];
        show_count: boolean;
        note_label: string | null;
        legend_title: string;
    };
    kop_lines: string[];
    unit: IdName;
    filters: { unit_id: number; month: number; year: number; machine_id: number | null };
    options: { units: IdName[]; years: number[]; machines: IdName[] };
    rows: Row[];
    catatan: string;
    summary: Summary;
    has_saved: boolean;
    can_write: boolean;
};

const th = 'border border-slate-400 px-0.5 py-1';
const td = 'border border-border';
const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';

/** PHP sends an empty cell map as a JSON array. */
const normalize = (rows: Row[], lines: { key: string }[]): Row[] =>
    rows.map((row) => ({
        ...row,
        fields: Array.isArray(row.fields) ? {} : row.fields,
        cells: Object.fromEntries(lines.map((line) => [line.key, Array.isArray(row.cells?.[line.key]) || !row.cells?.[line.key] ? {} : row.cells[line.key]])),
    }));

/**
 * Shared page of the HAR matrix sheets (App\Support\HarLembar) — Jadwal
 * Inventarisasi Tools & Material, Jadwal Pemeriksaan Instalasi Blackstart,
 * Laporan Patrol Check Pemeliharaan. Each has its own thin page at
 * resources/js/pages/har/{menu}/{key}/index.tsx rendering this component.
 */
export function HarLembarPage({ lembar, kop_lines, unit, filters, options, rows: initialRows, catatan: initialCatatan, summary, has_saved, can_write }: HarLembarPageProps) {
    const [rows, setRows] = useState<Row[]>(() => normalize(initialRows, lembar.lines));
    const [catatan, setCatatan] = useState(initialCatatan);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    const { can } = usePermissions();
    // Phone layout edits one grid column (a date / week) at a time; start on today's date when shown.
    const [selectedColumn, setSelectedColumn] = useState<string>(() => {
        const now = new Date();
        const today = now.getFullYear() === filters.year && now.getMonth() + 1 === filters.month ? String(now.getDate()) : null;

        return (lembar.grid.find((c) => c.label === today) ?? lembar.grid[0])?.key ?? '';
    });

    const routes = lembar.menu === 'input' ? inputLembar : jadwalLembar;
    const before = lembar.fields.filter((f) => f.position === 'before');
    const after = lembar.fields.filter((f) => f.position === 'after');
    const multiLine = lembar.lines.length > 1;
    const pair = lembar.cell_type === 'pair';
    const codes = lembar.codes.map((c) => c.code);
    const hasGroups = lembar.grid.some((c) => c.group);
    const hasSub = lembar.grid.some((c) => c.sub);
    const headRows = 1 + (hasGroups ? 1 : 0) + (hasSub ? 1 : 0) + (pair ? 1 : 0);
    const perColumn = pair ? codes.length : 1;
    const totalCols = 1 + before.length + (multiLine ? 1 : 0) + lembar.grid.length * perColumn + (lembar.show_count ? 1 : 0) + after.length + (can_write ? 1 : 0);
    const groups = lembar.grid.reduce<{ label: string; span: number }[]>((list, column) => {
        const last = list[list.length - 1];

        if (last && last.label === column.group) {
            last.span++;
        } else {
            list.push({ label: column.group ?? '', span: 1 });
        }

        return list;
    }, []);
    const periodLabel = lembar.yearly ? `Tahun ${filters.year}` : `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const machineName = options.machines.find((m) => m.id === filters.machine_id)?.name;
    const grandTotal = rows.reduce((sum, row) => sum + lembar.lines.reduce((s, line) => s + Object.keys(row.cells[line.key] ?? {}).length, 0), 0);
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...(lembar.per_machine && filters.machine_id ? { machine_id: filters.machine_id } : {}) };

    const touch = () => setDirty(true);

    const visit = (patch: Partial<HarLembarPageProps['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(routes.index(lembar.key).url, { ...query, ...patch }, { preserveScroll: true });
    };

    const setField = (index: number, key: string, value: string) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, fields: { ...row.fields, [key]: value === '' ? null : value } } : row)));
        touch();
    };

    const setCell = (index: number, line: string, column: string, value: string) => {
        setRows((current) =>
            current.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                const next = { ...row.cells[line] };

                if (value === '') {
                    delete next[column];
                } else {
                    next[column] = value;
                }

                return { ...row, cells: { ...row.cells, [line]: next } };
            }),
        );
        touch();
    };

    const addRow = (section: string | null) => {
        setRows((current) => {
            const last = current.map((row) => row.section).lastIndexOf(section);
            const next = [...current];
            next.splice(last + 1, 0, { section, fields: {}, cells: Object.fromEntries(lembar.lines.map((line) => [line.key, {}])) });

            return next;
        });
        touch();
    };

    const removeRow = (index: number) => {
        setRows((current) => current.filter((_, i) => i !== index));
        touch();
    };

    const save = () => {
        setSaving(true);
        router.post(
            routes.store(lembar.key).url,
            { ...query, rows, catatan },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as unknown as HarLembarPageProps;
                    setRows(normalize(props.rows, lembar.lines));
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const cellEditor = (index: number, line: string, column: GridColumn, value: string) => {
        if (lembar.cell_type === 'code') {
            return <PdmCellSelect value={value} onChange={(v) => setCell(index, line, column.key, v)} options={codes} className="justify-center" disabled={!can_write} />;
        }

        if (pair) {
            return codes.map((code) => (
                <td key={code} className={`${td} p-0 text-center ${column.is_red ? 'bg-red-500/25' : ''}`}>
                    <button
                        type="button"
                        onClick={() => setCell(index, line, column.key, value === code ? '' : code)}
                        disabled={!can_write}
                        aria-pressed={value === code}
                        aria-label={`${code} tanggal ${column.label}`}
                        className={`h-6 w-full text-[10px] font-bold transition-colors disabled:cursor-not-allowed ${
                            value === code ? (code === codes[0] ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white') : 'text-transparent hover:bg-muted hover:text-muted-foreground'
                        }`}
                    >
                        {code}
                    </button>
                </td>
            ));
        }

        return (
            <button
                type="button"
                onClick={() => setCell(index, line, column.key, value === '1' ? '' : '1')}
                disabled={!can_write}
                aria-pressed={value === '1'}
                aria-label={`Minggu ${column.label} ${column.group ?? ''}`}
                className={`h-7 w-full text-xs font-bold transition-colors disabled:cursor-not-allowed ${value === '1' ? 'bg-primary/15 text-primary' : 'text-transparent hover:bg-muted hover:text-muted-foreground'}`}
            >
                1
            </button>
        );
    };

    const fieldCell = (field: Field, index: number, row: Row) => (
        <td key={field.key} className={`${td} p-0`} rowSpan={lembar.lines.length} style={{ minWidth: field.width }}>
            <Input
                type={field.type === 'number' ? 'number' : 'text'}
                value={row.fields[field.key] ?? ''}
                onChange={(e) => setField(index, field.key, e.target.value)}
                className={`${cellInput} ${field.align === 'c' ? 'text-center' : ''}`}
                disabled={!can_write}
                aria-label={`${field.label} baris ${index + 1}`}
            />
        </td>
    );

    if (compact) {
        const column = lembar.grid.find((c) => c.key === selectedColumn) ?? lembar.grid[0];
        const mobileField = (field: Field, index: number, row: Row) => (
            <label key={field.key} className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                {field.label}
                <Input
                    type={field.type === 'number' ? 'number' : 'text'}
                    inputMode={field.type === 'number' ? 'decimal' : undefined}
                    value={row.fields[field.key] ?? ''}
                    onChange={(e) => setField(index, field.key, e.target.value)}
                    disabled={!can_write}
                />
            </label>
        );
        const mobileCell = (index: number, line: string, value: string) => {
            if (!column) {
                return null;
            }

            if (lembar.cell_type === 'mark') {
                return (
                    <button
                        type="button"
                        onClick={() => setCell(index, line, column.key, value === '1' ? '' : '1')}
                        disabled={!can_write}
                        aria-pressed={value === '1'}
                        className={`min-h-10 rounded-lg border px-4 text-[13px] font-semibold transition active:scale-95 ${value === '1' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background'}`}
                    >
                        {value === '1' ? '✓ Dilaksanakan' : 'Tandai dilaksanakan'}
                    </button>
                );
            }

            return (
                <ChoiceChips
                    options={codes}
                    value={value}
                    onChange={(v) => setCell(index, line, column.key, v)}
                    disabled={!can_write}
                    tone={(code) => (pair ? (code === codes[0] ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-rose-600 bg-rose-600 text-white') : 'border-primary bg-primary text-primary-foreground')}
                />
            );
        };

        return (
            <>
                <Head title={`${lembar.title} - ${unit.name}`} />
                <div className={`flex flex-col gap-3 p-4 ${can_write ? 'pb-28' : ''}`}>
                    <PageHeader title={lembar.title} description={`${unit.name} · ${periodLabel}${machineName ? ` · ${machineName}` : ''}`} />

                    <div className="grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-3">
                        <div className="col-span-2">
                            <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-full" />
                        </div>
                        {lembar.per_machine && (
                            <div className="col-span-2">
                                <OperasiSelect label="Mesin" value={String(filters.machine_id ?? '')} onChange={(v) => visit({ machine_id: Number(v) })} options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))} className="w-full" />
                            </div>
                        )}
                        <OperasiSelect label={lembar.yearly ? 'Bulan (rekap)' : 'Bulan'} value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} className="w-full" />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} className="w-full" />
                    </div>

                    {lembar.per_machine && options.machines.length === 0 && <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>}

                    <DayStrip
                        items={lembar.grid.map((c) => ({
                            key: c.key,
                            label: c.label,
                            sub: c.sub ?? c.group,
                            isRed: c.is_red,
                            done: rows.some((row) => lembar.lines.some((line) => row.cells[line.key]?.[c.key])),
                        }))}
                        value={column?.key ?? ''}
                        onChange={setSelectedColumn}
                    />

                    <p className="flex flex-wrap gap-x-3 gap-y-1 px-0.5 text-[12px] text-muted-foreground">
                        <span className="font-semibold text-foreground">{lembar.legend_title}:</span>
                        {lembar.codes.map((c) => (
                            <span key={c.code}>
                                <strong>{c.code}</strong> = {c.label}
                            </span>
                        ))}
                    </p>

                    {lembar.sections.map((section) => (
                        <div key={section.key} className="flex flex-col gap-2">
                            {section.title && <p className="px-0.5 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">{section.title}</p>}
                            {rows.map((row, index) => {
                                if (row.section !== section.key) {
                                    return null;
                                }

                                return (
                                    <div key={index} className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3">
                                        {before.map((f) => mobileField(f, index, row))}
                                        {lembar.lines.map((line) => (
                                            <div key={line.key} className="flex flex-col gap-1">
                                                {multiLine && <span className="text-[12px] font-medium text-muted-foreground">{line.label}</span>}
                                                {mobileCell(index, line.key, column ? (row.cells[line.key]?.[column.key] ?? '') : '')}
                                            </div>
                                        ))}
                                        {after.map((f) => mobileField(f, index, row))}
                                        {can_write && (
                                            <Button variant="ghost" size="sm" onClick={() => removeRow(index)} className="self-start text-destructive">
                                                <Trash2 className="size-4" />
                                                Hapus baris
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                            {can_write && (
                                <Button type="button" variant="outline" size="sm" onClick={() => addRow(section.key)} className="h-auto max-w-full self-start py-1.5 text-left whitespace-normal">
                                    <Plus className="size-4" />
                                    Tambah baris{section.title ? ` ${section.title}` : ''}
                                </Button>
                            )}
                        </div>
                    ))}

                    {lembar.note_label && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="catatan">{lembar.note_label}</Label>
                            <Textarea
                                id="catatan"
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    touch();
                                }}
                                disabled={!can_write}
                                rows={3}
                            />
                        </div>
                    )}
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" onClick={save} disabled={saving || !dirty || (lembar.per_machine && !filters.machine_id)}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : dirty ? 'Simpan' : 'Tersimpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    let number = 0;

    return (
        <>
            <Head title={`${lembar.title} - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={lembar.title}
                    description={`${lembar.description} — ${unit.name} · ${periodLabel}${machineName ? ` · ${machineName}` : ''}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {(lembar.menu !== 'input' || can('har.input.view')) && (
                                <Button variant="outline" onClick={() => router.get(lembar.menu === 'input' ? harInput.index().url : harJadwal.index().url)}>
                                    Kembali
                                </Button>
                            )}
                            <Button variant="outline" onClick={() => window.open(routes.pdf(lembar.key, { query }).url, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty || (lembar.per_machine && !filters.machine_id)} className="gap-1.5">
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
                        {lembar.per_machine && (
                            <OperasiSelect label="Mesin" value={String(filters.machine_id ?? '')} onChange={(v) => visit({ machine_id: Number(v) })} options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))} />
                        )}
                        <OperasiSelect
                            label={lembar.yearly ? 'Bulan (rekap)' : 'Bulan'}
                            value={String(filters.month)}
                            onChange={(v) => visit({ month: Number(v) })}
                            options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                        />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    </div>
                    {dirty ? (
                        <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                    ) : has_saved ? (
                        <StatusBadge tone="success">Tersimpan</StatusBadge>
                    ) : (
                        <StatusBadge tone="neutral">Belum ada data — daftar item bawaan</StatusBadge>
                    )}
                </div>

                {lembar.per_machine && options.machines.length === 0 && (
                    <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>
                )}

                <div className="grid grid-cols-[64px_1fr_64px] items-center gap-3 rounded-md border border-border bg-muted/10 p-4 sm:grid-cols-[140px_1fr_140px]">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                    <div className="text-center">
                        {kop_lines.map((line, index) => (
                            <div key={`${index}-${line}`} className={index === kop_lines.length - 1 ? 'text-base font-bold uppercase text-foreground' : 'text-xs font-semibold uppercase text-muted-foreground'}>
                                {line}
                            </div>
                        ))}
                        {machineName && <div className="mt-1 text-xs font-semibold">MESIN: {machineName.toUpperCase()}</div>}
                    </div>
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                </div>

                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <span className="font-semibold">{lembar.legend_title}:</span>
                    {lembar.codes.map((c) => (
                        <span key={c.code}>
                            <strong>{c.code}</strong> = {c.label}
                        </span>
                    ))}
                    {lembar.grid.some((c) => c.is_red) && <span className="text-red-600">Kolom merah = akhir pekan / hari libur</span>}
                </div>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full border-collapse text-xs" style={{ minWidth: Math.max(900, before.length * 120 + lembar.grid.length * perColumn * 26 + 200) }}>
                        <thead className="bg-orange-200/70 text-center text-[11px] font-semibold text-slate-900 dark:bg-orange-950/40 dark:text-orange-100">
                            <tr>
                                <th className={`${th} w-8`} rowSpan={headRows}>NO</th>
                                {before.map((f) => (
                                    <th key={f.key} className={th} rowSpan={headRows}>{f.label}</th>
                                ))}
                                {multiLine && <th className={th} rowSpan={headRows}>{lembar.lines.map((l) => l.label).join(' & ')}</th>}
                                {hasGroups
                                    ? groups.map((g, i) => <th key={`${g.label}-${i}`} className={th} colSpan={g.span * perColumn}>{g.label}</th>)
                                    : lembar.grid.map((c) => <th key={c.key} className={`${th} ${c.is_red ? 'bg-red-500/60 text-white' : ''}`} colSpan={perColumn}>{c.label}</th>)}
                                {lembar.show_count && <th className={th} rowSpan={headRows}>JUMLAH</th>}
                                {after.map((f) => (
                                    <th key={f.key} className={th} rowSpan={headRows}>{f.label}</th>
                                ))}
                                {can_write && <th className={`${th} w-8`} rowSpan={headRows} aria-label="Aksi" />}
                            </tr>
                            {hasGroups && (
                                <tr>
                                    {lembar.grid.map((c) => <th key={c.key} className={th} colSpan={perColumn}>{c.label}</th>)}
                                </tr>
                            )}
                            {hasSub && (
                                <tr>
                                    {lembar.grid.map((c) => <th key={c.key} className={`${th} ${c.is_red ? 'bg-red-500/60 text-white' : ''}`} colSpan={perColumn}>{c.sub}</th>)}
                                </tr>
                            )}
                            {pair && (
                                <tr>
                                    {lembar.grid.flatMap((c) => codes.map((code) => <th key={`${c.key}-${code}`} className={`${th} ${c.is_red ? 'bg-red-500/60 text-white' : ''}`}>{code}</th>))}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {lembar.sections.map((section) => (
                                <Fragment key={section.key}>
                                    {section.title && (
                                        <tr className="bg-yellow-200/70 font-bold italic dark:bg-yellow-900/40">
                                            <td className={`${td} px-2 py-1`} colSpan={totalCols}>{section.title}</td>
                                        </tr>
                                    )}
                                    {rows.map((row, index) => {
                                        if (row.section !== section.key) {
                                            return null;
                                        }

                                        number++;

                                        return lembar.lines.map((line, lineIndex) => {
                                            const cells = row.cells[line.key] ?? {};

                                            return (
                                                <tr key={`${index}-${line.key}`} className="hover:bg-muted/30">
                                                    {lineIndex === 0 && (
                                                        <>
                                                            <td className={`${td} text-center`} rowSpan={lembar.lines.length}>{number}</td>
                                                            {before.map((f) => fieldCell(f, index, row))}
                                                        </>
                                                    )}
                                                    {multiLine && <td className={`${td} px-1 text-center text-[11px] font-medium`}>{line.label}</td>}
                                                    {lembar.grid.map((column) =>
                                                        pair ? (
                                                            <Fragment key={column.key}>{cellEditor(index, line.key, column, cells[column.key] ?? '')}</Fragment>
                                                        ) : (
                                                            <td key={column.key} className={`${td} p-0 text-center ${column.is_red ? 'bg-red-500/25' : ''}`}>
                                                                {cellEditor(index, line.key, column, cells[column.key] ?? '')}
                                                            </td>
                                                        ),
                                                    )}
                                                    {lembar.show_count && <td className={`${td} text-center font-semibold`}>{Object.keys(cells).length}</td>}
                                                    {lineIndex === 0 && (
                                                        <>
                                                            {after.map((f) => fieldCell(f, index, row))}
                                                            {can_write && (
                                                                <td className={`${td} text-center`} rowSpan={lembar.lines.length}>
                                                                    <Button variant="ghost" size="icon" onClick={() => removeRow(index)} className="size-7 text-muted-foreground hover:text-destructive" aria-label={`Hapus baris ${number}`}>
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                </td>
                                                            )}
                                                        </>
                                                    )}
                                                </tr>
                                            );
                                        });
                                    })}
                                    {can_write && (
                                        <tr>
                                            <td className={`${td} bg-muted/10 p-1`} colSpan={totalCols}>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => addRow(section.key)} className="h-7 gap-1 text-[11px] text-primary">
                                                    <Plus className="size-3.5" />
                                                    Tambah Baris{section.title ? ` ${section.title}` : ''}
                                                </Button>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {lembar.show_count && (
                                <tr className="font-bold">
                                    <td className={`${td} py-1 text-center`} colSpan={1 + before.length + (multiLine ? 1 : 0) + lembar.grid.length * perColumn}>TOTAL</td>
                                    <td className={`${td} text-center`}>{grandTotal}</td>
                                    <td className={td} colSpan={after.length + (can_write ? 1 : 0)} />
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {summary && (
                    <div className="flex flex-col gap-1.5">
                        <div className="text-sm font-semibold">
                            {summary.title} <span className="text-xs font-normal text-muted-foreground">(dari data tersimpan)</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="border-collapse text-xs">
                                <thead className="bg-orange-200/70 dark:bg-orange-950/40">
                                    <tr>
                                        {summary.columns.map((label) => (
                                            <th key={label} className={`${th} px-3`}>{label}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {summary.rows.map((line, i) => (
                                        <tr key={i}>
                                            {line.map((cell, j) => (
                                                <td key={j} className={`${td} px-3 py-1 ${typeof cell === 'number' || String(cell).endsWith('%') ? 'text-center' : ''}`}>{cell}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {lembar.note_label && (
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="catatan">{lembar.note_label}</Label>
                        <Textarea
                            id="catatan"
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                touch();
                            }}
                            disabled={!can_write}
                            rows={3}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Breadcrumbs of a HAR sheet page (pages/har/{menu}/{key}/index.tsx).
 */
export function harLembarBreadcrumbs(menu: 'jadwal' | 'input', key: string, title: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        menu === 'input' ? { title: 'Input Pemeliharaan', href: harInput.index() } : { title: 'Jadwal Pemeliharaan', href: harJadwal.index() },
        { title, href: (menu === 'input' ? inputLembar : jadwalLembar).index(key) },
    ];
}
