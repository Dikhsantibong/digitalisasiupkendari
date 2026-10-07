import { Head, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save, Timer } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
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
import type { RouteQueryOptions } from '@/wayfinder';

export type JamMachine = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
};

/** machine id → day → hours. */
export type JamReadings = Record<string, Record<string, number | string>>;

type Filters = { unit_id: number; month: number; year: number };

type Url = (options?: RouteQueryOptions) => { url: string };

export type JamMesinPageProps = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    machines: JamMachine[];
    days_in_month: number;
    readings: JamReadings;
};

type Editable = {
    star_stop: JamReadings;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

const toNumber = (value: number | string | undefined): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};

const NUMBER = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

/**
 * A machine-hour sheet (Jam Operasi / Pemeliharaan / Gangguan / Siap Operasi):
 * mesin columns with TYPE, NO. SERI and UNIT rows, one row per tanggal, a
 * TOTAL column and a JMH row — the layout of the field Excel. Editable sheets
 * take `editable`; Jam Siap Operasi is shown read-only.
 */
export function JamMesinPage({
    unit,
    units,
    filters,
    period_label,
    machines,
    days_in_month,
    readings: saved,
    title,
    description,
    routes,
    editable,
    notice,
    highlight,
}: JamMesinPageProps & {
    title: string;
    description: string;
    routes: { index: Url; store?: Url; pdf: Url };
    editable?: Editable;
    notice?: ReactNode;
    /** Cells to flag, e.g. days whose hours exceed 24 ("{machineId}-{day}"). */
    highlight?: Set<string>;
}) {
    const compact = useCompactLayout();
    const canWrite = Boolean(editable?.can_write && routes.store);
    const [readings, setReadings] = useState<JamReadings>(saved);
    const [note, setNote] = useState(editable?.catatan ?? '');
    const [saving, setSaving] = useState(false);
    const [selectedDay, setSelectedDay] = useState(() => {
        const today = new Date();

        return today.getMonth() + 1 === filters.month &&
            today.getFullYear() === filters.year
            ? today.getDate()
            : 1;
    });

    const dirty = useMemo(
        () =>
            JSON.stringify(readings) !== JSON.stringify(saved) ||
            note !== (editable?.catatan ?? ''),
        [readings, saved, note, editable?.catatan],
    );
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const starStopCells = editable
        ? Object.values(editable.star_stop).reduce(
              (count, row) => count + Object.keys(row).length,
              0,
          )
        : 0;

    const cell = (machineId: number, day: number) =>
        toNumber(readings[machineId]?.[day]);
    const dayTotal = (day: number) =>
        machines.reduce((total, m) => total + cell(m.id, day), 0);
    const machineTotal = (machineId: number) =>
        days.reduce((total, day) => total + cell(machineId, day), 0);
    const grandTotal = machines.reduce(
        (total, m) => total + machineTotal(m.id),
        0,
    );

    const setCell = (machineId: number, day: number, value: string) =>
        setReadings((current) => {
            const row = { ...(current[machineId] ?? {}) };

            if (value === '') {
                delete row[day];
            } else {
                row[day] = value;
            }

            return { ...current, [machineId]: row };
        });

    /** Fill the empty cells with the hours recorded in Star-Stop (filled cells are kept). */
    const fillFromStarStop = () =>
        setReadings((current) => {
            const next = { ...current };

            for (const [machineId, row] of Object.entries(
                editable?.star_stop ?? {},
            )) {
                const merged = { ...(next[machineId] ?? {}) };

                for (const [day, value] of Object.entries(row)) {
                    if (toNumber(merged[day]) === 0) {
                        merged[day] = value;
                    }
                }

                next[machineId] = merged;
            }

            return next;
        });

    const visit = (patch: Partial<Filters>) => {
        if (
            dirty &&
            canWrite &&
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
        if (!routes.store) {
            return;
        }

        const payload: Record<string, Record<string, number>> = {};

        for (const [machineId, row] of Object.entries(readings)) {
            for (const [day, value] of Object.entries(row)) {
                const hours = toNumber(value);

                if (hours > 0) {
                    payload[machineId] = {
                        ...(payload[machineId] ?? {}),
                        [day]: hours,
                    };
                }
            }
        }

        router.post(
            routes.store().url,
            { ...filters, readings: payload, catatan: note },
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
    const saveButton = canWrite && (
        <Button onClick={save} disabled={saving || !dirty}>
            {saving ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <Save className="size-4" />
            )}
            Simpan
        </Button>
    );
    const display = (machineId: number, day: number) => {
        const value = cell(machineId, day);

        return editable && value === 0 ? '' : NUMBER.format(value);
    };

    return (
        <>
            <Head title={title} />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && canWrite && 'pb-28',
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
                    {editable && (
                        <p className="ml-auto self-center text-[13px] text-muted-foreground">
                            {dirty && canWrite ? (
                                <span className="font-medium text-amber-700">
                                    Ada perubahan yang belum disimpan
                                </span>
                            ) : editable.saved_at ? (
                                `Tersimpan ${formatStamp(editable.saved_at)}`
                            ) : (
                                'Belum pernah disimpan'
                            )}
                        </p>
                    )}
                </div>

                {notice}

                {machines.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <SummaryCard
                                label="Total"
                                value={NUMBER.format(grandTotal)}
                                unit="jam"
                                hint={`${machines.length} mesin`}
                            />
                            {machines.slice(0, 3).map((machine) => (
                                <SummaryCard
                                    key={machine.id}
                                    label={machine.name}
                                    value={NUMBER.format(
                                        machineTotal(machine.id),
                                    )}
                                    unit="jam"
                                />
                            ))}
                        </div>

                        {canWrite && starStopCells > 0 && (
                            <div className="flex flex-wrap items-center gap-3 rounded-md border border-border bg-card px-4 py-3 text-[13px]">
                                <Timer className="size-4 shrink-0 text-primary" />
                                <span className="flex-1 text-muted-foreground">
                                    Star-Stop bulan ini sudah mencatat jam per
                                    mesin. Isi sel yang masih kosong dengan jam
                                    dari Star-Stop, lalu periksa dan simpan.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={fillFromStarStop}
                                >
                                    Ambil dari Star-Stop
                                </Button>
                            </div>
                        )}

                        {compact ? (
                            <section className="flex flex-col gap-3">
                                <DayStrip
                                    items={days.map((day) => ({
                                        key: String(day),
                                        label: String(day),
                                        done: dayTotal(day) !== 0,
                                        isRed: highlight
                                            ? machines.some((m) =>
                                                  highlight.has(
                                                      `${m.id}-${day}`,
                                                  ),
                                              )
                                            : false,
                                    }))}
                                    value={String(selectedDay)}
                                    onChange={(key) =>
                                        setSelectedDay(Number(key))
                                    }
                                />
                                <div className="rounded-xl border border-border bg-card p-3">
                                    <div className="mb-2 flex items-baseline justify-between">
                                        <h2 className="text-sm font-semibold text-foreground">
                                            Tanggal {selectedDay}
                                        </h2>
                                        <span className="text-[12px] text-muted-foreground tabular-nums">
                                            Total{' '}
                                            <b className="text-foreground">
                                                {NUMBER.format(
                                                    dayTotal(selectedDay),
                                                )}
                                            </b>{' '}
                                            jam
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        {machines.map((machine) =>
                                            canWrite ? (
                                                <label
                                                    key={machine.id}
                                                    className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                                >
                                                    {machine.name}
                                                    <HoursInput
                                                        value={
                                                            readings[
                                                                machine.id
                                                            ]?.[selectedDay]
                                                        }
                                                        onChange={(value) =>
                                                            setCell(
                                                                machine.id,
                                                                selectedDay,
                                                                value,
                                                            )
                                                        }
                                                        className="h-10 rounded-md border border-border bg-background text-sm"
                                                        label={`${machine.name} tanggal ${selectedDay}`}
                                                    />
                                                </label>
                                            ) : (
                                                <div
                                                    key={machine.id}
                                                    className={cn(
                                                        'rounded-md border border-border px-3 py-2',
                                                        highlight?.has(
                                                            `${machine.id}-${selectedDay}`,
                                                        ) &&
                                                            'border-red-300 bg-red-50',
                                                    )}
                                                >
                                                    <p className="text-[12px] text-muted-foreground">
                                                        {machine.name}
                                                    </p>
                                                    <p className="text-sm font-semibold text-foreground tabular-nums">
                                                        {NUMBER.format(
                                                            cell(
                                                                machine.id,
                                                                selectedDay,
                                                            ),
                                                        )}
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </section>
                        ) : (
                            <section className="overflow-hidden rounded-md border border-border bg-card">
                                <div className="overflow-x-auto">
                                    <table
                                        className="w-full border-collapse text-[13px]"
                                        data-keep-table
                                    >
                                        <thead>
                                            <tr className="bg-secondary text-foreground">
                                                <th className="sticky left-0 z-20 w-24 border-r border-b border-border bg-secondary px-2 py-2 text-left text-[12px] font-semibold">
                                                    MESIN
                                                </th>
                                                <th
                                                    colSpan={machines.length}
                                                    className="border-r border-b border-border px-2 py-2 text-center text-[13px] font-semibold tracking-[0.3em]"
                                                >
                                                    MAK
                                                </th>
                                                <th
                                                    rowSpan={4}
                                                    className="min-w-24 border-b border-border bg-secondary px-2 py-2 text-center text-[12px] font-semibold"
                                                >
                                                    TOTAL
                                                </th>
                                            </tr>
                                            {(
                                                [
                                                    [
                                                        'TYPE',
                                                        (m: JamMachine) =>
                                                            m.type ?? '-',
                                                    ],
                                                    [
                                                        'NO. SERI',
                                                        (m: JamMachine) =>
                                                            m.serial_number ??
                                                            '-',
                                                    ],
                                                    [
                                                        'UNIT',
                                                        (m: JamMachine) =>
                                                            m.name,
                                                    ],
                                                ] as const
                                            ).map(([label, value]) => (
                                                <tr
                                                    key={label}
                                                    className="text-[12px] text-muted-foreground"
                                                >
                                                    <th className="sticky left-0 z-20 border-r border-b border-border bg-card px-2 py-1 text-left font-semibold">
                                                        {label}
                                                    </th>
                                                    {machines.map((machine) => (
                                                        <th
                                                            key={machine.id}
                                                            className={cn(
                                                                'min-w-24 border-r border-b border-border px-2 py-1 text-center whitespace-nowrap',
                                                                label === 'UNIT'
                                                                    ? 'font-semibold text-foreground'
                                                                    : 'font-normal',
                                                            )}
                                                        >
                                                            {value(machine)}
                                                        </th>
                                                    ))}
                                                </tr>
                                            ))}
                                        </thead>
                                        <tbody>
                                            {days.map((day) => (
                                                <tr
                                                    key={day}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 text-center text-[12px] font-medium text-muted-foreground tabular-nums">
                                                        {day}
                                                    </td>
                                                    {machines.map((machine) => {
                                                        const flagged =
                                                            highlight?.has(
                                                                `${machine.id}-${day}`,
                                                            );

                                                        return (
                                                            <td
                                                                key={machine.id}
                                                                className={cn(
                                                                    'border-r border-b border-border',
                                                                    canWrite
                                                                        ? 'p-0'
                                                                        : 'px-2 py-1 text-right tabular-nums',
                                                                    flagged &&
                                                                        'bg-red-50 font-semibold text-red-700',
                                                                )}
                                                            >
                                                                {canWrite ? (
                                                                    <HoursInput
                                                                        value={
                                                                            readings[
                                                                                machine
                                                                                    .id
                                                                            ]?.[
                                                                                day
                                                                            ]
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            setCell(
                                                                                machine.id,
                                                                                day,
                                                                                value,
                                                                            )
                                                                        }
                                                                        className="h-7 border-0 bg-transparent focus:bg-primary/5"
                                                                        label={`${machine.name} tanggal ${day}`}
                                                                    />
                                                                ) : (
                                                                    display(
                                                                        machine.id,
                                                                        day,
                                                                    )
                                                                )}
                                                            </td>
                                                        );
                                                    })}
                                                    <td className="border-b border-border bg-secondary/60 px-2 text-right font-medium text-foreground tabular-nums">
                                                        {NUMBER.format(
                                                            dayTotal(day),
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-secondary font-semibold text-foreground">
                                                <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                    JMH
                                                </td>
                                                {machines.map((machine) => (
                                                    <td
                                                        key={machine.id}
                                                        className="border-r border-border px-2 py-1.5 text-right tabular-nums"
                                                    >
                                                        {NUMBER.format(
                                                            machineTotal(
                                                                machine.id,
                                                            ),
                                                        )}
                                                    </td>
                                                ))}
                                                <td className="px-2 py-1.5 text-right text-primary tabular-nums">
                                                    {NUMBER.format(grandTotal)}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </>
                )}

                {editable && (
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
                            readOnly={!canWrite}
                            placeholder={
                                canWrite
                                    ? 'Catatan tambahan (opsional)'
                                    : 'Tidak ada catatan'
                            }
                        />
                    </section>
                )}
            </div>

            {compact && canWrite && (
                <StickyActionBar>{saveButton}</StickyActionBar>
            )}
        </>
    );
}

/** A borderless hours cell: up to 24, two decimals, "," or "." as separator. */
function HoursInput({
    value,
    onChange,
    className,
    label,
}: {
    value: number | string | undefined;
    onChange: (value: string) => void;
    className?: string;
    label: string;
}) {
    const invalid = toNumber(value) > 24;

    return (
        <input
            type="text"
            inputMode="decimal"
            aria-label={label}
            aria-invalid={invalid || undefined}
            value={value === undefined ? '' : String(value)}
            onChange={(e) => {
                const next = e.target.value.replace(/[^\d.,]/g, '');

                if (/^\d{0,2}([.,]\d{0,2})?$/.test(next)) {
                    onChange(next);
                }
            }}
            className={cn(
                'w-full px-2 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring',
                invalid && 'bg-red-50 text-red-700',
                className,
            )}
        />
    );
}
