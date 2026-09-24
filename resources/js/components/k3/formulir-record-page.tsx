import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ClipboardList, Plus, Trash2 } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    CompactSelect,
    FormulirDocumentLayout,
    MetaField,
    useFormulirDocument,
} from '@/components/k3/formulir-document';
import type { FormulirDocumentProps } from '@/components/k3/formulir-document';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import formulir from '@/routes/k3/formulir';
import recordRoutes from '@/routes/k3/formulir/record';
import type { IdName } from '@/types';

export type RecordColumn = {
    key: string;
    label: string;
    type: 'text' | 'date' | 'select';
    options?: string[];
    group?: string;
    width?: string;
    align?: 'left' | 'center';
    item?: boolean;
};

export type RecordSection = {
    key: string;
    label: string | null;
    letter: string | null;
    addable: boolean;
    columns: RecordColumn[];
};

type CellValues = Record<string, string>;

type Row = CellValues & { _key: string };

type HistoryItem = {
    year: number;
    month: number;
    week: number;
    label: string;
    format: string;
    updated_at: string | null;
};

export type FormulirRecordPageProps = FormulirDocumentProps & {
    form: {
        key: string;
        title: string;
        description: string;
        period: 'monthly' | 'weekly';
        single_table: boolean;
        header_fields: { key: string; label: string }[];
        notes_label: string | null;
        sign_place: boolean;
        sections: RecordSection[];
    };
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number; week: number };
    options: { units: IdName[]; years: number[] };
    sections: Record<string, CellValues[]>;
    header: Record<string, string>;
    catatan: string;
    has_saved: boolean;
    history: HistoryItem[];
    can_write: boolean;
};

/** Nilai yang dianggap perlu tindak lanjut pada ringkasan. */
const ATTENTION_VALUES = ['Rusak', 'Kurang Baik', 'Tidak Ada', 'Tidak Sesuai', 'Open', 'On Progress'];

let rowSeed = 0;
const withKeys = (rows: CellValues[]): Row[] => rows.map((row) => ({ ...row, _key: `r${++rowSeed}` }));

const toRowState = (sections: Record<string, CellValues[]>, definitions: RecordSection[]) =>
    Object.fromEntries(definitions.map((section) => [section.key, withKeys(sections[section.key] ?? [])])) as Record<string, Row[]>;

const emptyRow = (section: RecordSection): Row => ({
    ...Object.fromEntries(section.columns.map((column) => [column.key, column.type === 'text' ? '' : '-'])),
    _key: `r${++rowSeed}`,
});

/**
 * Halaman bersama Formulir K3 berbasis lembar (definisi kolom dari
 * K3FormulirRegistry): editor tabel + dokumen resmi / PDF.
 */
