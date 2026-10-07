import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save } from 'lucide-react';
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
import pemakaianPelumas from '@/routes/operasi/pengusahaan/pemakaian-pelumas';

type MachineRef = { id: number; name: string };

type Lubricant = {
    id: number;
    name: string;
    code: string | null;
    unit_of_measure: string;
    unit_label: string;
    machines: MachineRef[];
};

type Period = { key: string; label: string; from: number; to: number };

/** "{lubricantId}_{machineId}" → day → amount. */
type Readings = Record<string, Record<string, number | string>>;

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: { unit_id: number; month: number; year: number };
    period_label: string;
    lubricants: Lubricant[];
    days_in_month: number;
    periods: Period[];
    /** The pelumas tambah (top-up) per cell. */
    readings: Readings;
    /** The pelumas ganti (oil change) per cell; the sheet total is tambah + ganti. */
    readings_ganti: Readings;
    totals_by_lubricant: Record<string, number>;
    grand_total_liter: number;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
    can_manage_master: boolean;
};

const keyOf = (lubricantId: number, machineId: number) =>
    `${lubricantId}_${machineId}`;

const toNumber = (value: number | string | undefined): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

const NUMBER = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const format = (value: number, dash = true) =>
    value === 0 && dash ? '-' : NUMBER.format(value);

type PelumasMode = 'tambah' | 'ganti';

const PELUMAS_MODES: { key: PelumasMode; label: string; hint: string }[] = [
    {
        key: 'tambah',
        label: 'Pelumas Tambah',
        hint: 'Penambahan (top-up) pelumas mesin',
    },
    {
        key: 'ganti',
        label: 'Pelumas Ganti',
        hint: 'Penggantian pelumas (ganti oli)',
    },
];

const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

