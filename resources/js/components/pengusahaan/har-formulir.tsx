import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileDown, History, Plus, RotateCcw, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout, useInMobileShell } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import harPengusahaan from '@/routes/har/pengusahaan';
import type { IdName } from '@/types';

export type FormRow = Record<string, string | number | null>;

export type FormValues = Record<string, unknown>;

export type HarField = {
    key: string;
    label: string;
    type?: 'text' | 'number' | 'textarea' | 'select';
    options?: readonly string[];
    optionLabels?: Record<string, string>;
    placeholder?: string;
    /** Grid / table width hint. */
    wide?: boolean;
};

export type HarTable = {
    /** JSON column of the rows (checklist_items, measurements, items …). */
    key: string;
    title: string;
    description?: string;
    /** Header & value of the first (fixed) column, e.g. "Silinder" / the cylinder number. */
    rowHeader: string;
    rowLabel: (row: FormRow, index: number) => string;
    columns: HarField[];
    /** The rows follow `cylinders_count`: one per cylinder, numbered in `numberKey`. */
    cylinders?: { numberKey: string; blank: FormRow };
    /** Rows can be added / removed by the user. */
    addable?: { label: string; blank: (rows: FormRow[]) => FormRow };
};

export type HarFormulirProps = {
    unit: IdName;
    units: IdName[];
    machines: IdName[];
    filters: { unit_id: number; machine_id: number | null; test_date: string };
    form: FormValues;
    document: { title: string; number: string; revision: string; effective_date: string };
    signatories: { title: string; name: string }[];
    has_saved: boolean;
    history: { id: number; test_date: string; updated_at: string | null }[];
    pdf_url: string;
    can_write: boolean;
};

/** The engine identity block shared by most formulir. */
export const MACHINE_FIELDS: HarField[] = [
    { key: 'brand', label: 'Merk' },
    { key: 'model_type', label: 'Type' },
    { key: 'serial_number', label: 'No. Seri' },
    { key: 'machine_number', label: 'No. Mesin' },
    { key: 'installed_power', label: 'Daya Terpasang (kW)' },
    { key: 'capable_power', label: 'Daya Mampu (kW)' },
    { key: 'rpm', label: 'RPM' },
];

/** ✓ / X checklist cell (the PDF prints ✓ for "v"). */
export const CHECK_FIELD = { type: 'select', options: ['v', 'X', '-'], optionLabels: { v: '✓ Baik', X: '✗ Tidak', '-': '— N/A' } } as const;

/** Baik / Tidak condition cell. */
export const CONDITION_FIELD = { type: 'select', options: ['baik', 'tidak'], optionLabels: { baik: 'Baik', tidak: 'Tidak Baik' } } as const;

