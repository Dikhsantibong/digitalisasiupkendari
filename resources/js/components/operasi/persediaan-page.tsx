import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Loader2, RotateCcw, Save } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import master from '@/routes/operasi/master';
import type { RouteQueryOptions } from '@/wayfinder';

export type PersediaanItem = {
    key: string;
    name: string;
    code: string | null;
    unit_label: string;
};

type Filters = { unit_id: number; month: number; year: number };

/** item key → day → field → amount. */
type Entries = Record<string, Record<string, Record<string, number | string>>>;

type Url = (options?: RouteQueryOptions) => { url: string };

export type PersediaanPageProps = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    items: PersediaanItem[];
    /** field (kirim_1, kirim_2, …) → label. */
    kirim_columns: Record<string, string>;
    periods: { key: string; label: string; from: number; to: number }[];
    days_in_month: number;
    entries: Entries;
    /** Corrected opening stocks only. */
    opening: Record<string, number>;
    /** Last month's saldo akhir per item (null = no sheet last month). */
    carried_opening: Record<string, number | null>;
    /** item key → day → liters, from the Pemakaian sheet. */
    pemakaian: Record<string, Record<string, number>>;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
    can_manage_master: boolean;
};

type Row = Record<string, number>;

const toNumber = (value: number | string | undefined | null): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

const NUMBER = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const format = (value: number, dash = true) =>
    value === 0 && dash ? '-' : NUMBER.format(Math.round(value * 100) / 100);
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const groupStart = 'border-l-2 border-l-primary/30';

/**
 * Persediaan Bahan Bakar / Pelumas: one wide table with a block per jenis
 * (PERSEDIAAN AWAL, PENERIMAAN, PEMAKAIAN, the PENGIRIMAN columns, SALDO
 * AKHIR), rows per tanggal, the periods and TOTAL. Penerimaan and pengiriman
 * are typed in; pemakaian comes from the Pemakaian sheet; the opening stock is
 * last month's saldo akhir unless corrected. Mirrors App\Services\Operasi\PersediaanSheet.
 */
