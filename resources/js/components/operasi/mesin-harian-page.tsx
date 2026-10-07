import { Head, router } from '@inertiajs/react';
import {
    ClipboardPaste,
    Download,
    Eye,
    Loader2,
    Save,
    Timer,
    Zap,
} from 'lucide-react';
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

type Machine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
    capacity_kw: number | null;
};

/** machine id → day → value. */
type Readings = Record<string, Record<string, number | string>>;

type Filters = { unit_id: number; month: number; year: number };

type Url = (options?: RouteQueryOptions) => { url: string };

export type MesinHarianPageProps = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    machines: Machine[];
    days_in_month: number;
    readings: Readings;
    daya_mampu: Record<string, number | null> | null;
    star_stop: Readings | null;
    star_stop_notes: string[];
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

type Config = {
    title: string;
    description: string;
    /** "max": Beban Tertinggi (TERTINGGI row, highest daily sum); "sum": Jumlah Kali Gangguan; "average": Tara Kalor. */
    mode: 'max' | 'sum' | 'average';
    satuan: string;
    integer?: boolean;
    routes: { index: Url; store: Url; pdf: Url };
};

const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

const toNumber = (value: number | string | null | undefined): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};

const merkOf = (machine: Machine) =>
    machine.merk?.trim().toUpperCase() || 'M A K';

/**
 * Beban Tertinggi / Jumlah Kali Gangguan / Tara Kalor per mesin per tanggal, in the layout
 * of the field Excel: machines grouped under their MERK, TYPE / NO. SERI /
 * UNIT rows, a total column per day and a TERTINGGI (max) or JMH (sum) row.
 */
