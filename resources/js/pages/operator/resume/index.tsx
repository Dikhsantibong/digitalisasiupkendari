import { Head, router } from '@inertiajs/react';
import {
    ArrowRightLeft,
    BarChart3,
    CalendarDays,
    ChevronDown,
    ClipboardList,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import resume from '@/routes/operator/resume';
import type { IdName } from '@/types';

type EmployeeResume = {
    id: number;
    name: string;
    position: string | null;
    regu: string | null;
    is_shift_leader: boolean;
    group: 'shift' | 'non_shift';
    has_account: boolean;
    kehadiran: {
        jadwal_kerja: number;
        shift: Record<'P' | 'S' | 'M' | 'OFF', number>;
        hadir: number;
        terlambat: number;
        tidak_hadir: number;
        di_luar_jadwal: number;
        percent: number | null;
        absen: Record<string, number>;
    };
    tukar_shift: { menggantikan: number; digantikan: number };
    pekerjaan: Record<string, number>;
};

type Day = {
    day: number;
    date: string;
    name: string;
    code: string | null;
    code_label: string | null;
    working: boolean;
    note: string | null;
    check_in: string | null;
    check_out: string | null;
    late_minutes: number | null;
    status:
        | 'hadir'
        | 'terlambat'
        | 'tidak_hadir'
        | 'menunggu'
        | 'di_luar_jadwal'
        | null;
    logsheet: number;
    aktivitas: { time: string; description: string }[];
};

type Props = {
    unit: IdName;
    filters: {
        unit_id: number;
        month: number;
        year: number;
        employee_id: number | null;
    };
    period_label: string;
    options: {
        units: IdName[];
        years: number[];
        pekerjaan: Record<string, string>;
        absen: Record<string, string>;
    };
    employees: EmployeeResume[];
    selected: EmployeeResume | null;
    days: Day[];
    sees_all: boolean;
    me_id: number | null;
};

const STATUS: Record<
    NonNullable<Day['status']>,
    { label: string; className: string }
> = {
    hadir: {
        label: 'Hadir',
        className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
    terlambat: {
        label: 'Terlambat',
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    tidak_hadir: {
        label: 'Tidak hadir',
        className: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
    },
    menunggu: {
        label: 'Menunggu absen',
        className: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
    },
    di_luar_jadwal: {
        label: 'Di luar jadwal',
        className: 'bg-violet-500/15 text-violet-700 dark:text-violet-400',
    },
};

const percent = (value: number | null) =>
    value === null ? '-' : `${Math.round(value * 100)}%`;

/**
 * Resume Statistik — realisasi shift harian per operator: kehadiran against the
 * Jadwal Shift, tukar shift, the work saved and, per day, the planned shift,
 * the absen masuk / pulang and what was done. An operator sees only their own.
 */
export default function OperatorResume({
    unit,
    filters,
    period_label,
    options,
    employees,
    selected,
    days,
    sees_all,
}: Props) {
    const [openDay, setOpenDay] = useState<number | null>(null);

    const visit = (patch: Partial<Props['filters']>) =>
        router.get(
            resume.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const pekerjaan = Object.entries(options.pekerjaan);
    const groups = (['shift', 'non_shift'] as const)
        .map((group) => ({
            group,
            rows: employees.filter((e) => e.group === group),
        }))
        .filter((g) => g.rows.length > 0);

    return (
        <>
            <Head title="Resume Statistik" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Resume Statistik"
                    description={`Realisasi shift harian & pekerjaan ${sees_all ? 'setiap operator' : 'Anda'} — ${unit.name}, ${period_label}. Kehadiran dihitung dari absen masuk terhadap Jadwal Shift; pekerjaan dari data yang disimpan dengan akun masing-masing.`}
                />

                <Card className="grid grid-cols-2 gap-3 p-3 py-3 sm:flex sm:flex-row sm:flex-wrap sm:items-end">
                    {sees_all && options.units.length > 1 && (
                        <div className="col-span-2 sm:col-span-1">
                            <OperasiSelect
                                label="Unit"
                                value={String(filters.unit_id)}
                                onChange={(v) =>
                                    visit({
                                        unit_id: Number(v),
                                        employee_id: null,
                                    })
                                }
                                options={options.units.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                }))}
                                className="w-full sm:w-56"
                            />
                        </div>
                    )}
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                        className="w-full sm:w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                        className="w-full sm:w-28"
                    />
                </Card>

                {employees.length === 0 && (
                    <p className="rounded-md border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                        Belum ada pegawai di Jadwal Shift unit ini.
                    </p>
                )}

                {groups.map(({ group, rows }) => (
                    <section key={group} className="flex flex-col gap-2">
                        {sees_all && (
                            <h2 className="text-sm font-semibold text-foreground">
                                {group === 'shift'
                                    ? 'Kerja Shift'
                                    : 'Non Shift'}
                            </h2>
                        )}
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {rows.map((employee) => (
                                <button
                                    key={employee.id}
                                    type="button"
                                    onClick={() =>
                                        sees_all &&
                                        visit({ employee_id: employee.id })
                                    }
                                    className={cn(
                                        'w-full min-w-0 rounded-xl border bg-card p-3 text-left transition',
                                        selected?.id === employee.id
                                            ? 'border-primary ring-1 ring-primary'
                                            : 'border-border',
                                        sees_all && 'hover:border-primary/60',
                                    )}
                                >
                                    <EmployeeCard
                                        employee={employee}
                                        pekerjaan={pekerjaan}
                                        absen={options.absen}
                                    />
                                </button>
                            ))}
                        </div>
                    </section>
                ))}

                {selected && (
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <CalendarDays className="size-4 text-primary" />
                            Realisasi shift harian — {selected.name}
                        </h2>
                        {!selected.has_account && (
                            <p className="text-[12px] text-amber-600">
                                Pegawai ini belum terhubung ke akun, jadi absen
                                & pekerjaannya belum tercatat.
                            </p>
                        )}
                        <Card className="gap-0 divide-y divide-border p-0 py-0">
                            {days.map((day) => {
                                const open = openDay === day.day;
                                const status = day.status
                                    ? STATUS[day.status]
                                    : null;
                                const done = day.aktivitas.length;

                                return (
                                    <div key={day.date}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpenDay(
                                                    open ? null : day.day,
                                                )
                                            }
                                            disabled={done === 0 && !day.note}
                                            className="flex w-full items-center gap-3 px-3 py-2 text-left disabled:cursor-default"
                                        >
                                            <span className="w-14 shrink-0 text-[12px] text-muted-foreground">
                                                <span className="block text-[14px] font-semibold text-foreground">
                                                    {day.day}
                                                </span>
                                                {day.name.slice(0, 3)}
                                            </span>
                                            <span
                                                className={cn(
                                                    'flex h-7 w-11 shrink-0 items-center justify-center rounded-md text-[12px] font-bold',
                                                    day.working
                                                        ? 'bg-primary/10 text-primary'
                                                        : 'bg-muted text-muted-foreground',
                                                )}
                                                title={
                                                    day.code_label ??
                                                    'Belum dijadwalkan'
                                                }
                                            >
                                                {day.code ?? '–'}
                                            </span>
                                            <span className="min-w-0 flex-1 text-[12px]">
                                                <span className="flex flex-wrap items-center gap-1.5">
                                                    {status && (
                                                        <span
                                                            className={cn(
                                                                'rounded px-1.5 py-0.5 text-[11px] font-semibold',
                                                                status.className,
                                                            )}
                                                        >
                                                            {status.label}
                                                        </span>
                                                    )}
                                                    {day.check_in && (
                                                        <span className="text-muted-foreground">
                                                            Masuk {day.check_in}
                                                            {day.check_out
                                                                ? ` · Pulang ${day.check_out}`
                                                                : ''}
                                                            {day.late_minutes
                                                                ? ` · telat ${day.late_minutes} mnt`
                                                                : ''}
                                                        </span>
                                                    )}
                                                </span>
                                                {day.note && (
                                                    <span className="mt-0.5 block truncate text-violet-600 dark:text-violet-400">
                                                        {day.note}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="flex shrink-0 items-center gap-1 text-[12px] text-muted-foreground">
                                                {day.logsheet > 0 && (
                                                    <span className="rounded bg-sky-500/10 px-1.5 py-0.5 text-sky-700 dark:text-sky-400">
                                                        {day.logsheet} logsheet
                                                    </span>
                                                )}
                                                {done > 0 && (
                                                    <>
                                                        <ClipboardList className="size-3.5" />
                                                        {done}
                                                        <ChevronDown
                                                            className={cn(
                                                                'size-3.5 transition',
                                                                open &&
                                                                    'rotate-180',
                                                            )}
                                                        />
                                                    </>
                                                )}
                                            </span>
                                        </button>
                                        {open && done > 0 && (
                                            <ul className="space-y-1 bg-muted/30 px-3 py-2 pl-[5.5rem] text-[12px]">
                                                {day.aktivitas.map(
                                                    (activity, index) => (
                                                        <li
                                                            key={index}
                                                            className="flex gap-2"
                                                        >
                                                            <span className="shrink-0 font-mono text-muted-foreground">
                                                                {activity.time}
                                                            </span>
                                                            <span className="text-foreground">
                                                                {
                                                                    activity.description
                                                                }
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}
                        </Card>
                    </section>
                )}
            </div>
        </>
    );
}

function EmployeeCard({
    employee: e,
    pekerjaan,
    absen,
}: {
    employee: EmployeeResume;
    pekerjaan: [string, string][];
    absen: Record<string, string>;
}) {
    const k = e.kehadiran;
    const pct = k.percent === null ? null : Math.round(k.percent * 100);
    const absences = Object.entries(absen).filter(
        ([code]) => (k.absen[code] ?? 0) > 0,
    );

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <div className="truncate text-[14px] font-semibold text-foreground">
                            {e.name}
                        </div>
                        <div className="truncate text-[12px] text-muted-foreground">
                            {e.regu
                                ? `Regu ${e.regu}${e.is_shift_leader ? ' · Leader Shift' : ''}`
                                : (e.position ?? '-')}
                        </div>
                    </div>
                </div>
                <div className="text-right">
                    <div
                        className={cn(
                            'text-lg font-bold',
                            pct === null
                                ? 'text-muted-foreground'
                                : pct >= 90
                                  ? 'text-emerald-600'
                                  : pct >= 75
                                    ? 'text-amber-600'
                                    : 'text-rose-600',
                        )}
                    >
                        {percent(k.percent)}
                    </div>
                    <div className="text-[11px] text-muted-foreground">
                        hadir
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-4 gap-1.5 text-center text-[11px]">
                {[
                    ['Jadwal', k.jadwal_kerja],
                    ['Hadir', k.hadir],
                    ['Terlambat', k.terlambat],
                    ['Tdk hadir', k.tidak_hadir],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-md bg-muted/50 px-1 py-1"
                    >
                        <div className="text-[14px] font-semibold text-foreground">
                            {value}
                        </div>
                        <div className="text-muted-foreground">{label}</div>
                    </div>
                ))}
            </div>

            <div className="flex flex-wrap gap-1 text-[11px] text-muted-foreground">
                <span className="rounded bg-muted px-1.5 py-0.5">
                    P {k.shift.P} · S {k.shift.S} · M {k.shift.M} · OFF{' '}
                    {k.shift.OFF}
                </span>
                {absences.map(([code, label]) => (
                    <span
                        key={code}
                        className="rounded bg-amber-500/10 px-1.5 py-0.5 text-amber-700 dark:text-amber-400"
                    >
                        {label} {k.absen[code]}
                    </span>
                ))}
                {(e.tukar_shift.menggantikan > 0 ||
                    e.tukar_shift.digantikan > 0) && (
                    <span className="inline-flex items-center gap-1 rounded bg-violet-500/10 px-1.5 py-0.5 text-violet-700 dark:text-violet-400">
                        <ArrowRightLeft className="size-3" />
                        Menggantikan {e.tukar_shift.menggantikan} · Digantikan{' '}
                        {e.tukar_shift.digantikan}
                    </span>
                )}
            </div>

            <div className="border-t border-border pt-2">
                <div className="mb-1 flex items-center gap-1 text-[11px] font-semibold text-muted-foreground uppercase">
                    <BarChart3 className="size-3.5" />
                    Pekerjaan bulan ini
                </div>
                <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[12px]">
                    {pekerjaan.map(([key, label]) => (
                        <div
                            key={key}
                            className={cn(
                                'flex justify-between gap-2',
                                key === 'aktivitas' &&
                                    'col-span-2 border-t border-dashed border-border pt-0.5 font-semibold',
                            )}
                        >
                            <span className="truncate text-muted-foreground">
                                {label}
                            </span>
                            <span className="text-foreground tabular-nums">
                                {e.pekerjaan[key] ?? 0}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

OperatorResume.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Resume Statistik', href: resume.index() },
    ],
};