export function FormulirRecordPage(props: FormulirRecordPageProps) {
    const { form, unit, filters, options, header: initialHeader, has_saved, history, can_write } = props;

    const [rows, setRows] = useState<Record<string, Row[]>>(() => toRowState(props.sections, form.sections));
    const [header, setHeader] = useState<Record<string, string>>(initialHeader);
    const [catatan, setCatatan] = useState<string>(props.catatan);
    const [dirty, setDirty] = useState<boolean>(false);
    const [saving, setSaving] = useState<boolean>(false);
    const doc = useFormulirDocument(props, () => setDirty(true));

    const isWeekly = form.period === 'weekly';
    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    const periodLabel = `${isWeekly ? `Minggu ke-${filters.week} ` : ''}${monthName} ${filters.year}`;

    const visit = (patch: Partial<FormulirRecordPageProps['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah halaman?')) {
            return;
        }

        router.get(recordRoutes.index({ form: form.key }).url, { ...filters, ...patch }, { preserveState: false, preserveScroll: true });
    };

    const goBack = () => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin kembali?')) {
            return;
        }

        router.get(formulir.index().url, { unit_id: filters.unit_id, month: filters.month, year: filters.year });
    };

    const updateCell = (sectionKey: string, rowKey: string, column: string, value: string) => {
        setRows((prev) => ({
            ...prev,
            [sectionKey]: prev[sectionKey].map((row) => (row._key === rowKey ? { ...row, [column]: value } : row)),
        }));
        setDirty(true);
    };

    const addRow = (section: RecordSection) => {
        setRows((prev) => ({ ...prev, [section.key]: [...prev[section.key], emptyRow(section)] }));
        setDirty(true);
        toast.info(`Baris baru ditambahkan${section.label ? ` pada ${section.label}` : ''}.`);
    };

    const deleteRow = (sectionKey: string, rowKey: string) => {
        setRows((prev) => ({ ...prev, [sectionKey]: prev[sectionKey].filter((row) => row._key !== rowKey) }));
        setDirty(true);
    };

    const resetChanges = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setRows(toRowState(props.sections, form.sections));
            setHeader(initialHeader);
            setCatatan(props.catatan);
            doc.reset();
            setDirty(false);
            toast.info('Perubahan dibatalkan.');
        }
    };

    const save = (onSuccess?: () => void) => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            recordRoutes.store({ form: form.key }).url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                week: filters.week,
                sections: Object.fromEntries(
                    Object.entries(rows).map(([key, sectionRows]) => [
                        key,
                        sectionRows.map((row) => {
                            const { _key, ...values } = row; // eslint-disable-line @typescript-eslint/no-unused-vars

                            return values;
                        }),
                    ]),
                ),
                header,
                catatan: catatan.trim(),
                ...doc.payload(),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setDirty(false);
                    doc.refreshPreview();
                    onSuccess?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const stats = useMemo(() => {
        const all = Object.values(rows).flat();
        const selectKeys = form.sections.flatMap((section) => section.columns.filter((c) => c.type === 'select').map((c) => c.key));
        const attention = all.filter((row) => selectKeys.some((key) => ATTENTION_VALUES.includes(row[key]))).length;

        return { total: all.length, attention };
    }, [rows, form.sections]);

    return (
        <>
            <Head title={`${form.title} - ${unit.name}`} />

            <FormulirDocumentLayout
                title={form.title}
                description={`${form.description} Atur penandatangan & layout, lalu cetak PDF resmi.`}
                unitName={unit.name}
                serviceUnitName={unit.service_unit_name}
                periodLabel={periodLabel}
                hasSaved={has_saved}
                canWrite={can_write}
                dirty={dirty}
                saving={saving}
                doc={doc}
                documentProps={props}
                showSignPlace={form.sign_place}
                onSave={save}
                onReset={resetChanges}
                onBack={goBack}
                history={history.map((item) => ({
                    key: `${item.year}-${item.month}-${item.week}`,
                    label: item.label,
                    description: `Mode: ${item.format === 'html' ? 'Teks HTML' : 'Form'} • diperbarui ${item.updated_at ?? '-'}`,
                    onOpen: () => visit({ month: item.month, year: item.year, week: item.week }),
                }))}
                periodControls={
                    <>
                        <MetaField label="Unit Pembangkit">
                            <CompactSelect
                                value={String(filters.unit_id)}
                                onChange={(v) => visit({ unit_id: Number(v) })}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                                disabled={options.units.length <= 1}
                            />
                        </MetaField>
                        <div className={`grid gap-2 ${isWeekly ? 'grid-cols-3' : 'grid-cols-2'}`}>
                            {isWeekly && (
                                <MetaField label="Minggu">
                                    <CompactSelect
                                        value={String(filters.week)}
                                        onChange={(v) => visit({ week: Number(v) })}
                                        options={[1, 2, 3, 4, 5].map((w) => ({ value: String(w), label: `Ke-${w}` }))}
                                    />
                                </MetaField>
                            )}
                            <MetaField label="Bulan">
                                <CompactSelect
                                    value={String(filters.month)}
                                    onChange={(v) => visit({ month: Number(v) })}
                                    options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                                />
                            </MetaField>
                            <MetaField label="Tahun">
                                <CompactSelect
                                    value={String(filters.year)}
                                    onChange={(v) => visit({ year: Number(v) })}
                                    options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                                />
                            </MetaField>
                        </div>
                    </>
                }
            >
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-muted/20 p-2.5 text-xs">
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1.5 font-medium text-foreground">
                            <ClipboardList className="size-4 text-primary" />
                            {stats.total} baris pemeriksaan
                        </span>
                        {stats.attention > 0 && (
                            <span className="flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="size-3.5" />
                                {stats.attention} perlu tindak lanjut
                            </span>
                        )}
                    </div>
                    <span className="text-muted-foreground">Kolom kosong dicetak sesuai lembar resmi.</span>
                </div>

                {form.header_fields.length > 0 && (
                    <div className="grid gap-3 rounded-md border border-border p-3 md:grid-cols-3">
                        <MetaField label="Unit / Lokasi">
                            <Input value={unit.name} disabled className="h-8 bg-muted text-xs" />
                        </MetaField>
                        {form.header_fields.map((field) => (
                            <MetaField key={field.key} label={field.label}>
                                <Input
                                    value={header[field.key] ?? ''}
                                    onChange={(e) => {
                                        setHeader((prev) => ({ ...prev, [field.key]: e.target.value }));
                                        setDirty(true);
                                    }}
                                    disabled={!can_write}
                                    className="h-8 text-xs"
                                />
                            </MetaField>
                        ))}
                    </div>
                )}

                {form.single_table ? (
                    <SectionTable
                        sections={form.sections}
                        rows={rows}
                        canWrite={can_write}
                        showSectionRows
                        onChange={updateCell}
                        onAdd={addRow}
                        onDelete={deleteRow}
                    />
                ) : (
                    form.sections.map((section) => (
                        <div key={section.key} className="space-y-0">
                            {section.label && (
                                <div className="rounded-t-md bg-[#1f4e79] px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white">
                                    {section.letter ? `${section.letter}. ` : ''}
                                    {section.label}
                                </div>
                            )}
                            <SectionTable
                                sections={[section]}
                                rows={rows}
                                canWrite={can_write}
                                showSectionRows={false}
                                onChange={updateCell}
                                onAdd={addRow}
                                onDelete={deleteRow}
                            />
                        </div>
                    ))
                )}

                {form.notes_label && (
                    <div className="space-y-1.5 text-xs">
                        <Label htmlFor="catatan" className="font-semibold">
                            {form.notes_label}:
                        </Label>
                        {can_write ? (
                            <Textarea
                                id="catatan"
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                rows={3}
                                placeholder="Tulis catatan pemeriksaan di sini..."
                                className="text-xs"
                            />
                        ) : (
                            <p className="italic text-muted-foreground">{catatan || 'Tidak ada catatan.'}</p>
                        )}
                    </div>
                )}
            </FormulirDocumentLayout>
        </>
    );
}

