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
    /** Beban Tertinggi: the cells are the operators' logsheet LOAD, read-only. */
    from_logsheet?: boolean;
    /** Jumlah Kali Gangguan: the gangguan stops of Start Stop Engine, read-only. */
    from_start_stop?: boolean;
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
    from_logsheet = false,
    from_start_stop = false,
    config,
}: MesinHarianPageProps & { config: Config }) {
    const compact = useCompactLayout();
    const automatic = from_logsheet || from_start_stop;
    const cellsEditable = can_write && !automatic;
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
            disabled={!cellsEditable}
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
                'w-full px-2 text-right tabular-nums outline-none focus:bg-yellow-50 focus-visible:ring-0 dark:focus:bg-yellow-950/40 disabled:cursor-default disabled:opacity-100',
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
                {!compact && (
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
                )}

                {!compact && (
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
                )}

                {machines.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    </section>
                ) : (
                    <>
                        <div className={cn('grid grid-cols-2 gap-3 lg:grid-cols-4', compact && 'hidden')}>
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

                        {automatic && !compact && (
                            <div className="flex flex-wrap items-center gap-3 rounded-md border border-sky-300 bg-sky-50 px-4 py-3 text-[13px] text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                                <Zap className="size-4 shrink-0" />
                                {from_logsheet ? (
                                    <span className="flex-1">
                                        Beban tertinggi tiap mesin per hari diambil otomatis dari
                                        <b> LOAD (kW) tertinggi di Logsheet Operator</b> — tidak perlu
                                        diinput. Yang bisa diubah di sini hanya daya mampu dan catatan.
                                    </span>
                                ) : (
                                    <span className="flex-1">
                                        Jumlah gangguan tiap mesin per hari dihitung otomatis dari
                                        <b> stop karena gangguan di Start Stop Engine</b> (menu Operator) — tidak
                                        perlu diinput. Yang bisa diubah di sini hanya catatan.
                                    </span>
                                )}
                            </div>
                        )}

                        {cellsEditable && daya_mampu && (
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

                        {cellsEditable && starStopCells > 0 && (
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
                                    {automatic ? (
                                        <div className="flex flex-col gap-2">
                                            <p className="text-[12px] text-sky-700 dark:text-sky-300">
                                                {from_logsheet ? 'Beban otomatis dari LOAD tertinggi Logsheet Operator.' : 'Jumlah otomatis dari stop gangguan di Start Stop Engine.'}
                                            </p>
                                            {machines.map((machine) => {
                                                const beban = toNumber(readings[machine.id]?.[selectedDay]);
                                                const cap = toNumber(mampu[machine.id]);

                                                return (
                                                    <div key={machine.id} className="flex items-center gap-3 rounded-lg border border-border p-2.5">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-[14px] font-semibold text-foreground">{machine.name}</p>
                                                            <p className="text-[12px] text-muted-foreground">
                                                                {from_start_stop
                                                                    ? beban > 0
                                                                        ? 'Dari Start Stop Engine'
                                                                        : 'Tidak ada gangguan'
                                                                    : beban > 0 && cap > 0
                                                                      ? `${Math.round((beban / cap) * 100)}% dari daya mampu`
                                                                      : beban > 0
                                                                        ? 'Dari logsheet'
                                                                        : 'Belum ada logsheet'}
                                                            </p>
                                                        </div>
                                                        <div className={cn('rounded-md px-2.5 py-1 text-right tabular-nums', beban > 0 ? 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' : 'bg-muted text-muted-foreground')}>
                                                            <span className="text-[17px] font-bold">{beban > 0 ? NUMBER.format(beban) : '–'}</span>
                                                            <span className="ml-0.5 text-[11px]">{config.satuan}</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
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
                                    )}
                                </div>
                                {daya_mampu && (
                                    <div className="rounded-xl border border-orange-200 bg-orange-50 p-3 dark:border-orange-900 dark:bg-orange-950/30">
                                        <h2 className="mb-2 text-sm font-semibold text-orange-950 dark:text-orange-100">Daya mampu (kW)</h2>
                                        <div className="grid grid-cols-2 gap-2">
                                            {machines.map((machine) => (
                                                <label key={machine.id} className="flex flex-col gap-1 text-[12px] text-orange-900 dark:text-orange-200">
                                                    {machine.name}
                                                    <input
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={mampu[machine.id] ?? ''}
                                                        readOnly={!can_write}
                                                        onChange={(e) =>
                                                            /^\d*([.,]\d{0,2})?$/.test(e.target.value) &&
                                                            setMampu((current) => ({ ...current, [machine.id]: e.target.value }))
                                                        }
                                                        className="h-10 w-full rounded-md border border-orange-200 bg-background px-2 text-right text-sm font-semibold tabular-nums outline-none focus:ring-2 focus:ring-primary dark:border-orange-900"
                                                        aria-label={`Daya mampu ${machine.name}`}
                                                    />
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </section>
                        ) : (
                            <section className="overflow-hidden rounded-md border border-border bg-card">
                                <div className="overflow-x-auto">
                                    <table
                                        className="w-full border-collapse border-2 border-black text-[13px] dark:border-white [&_td]:border-black [&_th]:border-black dark:[&_td]:border-white/60 dark:[&_th]:border-white"
                                        data-keep-table
                                    >
                                        <thead>
                                            <tr className="bg-yellow-300 text-[12px] text-black dark:bg-yellow-700 dark:text-yellow-50">
                                                <th className="sticky left-0 z-20 w-28 border border-border bg-yellow-400 px-2 py-2 text-left font-bold dark:bg-yellow-600">
                                                    MERK
                                                </th>
                                                {groups.map((group, index) => (
                                                    <th
                                                        key={`${group.merk}-${index}`}
                                                        colSpan={group.count}
                                                        className="border border-border px-2 py-2 text-center font-bold tracking-[0.2em]"
                                                    >
                                                        {group.merk}
                                                    </th>
                                                ))}
                                                <th
                                                    rowSpan={daya_mampu ? 4 : 4}
                                                    className="min-w-28 border border-border bg-yellow-400 px-2 py-2 text-center font-bold dark:bg-yellow-600"
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
                                                    className="bg-yellow-100 text-[12px] text-black dark:bg-yellow-950/60 dark:text-yellow-50"
                                                >
                                                    <th className="sticky left-0 z-20 border border-border bg-yellow-200 px-2 py-1 text-left font-bold dark:bg-yellow-900">
                                                        {label}
                                                    </th>
                                                    {machines.map((machine) => (
                                                        <th
                                                            key={machine.id}
                                                            className={cn(
                                                                'min-w-24 border border-border px-2 py-1 text-center whitespace-nowrap',
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
                                                <tr className="bg-orange-100 text-[12px] dark:bg-orange-950/50">
                                                    <th className="sticky left-0 z-20 border border-border bg-orange-200 px-2 py-1 text-left font-bold text-orange-950 dark:bg-orange-900 dark:text-orange-50">
                                                        DAYA MAMPU (kW)
                                                    </th>
                                                    {machines.map((machine) => (
                                                        <th
                                                            key={machine.id}
                                                            className="border border-border p-0"
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
                                                                className="h-8 w-full bg-transparent px-2 text-right font-semibold text-amber-900 tabular-nums outline-none focus:bg-yellow-50 focus-visible:ring-0 dark:focus:bg-yellow-950/40"
                                                            />
                                                        </th>
                                                    ))}
                                                    <th className="border border-border px-2 py-1 text-right font-bold text-orange-950 tabular-nums dark:text-orange-50">
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
                                                    <td className="sticky left-0 z-10 border border-border bg-yellow-50 px-2 text-center text-[12px] font-semibold text-black tabular-nums dark:bg-card dark:text-foreground">
                                                        {day}
                                                    </td>
                                                    {machines.map((machine) => (
                                                        <td
                                                            key={machine.id}
                                                            className={cn(
                                                                'border border-border p-0',
                                                                automatic && readings[machine.id]?.[day] !== undefined && 'bg-sky-50 dark:bg-sky-950/30',
                                                            )}
                                                        >
                                                            {input(
                                                                machine,
                                                                day,
                                                                'h-7 border-0 bg-transparent focus:bg-yellow-50 dark:focus:bg-yellow-950/40',
                                                            )}
                                                        </td>
                                                    ))}
                                                    <td className="border border-border bg-emerald-50 px-2 text-right font-semibold text-emerald-900 tabular-nums dark:bg-emerald-950/40 dark:text-emerald-200">
                                                        {NUMBER.format(
                                                            dayTotal(day),
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-yellow-300 font-bold text-black dark:bg-yellow-700 dark:text-yellow-50">
                                                <td className="sticky left-0 z-10 border border-border bg-yellow-400 px-2 py-1.5 text-center text-[12px] dark:bg-yellow-600">
                                                    {config.mode === 'max'
                                                        ? 'TERTINGGI'
                                                        : 'JMH'}
                                                </td>
                                                {machines.map((machine) => (
                                                    <td
                                                        key={machine.id}
                                                        className="border border-border px-2 py-1.5 text-right tabular-nums"
                                                    >
                                                        {NUMBER.format(
                                                            machineTotal(
                                                                machine.id,
                                                            ),
                                                        )}
                                                    </td>
                                                ))}
                                                <td className="border border-border px-2 py-1.5 text-right tabular-nums">
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
