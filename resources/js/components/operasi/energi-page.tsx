import { Head, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { Fragment } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import standKwh from '@/routes/operasi/pengusahaan/stand-kwh';
import type { RouteQueryOptions } from '@/wayfinder';

type Machine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
};

type Filters = { unit_id: number; month: number; year: number };

type Url = (options?: RouteQueryOptions) => { url: string };

export type EnergiPageProps = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    title: string;
    days_in_month: number;
    groups: {
        merk: string;
        machines: Machine[];
        totals_by_day: Record<string, number>;
        total: number;
    }[];
    values: Record<string, Record<string, number>>;
    totals_by_machine: Record<string, number>;
    totals_by_day: Record<string, number>;
    grand_total: number;
};

const KWH = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

const format = (value: number) => (value === 0 ? '-' : KWH.format(value));

/**
 * Energi Dibangkit / Energi Pemakaian Sendiri per tanggal, read-only from
 * Stand kWh Harian: machines grouped by merk with a JUMLAH per merk (I, II …)
 * and the unit TOTAL.
 */
export function EnergiPage({
    unit,
    units,
    filters,
    period_label,
    title,
    days_in_month,
    groups,
    values,
    totals_by_machine,
    totals_by_day,
    grand_total,
    description,
    routes,
}: EnergiPageProps & {
    description: string;
    routes: { index: Url; pdf: Url };
}) {
    const visit = (patch: Partial<Filters>) =>
        router.get(
            routes.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const machineCount = groups.reduce(
        (count, group) => count + group.machines.length,
        0,
    );

    return (
        <>
            <Head title={title} />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={`${description} — ${unit.name}, ${period_label}. Dihitung dari Stand kWh Harian.`}
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
                    <Button
                        variant="ghost"
                        size="sm"
                        className="ml-auto self-center"
                        asChild
                    >
                        <a href={standKwh.index({ query }).url}>
                            Ubah di Stand kWh Harian
                        </a>
                    </Button>
                </div>

                {machineCount === 0 ? (
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
                                label={`Total ${unit.name}`}
                                value={KWH.format(grand_total)}
                                unit="kWh"
                                hint={period_label}
                            />
                            {groups.slice(0, 3).map((group, index) => (
                                <SummaryCard
                                    key={group.merk}
                                    label={`Jumlah ${group.merk} (${ROMAN[index]})`}
                                    value={KWH.format(group.total)}
                                    unit="kWh"
                                    hint={`${group.machines.length} mesin`}
                                />
                            ))}
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="bg-secondary text-[12px] text-foreground">
                                            <th className="sticky left-0 z-20 w-24 border-r border-b border-border bg-secondary px-2 py-2 text-left font-semibold">
                                                MERK
                                            </th>
                                            {groups.map((group) => (
                                                <Fragment key={group.merk}>
                                                    <th
                                                        colSpan={
                                                            group.machines
                                                                .length
                                                        }
                                                        className="border-r border-b border-border px-2 py-2 text-center font-semibold tracking-[0.2em]"
                                                    >
                                                        {group.merk}
                                                    </th>
                                                    <th
                                                        rowSpan={4}
                                                        className="min-w-24 border-r border-b border-border bg-secondary px-2 py-2 text-center font-semibold"
                                                    >
                                                        JUMLAH {group.merk}
                                                    </th>
                                                </Fragment>
                                            ))}
                                            <th
                                                rowSpan={4}
                                                className="min-w-28 border-b border-border bg-secondary px-2 py-2 text-center font-semibold"
                                            >
                                                TOTAL KWH
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
                                                        m.serial_number ?? '-',
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
                                                {groups.map((group) =>
                                                    group.machines.map(
                                                        (machine) => (
                                                            <th
                                                                key={machine.id}
                                                                className={cn(
                                                                    'min-w-24 border-r border-b border-border px-2 py-1 text-center whitespace-nowrap',
                                                                    label ===
                                                                        'UNIT'
                                                                        ? 'font-semibold text-foreground'
                                                                        : 'font-normal',
                                                                )}
                                                            >
                                                                {value(machine)}
                                                            </th>
                                                        ),
                                                    ),
                                                )}
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
                                                {groups.map((group) => (
                                                    <Fragment key={group.merk}>
                                                        {group.machines.map(
                                                            (machine) => {
                                                                const value =
                                                                    values[
                                                                        machine
                                                                            .id
                                                                    ]?.[day] ??
                                                                    0;

                                                                return (
                                                                    <td
                                                                        key={
                                                                            machine.id
                                                                        }
                                                                        className={cn(
                                                                            'border-r border-b border-border px-2 py-1 text-right tabular-nums',
                                                                            value <
                                                                                0 &&
                                                                                'font-semibold text-red-700',
                                                                        )}
                                                                    >
                                                                        {format(
                                                                            value,
                                                                        )}
                                                                    </td>
                                                                );
                                                            },
                                                        )}
                                                        <td className="border-r border-b border-border bg-secondary/60 px-2 text-right font-medium tabular-nums">
                                                            {format(
                                                                group
                                                                    .totals_by_day[
                                                                    day
                                                                ] ?? 0,
                                                            )}
                                                        </td>
                                                    </Fragment>
                                                ))}
                                                <td className="border-b border-border bg-secondary/60 px-2 text-right font-semibold tabular-nums">
                                                    {format(
                                                        totals_by_day[day] ?? 0,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="bg-secondary font-semibold">
                                            <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                JMH
                                            </td>
                                            {groups.map((group) => (
                                                <Fragment key={group.merk}>
                                                    {group.machines.map(
                                                        (machine) => (
                                                            <td
                                                                key={machine.id}
                                                                className="border-r border-border px-2 py-1.5 text-right tabular-nums"
                                                            >
                                                                {format(
                                                                    totals_by_machine[
                                                                        machine
                                                                            .id
                                                                    ] ?? 0,
                                                                )}
                                                            </td>
                                                        ),
                                                    )}
                                                    <td className="border-r border-border px-2 py-1.5 text-right tabular-nums">
                                                        {format(group.total)}
                                                    </td>
                                                </Fragment>
                                            ))}
                                            <td className="px-2 py-1.5 text-right text-primary tabular-nums">
                                                {format(grand_total)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}