/**
 * Header 2 baris bila ada kolom ber-group (mis. "Kondisi / Masa Berlaku").
 */
function headerCells(columns: RecordColumn[]) {
    const top: { label: string; colSpan: number; rowSpan: number; group?: string; width?: string }[] = [];
    const bottom: RecordColumn[] = [];

    columns.forEach((column) => {
        if (!column.group) {
            top.push({ label: column.label, colSpan: 1, rowSpan: 2, width: column.width });

            return;
        }

        const last = top[top.length - 1];

        if (last?.group === column.group) {
            last.colSpan++;
        } else {
            top.push({ label: column.group, group: column.group, colSpan: 1, rowSpan: 1 });
        }

        bottom.push(column);
    });

    return { top, bottom };
}

function SectionTable({
    sections,
    rows,
    canWrite,
    showSectionRows,
    onChange,
    onAdd,
    onDelete,
}: {
    sections: RecordSection[];
    rows: Record<string, Row[]>;
    canWrite: boolean;
    showSectionRows: boolean;
    onChange: (sectionKey: string, rowKey: string, column: string, value: string) => void;
    onAdd: (section: RecordSection) => void;
    onDelete: (sectionKey: string, rowKey: string) => void;
}) {
    const columns = sections[0].columns;
    const { top, bottom } = headerCells(columns);
    const hasGroup = bottom.length > 0;
    const totalColumns = columns.length + 1 + (canWrite ? 1 : 0);

    return (
        <div className={`overflow-x-auto border border-border ${showSectionRows || !sections[0].label ? 'rounded-md' : 'rounded-b-md'}`}>
            <table className="w-full border-collapse text-xs" style={{ minWidth: `${Math.max(760, columns.length * 125)}px` }}>
                <thead>
                    <tr className="bg-sky-100/70 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:font-semibold">
                        <th rowSpan={hasGroup ? 2 : 1} className="w-10">No</th>
                        {top.map((cell) => (
                            <th
                                key={cell.label}
                                colSpan={cell.colSpan}
                                rowSpan={hasGroup ? cell.rowSpan : 1}
                                style={cell.width ? { width: cell.width } : undefined}
                            >
                                {cell.label}
                            </th>
                        ))}
                        {canWrite && <th rowSpan={hasGroup ? 2 : 1} className="w-10">Aksi</th>}
                    </tr>
                    {hasGroup && (
                        <tr className="bg-sky-100/40 text-[11px] text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-1.5 [&>th]:text-center [&>th]:font-semibold">
                            {bottom.map((column) => (
                                <th key={column.key}>{column.label}</th>
                            ))}
                        </tr>
                    )}
                </thead>
                <tbody>
                    {sections.map((section) => (
                        <Fragment key={section.key}>
                            {showSectionRows && section.label && (
                                <tr className="bg-muted/50 font-bold">
                                    <td className="border border-border p-2 text-center">{section.letter}</td>
                                    <td colSpan={totalColumns - 1} className="border border-border p-2 uppercase">
                                        {section.label}
                                    </td>
                                </tr>
                            )}
                            {(rows[section.key] ?? []).map((row, index) => (
                                <tr key={row._key} className="transition-colors hover:bg-muted/30 [&>td]:border [&>td]:border-border [&>td]:p-1.5 [&>td]:align-middle">
                                    <td className="text-center font-medium text-muted-foreground">{index + 1}</td>
                                    {section.columns.map((column) => (
                                        <td key={column.key}>
                                            <RecordCell
                                                column={column}
                                                value={row[column.key] ?? ''}
                                                canWrite={canWrite}
                                                onChange={(value) => onChange(section.key, row._key, column.key, value)}
                                            />
                                        </td>
                                    ))}
                                    {canWrite && (
                                        <td className="text-center">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => onDelete(section.key, row._key)}
                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                title="Hapus baris"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {canWrite && section.addable && (
                                <tr>
                                    <td colSpan={totalColumns} className="border border-border bg-muted/10 p-1.5">
                                        <Button type="button" size="sm" variant="ghost" onClick={() => onAdd(section)} className="h-7 gap-1 text-[11px] text-primary">
                                            <Plus className="size-3.5" />
                                            Tambah Baris{section.label ? ` ${section.label}` : ''}
                                        </Button>
                                    </td>
                                </tr>
                            )}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

function RecordCell({
    column,
    value,
    canWrite,
    onChange,
}: {
    column: RecordColumn;
    value: string;
    canWrite: boolean;
    onChange: (value: string) => void;
}) {
    const align = column.align === 'center' ? 'text-center' : '';

    if (!canWrite) {
        return <div className={`${align} ${column.item ? 'font-medium' : ''}`}>{value || (column.type === 'text' ? '' : '-')}</div>;
    }

    if (column.type === 'select') {
        const current = value.trim() !== '' ? value : '-';
        const choices = column.options?.includes(current) ? column.options : [current, ...(column.options ?? [])];
        const tone =
            ATTENTION_VALUES.includes(current)
                ? 'border-rose-300 bg-rose-50/80 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300'
                : current === '-'
                  ? 'text-muted-foreground'
                  : 'border-emerald-300 bg-emerald-50/80 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';

        return (
            <Select value={current} onValueChange={onChange}>
                <SelectTrigger size="sm" className={`h-7 w-full min-w-[90px] justify-center text-xs ${tone}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {choices.map((option) => (
                        <SelectItem key={option} value={option} className="text-xs">
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    if (column.type === 'date') {
        return (
            <Input
                type="date"
                value={ISO_DATE.test(value) ? value : ''}
                onChange={(e) => onChange(e.target.value || '-')}
                className="h-7 min-w-[130px] text-xs"
            />
        );
    }

    return (
        <Input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={`h-7 min-w-[80px] text-xs ${align} ${column.item ? 'min-w-[160px] font-medium' : ''}`}
        />
    );
}

/** Breadcrumb bersama untuk halaman formulir berbasis lembar. */
export function recordBreadcrumbs(formKey: string, title: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir K3 & Keamanan', href: formulir.index() },
        { title, href: recordRoutes.index({ form: formKey }) },
    ];
}
