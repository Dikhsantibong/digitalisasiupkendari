import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Gauge, Loader2, Save } from 'lucide-react';
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
import master from '@/routes/operasi/master';
import pemakaianBbm from '@/routes/operasi/pengusahaan/pemakaian-bbm';

type MachineRef = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
};

type Fuel = {
    code: string;
    name: string;
    material_code: string | null;
    machines: MachineRef[];
};

/** "{fuelCode}_{machineId}" → day → liters. */
type Readings = Record<string, Record<string, number | string>>;

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: { unit_id: number; month: number; year: number };
    period_label: string;
    fuels: Fuel[];
    days_in_month: number;
    readings: Readings;
    stand_meter: Readings;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
    can_manage_master: boolean;
};

const keyOf = (code: string, machineId: number) => `${code}_${machineId}`;

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
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

export default function PemakaianBbmIndex({
    unit,
    units,
    filters,
    period_label,
    fuels,
    days_in_month,
    readings: saved,
    stand_meter,
    catatan: savedNote,
    saved_at,
    can_write,
    can_manage_master,
}: Props) {
    const compact = useCompactLayout();
    const [readings, setReadings] = useState<Readings>(saved);
    const [note, setNote] = useState(savedNote);
    const [saving, setSaving] = useState(false);
    /** The jenis BBM shown in the table ('all' = every jenis side by side). */
    const [fuelFilter, setFuelFilter] = useState('all');
    const shownFuels =
        fuelFilter === 'all'
            ? fuels
            : fuels.filter((fuel) => fuel.code === fuelFilter);
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
            note !== savedNote,
        [readings, saved, note, savedNote],
    );
    const standMeterCells = Object.values(stand_meter).reduce(
        (count, row) => count + Object.keys(row).length,
        0,
    );
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);

    const cell = (code: string, machineId: number, day: number) =>
        toNumber(readings[keyOf(code, machineId)]?.[day]);
    const dayTotal = (fuel: Fuel, day: number) =>
        fuel.machines.reduce(
            (total, m) => total + cell(fuel.code, m.id, day),
            0,
        );
    const machineTotal = (fuel: Fuel, machineId: number) =>
        days.reduce((total, day) => total + cell(fuel.code, machineId, day), 0);
    const fuelTotal = (fuel: Fuel) =>
        fuel.machines.reduce((total, m) => total + machineTotal(fuel, m.id), 0);
    const filledDay = (day: number) =>
        fuels.some((fuel) => dayTotal(fuel, day) > 0);

    const setCell = (
        code: string,
        machineId: number,
        day: number,
        value: string,
    ) =>
        setReadings((current) => {
            const key = keyOf(code, machineId);
            const row = { ...(current[key] ?? {}) };

            if (value === '') {
                delete row[day];
            } else {
                row[day] = value;
            }

            return { ...current, [key]: row };
        });

    /** Fill the empty cells with the daily PEMAKAIAN of Stand Flow Meter (filled cells are kept). */
    const fillFromStandMeter = () =>
        setReadings((current) => {
            const next = { ...current };

            for (const [key, row] of Object.entries(stand_meter)) {
                const merged = { ...(next[key] ?? {}) };

                for (const [day, value] of Object.entries(row)) {
                    if (toNumber(merged[day]) === 0) {
                        merged[day] = value;
                    }
                }

                next[key] = merged;
            }

            return next;
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
            pemakaianBbm.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () => {
        const payload: Record<string, Record<string, number>> = {};

        for (const [key, row] of Object.entries(readings)) {
            for (const [day, value] of Object.entries(row)) {
                const amount = toNumber(value);

                if (amount > 0) {
                    payload[key] = { ...(payload[key] ?? {}), [day]: amount };
                }
            }
        }

        router.post(
            pemakaianBbm.store().url,
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
    const grandTotal = fuels.reduce(
        (total, fuel) => total + fuelTotal(fuel),
        0,
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
            <Head title="Pemakaian Bahan Bakar" />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title="Pemakaian Bahan Bakar"
                    description={`Pemakaian BBM harian (liter) per mesin — ${unit.name}, ${period_label}. Jenis BBM mengikuti Data Master unit.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={pemakaianBbm.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        pemakaianBbm.pdf({
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
                    {fuels.length > 1 && (
                        <OperasiSelect
                            label="Bahan Bakar"
                            className="w-56"
                            value={fuelFilter}
                            onChange={setFuelFilter}
                            options={[
                                { value: 'all', label: 'Semua jenis BBM' },
                                ...fuels.map((fuel) => ({
                                    value: fuel.code,
                                    label: `${fuel.name} (${fuel.code})`,
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

                {fuels.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada jenis BBM untuk unit ini"
                            description="Jenis BBM diambil dari Tangki BBM aktif unit (Data Master Operasi → Tangki BBM) dan BBM mesin di Master Mesin."
                            action={
                                can_manage_master && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={
                                                master.index('fuel-tanks').url
                                            }
                                        >
                                            Buka Tangki BBM
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
                                label="Total pemakaian"
                                value={format(grandTotal, false)}
                                unit="Liter"
                                hint={period_label}
                            />
                            {fuels.slice(0, 3).map((fuel) => (
                                <SummaryCard
                                    key={fuel.code}
                                    label={`${fuel.name} (${fuel.code})`}
                                    value={format(fuelTotal(fuel), false)}
                                    unit="Liter"
                                    hint={`${fuel.machines.length} mesin`}
                                />
                            ))}
                        </div>

                        {can_write && standMeterCells > 0 && (
                            <div className="flex flex-wrap items-center gap-3 rounded-md border border-border bg-card px-4 py-3 text-[13px]">
                                <Gauge className="size-4 shrink-0 text-primary" />
                                <span className="flex-1 text-muted-foreground">
                                    Stand Flow Meter bulan ini sudah berisi
                                    pemakaian harian per mesin. Isi sel yang
                                    masih kosong dengan angka tersebut, lalu
                                    periksa dan simpan.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={fillFromStandMeter}
                                >
                                    Ambil dari Stand Meter
                                </Button>
                            </div>
                        )}

                        {compact ? (
                            <div className="flex flex-col gap-3">
                                <DayStrip
                                    items={days.map((day) => ({
                                        key: String(day),
                                        label: String(day),
                                        done: filledDay(day),
                                    }))}
                                    value={String(selectedDay)}
                                    onChange={(key) =>
                                        setSelectedDay(Number(key))
                                    }
                                />
                                {shownFuels.map((fuel) => (
                                    <section
                                        key={fuel.code}
                                        className="rounded-xl border border-border bg-card p-3"
                                    >
                                        <div className="mb-2 flex items-baseline justify-between gap-2">
                                            <h2 className="text-sm font-semibold text-foreground">
                                                {fuel.name} ({fuel.code})
                                            </h2>
                                            <span className="text-[12px] text-muted-foreground tabular-nums">
                                                Tgl {selectedDay}:{' '}
                                                <b className="text-foreground">
                                                    {format(
                                                        dayTotal(
                                                            fuel,
                                                            selectedDay,
                                                        ),
                                                        false,
                                                    )}
                                                </b>{' '}
                                                L
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            {fuel.machines.map((machine) => (
                                                <label
                                                    key={machine.id}
                                                    className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                                >
                                                    {machine.name}
                                                    <AmountInput
                                                        value={
                                                            readings[
                                                                keyOf(
                                                                    fuel.code,
                                                                    machine.id,
                                                                )
                                                            ]?.[selectedDay]
                                                        }
                                                        disabled={!can_write}
                                                        onChange={(value) =>
                                                            setCell(
                                                                fuel.code,
                                                                machine.id,
                                                                selectedDay,
                                                                value,
                                                            )
                                                        }
                                                        className="h-10 rounded-md border border-border bg-background text-sm"
                                                        label={`${fuel.code} ${machine.name} tanggal ${selectedDay}`}
                                                    />
                                                </label>
                                            ))}
                                        </div>
                                    </section>
                                ))}
                            </div>
                        ) : (
                            <FuelTable
                                fuels={shownFuels}
                                days={days}
                                readings={readings}
                                canWrite={can_write}
                                dayTotal={dayTotal}
                                machineTotal={machineTotal}
                                fuelTotal={fuelTotal}
                                setCell={setCell}
                            />
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

const HEADER_ROWS = [
    ['UNIT', (m: MachineRef) => m.name],
    ['TYPE', (m: MachineRef) => m.type ?? '-'],
    ['NO. SERI', (m: MachineRef) => m.serial_number ?? '-'],
] as const;

/**
 * One wide table: per day, a column per machine of every shown jenis BBM, a
 * JUMLAH per jenis and — with more than one jenis — the TOTAL of the day.
 */
function FuelTable({
    fuels,
    days,
    readings,
    canWrite,
    dayTotal,
    machineTotal,
    fuelTotal,
    setCell,
}: {
    fuels: Fuel[];
    days: number[];
    readings: Readings;
    canWrite: boolean;
    dayTotal: (fuel: Fuel, day: number) => number;
    machineTotal: (fuel: Fuel, machineId: number) => number;
    fuelTotal: (fuel: Fuel) => number;
    setCell: (
        code: string,
        machineId: number,
        day: number,
        value: string,
    ) => void;
}) {
    const many = fuels.length > 1;
    const groupStart = 'border-l-2 border-l-primary/30';
    const firstOfGroup = (fuelIndex: number, machineIndex: number) =>
        fuelIndex > 0 && machineIndex === 0 && groupStart;

    return (
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
                            {fuels.map((fuel, index) => (
                                <th
                                    key={fuel.code}
                                    colSpan={fuel.machines.length + 1}
                                    className={cn(
                                        'border-r border-b border-border px-2 py-2 text-center text-[13px] font-semibold',
                                        index > 0 && groupStart,
                                    )}
                                >
                                    {fuel.name.toUpperCase()}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        ({fuel.code})
                                    </span>
                                </th>
                            ))}
                            {many && (
                                <th
                                    rowSpan={4}
                                    className={cn(
                                        'min-w-28 border-b border-border bg-secondary px-2 py-2 text-center text-[12px] font-semibold',
                                        groupStart,
                                    )}
                                >
                                    TOTAL
                                </th>
                            )}
                        </tr>
                        {HEADER_ROWS.map(([label, value]) => (
                            <tr
                                key={label}
                                className="text-[12px] text-muted-foreground"
                            >
                                <th className="sticky left-0 z-20 border-r border-b border-border bg-card px-2 py-1 text-left font-semibold">
                                    {label}
                                </th>
                                {fuels.map((fuel, index) => [
                                    ...fuel.machines.map(
                                        (machine, machineIndex) => (
                                            <th
                                                key={`${fuel.code}-${machine.id}`}
                                                className={cn(
                                                    'min-w-24 border-r border-b border-border px-2 py-1 text-center whitespace-nowrap',
                                                    label === 'UNIT'
                                                        ? 'font-semibold text-foreground'
                                                        : 'font-normal',
                                                    firstOfGroup(
                                                        index,
                                                        machineIndex,
                                                    ),
                                                )}
                                            >
                                                {value(machine)}
                                            </th>
                                        ),
                                    ),
                                    label === 'UNIT' ? (
                                        <th
                                            key={`${fuel.code}-jumlah`}
                                            rowSpan={3}
                                            className="min-w-28 border-r border-b border-border bg-secondary px-2 py-1 text-center text-[12px] font-semibold text-foreground"
                                        >
                                            JUMLAH {fuel.code}
                                        </th>
                                    ) : null,
                                ])}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {days.map((day) => (
                            <tr key={day} className="hover:bg-muted/30">
                                <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 text-center text-[12px] font-medium text-muted-foreground tabular-nums">
                                    {day}
                                </td>
                                {fuels.map((fuel, index) => [
                                    ...fuel.machines.map(
                                        (machine, machineIndex) => (
                                            <td
                                                key={`${fuel.code}-${machine.id}`}
                                                className={cn(
                                                    'border-r border-b border-border p-0',
                                                    firstOfGroup(
                                                        index,
                                                        machineIndex,
                                                    ),
                                                )}
                                            >
                                                <AmountInput
                                                    value={
                                                        readings[
                                                            keyOf(
                                                                fuel.code,
                                                                machine.id,
                                                            )
                                                        ]?.[day]
                                                    }
                                                    disabled={!canWrite}
                                                    onChange={(value) =>
                                                        setCell(
                                                            fuel.code,
                                                            machine.id,
                                                            day,
                                                            value,
                                                        )
                                                    }
                                                    className="h-7 border-0 bg-transparent focus:bg-primary/5"
                                                    label={`${fuel.code} ${machine.name} tanggal ${day}`}
                                                />
                                            </td>
                                        ),
                                    ),
                                    <td
                                        key={`${fuel.code}-jumlah`}
                                        className="border-r border-b border-border bg-secondary/60 px-2 text-right font-medium text-foreground tabular-nums"
                                    >
                                        {format(dayTotal(fuel, day))}
                                    </td>,
                                ])}
                                {many && (
                                    <td
                                        className={cn(
                                            'border-b border-border bg-secondary px-2 text-right font-semibold text-foreground tabular-nums',
                                            groupStart,
                                        )}
                                    >
                                        {format(
                                            fuels.reduce(
                                                (total, fuel) =>
                                                    total + dayTotal(fuel, day),
                                                0,
                                            ),
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                        <tr className="bg-secondary font-semibold text-foreground">
                            <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                JMH
                            </td>
                            {fuels.map((fuel, index) => [
                                ...fuel.machines.map(
                                    (machine, machineIndex) => (
                                        <td
                                            key={`${fuel.code}-${machine.id}`}
                                            className={cn(
                                                'border-r border-border px-2 py-1.5 text-right tabular-nums',
                                                firstOfGroup(
                                                    index,
                                                    machineIndex,
                                                ),
                                            )}
                                        >
                                            {format(
                                                machineTotal(fuel, machine.id),
                                                false,
                                            )}
                                        </td>
                                    ),
                                ),
                                <td
                                    key={`${fuel.code}-jumlah`}
                                    className="border-r border-border px-2 py-1.5 text-right text-primary tabular-nums"
                                >
                                    {format(fuelTotal(fuel), false)}
                                </td>,
                            ])}
                            {many && (
                                <td
                                    className={cn(
                                        'px-2 py-1.5 text-right text-primary tabular-nums',
                                        groupStart,
                                    )}
                                >
                                    {format(
                                        fuels.reduce(
                                            (total, fuel) =>
                                                total + fuelTotal(fuel),
                                            0,
                                        ),
                                        false,
                                    )}
                                </td>
                            )}
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
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