export function MesinHarianPage({
    unit,
    units,
    filters,
    period_label,
    machines: unordered,
    days_in_month,
    readings: saved,
    daya_mampu,
    star_stop,
    star_stop_notes,
    catatan,
    saved_at,
    can_write,
    config,
}: MesinHarianPageProps & { config: Config }) {
    const compact = useCompactLayout();
    const machines = useMemo(
        () =>
            [...unordered].sort(
                (a, b) =>
                    merkOf(a).localeCompare(merkOf(b)) ||
                    a.name.localeCompare(b.name),
            ),
        [unordered],
    );
    const groups = useMemo(
        () =>
            machines.reduce<{ merk: string; count: number }[]>(
                (list, machine) => {
                    const last = list[list.length - 1];

                    if (last && last.merk === merkOf(machine)) {
                        last.count++;
                    } else {
                        list.push({ merk: merkOf(machine), count: 1 });
                    }

                    return list;
                },
                [],
            ),
        [machines],
    );

    const initialMampu = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(daya_mampu ?? {}).map(([id, value]) => [
                    id,
                    value === null ? '' : String(value),
                ]),
            ),
        [daya_mampu],
    );
    const [readings, setReadings] = useState<Readings>(saved);
    const [mampu, setMampu] = useState<Record<string, string>>(initialMampu);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const [selectedDay, setSelectedDay] = useState(() => {
        const today = new Date();

        return today.getMonth() + 1 === filters.month &&
            today.getFullYear() === filters.year
            ? today.getDate()
            : 1;
    });

    const dirty =
        JSON.stringify(readings) !== JSON.stringify(saved) ||
        JSON.stringify(mampu) !== JSON.stringify(initialMampu) ||
        note !== catatan;
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const cell = (machineId: number, day: number) =>
        toNumber(readings[machineId]?.[day]);
    /** The average of the filled (positive) values, 0 when none. */
    const average = (values: number[]) => {
        const filled = values.filter((value) => value > 0);

        return filled.length === 0
            ? 0
            : filled.reduce((total, value) => total + value, 0) / filled.length;
    };
    const dayTotal = (day: number) =>
        config.mode === 'average'
            ? average(machines.map((m) => cell(m.id, day)))
            : machines.reduce((total, m) => total + cell(m.id, day), 0);
    const machineTotal = (machineId: number) =>
        config.mode === 'max'
            ? Math.max(0, ...days.map((day) => cell(machineId, day)))
            : config.mode === 'average'
              ? average(days.map((day) => cell(machineId, day)))
              : days.reduce((total, day) => total + cell(machineId, day), 0);
    const unitTotal =
        config.mode === 'max'
            ? Math.max(0, ...days.map(dayTotal))
            : config.mode === 'average'
              ? average(
                    machines.flatMap((m) => days.map((day) => cell(m.id, day))),
                )
              : days.reduce((total, day) => total + dayTotal(day), 0);
    const mampuTotal = machines.reduce(
        (total, m) => total + toNumber(mampu[m.id]),
        0,
    );
    const starStopCells = star_stop
        ? Object.values(star_stop).reduce(
              (count, row) => count + Object.keys(row).length,
              0,
          )
        : 0;

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

    /** Fill the empty cells, keeping what was typed. */
    const fillEmpty = (
        valueOf: (
            machineId: number,
            day: number,
        ) => number | string | undefined,
    ) =>
        setReadings((current) => {
            const next = { ...current };

            for (const machine of machines) {
                const row = { ...(next[machine.id] ?? {}) };

                for (const day of days) {
                    const value = valueOf(machine.id, day);

                    if (
                        toNumber(row[day]) === 0 &&
                        value !== undefined &&
                        toNumber(value) > 0
                    ) {
                        row[day] = String(value);
                    }
                }

                next[machine.id] = row;
            }

            return next;
        });

    const visit = (patch: Partial<Filters>) => {
        if (
            dirty &&
            can_write &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            config.routes.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () => {
        const payload: Record<string, Record<string, number>> = {};

        for (const [machineId, row] of Object.entries(readings)) {
            for (const [day, value] of Object.entries(row)) {
                const number = toNumber(value);

                if (number > 0) {
                    payload[machineId] = {
                        ...(payload[machineId] ?? {}),
                        [day]: number,
                    };
                }
            }
        }

        router.post(
            config.routes.store().url,
            {
                ...filters,
                readings: payload,
                daya_mampu: daya_mampu
                    ? Object.fromEntries(
                          Object.entries(mampu).map(([id, value]) => [
                              id,
                              value === '' ? null : toNumber(value),
                          ]),
                      )
                    : undefined,
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
    const input = (machine: Machine, day: number, className: string) => (
        <input
            type="text"
            inputMode={config.integer ? 'numeric' : 'decimal'}
            aria-label={`${machine.name} tanggal ${day}`}
            value={
                readings[machine.id]?.[day] === undefined
                    ? ''
                    : String(readings[machine.id][day])
            }
            disabled={!can_write}
            onChange={(e) => {
                const next = e.target.value.replace(
                    config.integer ? /[^\d]/g : /[^\d.,]/g,
                    '',
                );

                if (
                    config.integer
                        ? /^\d{0,3}$/.test(next)
                        : /^\d*([.,]\d{0,2})?$/.test(next)
                ) {
                    setCell(machine.id, day, next);
                }
            }}
            className={cn(
                'w-full px-2 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-default disabled:opacity-100',
                className,
            )}
        />
    );

    return (
        <>
            <Head title={config.title} />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title={config.title}
                    description={`${config.description} — ${unit.name}, ${period_label}.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={config.routes.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        config.routes.pdf({
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
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {dirty && can_write ? (
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
                                label={
                                    config.mode === 'max'
                                        ? 'Beban tertinggi unit'
                                        : config.mode === 'average'
                                          ? 'Rata-rata unit'
                                          : 'Total gangguan'
                                }
                                value={NUMBER.format(unitTotal)}
                                unit={config.satuan}
                                hint={period_label}
                            />
                            {daya_mampu && (
                                <SummaryCard
                                    label="Daya mampu unit"
                                    value={NUMBER.format(mampuTotal)}
                                    unit="kW"
                                    hint={`${machines.length} mesin`}
                                />
                            )}
                            {machines
                                .slice(0, daya_mampu ? 2 : 3)
                                .map((machine) => (
                                    <SummaryCard
                                        key={machine.id}
                                        label={machine.name}
                                        value={NUMBER.format(
                                            machineTotal(machine.id),
                                        )}
                                        unit={config.satuan}
                                    />
                                ))}
                        </div>

                        {can_write && daya_mampu && (
                            <div className="flex flex-wrap items-center gap-3 rounded-md border border-border bg-card px-4 py-3 text-[13px]">
                                <Zap className="size-4 shrink-0 text-primary" />
                                <span className="flex-1 text-muted-foreground">
                                    Bila mesin beroperasi pada daya mampunya,
                                    isi tanggal yang masih kosong dengan daya
                                    mampu tiap mesin, lalu ubah hari yang
                                    berbeda.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        fillEmpty(
                                            (machineId) => mampu[machineId],
                                        )
                                    }
                                >
                                    Isi dengan daya mampu
                                </Button>
                            </div>
                        )}

                        {can_write && starStopCells > 0 && (
                            <div className="flex flex-wrap items-center gap-3 rounded-md border border-border bg-card px-4 py-3 text-[13px]">
                                <Timer className="size-4 shrink-0 text-primary" />
                                <span className="flex-1 text-muted-foreground">
                                    Star-Stop bulan ini mencatat{' '}
                                    {star_stop_notes.length} kejadian gangguan.
                                    Isi sel yang masih kosong dengan jumlahnya,
                                    lalu periksa dan simpan.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        fillEmpty(
                                            (machineId, day) =>
                                                star_stop?.[machineId]?.[day],
                                        )
                                    }
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
                                        done: dayTotal(day) > 0,
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
                                            {config.mode === 'average'
                                                ? 'Rata-rata'
                                                : 'Jumlah'}{' '}
                                            <b className="text-foreground">
                                                {NUMBER.format(
                                                    dayTotal(selectedDay),
                                                )}
                                            </b>{' '}
                                            {config.satuan}
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        {machines.map((machine) => (
                                            <label
                                                key={machine.id}
                                                className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                            >
                                                {machine.name}
                                                {input(
                                                    machine,
                                                    selectedDay,
                                                    'h-10 rounded-md border border-border bg-background text-sm',
                                                )}
                                            </label>
                                        ))}
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
                                            <tr className="bg-secondary text-[12px] text-foreground">
                                                <th className="sticky left-0 z-20 w-28 border-r border-b border-border bg-secondary px-2 py-2 text-left font-semibold">
                                                    MERK
                                                </th>
                                                {groups.map((group, index) => (
                                                    <th
                                                        key={`${group.merk}-${index}`}
                                                        colSpan={group.count}
                                                        className="border-r border-b border-border px-2 py-2 text-center font-semibold tracking-[0.2em]"
                                                    >
                                                        {group.merk}
                                                    </th>
                                                ))}
                                                <th
                                                    rowSpan={daya_mampu ? 4 : 4}
                                                    className="min-w-28 border-b border-border bg-secondary px-2 py-2 text-center font-semibold"
                                                >
                                                    {config.mode === 'max'
                                                        ? `JUM. ${unit.name.toUpperCase()}`
                                                        : 'TOTAL'}
                                                </th>
                                            </tr>
                                            {(
                                                [
                                                    [
                                                        'TYPE',
                                                        (m: Machine) =>
                                                            m.type ?? '-',
                                                    ],
                                                    [
                                                        'NO. SERI',
                                                        (m: Machine) =>
                                                            m.serial_number ??
                                                            '-',
                                                    ],
                                                    [
                                                        'UNIT',
                                                        (m: Machine) => m.name,
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
                                            {daya_mampu && (
                                                <tr className="bg-amber-50 text-[12px]">
                                                    <th className="sticky left-0 z-20 border-r border-b border-border bg-amber-50 px-2 py-1 text-left font-semibold text-amber-900">
                                                        DAYA MAMPU (kW)
                                                    </th>
                                                    {machines.map((machine) => (
                                                        <th
                                                            key={machine.id}
                                                            className="border-r border-b border-border p-0"
                                                        >
                                                            <input
                                                                type="text"
                                                                inputMode="decimal"
                                                                aria-label={`Daya mampu ${machine.name}`}
                                                                value={
                                                                    mampu[
                                                                        machine
                                                                            .id
                                                                    ] ?? ''
                                                                }
                                                                readOnly={
                                                                    !can_write
                                                                }
                                                                onChange={(e) =>
                                                                    /^\d*([.,]\d{0,2})?$/.test(
                                                                        e.target
                                                                            .value,
                                                                    ) &&
                                                                    setMampu(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [machine.id]:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                className="h-8 w-full bg-transparent px-2 text-right font-semibold text-amber-900 tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                            />
                                                        </th>
                                                    ))}
                                                    <th className="border-b border-border px-2 py-1 text-right font-semibold text-amber-900 tabular-nums">
                                                        {NUMBER.format(
                                                            mampuTotal,
                                                        )}
                                                    </th>
                                                </tr>
                                            )}
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
                                                    {machines.map((machine) => (
                                                        <td
                                                            key={machine.id}
                                                            className="border-r border-b border-border p-0"
                                                        >
                                                            {input(
                                                                machine,
                                                                day,
                                                                'h-7 border-0 bg-transparent focus:bg-primary/5',
                                                            )}
                                                        </td>
                                                    ))}
                                                    <td className="border-b border-border bg-secondary/60 px-2 text-right font-medium tabular-nums">
                                                        {NUMBER.format(
                                                            dayTotal(day),
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-secondary font-semibold">
                                                <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                    {config.mode === 'max'
                                                        ? 'TERTINGGI'
                                                        : 'JMH'}
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
                                                    {NUMBER.format(unitTotal)}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </>
                )}

                {star_stop_notes.length > 0 && (
                    <section className="flex flex-col gap-2 rounded-md border border-border bg-card p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="flex-1 text-base font-semibold text-foreground">
                                Kejadian gangguan di Star-Stop
                            </h2>
                            {can_write && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setNote((current) =>
                                            [
                                                current.trim(),
                                                ...star_stop_notes.map(
                                                    (line, index) =>
                                                        `${index + 1}. ${line}`,
                                                ),
                                            ]
                                                .filter(Boolean)
                                                .join('\n'),
                                        )
                                    }
                                >
                                    <ClipboardPaste className="size-4" /> Salin
                                    ke keterangan
                                </Button>
                            )}
                        </div>
                        <ol className="list-decimal pl-5 text-[13px] text-muted-foreground">
                            {star_stop_notes.map((line, index) => (
                                <li key={index}>{line}</li>
                            ))}
                        </ol>
                    </section>
                )}

                <section className="flex flex-col gap-1.5 rounded-md border border-border bg-card p-4">
                    <label
                        htmlFor="catatan"
                        className="text-sm font-medium text-foreground"
                    >
                        Keterangan
                    </label>
                    <Textarea
                        id="catatan"
                        rows={4}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        readOnly={!can_write}
                        placeholder={
                            can_write
                                ? 'Keterangan (opsional), mis. rincian kejadian gangguan'
                                : 'Tidak ada keterangan'
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