const TONE: Record<string, string> = {
    v: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    baik: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    X: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
    tidak: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

const CHIP_TONE: Record<string, string> = {
    v: 'border-emerald-600 bg-emerald-600 text-white',
    baik: 'border-emerald-600 bg-emerald-600 text-white',
    X: 'border-rose-600 bg-rose-600 text-white',
    tidak: 'border-rose-600 bg-rose-600 text-white',
};

const formatDate = (iso: string): string => {
    const date = new Date(`${iso}T00:00:00`);

    return Number.isNaN(date.getTime()) ? iso : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

/**
 * An Akses 2 — Pengusahaan HAR technical formulir (Prelube Test, Clearance
 * Valve, Tegangan Baterai …): pick unit, machine and test date, fill the
 * engine data, the measurement tables and the notes, then Simpan. The kop is
 * static and the signatories come from the unit's jabatan holders; the PDF is
 * rendered by the server from the saved data. On a phone the tables become
 * row cards with the actions pinned to the bottom.
 */
export function HarFormulirPage({
    props,
    description,
    indexUrl,
    storeUrl,
    identity = MACHINE_FIELDS,
    tables,
    notes = [],
    extra,
    extraPayload,
}: {
    props: HarFormulirProps;
    description: string;
    indexUrl: string;
    storeUrl: string;
    identity?: HarField[];
    tables: HarTable[];
    notes?: HarField[];
    /** Extra section under the notes (e.g. a photo). */
    extra?: (state: { canWrite: boolean; markDirty: () => void }) => ReactNode;
    /** Extra values posted with the form (e.g. a photo file); a File switches to multipart. */
    extraPayload?: () => Record<string, unknown>;
}) {
    const { unit, units, machines, filters, document, signatories, has_saved, history, pdf_url, can_write } = props;
    const compact = useCompactLayout();
    const inShell = useInMobileShell();
    const [form, setForm] = useState<FormValues>(props.form);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // A new record (unit / machine / date) replaces the form.
    const signature = `${filters.unit_id}-${filters.machine_id}-${filters.test_date}-${has_saved}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setForm(props.form);
        setDirty(false);
    }

    const machineName = machines.find((m) => m.id === filters.machine_id)?.name ?? '—';

    const visit = (patch: Partial<HarFormulirProps['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah?')) {
            return;
        }

        router.get(indexUrl, { ...filters, ...patch }, { preserveScroll: true, replace: true });
    };

    const setValue = (key: string, value: unknown) => {
        setForm((prev) => ({ ...prev, [key]: value }));
        setDirty(true);
    };

    const rowsOf = (table: HarTable): FormRow[] => (Array.isArray(form[table.key]) ? (form[table.key] as FormRow[]) : []);

    const setCell = (table: HarTable, index: number, column: string, value: string) =>
        setValue(
            table.key,
            rowsOf(table).map((row, i) => (i === index ? { ...row, [column]: value } : row)),
        );

    const setCylinders = (count: number) => {
        const cylinders = Math.min(32, Math.max(1, count || 1));
        const next: FormValues = { ...form, cylinders_count: cylinders };

        tables
            .filter((table) => table.cylinders)
            .forEach((table) => {
                const { numberKey, blank } = table.cylinders!;
                const current = new Map(rowsOf(table).map((row) => [Number(row[numberKey]), row]));

                next[table.key] = Array.from({ length: cylinders }, (_, i) => current.get(i + 1) ?? { ...blank, [numberKey]: i + 1 });
            });

        setForm(next);
        setDirty(true);
    };

    const addRow = (table: HarTable) => setValue(table.key, [...rowsOf(table), table.addable!.blank(rowsOf(table))]);

    const removeRow = (table: HarTable, index: number) => setValue(table.key, rowsOf(table).filter((_, i) => i !== index));

    const reset = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setForm(props.form);
            setDirty(false);
        }
    };

    const save = () => {
        const extraValues = extraPayload?.() ?? {};
        const hasFile = Object.values(extraValues).some((value) => value instanceof File);

        setSaving(true);
        router.post(
            storeUrl,
            { unit_id: filters.unit_id, machine_id: filters.machine_id, test_date: filters.test_date, ...form, ...extraValues } as never,
            {
                preserveScroll: true,
                forceFormData: hasFile,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const openPdf = () => {
        if (dirty && !window.confirm('Perubahan belum disimpan — PDF memakai data tersimpan. Tetap buka PDF?')) {
            return;
        }

        window.open(pdf_url, '_blank', 'noopener');
    };

    const text = (key: string): string => {
        const value = form[key];

        return value === null || value === undefined ? '' : String(value);
    };

    const actions = (
        <>
            {dirty && (
                <Button variant="outline" size={compact ? 'default' : 'sm'} onClick={reset} disabled={saving} className="gap-1.5">
                    <RotateCcw className="size-4" />
                    Reset
                </Button>
            )}
            <Button variant="outline" size={compact ? 'default' : 'sm'} onClick={openPdf} disabled={!filters.machine_id} className="gap-1.5">
                <FileDown className="size-4" />
                PDF
            </Button>
            {can_write && (
                <Button size={compact ? 'default' : 'sm'} onClick={save} disabled={saving || !dirty || !filters.machine_id} className="gap-1.5">
                    <Save className="size-4" />
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            )}
        </>
    );

    return (
        <>
            <Head title={`${document.title} — ${unit.name}`} />

            <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', compact && 'pb-28')}>
                {/* Header */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        {!inShell && (
                            <Button variant="outline" size="icon" onClick={() => router.get(harPengusahaan.index('formulir').url)} title="Kembali ke Formulir Pengusahaan Pemeliharaan" className="shrink-0">
                                <ArrowLeft className="size-4" />
                            </Button>
                        )}
                        <div className="min-w-0">
                            <h1 className="text-lg font-bold text-foreground md:text-xl">{document.title}</h1>
                            <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="outline">{machineName}</Badge>
                                <Badge variant="outline">{formatDate(filters.test_date)}</Badge>
                                {has_saved ? (
                                    <Badge variant="secondary" className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        Tersimpan
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="border-amber-300 text-amber-700 dark:text-amber-300">
                                        Belum disimpan
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
                        </div>
                    </div>
                    {!compact && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value), machine_id: null })}
                        options={units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className="w-full sm:w-48"
                    />
                    <OperasiSelect
                        label="Mesin"
                        value={filters.machine_id ? String(filters.machine_id) : ''}
                        onChange={(value) => visit({ machine_id: Number(value) })}
                        options={machines.map((m) => ({ value: String(m.id), label: m.name }))}
                        className="w-full sm:w-48"
                    />
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Tanggal Pemeriksaan</span>
                        <Input type="date" value={filters.test_date} onChange={(e) => e.target.value && visit({ test_date: e.target.value })} className="h-9 w-full sm:w-44" />
                    </label>
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {history.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5 text-xs">
                        <span className="flex items-center gap-1 text-muted-foreground">
                            <History className="size-3.5" />
                            Tersimpan:
                        </span>
                        {history.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => visit({ test_date: item.test_date })}
                                className={cn(
                                    'rounded-md border px-2 py-1 font-medium transition hover:bg-muted',
                                    item.test_date === filters.test_date ? 'border-primary bg-primary/10 text-primary' : 'border-border text-foreground',
                                )}
                            >
                                {formatDate(item.test_date)}
                            </button>
                        ))}
                    </div>
                )}

                {machines.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Unit ini belum memiliki data mesin aktif. Tambahkan mesin di master data terlebih dahulu.
                    </p>
                ) : (
                    <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-3 md:p-4">
                        {/* Static kop */}
                        <div className="grid grid-cols-1 gap-2 rounded-md border border-border p-3 sm:grid-cols-[auto_1fr_auto] sm:items-center">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="hidden max-h-10 object-contain sm:block"
                                onError={(e) => {
                                    (e.target as HTMLElement).style.display = 'none';
                                }}
                            />
                            <div className="text-center">
                                <div className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">PT PLN Nusantara Power · {unit.name}</div>
                                <div className="text-sm font-extrabold text-foreground uppercase">{document.title}</div>
                            </div>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-2 text-[11px] text-muted-foreground">
                                <dt>No. Dokumen</dt>
                                <dd className="font-mono text-foreground">{document.number || '—'}</dd>
                                <dt>Revisi</dt>
                                <dd className="font-mono text-foreground">{document.revision || '—'}</dd>
                                <dt>Berlaku</dt>
                                <dd className="text-foreground">{document.effective_date || '—'}</dd>
                            </dl>
                        </div>

                        {/* Identity */}
                        {identity.length > 0 && (
                            <Section title="Data Mesin">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    {identity.map((field) => (
                                        <FieldInput key={field.key} field={field} value={text(field.key)} onChange={(v) => setValue(field.key, v)} canWrite={can_write} />
                                    ))}
                                    {tables.some((table) => table.cylinders) && (
                                        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Jumlah Silinder
                                            <Input
                                                type="number"
                                                inputMode="numeric"
                                                min={1}
                                                max={32}
                                                value={text('cylinders_count')}
                                                onChange={(e) => setCylinders(Number(e.target.value))}
                                                disabled={!can_write}
                                                className="h-9 text-sm"
                                            />
                                        </label>
                                    )}
                                </div>
                            </Section>
                        )}

                        {/* Tables */}
                        {tables.map((table) => (
                            <Section key={table.key} title={table.title} description={table.description}>
                                {compact ? (
                                    <MobileRowEditor<FormRow>
                                        rows={rowsOf(table)}
                                        fields={table.columns.map(
                                            (column): RowField<FormRow> => ({
                                                key: column.key,
                                                label: column.label,
                                                type: column.type ?? 'text',
                                                options: column.options ? [...column.options] : undefined,
                                                optionLabels: column.optionLabels,
                                                optionTone: (option) => CHIP_TONE[option] ?? 'border-primary bg-primary text-primary-foreground',
                                                placeholder: column.placeholder,
                                                parse: (value) => String(value),
                                            }),
                                        )}
                                        title={(row, index) => table.rowLabel(row, index)}
                                        subtitle={(row) =>
                                            table.columns
                                                .filter((c) => c.type !== 'textarea' && row[c.key] !== '' && row[c.key] !== null && row[c.key] !== undefined)
                                                .slice(0, 3)
                                                .map((c) => `${c.label}: ${c.optionLabels?.[String(row[c.key])] ?? row[c.key]}`)
                                                .join(' · ')
                                        }
                                        onChange={(index, key, value) => setCell(table, index, key, String(value ?? ''))}
                                        onRemove={table.addable ? (index) => removeRow(table, index) : undefined}
                                        canWrite={can_write}
                                    />
                                ) : (
                                    <div className="overflow-x-auto rounded-md border border-border">
                                        <table className="w-full border-collapse text-sm">
                                            <thead className="bg-muted/60 text-xs text-foreground">
                                                <tr className="[&>th]:border-b [&>th]:border-border [&>th]:px-2 [&>th]:py-2 [&>th]:text-left [&>th]:font-semibold">
                                                    <th className="w-32">{table.rowHeader}</th>
                                                    {table.columns.map((column) => (
                                                        <th key={column.key} className={column.wide ? 'min-w-56' : 'min-w-28'}>
                                                            {column.label}
                                                        </th>
                                                    ))}
                                                    {table.addable && can_write && <th className="w-10" />}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rowsOf(table).map((row, index) => (
                                                    <tr key={index} className="hover:bg-muted/30 [&>td]:border-b [&>td]:border-border [&>td]:px-2 [&>td]:py-1.5">
                                                        <td className="font-medium text-foreground">{table.rowLabel(row, index)}</td>
                                                        {table.columns.map((column) => (
                                                            <td key={column.key}>
                                                                <CellInput
                                                                    field={column}
                                                                    value={row[column.key] === null || row[column.key] === undefined ? '' : String(row[column.key])}
                                                                    onChange={(value) => setCell(table, index, column.key, value)}
                                                                    canWrite={can_write}
                                                                />
                                                            </td>
                                                        ))}
                                                        {table.addable && can_write && (
                                                            <td>
                                                                <Button variant="ghost" size="icon" onClick={() => removeRow(table, index)} className="size-8 text-muted-foreground hover:text-destructive" title="Hapus baris">
                                                                    <Trash2 className="size-4" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                                {rowsOf(table).length === 0 && (
                                                    <tr>
                                                        <td colSpan={table.columns.length + 2} className="p-4 text-center text-xs text-muted-foreground">
                                                            Belum ada baris.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {table.addable && can_write && (
                                    <Button variant="outline" size="sm" onClick={() => addRow(table)} className="mt-2 gap-1.5 self-start">
                                        <Plus className="size-4" />
                                        {table.addable.label}
                                    </Button>
                                )}
                            </Section>
                        ))}

                        {/* Notes */}
                        {notes.length > 0 && (
                            <Section title="Catatan & Standar">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    {notes.map((field) => (
                                        <div key={field.key} className={field.type === 'textarea' && field.wide ? 'md:col-span-2' : ''}>
                                            <FieldInput field={field} value={text(field.key)} onChange={(v) => setValue(field.key, v)} canWrite={can_write} />
                                        </div>
                                    ))}
                                </div>
                            </Section>
                        )}

                        {extra?.({ canWrite: can_write, markDirty: () => setDirty(true) })}

                        {/* Signatories */}
                        <div className="rounded-md border border-dashed border-border bg-muted/20 p-3 text-xs text-muted-foreground">
                            <span className="font-semibold text-foreground">Tanda tangan di PDF (otomatis dari pemegang jabatan unit):</span>
                            <div className="mt-1.5 grid grid-cols-1 gap-1 sm:grid-cols-3">
                                {signatories.map((signatory) => (
                                    <div key={signatory.title}>
                                        <span className="block text-[11px]">{signatory.title}</span>
                                        <span className="font-medium text-foreground">{signatory.name || '—'}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {compact && <StickyActionBar>{actions}</StickyActionBar>}
        </>
    );
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-2">
            <div>
                <h2 className="text-sm font-semibold text-foreground">{title}</h2>
                {description && <p className="text-xs text-muted-foreground">{description}</p>}
            </div>
            {children}
        </section>
    );
}

function FieldInput({ field, value, onChange, canWrite }: { field: HarField; value: string; onChange: (value: string) => void; canWrite: boolean }) {
    return (
        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
            {field.label}
            {field.type === 'textarea' ? (
                <Textarea value={value} onChange={(e) => onChange(e.target.value)} disabled={!canWrite} placeholder={field.placeholder} rows={3} className="text-sm" />
            ) : (
                <CellInput field={field} value={value} onChange={onChange} canWrite={canWrite} className="h-9" />
            )}
        </label>
    );
}

function CellInput({
    field,
    value,
    onChange,
    canWrite,
    className = 'h-8',
}: {
    field: HarField;
    value: string;
    onChange: (value: string) => void;
    canWrite: boolean;
    className?: string;
}) {
    if (field.type === 'select' && field.options) {
        const options = value === '' || field.options.includes(value) ? field.options : [value, ...field.options];

        return (
            <Select value={value || undefined} onValueChange={onChange} disabled={!canWrite}>
                <SelectTrigger size="sm" className={cn('w-full text-sm', className, TONE[value])}>
                    <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option} value={option}>
                            {field.optionLabels?.[option] ?? option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    if (field.type === 'textarea') {
        return <Textarea value={value} onChange={(e) => onChange(e.target.value)} disabled={!canWrite} placeholder={field.placeholder} rows={2} className="min-h-8 text-sm" />;
    }

    return (
        <Input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            disabled={!canWrite}
            placeholder={field.placeholder}
            inputMode={field.type === 'number' ? 'decimal' : undefined}
            className={cn('text-sm', className)}
        />
    );
}