export function PersediaanPage({
    title,
    description,
    itemLabel,
    pemakaianSource,
    routes,
    unit,
    units,
    filters,
    period_label,
    items,
    kirim_columns,
    periods,
    days_in_month,
    entries: savedEntries,
    opening: savedOpening,
    carried_opening,
    pemakaian,
    catatan: savedNote,
    saved_at,
    can_write,
    can_manage_master,
}: PersediaanPageProps & {
    title: string;
    description: string;
    /** "BBM" or "pelumas". */
    itemLabel: string;
    pemakaianSource: string;
    routes: { index: Url; store: Url; pdf: Url };
}) {
    const compact = useCompactLayout();
    const kirimFields = Object.keys(kirim_columns);
    const fields = ['penerimaan', ...kirimFields];
    const initialOpening = useMemo(
        () =>
            Object.fromEntries(
                items.map((item) => [
                    item.key,
                    String(
                        savedOpening[item.key] ??
                            carried_opening[item.key] ??
                            '',
                    ),
                ]),
            ),
        [items, savedOpening, carried_opening],
    );
    const [entries, setEntries] = useState<Entries>(savedEntries);
    const [opening, setOpening] =
        useState<Record<string, string>>(initialOpening);
    const [note, setNote] = useState(savedNote);
    const [saving, setSaving] = useState(false);
    const [itemFilter, setItemFilter] = useState('all');
    const [selectedDay, setSelectedDay] = useState(() => {
        const today = new Date();

        return today.getMonth() + 1 === filters.month &&
            today.getFullYear() === filters.year
            ? today.getDate()
            : 1;
    });

    const dirty =
        JSON.stringify(entries) !== JSON.stringify(savedEntries) ||
        JSON.stringify(opening) !== JSON.stringify(initialOpening) ||
        note !== savedNote;
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const shownItems =
        itemFilter === 'all'
            ? items
            : items.filter((item) => item.key === itemFilter);

    /** The running stock of every item (same arithmetic as the server). */
    const sheet = useMemo(() => {
        const result: Record<
            string,
            {
                opening: number;
                rows: Record<number, Row>;
                periods: Row[];
                total: Row;
            }
        > = {};

        for (const item of items) {
            const start = toNumber(opening[item.key]);
            let stock = start;
            const rows: Record<number, Row> = {};

            for (const day of days) {
                const row: Row = { awal: stock };

                for (const field of fields) {
                    row[field] = toNumber(entries[item.key]?.[day]?.[field]);
                }

                row.pemakaian = toNumber(pemakaian[item.key]?.[day]);
                stock += row.penerimaan - row.pemakaian;

                for (const field of kirimFields) {
                    stock -= row[field];
                }

                row.akhir = stock;
                rows[day] = row;
            }

            const sum = (from: number, to: number): Row => {
                const total: Row = { akhir: rows[to]?.akhir ?? start };

                for (const field of [...fields, 'pemakaian']) {
                    total[field] = days
                        .filter((day) => day >= from && day <= to)
                        .reduce((acc, day) => acc + rows[day][field], 0);
                }

                return total;
            };

            result[item.key] = {
                opening: start,
                rows,
                periods: periods.map((period) => sum(period.from, period.to)),
                total: sum(1, days_in_month),
            };
        }

        return result;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [items, opening, entries, pemakaian, periods, days_in_month]);

    const setEntry = (key: string, day: number, field: string, value: string) =>
        setEntries((current) => {
            const dayRow = { ...(current[key]?.[day] ?? {}) };

            if (value === '') {
                delete dayRow[field];
            } else {
                dayRow[field] = value;
            }

            return {
                ...current,
                [key]: { ...(current[key] ?? {}), [day]: dayRow },
            };
        });

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

    const save = () => {
        const payload: Record<
            string,
            Record<string, Record<string, number>>
        > = {};

        for (const [key, byDay] of Object.entries(entries)) {
            for (const [day, row] of Object.entries(byDay)) {
                for (const [field, value] of Object.entries(row)) {
                    const amount = toNumber(value);

                    if (amount > 0) {
                        payload[key] ??= {};
                        payload[key][day] = {
                            ...(payload[key][day] ?? {}),
                            [field]: amount,
                        };
                    }
                }
            }
        }

        router.post(
            routes.store().url,
            {
                ...filters,
                entries: payload,
                opening: Object.fromEntries(
                    Object.entries(opening).map(([key, value]) => [
                        key,
                        value === '' ? null : toNumber(value),
                    ]),
                ),
                catatan: note,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const columnLabels: [string, string][] = [
        ['penerimaan', 'Penerimaan'],
        ['pemakaian', 'Pemakaian'],
        ...Object.entries(kirim_columns),
    ];
    const span = columnLabels.length + 2;

    const saveButton = can_write && (
        <Button onClick={save} disabled={saving || !dirty}>
            {saving ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <Save className="size-4" />
            )}
            Simpan
        </Button>
    );

    const openingInput = (item: PersediaanItem, className: string) => {
        const carried = carried_opening[item.key];
        const corrected =
            carried !== null &&
            carried !== undefined &&
            toNumber(opening[item.key]) !== carried;

        return (
            <div className="flex items-center gap-1">
                <AmountInput
                    value={opening[item.key]}
                    disabled={!can_write}
                    onChange={(value) =>
                        setOpening((current) => ({
                            ...current,
                            [item.key]: value,
                        }))
                    }
                    className={cn(className, corrected && 'text-primary')}
                    label={`Persediaan awal ${item.name}`}
                />
                {corrected && can_write && (
                    <button
                        type="button"
                        title={`Kembalikan ke saldo akhir bulan lalu (${format(carried, false)})`}
                        onClick={() =>
                            setOpening((current) => ({
                                ...current,
                                [item.key]: String(carried),
                            }))
                        }
                        className="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <RotateCcw className="size-3.5" />
                    </button>
                )}
            </div>
        );
    };

    return (
        <>
            <Head title={title} />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
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
                            {!compact && saveButton}
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
                    {items.length > 1 && (
                        <OperasiSelect
                            label={`Jenis ${itemLabel}`}
                            className="w-56"
                            value={itemFilter}
                            onChange={setItemFilter}
                            options={[
                                {
                                    value: 'all',
                                    label: `Semua jenis ${itemLabel}`,
                                },
                                ...items.map((item) => ({
                                    value: item.key,
                                    label: item.name,
                                })),
                            ]}
                        />
                    )}
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {dirty ? (
                            <span className="font-medium text-amber-700">
                                Ada perubahan yang belum disimpan
                            </span>
                        ) : saved_at ? (
                            `Tersimpan ${formatStamp(saved_at)}`
                        ) : (
                            'Belum pernah disimpan'
                        )}
                    </p>
                </div>

                {items.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title={`Belum ada jenis ${itemLabel} untuk unit ini`}
                            description={`Jenis ${itemLabel} diambil dari Data Master Operasi unit.`}
                            action={
                                can_manage_master && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={
                                                master.index(
                                                    itemLabel === 'BBM'
                                                        ? 'fuel-tanks'
                                                        : 'lubricant-types',
                                                ).url
                                            }
                                        >
                                            Buka Data Master
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            {items.slice(0, 4).map((item) => (
                                <SummaryCard
                                    key={item.key}
                                    label={`Saldo akhir ${item.name}`}
                                    value={format(
                                        sheet[item.key].total.akhir,
                                        false,
                                    )}
                                    unit={item.unit_label}
                                    hint={`Pemakaian ${format(sheet[item.key].total.pemakaian, false)}`}
                                />
                            ))}
                        </div>

                        <p className="text-[13px] text-muted-foreground">
                            Kolom <b>Pemakaian</b> diambil otomatis dari menu{' '}
                            {pemakaianSource}. Persediaan awal bulan diambil
                            dari saldo akhir bulan lalu dan bisa dikoreksi.
                        </p>

                        {compact ? (
                            <div className="flex flex-col gap-3">
                                <DayStrip
                                    items={days.map((day) => ({
                                        key: String(day),
                                        label: String(day),
                                        done: items.some((item) =>
                                            fields.some(
                                                (field) =>
                                                    sheet[item.key].rows[day][
                                                        field
                                                    ] > 0,
                                            ),
                                        ),
                                    }))}
                                    value={String(selectedDay)}
                                    onChange={(key) =>
                                        setSelectedDay(Number(key))
                                    }
                                />
                                {shownItems.map((item) => {
                                    const row =
                                        sheet[item.key].rows[selectedDay];

                                    return (
                                        <section
                                            key={item.key}
                                            className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3"
                                        >
                                            <h2 className="text-sm font-semibold text-foreground">
                                                {item.name}
                                            </h2>
                                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                                Persediaan awal bulan
                                                {openingInput(
                                                    item,
                                                    'h-10 rounded-md border border-border bg-background text-sm',
                                                )}
                                            </label>
                                            <div className="grid grid-cols-3 gap-2 text-[12px] text-muted-foreground">
                                                <span>
                                                    Awal tgl {selectedDay}
                                                    <b className="block text-foreground tabular-nums">
                                                        {format(
                                                            row.awal,
                                                            false,
                                                        )}
                                                    </b>
                                                </span>
                                                <span>
                                                    Pemakaian
                                                    <b className="block text-foreground tabular-nums">
                                                        {format(
                                                            row.pemakaian,
                                                            false,
                                                        )}
                                                    </b>
                                                </span>
                                                <span>
                                                    Saldo akhir
                                                    <b className="block text-primary tabular-nums">
                                                        {format(
                                                            row.akhir,
                                                            false,
                                                        )}
                                                    </b>
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                {fields.map((field) => (
                                                    <label
                                                        key={field}
                                                        className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                                    >
                                                        {field === 'penerimaan'
                                                            ? 'Penerimaan'
                                                            : kirim_columns[
                                                                  field
                                                              ]}
                                                        <AmountInput
                                                            value={
                                                                entries[
                                                                    item.key
                                                                ]?.[
                                                                    selectedDay
                                                                ]?.[field]
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            onChange={(value) =>
                                                                setEntry(
                                                                    item.key,
                                                                    selectedDay,
                                                                    field,
                                                                    value,
                                                                )
                                                            }
                                                            className="h-10 rounded-md border border-border bg-background text-sm"
                                                            label={`${field} ${item.name} tanggal ${selectedDay}`}
                                                        />
                                                    </label>
                                                ))}
                                            </div>
                                        </section>
                                    );
                                })}
                            </div>
                        ) : (
                            <section className="overflow-hidden rounded-md border border-border bg-card">
                                <div className="overflow-x-auto">
                                    <table
                                        className="w-full border-collapse text-[13px]"
                                        data-keep-table
                                    >
                                        <thead>
                                            <tr className="bg-secondary text-foreground">
                                                <th
                                                    rowSpan={3}
                                                    className="sticky left-0 z-20 w-24 border-r border-b border-border bg-secondary px-2 py-2 text-left text-[12px] font-semibold"
                                                >
                                                    TGL
                                                </th>
                                                {shownItems.map(
                                                    (item, index) => (
                                                        <th
                                                            key={item.key}
                                                            colSpan={span}
                                                            className={cn(
                                                                'border-r border-b border-border px-2 py-2 text-center text-[13px] font-semibold',
                                                                index > 0 &&
                                                                    groupStart,
                                                            )}
                                                        >
                                                            {item.name.toUpperCase()}
                                                        </th>
                                                    ),
                                                )}
                                            </tr>
                                            <tr className="text-[11px] text-foreground">
                                                {shownItems.map(
                                                    (item, index) => (
                                                        <Fragment
                                                            key={item.key}
                                                        >
                                                            <th
                                                                className={cn(
                                                                    'min-w-24 border-r border-b border-border px-2 py-1 font-semibold uppercase',
                                                                    index > 0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                Persediaan awal
                                                            </th>
                                                            {columnLabels.map(
                                                                ([
                                                                    field,
                                                                    label,
                                                                ]) => (
                                                                    <th
                                                                        key={
                                                                            field
                                                                        }
                                                                        className="min-w-24 border-r border-b border-border px-2 py-1 font-semibold uppercase"
                                                                    >
                                                                        {label}
                                                                    </th>
                                                                ),
                                                            )}
                                                            <th className="min-w-24 border-r border-b border-border bg-secondary px-2 py-1 font-semibold uppercase">
                                                                Saldo akhir
                                                            </th>
                                                        </Fragment>
                                                    ),
                                                )}
                                            </tr>
                                            <tr className="text-[11px] text-muted-foreground">
                                                {shownItems.map((item, index) =>
                                                    Array.from(
                                                        {
                                                            length: span,
                                                        },
                                                        (_, i) => (
                                                            <th
                                                                key={`${item.key}-${i}`}
                                                                className={cn(
                                                                    'border-r border-b border-border px-2 py-0.5 font-normal',
                                                                    index > 0 &&
                                                                        i ===
                                                                            0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {
                                                                    item.unit_label
                                                                }
                                                            </th>
                                                        ),
                                                    ),
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr className="bg-muted/30">
                                                <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 py-1 text-[12px] font-medium text-muted-foreground">
                                                    Awal bulan
                                                </td>
                                                {shownItems.map(
                                                    (item, index) => (
                                                        <Fragment
                                                            key={item.key}
                                                        >
                                                            <td
                                                                colSpan={
                                                                    span - 1
                                                                }
                                                                className={cn(
                                                                    'border-r border-b border-border px-2 text-right text-[12px] text-muted-foreground',
                                                                    index > 0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {carried_opening[
                                                                    item.key
                                                                ] === null
                                                                    ? 'Isi persediaan awal bulan →'
                                                                    : 'Saldo akhir bulan lalu →'}
                                                            </td>
                                                            <td className="border-r border-b border-border p-0">
                                                                {openingInput(
                                                                    item,
                                                                    'h-7 border-0 bg-transparent font-semibold focus:bg-primary/5',
                                                                )}
                                                            </td>
                                                        </Fragment>
                                                    ),
                                                )}
                                            </tr>
                                            {days.map((day) => (
                                                <tr
                                                    key={day}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 text-center text-[12px] font-medium text-muted-foreground tabular-nums">
                                                        {day}
                                                    </td>
                                                    {shownItems.map(
                                                        (item, index) => {
                                                            const row =
                                                                sheet[item.key]
                                                                    .rows[day];

                                                            return (
                                                                <Fragment
                                                                    key={
                                                                        item.key
                                                                    }
                                                                >
                                                                    <td
                                                                        className={cn(
                                                                            'border-r border-b border-border px-2 text-right text-muted-foreground tabular-nums',
                                                                            index >
                                                                                0 &&
                                                                                groupStart,
                                                                        )}
                                                                    >
                                                                        {format(
                                                                            row.awal,
                                                                        )}
                                                                    </td>
                                                                    {columnLabels.map(
                                                                        ([
                                                                            field,
                                                                        ]) =>
                                                                            field ===
                                                                            'pemakaian' ? (
                                                                                <td
                                                                                    key={
                                                                                        field
                                                                                    }
                                                                                    className="border-r border-b border-border bg-muted/40 px-2 text-right tabular-nums"
                                                                                >
                                                                                    {format(
                                                                                        row.pemakaian,
                                                                                    )}
                                                                                </td>
                                                                            ) : (
                                                                                <td
                                                                                    key={
                                                                                        field
                                                                                    }
                                                                                    className="border-r border-b border-border p-0"
                                                                                >
                                                                                    <AmountInput
                                                                                        value={
                                                                                            entries[
                                                                                                item
                                                                                                    .key
                                                                                            ]?.[
                                                                                                day
                                                                                            ]?.[
                                                                                                field
                                                                                            ]
                                                                                        }
                                                                                        disabled={
                                                                                            !can_write
                                                                                        }
                                                                                        onChange={(
                                                                                            value,
                                                                                        ) =>
                                                                                            setEntry(
                                                                                                item.key,
                                                                                                day,
                                                                                                field,
                                                                                                value,
                                                                                            )
                                                                                        }
                                                                                        className="h-7 border-0 bg-transparent focus:bg-primary/5"
                                                                                        label={`${field} ${item.name} tanggal ${day}`}
                                                                                    />
                                                                                </td>
                                                                            ),
                                                                    )}
                                                                    <td
                                                                        className={cn(
                                                                            'border-r border-b border-border bg-secondary/60 px-2 text-right font-medium tabular-nums',
                                                                            row.akhir <
                                                                                0
                                                                                ? 'text-destructive'
                                                                                : 'text-foreground',
                                                                        )}
                                                                    >
                                                                        {format(
                                                                            row.akhir,
                                                                        )}
                                                                    </td>
                                                                </Fragment>
                                                            );
                                                        },
                                                    )}
                                                </tr>
                                            ))}
                                            {[
                                                ...periods.map(
                                                    (period, index) => ({
                                                        key: period.key,
                                                        label: period.label,
                                                        pick: (key: string) =>
                                                            sheet[key].periods[
                                                                index
                                                            ],
                                                    }),
                                                ),
                                                {
                                                    key: 'total',
                                                    label: 'TOTAL',
                                                    pick: (key: string) =>
                                                        sheet[key].total,
                                                },
                                            ].map((summary) => (
                                                <tr
                                                    key={summary.key}
                                                    className={cn(
                                                        'font-semibold text-foreground',
                                                        summary.key === 'total'
                                                            ? 'bg-secondary'
                                                            : 'bg-muted/40',
                                                    )}
                                                >
                                                    <td
                                                        className={cn(
                                                            'sticky left-0 z-10 border-r border-b border-border px-2 py-1.5 text-[12px] whitespace-nowrap',
                                                            summary.key ===
                                                                'total'
                                                                ? 'bg-secondary'
                                                                : 'bg-card',
                                                        )}
                                                    >
                                                        {summary.label}
                                                    </td>
                                                    {shownItems.map(
                                                        (item, index) => {
                                                            const sum =
                                                                summary.pick(
                                                                    item.key,
                                                                );

                                                            return (
                                                                <Fragment
                                                                    key={
                                                                        item.key
                                                                    }
                                                                >
                                                                    <td
                                                                        className={cn(
                                                                            'border-r border-b border-border',
                                                                            index >
                                                                                0 &&
                                                                                groupStart,
                                                                        )}
                                                                    />
                                                                    {columnLabels.map(
                                                                        ([
                                                                            field,
                                                                        ]) => (
                                                                            <td
                                                                                key={
                                                                                    field
                                                                                }
                                                                                className="border-r border-b border-border px-2 py-1.5 text-right tabular-nums"
                                                                            >
                                                                                {format(
                                                                                    sum[
                                                                                        field
                                                                                    ],
                                                                                )}
                                                                            </td>
                                                                        ),
                                                                    )}
                                                                    <td className="border-r border-b border-border px-2 py-1.5 text-right text-primary tabular-nums">
                                                                        {format(
                                                                            sum.akhir,
                                                                            false,
                                                                        )}
                                                                    </td>
                                                                </Fragment>
                                                            );
                                                        },
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </>
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
                        readOnly={!can_write}
                        placeholder={
                            can_write
                                ? 'Catatan tambahan (opsional)'
                                : 'Tidak ada catatan'
                        }
                    />
                </section>
            </div>

            {compact && can_write && (
                <StickyActionBar>{saveButton}</StickyActionBar>
            )}
        </>
    );
}

/** A borderless amount cell: digits and one decimal separator ("," or "."). */
function AmountInput({
    value,
    onChange,
    disabled,
    className,
    label,
}: {
    value: number | string | undefined;
    onChange: (value: string) => void;
    disabled: boolean;
    className?: string;
    label: string;
}) {
    return (
        <input
            type="text"
            inputMode="decimal"
            aria-label={label}
            value={value === undefined ? '' : String(value)}
            disabled={disabled}
            onChange={(e) => {
                const next = e.target.value.replace(/[^\d.,]/g, '');

                if (/^\d*([.,]\d{0,2})?$/.test(next)) {
                    onChange(next);
                }
            }}
            className={cn(
                'w-full px-2 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-default disabled:opacity-100',
                className,
            )}
        />
    );
}
