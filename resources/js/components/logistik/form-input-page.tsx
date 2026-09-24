import { Head, router } from '@inertiajs/react';
import { Check, Download, FileSpreadsheet, ImagePlus, Plus, Save, Trash2, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computeRow, downloadLogistikFormWorkbook, sectionTotals } from '@/lib/logistik-form-excel';
import type { FormColumn, FormDefinition, FormRow, FormSummary, FormValue } from '@/lib/logistik-form-excel';
import { dashboard } from '@/routes';
import logistikInput from '@/routes/logistik/input';
import formRoutes from '@/routes/logistik/input/form';
import type { IdName } from '@/types';

export type LogistikFormInputPageProps = {
    form: FormDefinition;
    unit: IdName;
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[] };
    rows: FormRow[];
    summary: FormSummary;
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const cellTextarea =
    'block min-h-14 w-full resize-y rounded-none border-0 bg-transparent px-1 py-1 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';
const th = 'border border-slate-400 p-1';
const td = 'border border-border';
const ALIGN = { l: 'text-left', c: 'text-center', r: 'text-right' } as const;

/** PHP sends an empty data map as a JSON array. */
const normalize = (list: FormRow[]): FormRow[] => list.map((row) => ({ ...row, data: Array.isArray(row.data) ? {} : row.data, image_urls: Array.isArray(row.image_urls) ? {} : row.image_urls }));

/**
 * Shared page of the Logistik & Gudang table forms (App\Support\LogistikForms) —
 * Laporan Pendukung, Peralatan/Material/Tools, Kondisi Stok, Unsafe Action &
 * Condition, Permit To Work — with live computed columns & totals, photos,
 * date & tick-box cells, Simpan, PDF, Excel. Each form has its own thin page at
 * resources/js/pages/logistik/input/{form}/index.tsx that renders this component.
 */