export default function PemakaianPelumasIndex({
    unit,
    units,
    filters,
    period_label,
    lubricants,
    days_in_month,
    periods,
    readings: savedTambah,
    readings_ganti: savedGanti,
    catatan: savedNote,
    saved_at,
    can_write,
    can_manage_master,
}: Props) {
    const compact = useCompactLayout();
    /** Which part is being filled: pelumas tambah or pelumas ganti. */
    const [mode, setMode] = useState<PelumasMode>('tambah');
    const [byMode, setByMode] = useState<Record<PelumasMode, Readings>>({
        tambah: savedTambah,
        ganti: savedGanti,
    });
    const readings = byMode[mode];
    const setReadings = (update: (current: Readings) => Readings) =>
        setByMode((current) => ({
            ...current,
            [mode]: update(current[mode]),
        }));
    const [note, setNote] = useState(savedNote);
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
            JSON.stringify(byMode.tambah) !== JSON.stringify(savedTambah) ||
            JSON.stringify(byMode.ganti) !== JSON.stringify(savedGanti) ||
            note !== savedNote,
        [byMode, savedTambah, savedGanti, note, savedNote],
    );

    const cell = (lubricantId: number, machineId: number, day: number) =>
        toNumber(readings[keyOf(lubricantId, machineId)]?.[day]);
    const sum = (
        lubricant: Lubricant,
        machine: MachineRef | null,
        from: number,
        to: number,
    ) => {
        let total = 0;

        for (const m of machine ? [machine] : lubricant.machines) {
            for (let day = from; day <= to; day++) {
                total += cell(lubricant.id, m.id, day);
            }
        }

        return total;
    };
    const lubricantTotal = (lubricant: Lubricant) =>
        sum(lubricant, null, 1, days_in_month);
    const grandTotal = lubricants.reduce(
        (total, lubricant) => total + lubricantTotal(lubricant),
        0,
    );
    const filledDays = (day: number) =>
        lubricants.some((lubricant) =>
            lubricant.machines.some((m) => cell(lubricant.id, m.id, day) > 0),
        );

    const setCell = (
        lubricantId: number,
        machineId: number,
        day: number,
        value: string,
    ) =>
        setReadings((current) => {
            const key = keyOf(lubricantId, machineId);
            const row = { ...(current[key] ?? {}) };

            if (value === '') {
                delete row[day];
            } else {
                row[day] = value;
            }

            return { ...current, [key]: row };
        });

    const visit = (patch: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            pemakaianPelumas.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () => {
        const payloadOf = (source: Readings) => {
            const payload: Record<string, Record<string, number>> = {};

            for (const [key, row] of Object.entries(source)) {
                for (const [day, value] of Object.entries(row)) {
                    const amount = toNumber(value);

                    if (amount > 0) {
                        payload[key] = {
                            ...(payload[key] ?? {}),
                            [day]: amount,
                        };
                    }
                }
            }

            return payload;
        };

        router.post(
            pemakaianPelumas.store().url,
            {
                ...filters,
                readings: payloadOf(byMode.tambah),
                readings_ganti: payloadOf(byMode.ganti),
                catatan: note,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const pdfQuery = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const columnCount = lubricants.reduce(
        (total, lubricant) => total + lubricant.machines.length + 1,
        1,
    );

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

    return (
        <>
            <Head title="Pemakaian Pelumas" />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title="Pemakaian Pelumas"
                    description={`Pemakaian pelumas harian per jenis pelumas dan mesin — ${unit.name}, ${period_label}. Isi pelumas tambah dan pelumas ganti terpisah (pilih di "Isian"); pemakaian total = tambah + ganti.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        pemakaianPelumas.pdf({
                                            query: pdfQuery,
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        pemakaianPelumas.pdf({
                                            query: { ...pdfQuery, download: 1 },
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
                    <div className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Isian</span>
                        <div className="flex h-9 rounded-md border border-border bg-background p-0.5">
                            {PELUMAS_MODES.map((option) => (
                                <button
                                    key={option.key}
                                    type="button"
                                    onClick={() => setMode(option.key)}
                                    title={option.hint}
                                    className={cn(
                                        'rounded px-3 font-medium transition-colors',
                                        mode === option.key
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>
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

                {lubricants.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada jenis pelumas untuk unit ini"
                            description="Tambahkan jenis pelumas unit di Data Master Operasi → Jenis Pelumas. Kolom mesin per pelumas diatur di Master Mesin (pelumas yang dipakai mesin)."
                            action={
                                can_manage_master && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={
                                                master.index('lubricant-types')
                                                    .url
                                            }
                                        >
                                            Buka Jenis Pelumas
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <SummaryCard
                                label={`Total pelumas ${mode}`}
                                value={format(grandTotal, false)}
                                hint={`${lubricants.length} jenis pelumas`}
                            />
                            {lubricants.slice(0, 3).map((lubricant) => (
                                <SummaryCard
                                    key={lubricant.id}
                                    label={lubricant.name}
                                    value={format(
                                        lubricantTotal(lubricant),
                                        false,
                                    )}
                                    unit={lubricant.unit_label}
                                />
                            ))}
                        </div>

                        {compact ? (
                            <div className="flex flex-col gap-3">
                                <DayStrip
                                    items={Array.from(
                                        { length: days_in_month },
                                        (_, i) => ({
                                            key: String(i + 1),
                                            label: String(i + 1),
                                            done: filledDays(i + 1),
                                        }),
                                    )}
                                    value={String(selectedDay)}
                                    onChange={(key) =>
                                        setSelectedDay(Number(key))
                                    }
                                />
                                {lubricants.map((lubricant) => {
                                    const dayTotal = sum(
                                        lubricant,
                                        null,
                                        selectedDay,
                                        selectedDay,
                                    );

                                    return (
                                        <section
                                            key={lubricant.id}
                                            className="rounded-xl border border-border bg-card p-3"
                                        >
                                            <div className="mb-2 flex items-baseline justify-between gap-2">
                                                <h2 className="text-sm font-semibold text-foreground">
                                                    {lubricant.name}
                                                </h2>
                                                <span className="text-[12px] text-muted-foreground tabular-nums">
                                                    Tgl {selectedDay}:{' '}
                                                    <b className="text-foreground">
                                                        {format(
                                                            dayTotal,
                                                            false,
                                                        )}
                                                    </b>{' '}
                                                    · Bulan ini{' '}
                                                    {format(
                                                        lubricantTotal(
                                                            lubricant,
                                                        ),
                                                        false,
                                                    )}{' '}
                                                    {lubricant.unit_label}
                                                </span>
                                            </div>
                                            {lubricant.machines.length === 0 ? (
                                                <p className="text-[13px] text-muted-foreground">
                                                    Belum ada mesin aktif.
                                                </p>
                                            ) : (
                                                <div className="grid grid-cols-2 gap-2">
                                                    {lubricant.machines.map(
                                                        (machine) => (
                                                            <label
                                                                key={machine.id}
                                                                className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                                            >
                                                                {machine.name}
                                                                <AmountInput
                                                                    value={
                                                                        readings[
                                                                            keyOf(
                                                                                lubricant.id,
                                                                                machine.id,
                                                                            )
                                                                        ]?.[
                                                                            selectedDay
                                                                        ]
                                                                    }
                                                                    disabled={
                                                                        !can_write
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        setCell(
                                                                            lubricant.id,
                                                                            machine.id,
                                                                            selectedDay,
                                                                            value,
                                                                        )
                                                                    }
                                                                    className="h-10 rounded-md border border-border bg-background text-sm"
                                                                    label={`${lubricant.name} ${machine.name} tanggal ${selectedDay}`}
                                                                />
                                                            </label>
                                                        ),
                                                    )}
                                                </div>
                                            )}
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
                                                    rowSpan={2}
                                                    className="sticky left-0 z-20 w-12 border-r border-b border-border bg-secondary px-2 py-2 text-center text-[12px] font-semibold"
                                                >
                                                    TGL
                                                </th>
                                                {lubricants.map((lubricant) => (
                                                    <th
                                                        key={lubricant.id}
                                                        colSpan={
                                                            lubricant.machines
                                                                .length + 1
                                                        }
                                                        className="border-r border-b border-border px-2 py-2 text-center text-[13px] font-semibold whitespace-nowrap"
                                                    >
                                                        {lubricant.name.toUpperCase()}{' '}
                                                        <span className="font-normal text-muted-foreground">
                                                            (
                                                            {
                                                                lubricant.unit_label
                                                            }
                                                            )
                                                        </span>
                                                    </th>
                                                ))}
                                            </tr>
                                            <tr className="text-[12px] text-muted-foreground">
                                                {lubricants.map((lubricant) => (
                                                    <Fragment
                                                        key={lubricant.id}
                                                    >
                                                        {lubricant.machines.map(
                                                            (machine) => (
                                                                <th
                                                                    key={
                                                                        machine.id
                                                                    }
                                                                    className="min-w-20 border-r border-b border-border bg-card px-2 py-1.5 text-center font-semibold whitespace-nowrap"
                                                                >
                                                                    {
                                                                        machine.name
                                                                    }
                                                                </th>
                                                            ),
                                                        )}
                                                        <th className="min-w-22 border-r border-b border-border bg-secondary px-2 py-1.5 text-center font-semibold text-foreground">
                                                            JUMLAH
                                                        </th>
                                                    </Fragment>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {periods.map((period) => (
                                                <Fragment key={period.key}>
                                                    {period.key !== 'p1' && (
                                                        <tr>
                                                            <td
                                                                colSpan={
                                                                    columnCount
                                                                }
                                                                className="border-b border-border bg-muted/50 px-3 py-1 text-[12px] font-semibold text-muted-foreground italic"
                                                            >
                                                                {period.label}
                                                            </td>
                                                        </tr>
                                                    )}
                                                    {Array.from(
                                                        {
                                                            length:
                                                                period.to -
                                                                period.from +
                                                                1,
                                                        },
                                                        (_, i) =>
                                                            period.from + i,
                                                    ).map((day) => (
                                                        <tr
                                                            key={day}
                                                            className="hover:bg-muted/30"
                                                        >
                                                            <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 text-center text-[12px] text-muted-foreground tabular-nums">
                                                                {day}
                                                            </td>
                                                            {lubricants.map(
                                                                (lubricant) => (
                                                                    <Fragment
                                                                        key={
                                                                            lubricant.id
                                                                        }
                                                                    >
                                                                        {lubricant.machines.map(
                                                                            (
                                                                                machine,
                                                                            ) => (
                                                                                <td
                                                                                    key={
                                                                                        machine.id
                                                                                    }
                                                                                    className="border-r border-b border-border p-0"
                                                                                >
                                                                                    <AmountInput
                                                                                        value={
                                                                                            readings[
                                                                                                keyOf(
                                                                                                    lubricant.id,
                                                                                                    machine.id,
                                                                                                )
                                                                                            ]?.[
                                                                                                day
                                                                                            ]
                                                                                        }
                                                                                        disabled={
                                                                                            !can_write
                                                                                        }
                                                                                        onChange={(
                                                                                            value,
                                                                                        ) =>
                                                                                            setCell(
                                                                                                lubricant.id,
                                                                                                machine.id,
                                                                                                day,
                                                                                                value,
                                                                                            )
                                                                                        }
                                                                                        className="h-7 border-0 bg-transparent focus:bg-primary/5"
                                                                                        label={`${lubricant.name} ${machine.name} tanggal ${day}`}
                                                                                    />
                                                                                </td>
                                                                            ),
                                                                        )}
                                                                        <td className="border-r border-b border-border bg-secondary/60 px-2 text-right font-medium text-foreground tabular-nums">
                                                                            {format(
                                                                                sum(
                                                                                    lubricant,
                                                                                    null,
                                                                                    day,
                                                                                    day,
                                                                                ),
                                                                            )}
                                                                        </td>
                                                                    </Fragment>
                                                                ),
                                                            )}
                                                        </tr>
                                                    ))}
                                                    <tr className="bg-muted/60 font-semibold">
                                                        <td className="sticky left-0 z-10 border-r border-b border-border bg-muted px-2 py-1 text-center text-[12px]">
                                                            JML
                                                        </td>
                                                        {lubricants.map(
                                                            (lubricant) => (
                                                                <Fragment
                                                                    key={
                                                                        lubricant.id
                                                                    }
                                                                >
                                                                    {lubricant.machines.map(
                                                                        (
                                                                            machine,
                                                                        ) => (
                                                                            <td
                                                                                key={
                                                                                    machine.id
                                                                                }
                                                                                className="border-r border-b border-border px-2 py-1 text-right tabular-nums"
                                                                            >
                                                                                {format(
                                                                                    sum(
                                                                                        lubricant,
                                                                                        machine,
                                                                                        period.from,
                                                                                        period.to,
                                                                                    ),
                                                                                    false,
                                                                                )}
                                                                            </td>
                                                                        ),
                                                                    )}
                                                                    <td className="border-r border-b border-border bg-secondary px-2 py-1 text-right tabular-nums">
                                                                        {format(
                                                                            sum(
                                                                                lubricant,
                                                                                null,
                                                                                period.from,
                                                                                period.to,
                                                                            ),
                                                                            false,
                                                                        )}
                                                                    </td>
                                                                </Fragment>
                                                            ),
                                                        )}
                                                    </tr>
                                                </Fragment>
                                            ))}
                                            <tr className="bg-secondary font-semibold text-foreground">
                                                <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                    TTL
                                                </td>
                                                {lubricants.map((lubricant) => (
                                                    <Fragment
                                                        key={lubricant.id}
                                                    >
                                                        {lubricant.machines.map(
                                                            (machine) => (
                                                                <td
                                                                    key={
                                                                        machine.id
                                                                    }
                                                                    className="border-r border-border px-2 py-1.5 text-right tabular-nums"
                                                                >
                                                                    {format(
                                                                        sum(
                                                                            lubricant,
                                                                            machine,
                                                                            1,
                                                                            days_in_month,
                                                                        ),
                                                                        false,
                                                                    )}
                                                                </td>
                                                            ),
                                                        )}
                                                        <td className="border-r border-border px-2 py-1.5 text-right text-primary tabular-nums">
                                                            {format(
                                                                lubricantTotal(
                                                                    lubricant,
                                                                ),
                                                                false,
                                                            )}
                                                        </td>
                                                    </Fragment>
                                                ))}
                                            </tr>
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
