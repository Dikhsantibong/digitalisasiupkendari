import { Head, Link, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import type { IdName } from '@/types';

type Field = { key: string; label: string; computed?: boolean };
type Group = { key: string; label: string; fields: Field[] };
type Item = { key: string; name: string; group: string; note: string | null };
type Section = { key: string; title: string; name_label: string; totals: string[]; groups: Group[]; items: Item[] };
type Values = Record<string, Record<string, Record<string, number>>>;
type Draft = Record<string, Record<string, Record<string, string>>>;

export type OpnamePageProps = {
    unit: IdName;
    units: IdName[];
    filters: { unit_id: number; tanggal: string };
    tanggal_label: string;
    sections: Section[];
    values: Values;
    catatan: string;
    saved: { by: string | null; at: string | null } | null;
    summary: Record<string, number | null> | null;
    /** Opname kWh: the day's BBM pemakaian from the Opname BBM flowmeter. */
    bbm_pemakaian: number | null;
    master_url: string | null;
    can_write: boolean;
};

const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const RATIO = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

const xlTh = 'border border-black bg-yellow-200 px-1.5 py-1.5 text-center text-[11px] font-bold text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTd = 'h-8 border border-black p-0 dark:border-white/60';
const xlCell = 'h-8 w-full rounded-none border-0 bg-transparent px-1.5 text-right text-xs tabular-nums shadow-none focus:bg-yellow-50 focus-visible:border-0 focus-visible:ring-0 dark:bg-transparent dark:focus:bg-yellow-950/40';

const toDraft = (values: Values): Draft =>
    Object.fromEntries(
        Object.entries(values).map(([section, items]) => [
            section,
            Object.fromEntries(Object.entries(items).map(([item, fields]) => [item, Object.fromEntries(Object.entries(fields).map(([field, value]) => [field, String(value)]))])),
        ]),
    );

const numberOf = (value: string | undefined): number | null => (value !== undefined && value.trim() !== '' && Number.isFinite(Number(value)) ? Number(value) : null);

/** Stand akhir − stand awal of one row; null until both are filled. */
const subtotalOf = (fields: Record<string, string> | undefined): number | null => {
    const awal = numberOf(fields?.awal);
    const akhir = numberOf(fields?.akhir);

    return awal !== null && akhir !== null ? akhir - awal : null;
};

/**
 * One daily opname sheet of the Operator module (BBM, pelumas or kWh). Rows
 * and column groups come from the server (App\Services\Operator\OpnameSheet):
 * each row fills the fields of its own group, subtotals are stand akhir −
 * stand awal. Desktop = Excel-style tables with the unit & tanggal filters;
 * phone = one card per row for today, no filters.
 */
export function OpnameSheetPage({
    title,
    description,
    indexUrl,
    storeUrl,
    props,
}: {
    title: string;
    description: string;
    indexUrl: string;
    storeUrl: string;
    props: OpnamePageProps;
}) {
    const { unit, units, filters, tanggal_label, sections, values, saved, summary, bbm_pemakaian, master_url, can_write } = props;
    const compact = useCompactLayout();
    const [draft, setDraft] = useState<Draft>(() => toDraft(values));
    const [catatan, setCatatan] = useState(props.catatan);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const valueOf = (section: string, item: string, field: string) => draft[section]?.[item]?.[field] ?? '';
    const setValue = (section: string, item: string, field: string, value: string) => {
        setDraft((current) => ({
            ...current,
            [section]: { ...current[section], [item]: { ...current[section]?.[item], [field]: value } },
        }));
        setDirty(true);
    };

    const visit = (patch: Partial<OpnamePageProps['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(indexUrl, { ...filters, ...patch }, { preserveScroll: true });
    };

    const save = () => {
        setSaving(true);
        setErrors({});
        router.post(
            storeUrl,
            {
                ...filters,
                catatan,
                values: Object.fromEntries(
                    Object.entries(draft).map(([section, items]) => [
                        section,
                        Object.fromEntries(Object.entries(items).map(([item, fields]) => [item, Object.fromEntries(Object.entries(fields).map(([field, value]) => [field, numberOf(value)]))])),
                    ]),
                ),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    const fieldsOf = (section: Section, group: string) => section.groups.find((g) => g.key === group)?.fields ?? [];
    const totalOf = (section: Section, field: string) =>
        section.items.reduce((sum, item) => {
            const value = field === 'subtotal' ? subtotalOf(draft[section.key]?.[item.key]) : numberOf(draft[section.key]?.[item.key]?.[field]);

            return sum + (value ?? 0);
        }, 0);

    /** kWh netto = produksi − PSST, PSST = PS + susut trafo, SFC = kWh ÷ pemakaian BBM. */
    const kwh = (() => {
        if (summary === null) {
            return null;
        }

        const total = (key: string) => {
            const section = sections.find((s) => s.key === key);

            return section ? totalOf(section, 'subtotal') : 0;
        };
        const produksi = total('produksi');
        const psst = total('ps') + total('susut_trafo');
        const netto = produksi - psst;
        const bbm = bbm_pemakaian;

        return [
            { label: 'Total kWh Produksi', value: NUMBER.format(produksi) },
            { label: 'Total kWh PS', value: NUMBER.format(total('ps')) },
            { label: 'Susut Trafo', value: NUMBER.format(total('susut_trafo')) },
            { label: 'kWh PSST (PS + Susut Trafo)', value: NUMBER.format(psst) },
            { label: 'kWh Netto (Produksi − PSST)', value: NUMBER.format(netto), strong: true },
            { label: 'Pemakaian BBM (Opname BBM)', value: bbm !== null ? `${NUMBER.format(bbm)} L` : '–' },
            { label: 'SFC Bruto (kWh Produksi ÷ BBM)', value: bbm ? `${RATIO.format(produksi / bbm)} kWh/L` : '–', strong: true },
            { label: 'SFC Netto (kWh Netto ÷ BBM)', value: bbm ? `${RATIO.format(netto / bbm)} kWh/L` : '–', strong: true },
        ];
    })();

    const masterHint = (
        <p className="text-[12px] text-muted-foreground">
            Nama {title.replace('Opname ', '')} mengikuti master unit ini.
            {master_url && (
                <>
                    {' '}
                    <Link href={master_url} className="text-primary hover:underline">
                        Atur di Master Operasi
                    </Link>
                </>
            )}
        </p>
    );

    const fieldInput = (section: Section, item: Item, field: Field, className: string) => {
        const error = errors[`values.${section.key}.${item.key}.${field.key}`];

        if (field.computed) {
            const subtotal = subtotalOf(draft[section.key]?.[item.key]);

            return <span className={cn('block px-1.5 text-right font-semibold tabular-nums', subtotal !== null && subtotal < 0 && 'text-destructive')}>{subtotal !== null ? NUMBER.format(subtotal) : '–'}</span>;
        }

        return (
            <Input
                type="number"
                inputMode="decimal"
                step="any"
                min="0"
                value={valueOf(section.key, item.key, field.key)}
                onChange={(e) => setValue(section.key, item.key, field.key, e.target.value)}
                disabled={!can_write}
                aria-label={`${field.label} ${item.name}`}
                aria-invalid={error ? true : undefined}
                title={error}
                className={className}
            />
        );
    };

    const kwhCard = kwh && (
        <div className="rounded-md border border-border bg-card p-3">
            <p className="mb-2 text-sm font-semibold text-foreground">Ringkasan kWh</p>
            <dl className="grid grid-cols-1 gap-x-6 gap-y-1.5 text-[13px] sm:grid-cols-2">
                {kwh.map((row) => (
                    <div key={row.label} className="flex items-baseline justify-between gap-3 border-b border-border/60 pb-1">
                        <dt className="text-muted-foreground">{row.label}</dt>
                        <dd className={cn('tabular-nums', row.strong ? 'font-bold text-foreground' : 'font-medium')}>{row.value}</dd>
                    </div>
                ))}
            </dl>
            {bbm_pemakaian === null && <p className="mt-2 text-[12px] text-muted-foreground">SFC terisi setelah Flowmeter BBM pada Opname BBM tanggal ini diisi.</p>}
        </div>
    );

    const catatanField = (
        <label className="flex flex-col gap-1.5 text-[12px] font-medium text-muted-foreground">
            Catatan
            <Textarea value={catatan} onChange={(e) => {
                setCatatan(e.target.value); setDirty(true);
            }} rows={2} disabled={!can_write} placeholder="Catatan opname (opsional)" />
        </label>
    );

    if (compact) {
        return (
            <>
                <Head title={title} />
                <div className={cn('flex flex-col gap-3 p-4', can_write && 'pb-28')}>
                    <p className="px-0.5 text-[13px] font-semibold text-foreground">
                        {tanggal_label}
                        {saved?.at && <span className="ml-1 font-normal text-muted-foreground">· tersimpan {saved.at}</span>}
                    </p>

                    {sections.map((section) => (
                        <div key={section.key} className="flex flex-col gap-2">
                            <p className="px-0.5 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">{section.title}</p>
                            {section.items.length === 0 && <p className="rounded-xl border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">Belum ada di master unit.</p>}
                            {section.items.map((item) => {
                                const group = section.groups.find((g) => g.key === item.group);

                                return (
                                    <div key={item.key} className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="min-w-0 text-[14px] font-semibold text-foreground">{item.name}</span>
                                            {(group?.label || item.note) && <span className="shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">{[group?.label, item.note].filter(Boolean).join(' · ')}</span>}
                                        </div>
                                        <div className="grid grid-cols-3 gap-2">
                                            {fieldsOf(section, item.group).map((field) => (
                                                <label key={field.key} className="flex flex-col gap-1 text-[11px] font-semibold text-muted-foreground">
                                                    {field.label}
                                                    {field.computed ? (
                                                        <span className="flex h-10 items-center justify-end rounded-md bg-muted px-2 text-[15px]">{fieldInput(section, item, field, '')}</span>
                                                    ) : (
                                                        fieldInput(section, item, field, 'h-10 text-right text-[15px] font-semibold tabular-nums')
                                                    )}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ))}

                    {kwhCard}
                    {catatanField}
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" onClick={save} disabled={saving || !dirty} className="flex-1">
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={`${description} — ${unit.name} · ${tanggal_label}.`}
                    actions={
                        can_write ? (
                            <div className="flex flex-wrap items-center gap-3">
                                {dirty && <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        ) : undefined
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-56" />
                    <label className="flex flex-col gap-1 text-[12px] font-medium text-muted-foreground">
                        Tanggal
                        <Input type="date" value={filters.tanggal} onChange={(e) => e.target.value && visit({ tanggal: e.target.value })} className="h-9 w-44" />
                    </label>
                    <div className="ml-auto pb-1.5 text-[13px] text-muted-foreground">
                        {saved ? `Tersimpan${saved.by ? ` oleh ${saved.by}` : ''}${saved.at ? ` · ${saved.at}` : ''}` : 'Belum disimpan untuk tanggal ini'}
                    </div>
                </div>

                {masterHint}

                <div className={cn('grid gap-4', sections.length === 3 && 'xl:grid-cols-3')}>
                    {sections.map((section) => {
                        const grouped = section.groups.some((g) => g.label !== '');
                        const columns = section.groups.flatMap((g) => g.fields.map((field) => ({ group: g.key, field })));

                        return (
                            <div key={section.key} className="flex min-w-0 flex-col gap-1.5">
                                <h3 className="text-sm font-semibold text-foreground">{section.title}</h3>
                                <div className="overflow-x-auto">
                                    <table className="excel-sheet w-full border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                <th rowSpan={grouped ? 2 : 1} className={cn(xlTh, 'min-w-44 text-left')}>{section.name_label}</th>
                                                {grouped
                                                    ? section.groups.map((g) => (
                                                          <th key={g.key} colSpan={g.fields.length} className={xlTh}>{g.label}</th>
                                                      ))
                                                    : columns.map(({ field }) => (
                                                          <th key={field.key} className={cn(xlTh, 'min-w-24')}>{field.label}</th>
                                                      ))}
                                            </tr>
                                            {grouped && (
                                                <tr>
                                                    {columns.map(({ group, field }) => (
                                                        <th key={`${group}-${field.key}`} className={cn(xlTh, 'min-w-24')}>{field.label}</th>
                                                    ))}
                                                </tr>
                                            )}
                                        </thead>
                                        <tbody>
                                            {section.items.length === 0 && (
                                                <tr>
                                                    <td colSpan={columns.length + 1} className="border border-black p-4 text-center text-[13px] text-muted-foreground dark:border-white/60">
                                                        Belum ada di master unit ini.
                                                    </td>
                                                </tr>
                                            )}
                                            {section.items.map((item) => (
                                                <tr key={item.key}>
                                                    <td className={cn(xlTd, 'px-1.5')}>
                                                        <span className="font-medium">{item.name}</span>
                                                        {item.note && <span className="ml-1.5 text-[11px] text-muted-foreground">{item.note}</span>}
                                                    </td>
                                                    {columns.map(({ group, field }) => (
                                                        <td key={`${group}-${field.key}`} className={cn(xlTd, group !== item.group && 'bg-muted/50', field.computed && 'bg-muted/30')}>
                                                            {group === item.group ? fieldInput(section, item, field, xlCell) : <span className="block text-center text-muted-foreground">–</span>}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                            {section.totals.length > 0 && section.items.length > 0 && (
                                                <tr className="bg-yellow-100 font-bold dark:bg-yellow-900/40">
                                                    <td className={cn(xlTd, 'px-1.5')}>TOTAL</td>
                                                    {columns.map(({ group, field }) => {
                                                        const hasRows = section.items.some((item) => item.group === group);
                                                        const sum = section.items.filter((item) => item.group === group).reduce((total, item) => {
                                                            const value = field.key === 'subtotal' ? subtotalOf(draft[section.key]?.[item.key]) : numberOf(draft[section.key]?.[item.key]?.[field.key]);

                                                            return total + (value ?? 0);
                                                        }, 0);

                                                        return (
                                                            <td key={`${group}-${field.key}`} className={cn(xlTd, 'px-1.5 text-right tabular-nums')}>
                                                                {hasRows && section.totals.includes(field.key) ? NUMBER.format(sum) : ''}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {kwhCard}
                <div className="max-w-2xl">{catatanField}</div>
            </div>
        </>
    );
}