export function LogistikFormInputPage({ form, unit, filters, options, rows: initialRows, summary, has_saved, can_write }: LogistikFormInputPageProps) {
    const [rows, setRows] = useState<FormRow[]>(() => normalize(initialRows));
    // New photos per row index & image column, uploaded on Simpan.
    const [uploads, setUploads] = useState<Record<number, Record<string, File>>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} Tahun ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };
    const columns = form.columns;
    const hasGroups = columns.some((column) => column.group);
    const defaults = Object.fromEntries(columns.filter((column) => column.default !== undefined).map((column) => [column.key, column.default ?? null]));

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(formRoutes.index(form.key).url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const setValue = (index: number, key: string, value: FormValue) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, data: { ...row.data, [key]: value } } : row)));
        setDirty(true);
    };

    const toggleCheck = (index: number, column: FormColumn) => {
        setRows((current) =>
            current.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                const checked = String(row.data[column.key] ?? '') !== '1';
                const siblings = column.exclusive && checked
                    ? Object.fromEntries(columns.filter((c) => c.exclusive === column.exclusive && c.key !== column.key).map((c) => [c.key, null]))
                    : {};

                return { ...row, data: { ...row.data, ...siblings, [column.key]: checked ? 1 : null } };
            }),
        );
        setDirty(true);
    };

    const addRow = (section: string) => {
        setRows((current) => {
            const last = current.map((row) => row.section).lastIndexOf(section);
            const next = [...current];
            next.splice(last + 1, 0, { section, data: { ...defaults }, image_urls: {} });

            return next;
        });
        setDirty(true);
    };

    const removeRow = (index: number) => {
        setRows((current) => current.filter((_, i) => i !== index));
        setUploads((current) => Object.fromEntries(Object.entries(current).filter(([key]) => Number(key) !== index).map(([key, files]) => [Number(key) > index ? Number(key) - 1 : Number(key), files])));
        setDirty(true);
    };

    const setPhoto = (index: number, key: string, file: File | null) => {
        setUploads((current) => {
            const files = { ...(current[index] ?? {}) };

            if (file) {
                files[key] = file;
            } else {
                delete files[key];
            }

            return { ...current, [index]: files };
        });
        setDirty(true);
    };

    const removeSavedPhoto = (index: number, key: string) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, data: { ...row.data, [key]: null }, image_urls: Object.fromEntries(Object.entries(row.image_urls).filter(([k]) => k !== key)) } : row)));
        setDirty(true);
    };

    const save = () => {
        const hasFiles = Object.values(uploads).some((files) => Object.keys(files).length > 0);
        const payload = rows.map((row, index) => ({
            section: row.section,
            data: Object.fromEntries(columns.filter((column) => column.type !== 'computed').map((column) => [column.key, row.data[column.key] ?? null])),
            ...(hasFiles ? { files: uploads[index] ?? {} } : {}),
        }));

        setSaving(true);
        router.post(formRoutes.store(form.key).url, { ...query, rows: payload }, {
            forceFormData: hasFiles,
            preserveScroll: true,
            onSuccess: (page) => {
                setRows(normalize((page.props as unknown as LogistikFormInputPageProps).rows));
                setUploads({});
                setDirty(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadLogistikFormWorkbook(form, unit.name, periodLabel, rows, summary, `${form.title.replace(/[^A-Za-z0-9]+/g, '_')}_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const editor = (column: FormColumn, index: number, data: Record<string, FormValue>) => {
        const value = data[column.key];
        const align = ALIGN[column.align ?? 'l'];

        switch (column.type) {
            case 'computed':
                return <span className={`block px-1 text-xs font-semibold ${align}`}>{value ?? ''}</span>;
            case 'number':
                return (
                    <Input
                        type="number"
                        step="any"
                        value={value ?? ''}
                        onChange={(e) => setValue(index, column.key, e.target.value === '' ? null : Number(e.target.value))}
                        className={`${cellInput} ${align}`}
                        disabled={!can_write}
                    />
                );
            case 'date':
                return (
                    <Input
                        type="date"
                        value={String(value ?? '')}
                        onChange={(e) => setValue(index, column.key, e.target.value === '' ? null : e.target.value)}
                        className={`${cellInput} ${align}`}
                        disabled={!can_write}
                    />
                );
            case 'check': {
                const checked = String(value ?? '') === '1';

                return (
                    <div className="flex justify-center py-1">
                        <button
                            type="button"
                            role="checkbox"
                            aria-checked={checked}
                            aria-label={`${column.label} baris ${index + 1}`}
                            onClick={() => toggleCheck(index, column)}
                            disabled={!can_write}
                            className={`flex size-5 items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                                checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background hover:border-primary'
                            }`}
                        >
                            {checked && <Check className="size-3.5" />}
                        </button>
                    </div>
                );
            }
            case 'textarea':
                return <textarea rows={3} value={String(value ?? '')} onChange={(e) => setValue(index, column.key, e.target.value)} className={cellTextarea} disabled={!can_write} />;
            case 'select':
                return <PdmCellSelect value={String(value ?? '')} onChange={(next) => setValue(index, column.key, next || null)} options={column.options ?? []} className="h-8 justify-center" disabled={!can_write} />;
            case 'image': {
                const pending = uploads[index]?.[column.key];
                const url = pending ? URL.createObjectURL(pending) : rows[index].image_urls[column.key];

                return (
                    <div className="flex items-center justify-center p-1">
                        {url ? (
                            <span className="relative">
                                <img src={url} alt={column.label} className={`h-16 w-20 rounded object-cover ${pending ? 'ring-2 ring-amber-400' : ''}`} />
                                {can_write && (
                                    <button
                                        type="button"
                                        onClick={() => (pending ? setPhoto(index, column.key, null) : removeSavedPhoto(index, column.key))}
                                        className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white"
                                        aria-label="Hapus foto"
                                    >
                                        <X className="size-3" />
                                    </button>
                                )}
                            </span>
                        ) : (
                            can_write && (
                                <label className="flex h-16 w-20 cursor-pointer items-center justify-center rounded border border-dashed border-border text-muted-foreground hover:bg-muted" title={`Unggah foto ${column.label.toLowerCase()}`}>
                                    <ImagePlus className="size-4" />
                                    <input type="file" accept="image/*" className="hidden" onChange={(e) => setPhoto(index, column.key, e.target.files?.[0] ?? null)} />
                                </label>
                            )
                        )}
                    </div>
                );
            }
            default:
                return <Input value={String(value ?? '')} onChange={(e) => setValue(index, column.key, e.target.value)} className={`${cellInput} ${align}`} disabled={!can_write} />;
        }
    };

    // Header row 1: grouped columns share one cell.
    const headerCells: { group?: string; label: string; span: number; width?: number }[] = [];
    columns.forEach((column) => {
        const last = headerCells[headerCells.length - 1];

        if (column.group && last?.group === column.group) {
            last.span++;
        } else {
            headerCells.push({ group: column.group, label: column.group ?? column.label, span: 1, width: column.width });
        }
    });

    const tableWidth = 40 + columns.reduce((sum, column) => sum + (column.width ?? 110), 0) + (can_write ? 40 : 0);

    return (
        <>
            <Head title={form.title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={form.title}
                    description={`${form.description} — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(logistikInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(formRoutes.pdf(form.key, { query }).url, '_blank')} className="gap-1.5">
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

                <PdmInputToolbar filters={filters} options={options} onChange={visit} dirty={dirty} />

                <p className="text-[13px] text-muted-foreground">
                    {!has_saved && 'Belum ada data tersimpan untuk periode ini — isian bawaan sudah disiapkan. '}
                    Kolom berwarna tebal dihitung otomatis. Baris yang kosong tidak ikut disimpan.
                </p>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="border-collapse text-xs" style={{ minWidth: tableWidth, width: '100%' }}>
                        <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                            <tr>
                                <th rowSpan={hasGroups ? 2 : 1} className={`${th} w-10`}>NO</th>
                                {headerCells.map((cell, i) => (
                                    <th
                                        key={i}
                                        colSpan={cell.span}
                                        rowSpan={!cell.group && hasGroups ? 2 : 1}
                                        className={th}
                                        style={!cell.group && cell.width ? { minWidth: cell.width } : undefined}
                                    >
                                        {cell.label}
                                    </th>
                                ))}
                                {can_write && <th rowSpan={hasGroups ? 2 : 1} className={`${th} w-9`} />}
                            </tr>
                            {hasGroups && (
                                <tr>
                                    {columns
                                        .filter((column) => column.group)
                                        .map((column) => (
                                            <th key={column.key} className={`${th} text-[10px]`} style={{ minWidth: column.width }}>{column.label}</th>
                                        ))}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {form.sections.map((section) => {
                                const sectionIndexes = rows.map((row, index) => (row.section === section.key ? index : -1)).filter((index) => index !== -1);
                                const computed = sectionIndexes.map((index) => computeRow(columns, rows[index].data));
                                const totals = sectionTotals(section, computed);

                                return (
                                    <Fragment key={section.key}>
                                        {section.title && (
                                            <tr className="bg-yellow-300 font-semibold text-slate-900 dark:bg-yellow-700 dark:text-white">
                                                <td colSpan={columns.length + 1 + (can_write ? 1 : 0)} className={`${td} p-1`}>
                                                    <div className="flex items-center justify-between">
                                                        {section.title}
                                                        {can_write && (
                                                            <Button variant="ghost" size="sm" className="h-6 gap-1 text-xs" onClick={() => addRow(section.key)}>
                                                                <Plus className="size-3.5" />
                                                                Tambah Baris
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                        {sectionIndexes.map((index, position) => (
                                            <tr key={index} className="hover:bg-muted/30">
                                                <td className={`${td} p-1 text-center`}>{position + 1}</td>
                                                {columns.map((column) => (
                                                    <td key={column.key} className={`${td} p-0 ${column.type === 'computed' ? 'bg-muted/40' : ''}`}>
                                                        {editor(column, index, computed[position])}
                                                    </td>
                                                ))}
                                                {can_write && (
                                                    <td className={`${td} p-0 text-center`}>
                                                        <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                        {section.totals.length > 0 && (
                                            <tr className="bg-muted/50 font-semibold">
                                                <td className={td} />
                                                {columns.map((column, i) => (
                                                    <td key={column.key} className={`${td} p-1 text-center`}>{i === 0 ? 'Total' : (totals[column.key] ?? '')}</td>
                                                ))}
                                                {can_write && <td className={td} />}
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {can_write && form.sections.length === 1 && !form.sections[0].title && (
                    <div>
                        <Button variant="outline" size="sm" onClick={() => addRow(form.sections[0].key)} className="gap-1.5">
                            <Plus className="size-4" />
                            Tambah Baris
                        </Button>
                    </div>
                )}

                {summary && (
                    <div className="flex flex-col gap-1">
                        <table className="w-fit border-collapse text-xs">
                            <thead className="bg-[#5bc8f5] text-[11px] font-semibold text-slate-900">
                                <tr>
                                    <th colSpan={summary.columns.length} className={th}>{summary.title}</th>
                                </tr>
                                <tr>
                                    {summary.columns.map((label) => (
                                        <th key={label} className={`${th} px-3`}>{label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {summary.rows.map((row, i) => (
                                    <tr key={i}>
                                        {row.map((cell, j) => (
                                            <td key={j} className={`${td} px-3 py-1 text-center`}>{cell}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="text-[12px] text-muted-foreground">Ringkasan dihitung dari data tersimpan (perbarui dengan Simpan).</p>
                    </div>
                )}

                {form.notes.length > 0 && (
                    <div className="text-[12px] text-muted-foreground">
                        <span className="font-semibold">Catatan:</span>
                        <ol className="ml-5 list-decimal">
                            {form.notes.map((note) => (
                                <li key={note}>{note}</li>
                            ))}
                        </ol>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Breadcrumbs of a Logistik table form page (pages/logistik/input/{form}/index.tsx).
 */
export function logistikFormBreadcrumbs(formKey: string, title: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Logistik & Gudang', href: logistikInput.index() },
        { title, href: formRoutes.index(formKey) },
    ];
}
