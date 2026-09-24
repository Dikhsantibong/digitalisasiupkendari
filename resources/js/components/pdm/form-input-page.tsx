import { Head, router } from '@inertiajs/react';
import { Check, Download, FileSpreadsheet, ImagePlus, Plus, Save, Trash2, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { PdmDocumentHeader } from '@/components/pdm/document-header';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { columnTotal, downloadPdmFormWorkbook, sectionNumberOffset } from '@/lib/pdm-input-excel';
import type { PdmFormColumn, PdmFormDefinition, PdmFormField, PdmFormSection, PdmFormSummary } from '@/lib/pdm-input-excel';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import forms from '@/routes/pdm/input/forms';
import type { IdName } from '@/types';

type RowData = Record<string, string | null>;
type Image = { path: string; url: string };

export type PdmFormInputPageProps = {
    form: PdmFormDefinition;
    kop_lines: string[];
    unit: IdName;
    filters: PdmInputFilters & { machine_id: number | null };
    options: { units: IdName[]; years: number[]; machines: IdName[] };
    document: {
        id: number | null;
        saved: boolean;
        header: Record<string, string | string[] | null>;
        rows: Record<string, RowData[]>;
        summary: PdmFormSummary;
        images: Record<string, Image[]>;
    };
    can_write: boolean;
};

const HEAD_COLORS: Record<string, string> = {
    orange: 'bg-[#ed7d31] text-slate-900',
    navy: 'bg-[#1f4e79] text-white',
    cyan: 'bg-[#00b0f0] text-slate-900',
};

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const textareaClass = 'min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

const blankRow = (section: PdmFormSection): RowData => Object.fromEntries(section.columns.map((c) => [c.key, null]));

/**
 * Shared PdM input page for the forms defined in App\Support\PdmForms
 * (Checklist 5S5R, Kualitas Air Pendingin, Pelumas, Vibrasi, Kontrol Material,
 * Patrol Check PdM, Checklist Patrol Check). Each form has its own thin page
 * at resources/js/pages/pdm/input/{form}/index.tsx that renders this component:
 * header fields, section tables (with group/unit headers and totals), footer
 * fields & photos, Simpan, PDF and Excel. Signatures come later with the
 * verification flow.
 */
export function PdmFormInputPage({ form, kop_lines, unit, filters, options, document, can_write }: PdmFormInputPageProps) {
    const [header, setHeader] = useState<Record<string, string | null>>(() =>
        Object.fromEntries(Object.entries(document.header).filter(([, v]) => !Array.isArray(v)).map(([k, v]) => [k, (v as string | null) ?? null])),
    );
    const [rows, setRows] = useState<Record<string, RowData[]>>(document.rows);
    const [keptImages, setKeptImages] = useState<Record<string, Image[]>>(document.images);
    const [uploads, setUploads] = useState<Record<string, File[]>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const machineName = options.machines.find((m) => m.id === filters.machine_id)?.name;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...(form.per_machine && filters.machine_id ? { machine_id: filters.machine_id } : {}) };
    const touch = () => setDirty(true);

    const visit = (patch: Partial<PdmFormInputPageProps['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(forms.index(form.key).url, { ...query, ...patch }, { preserveScroll: true });
    };

    const setField = (key: string, value: string) => {
        setHeader((current) => ({ ...current, [key]: value === '' ? null : value }));
        touch();
    };

    const setCell = (section: string, index: number, key: string, value: string) => {
        setRows((current) => ({
            ...current,
            [section]: current[section].map((row, i) => (i === index ? { ...row, [key]: value === '' ? null : value } : row)),
        }));
        touch();
    };

    /** Tick box cell; columns sharing an `exclusive` group allow one tick per row (Ya / Tidak / N/A). */
    const toggleCheck = (section: PdmFormSection, index: number, column: PdmFormColumn) => {
        setRows((current) => ({
            ...current,
            [section.key]: current[section.key].map((row, i) => {
                if (i !== index) {
                    return row;
                }

                const checked = row[column.key] !== '1';
                const siblings = column.exclusive && checked
                    ? Object.fromEntries(section.columns.filter((c) => c.exclusive === column.exclusive && c.key !== column.key).map((c) => [c.key, null]))
                    : {};

                return { ...row, ...siblings, [column.key]: checked ? '1' : null };
            }),
        }));
        touch();
    };

    const addRow = (section: PdmFormSection) => {
        setRows((current) => ({ ...current, [section.key]: [...current[section.key], blankRow(section)] }));
        touch();
    };

    const removeRow = (section: string, index: number) => {
        setRows((current) => ({ ...current, [section]: current[section].filter((_, i) => i !== index) }));
        touch();
    };

    const save = () => {
        const imageFields = form.fields.filter((f) => f.type === 'images');
        const payload = {
            ...query,
            header: {
                ...header,
                ...Object.fromEntries(imageFields.map((f) => [f.key, (keptImages[f.key] ?? []).map((image) => image.path)])),
            },
            rows,
            uploads,
        };

        setSaving(true);
        router.post(forms.store(form.key).url, payload, {
            forceFormData: imageFields.length > 0,
            preserveScroll: true,
            onSuccess: () => {
                setDirty(false);
                setUploads({});
            },
            onFinish: () => setSaving(false),
        });
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadPdmFormWorkbook(
                form,
                [kop_lines[0] ?? '', kop_lines[1] ?? '', kop_lines[2] ?? ''],
                [kop_lines[3], periodLabel.toUpperCase(), machineName ? `MESIN ${machineName}` : null].filter(Boolean).join(' — '),
                header,
                rows,
                document.summary,
                `${form.title.replace(/\s+/g, '_')}_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}${machineName ? `_${machineName.replace(/\s+/g, '_')}` : ''}.xlsx`,
            );
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const fieldInput = (field: PdmFormField) => {
        const value = header[field.key] ?? '';

        if (field.type === 'textarea') {
            return <textarea value={value} onChange={(e) => setField(field.key, e.target.value)} disabled={!can_write} className={textareaClass} />;
        }

        if (field.type === 'images') {
            return (
                <div className="flex flex-col gap-2">
                    <div className="flex flex-wrap gap-2">
                        {(keptImages[field.key] ?? []).map((image) => (
                            <div key={image.path} className="relative">
                                <img src={image.url} alt={field.label} className="h-24 rounded border object-cover" />
                                {can_write && (
                                    <button
                                        type="button"
                                        className="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                                        onClick={() => {
                                            setKeptImages((current) => ({ ...current, [field.key]: current[field.key].filter((i) => i.path !== image.path) }));
                                            touch();
                                        }}
                                        title="Hapus foto"
                                    >
                                        <X className="size-3" />
                                    </button>
                                )}
                            </div>
                        ))}
                        {(uploads[field.key] ?? []).map((file) => (
                            <span key={file.name} className="rounded border border-dashed px-2 py-1 text-xs text-muted-foreground">{file.name} (baru)</span>
                        ))}
                    </div>
                    {can_write && (
                        <label className="inline-flex w-fit cursor-pointer items-center gap-1.5 rounded-md border border-input px-3 py-1.5 text-xs hover:bg-muted">
                            <ImagePlus className="size-4" />
                            Tambah foto
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                className="hidden"
                                onChange={(e) => {
                                    const files = Array.from(e.target.files ?? []);
                                    setUploads((current) => ({ ...current, [field.key]: [...(current[field.key] ?? []), ...files] }));
                                    touch();
                                    e.target.value = '';
                                }}
                            />
                        </label>
                    )}
                </div>
            );
        }

        return (
            <Input
                type={field.type === 'date' ? 'date' : field.type === 'time' ? 'time' : field.type === 'number' ? 'number' : 'text'}
                value={value}
                onChange={(e) => setField(field.key, e.target.value)}
                disabled={!can_write}
            />
        );
    };

    const fieldBlock = (position: 'header' | 'footer') => {
        const fields = form.fields.filter((f) => (f.position ?? 'header') === position);

        if (fields.length === 0) {
            return null;
        }

        const groups = fields.reduce<Record<string, PdmFormField[]>>((acc, field) => {
            (acc[field.group ?? ''] ??= []).push(field);

            return acc;
        }, {});

        return Object.entries(groups).map(([group, items]) => (
            <div key={`${position}-${group}`} className="flex flex-col gap-2 rounded-md border border-border bg-card p-3">
                {group && <span className="text-[13px] font-semibold">{group}</span>}
                <div className="grid gap-3 md:grid-cols-2">
                    {items.map((field) => (
                        <div key={field.key} className={`flex flex-col gap-1.5 ${field.type === 'textarea' || field.type === 'images' ? 'md:col-span-1' : ''}`}>
                            <Label>{field.label}</Label>
                            {fieldInput(field)}
                        </div>
                    ))}
                </div>
            </div>
        ));
    };

    const cellEditor = (section: PdmFormSection, column: PdmFormColumn, row: RowData, index: number) => {
        const value = row[column.key] ?? '';

        if (column.type === 'readonly') {
            return <span className="block px-1 text-center text-xs font-medium">{value}</span>;
        }

        if (column.type === 'check') {
            const checked = value === '1';

            return (
                <div className="flex justify-center">
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked={checked}
                        aria-label={`${column.label} baris ${index + 1}`}
                        disabled={!can_write}
                        onClick={() => toggleCheck(section, index, column)}
                        className={`flex size-5 items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                            checked
                                ? column.key === 'tidak'
                                    ? 'border-rose-600 bg-rose-600 text-white'
                                    : column.key === 'na'
                                      ? 'border-slate-500 bg-slate-500 text-white'
                                      : 'border-emerald-600 bg-emerald-600 text-white'
                                : 'border-input bg-background hover:border-primary'
                        }`}
                    >
                        {checked && <Check className="size-3.5" />}
                    </button>
                </div>
            );
        }

        if (column.type === 'select') {
            return (
                <PdmCellSelect value={value} onChange={(next) => setCell(section.key, index, column.key, next)} options={column.options ?? []} disabled={!can_write} />
            );
        }

        return (
            <Input
                type={column.type === 'number' ? 'number' : column.type === 'date' ? 'date' : column.type === 'time' ? 'time' : 'text'}
                value={value}
                onChange={(e) => setCell(section.key, index, column.key, e.target.value)}
                className={`${cellInput} ${column.align === 'c' || column.type === 'number' ? 'text-center' : ''}`}
                disabled={!can_write}
            />
        );
    };

    const sectionTable = (section: PdmFormSection) => {
        const grouped = section.columns.some((c) => c.group);
        const units = section.columns.some((c) => c.unit);
        const headRows = 1 + (grouped ? 1 : 0) + (units ? 1 : 0);
        const head = HEAD_COLORS[form.header_color] ?? HEAD_COLORS.orange;
        const list = rows[section.key] ?? [];
        const groupCells: { label: string; span: number; column: PdmFormColumn; grouped: boolean }[] = [];
        section.columns.forEach((column) => {
            const last = groupCells[groupCells.length - 1];

            if (column.group && last?.grouped && last.label === column.group) {
                last.span++;
            } else {
                groupCells.push({ label: column.group ?? column.label, span: 1, column, grouped: !!column.group });
            }
        });
        const canAdd = can_write && !section.fixed;

        return (
            <div key={section.key} className="flex flex-col gap-1.5">
                <div className="flex items-center justify-between gap-2">
                    <div>
                        <div className="text-sm font-semibold">{section.title}</div>
                        {section.note && <div className="text-xs text-muted-foreground">{section.note}</div>}
                    </div>
                    {canAdd && (
                        <Button variant="outline" size="sm" onClick={() => addRow(section)} className="h-7 gap-1 text-xs">
                            <Plus className="size-3.5" />
                            Tambah Baris
                        </Button>
                    )}
                </div>
                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full border-collapse text-xs" style={{ minWidth: Math.max(720, section.columns.length * 80) }}>
                        <thead className={`${head} text-center text-[11px] font-semibold`}>
                            <tr>
                                <th rowSpan={headRows} className="w-9 border border-slate-400 p-1">No</th>
                                {groupCells.map((cell, i) =>
                                    cell.grouped ? (
                                        <th key={i} colSpan={cell.span} className="border border-slate-400 p-1">{cell.label}</th>
                                    ) : (
                                        <th
                                            key={i}
                                            rowSpan={headRows - (units && cell.column.unit ? 1 : 0)}
                                            className="border border-slate-400 p-1 leading-tight"
                                            style={cell.column.width ? { minWidth: cell.column.width } : undefined}
                                        >
                                            {cell.label}
                                        </th>
                                    ),
                                )}
                                {canAdd && <th rowSpan={headRows} className="w-9 border border-slate-400 p-1" />}
                            </tr>
                            {grouped && (
                                <tr>
                                    {section.columns.filter((c) => c.group).map((c) => (
                                        <th key={c.key} className="border border-slate-400 p-1 leading-tight" style={c.width ? { minWidth: c.width } : undefined}>{c.label}</th>
                                    ))}
                                </tr>
                            )}
                            {units && (
                                <tr>
                                    {section.columns.filter((c) => c.unit || c.group).map((c) => (
                                        <th key={c.key} className="border border-slate-400 p-0.5 font-normal">{c.unit ?? ''}</th>
                                    ))}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {list.map((row, index) => (
                                <tr key={index} className="hover:bg-muted/30">
                                    <td className="border border-border p-1 text-center">{sectionNumberOffset(form, rows, section.key) + index + 1}</td>
                                    {section.columns.map((column) => (
                                        <td key={column.key} className="border border-border p-0" style={column.width ? { minWidth: column.width } : undefined}>
                                            {cellEditor(section, column, row, index)}
                                        </td>
                                    ))}
                                    {canAdd && (
                                        <td className="border border-border p-0 text-center">
                                            <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(section.key, index)} title="Hapus baris">
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {section.totals && section.totals.length > 0 && (
                                <tr className="bg-muted/50 font-semibold">
                                    <td colSpan={2} className="border border-border p-1 text-center">TOTAL</td>
                                    {section.columns.slice(1).map((column) => (
                                        <td key={column.key} className="border border-border p-1 text-center">
                                            {section.totals?.includes(column.key) ? columnTotal(list, column.key) : ''}
                                        </td>
                                    ))}
                                    {canAdd && <td className="border border-border" />}
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title={form.title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={form.title}
                    description={`${form.description} — ${unit.name} · ${periodLabel}${machineName ? ` · ${machineName}` : ''}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(pdmInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(forms.pdf(form.key, { query }).url, '_blank')} className="gap-1.5">
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

                <div className="flex flex-wrap items-end gap-3">
                    <PdmInputToolbar filters={filters} options={options} onChange={visit} dirty={dirty} />
                    {form.per_machine && (
                        <div className="rounded-md border border-border bg-card p-3">
                            {options.machines.length > 0 ? (
                                <OperasiSelect
                                    label="Mesin"
                                    value={String(filters.machine_id ?? '')}
                                    onChange={(value) => visit({ machine_id: Number(value) })}
                                    options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                                />
                            ) : (
                                <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>
                            )}
                        </div>
                    )}
                </div>

                {!document.saved && (
                    <p className="text-[13px] text-muted-foreground">
                        Belum ada data tersimpan untuk periode ini{machineName ? ` (${machineName})` : ''}. Baris kosong tidak ikut disimpan. PDF mencetak data tersimpan; Excel mencetak isian di layar.
                    </p>
                )}

                <PdmDocumentHeader lines={kop_lines} period={`${periodLabel}${machineName ? ` · Mesin ${machineName}` : ''}`} />

                {fieldBlock('header')}

                {form.sections.map((section) => (
                    <Fragment key={section.key}>{sectionTable(section)}</Fragment>
                ))}

                {document.summary && (
                    <div className="flex flex-col gap-1.5">
                        <div className="text-sm font-semibold">{document.summary.title}</div>
                        <div className="overflow-x-auto rounded-md border border-border bg-card md:w-2/3">
                            <table className="w-full border-collapse text-xs">
                                <thead className={`${HEAD_COLORS[form.header_color] ?? HEAD_COLORS.orange} text-center text-[11px] font-semibold`}>
                                    <tr>
                                        {document.summary.columns.map((label) => (
                                            <th key={label} className="border border-slate-400 p-1">{label}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {document.summary.rows.map((row, i) => (
                                        <tr key={i}>
                                            {row.map((cell, j) => (
                                                <td key={j} className="border border-border p-1 text-center">{cell}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-[12px] text-muted-foreground">Akumulatif dihitung dari data tersimpan (perbarui dengan Simpan).</p>
                    </div>
                )}

                {fieldBlock('footer')}
            </div>
        </>
    );
}

/**
 * Breadcrumbs of a generic PdM form page (pages/pdm/input/{form}/index.tsx).
 */
export function pdmFormBreadcrumbs(formKey: string, title: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title, href: forms.index(formKey) },
    ];
}
