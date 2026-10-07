import { Head, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { RouteQueryOptions } from '@/wayfinder';

export type Lubricant = {
    key: string;
    name: string;
    code: string | null;
    unit_label: string;
    unit_of_measure: string;
};

export type RincianMachine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
};

/** jenis pelumas key → amount. */
export type Amounts = Record<string, number>;

export type RincianAuto = {
    /** Persediaan awal (Persediaan Pelumas). */
    awal: Amounts;
    /** One line per tanggal with penerimaan (Persediaan Pelumas). */
    penerimaan: { day: number; amounts: Amounts }[];
    /** TUG 8 / TUG 10 / Over Flow of Persediaan Pelumas. */
    kirim_persediaan: Amounts;
    /** machine id → jenis pelumas → liters (Pemakaian Pelumas). */
    pemakaian: Record<string, Amounts>;
    /** Liters counted in the BA Pemeriksaan Fisik Pelumas (the default sisa fisik). */
    ba_fisik: Amounts;
};

type Filters = { unit_id: number; month: number; year: number };

type Url = (options?: RouteQueryOptions) => { url: string };

export type RincianPageProps = {
    title: string;
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    lubricants: Lubricant[];
    machines: RincianMachine[];
    auto: RincianAuto;
    /** line key → label (pengiriman ke unit / alat bantu). */
    lines: Record<string, string>;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

/** Typed amounts as the inputs hold them. */
export type AmountInputs = Record<string, string>;

const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

export const formatAmount = (value: number) =>
    value === 0 ? '-' : NUMBER.format(Math.round(value * 100) / 100);

export const toNumber = (value: number | string | undefined | null): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

/** Amounts → input strings (missing = ''). */
export const toInputs = (amounts: Amounts | unknown[] | undefined) =>
    Object.fromEntries(
        Object.entries(amounts && !Array.isArray(amounts) ? amounts : {}).map(
            ([key, value]) => [key, String(value)],
        ),
    ) as AmountInputs;

/** Input strings → the positive amounts to send. */
export const fromInputs = (inputs: AmountInputs) =>
    Object.fromEntries(
        Object.entries(inputs)
            .map(([key, value]) => [key, toNumber(value)] as const)
            .filter(([, value]) => value > 0),
    );

/** The empty state of the pelumas sheets. */
export const NO_PELUMAS = {
    title: 'Belum ada jenis pelumas untuk unit ini',
    description:
        'Jenis pelumas diambil dari Data Master Operasi → Jenis Pelumas.',
};

export const sumOf = (lubricants: Lubricant[], amounts: Amounts) =>
    lubricants.reduce((total, l) => total + (amounts[l.key] ?? 0), 0);

/** What the shell needs from a page's props. */
export type RincianShellProps = Pick<
    RincianPageProps,
    | 'title'
    | 'unit'
    | 'units'
    | 'filters'
    | 'period_label'
    | 'saved_at'
    | 'can_write'
>;

/**
 * Header, filters, save button, empty state and catatan shared by Perincian /
 * Rekap Pelumas and Perincian / Rekap Bahan Bakar.
 */
export function RincianShell({
    props,
    description,
    empty,
    filtersExtra,
    routes,
    dirty,
    saving,
    onSave,
    note,
    setNote,
    children,
}: {
    props: RincianShellProps;
    description: string;
    /** Shown instead of the sheet when the unit has no jenis in the master. */
    empty?: { title: string; description: string } | null;
    /** Extra controls in the filter bar (e.g. the jenis BBM). */
    filtersExtra?: ReactNode;
    routes: { index: Url; pdf: Url };
    dirty: boolean;
    saving: boolean;
    onSave: () => void;
    note: string;
    setNote: (note: string) => void;
    children: ReactNode;
}) {
    const { title, unit, units, filters, period_label } = props;
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const visit = (patch: Partial<Filters>) => {
        if (
            dirty &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            routes.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={`${description} — ${unit.name}, ${period_label}.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={routes.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        routes.pdf({
                                            query: { ...query, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh
                                </a>
                            </Button>
                            {props.can_write && (
                                <Button
                                    onClick={onSave}
                                    disabled={saving || !dirty}
                                >
                                    {saving ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Simpan
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    <OperasiSelect
                        label="Unit"
                        className="w-52"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={YEARS.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    {filtersExtra}
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {dirty ? (
                            <span className="font-medium text-amber-700">
                                Ada perubahan yang belum disimpan
                            </span>
                        ) : props.saved_at ? (
                            `Tersimpan ${formatStamp(props.saved_at)}`
                        ) : (
                            'Belum pernah disimpan'
                        )}
                    </p>
                </div>

                {empty ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title={empty.title}
                            description={empty.description}
                        />
                    </section>
                ) : (
                    children
                )}

                <section className="flex flex-col gap-1.5 rounded-md border border-border bg-card p-4">
                    <label
                        htmlFor="catatan"
                        className="text-sm font-medium text-foreground"
                    >
                        Catatan
                    </label>
                    <Textarea
                        id="catatan"
                        rows={3}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        readOnly={!props.can_write}
                        placeholder={
                            props.can_write
                                ? 'Catatan tambahan (opsional)'
                                : 'Tidak ada catatan'
                        }
                    />
                </section>
            </div>
        </>
    );
}

/** A borderless amount cell: digits and one decimal separator ("," or "."). */
export function AmountCell({
    value,
    onChange,
    disabled,
    label,
    placeholder,
    className,
}: {
    value: string | undefined;
    onChange: (value: string) => void;
    disabled: boolean;
    label: string;
    placeholder?: string;
    className?: string;
}) {
    return (
        <input
            type="text"
            inputMode="decimal"
            aria-label={label}
            value={value ?? ''}
            placeholder={placeholder}
            disabled={disabled}
            onChange={(e) => {
                const next = e.target.value.replace(/[^\d.,]/g, '');

                if (/^\d*([.,]\d{0,2})?$/.test(next)) {
                    onChange(next);
                }
            }}
            className={cn(
                'h-7 w-full bg-transparent px-2 text-right tabular-nums outline-none placeholder:text-muted-foreground/60 focus:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-default',
                className,
            )}
        />
    );
}
