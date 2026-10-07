import { Head, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import kwhRekap from '@/routes/operasi/pengusahaan/kwh-rekap';
import standKwh from '@/routes/operasi/pengusahaan/stand-kwh';

type Machine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
};

type Block = {
    key: 'produksi' | 'ps' | 'nett';
    title: string;
    values: Record<string, Record<string, number>>;
    totals_by_machine: Record<string, number>;
    totals_by_day: Record<string, number>;
    grand_total: number;
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    days_in_month: number;
    groups: { merk: string; machines: Machine[] }[];
    blocks: Block[];
};

const KWH = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const groupStart = 'border-l-2 border-l-primary/30';

/** The band colour of each block, as in the field Excel. */
const BAND: Record<Block['key'], string> = {
    produksi: 'bg-sky-500/15',
    ps: 'bg-emerald-500/15',
    nett: 'bg-amber-400/20',
};

/**
 * kWh Rekap: kWh produksi, kWh pemakaian sendiri and kWh netto (produksi −
 * PS) per tanggal and mesin, read-only from Stand kWh Harian.
 */
export default function KwhRekapIndex({
    unit,
    units,
    filters,
    period_label,
    days_in_month,
    groups,
    blocks,
}: Props) {
    const [shown, setShown] = useState('all');
    const visit = (patch: Partial<Filters>) =>
        router.get(
            kwhRekap.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const machines = groups.flatMap((group) => group.machines);
    const shownBlocks =
        shown === 'all' ? blocks : blocks.filter((b) => b.key === shown);
    const span = machines.length + 1;

    return (
        <>
            <Head title="kWh Rekap" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="kWh Rekap"
                    description={`kWh, kWh pemakaian sendiri dan kWh netto per mesin per hari — ${unit.name}, ${period_label}. Diambil dari Stand kWh Harian.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={kwhRekap.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        kwhRekap.pdf({
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
                    <OperasiSelect
                        label="Tampilkan"
                        className="w-48"
                        value={shown}
                        onChange={setShown}
                        options={[
                            { value: 'all', label: 'Semua (kWh, PS, Netto)' },
                            ...blocks.map((block) => ({
                                value: block.key,
                                label: block.title,
                            })),
                        ]}
                    />
                    <Button variant="link" className="ml-auto" asChild>
                        <a href={standKwh.index({ query }).url}>
                            Ubah di Stand kWh Harian
                        </a>
                    </Button>
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
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {blocks.map((block) => (
                                <SummaryCard
                                    key={block.key}
                                    label={block.title}
                                    value={KWH.format(block.grand_total)}
                                    unit="kWh"
                                    hint={period_label}
                                />
                            ))}
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead className="text-[12px]">
                                        <tr className="bg-secondary">
                                            <th className="sticky left-0 z-20 border-r border-b border-border bg-secondary px-2 py-1.5 text-left font-semibold">
                                                MERK
                                            </th>
                                            {shownBlocks.map((block, index) => (
                                                <Fragment key={block.key}>
                                                    {groups.map(
                                                        (group, groupIndex) => (
                                                            <th
                                                                key={group.merk}
                                                                colSpan={
                                                                    group
                                                                        .machines
                                                                        .length
                                                                }
                                                                className={cn(
                                                                    'border-r border-b border-border px-2 py-1.5 font-semibold',
                                                                    index > 0 &&
                                                                        groupIndex ===
                                                                            0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {group.merk}
                                                            </th>
                                                        ),
                                                    )}
                                                    <th
                                                        rowSpan={3}
                                                        className="min-w-24 border-r border-b border-border bg-secondary px-2 py-1.5 font-semibold"
                                                    >
                                                        JUMLAH
                                                    </th>
                                                </Fragment>
                                            ))}
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
                                            ] as const
                                        ).map(([label, value]) => (
                                            <tr
                                                key={label}
                                                className="text-muted-foreground"
                                            >
                                                <th className="sticky left-0 z-20 border-r border-b border-border bg-card px-2 py-1 text-left font-semibold">
                                                    {label}
                                                </th>
                                                {shownBlocks.map(
                                                    (block, index) =>
                                                        machines.map(
                                                            (
                                                                machine,
                                                                machineIndex,
                                                            ) => (
                                                                <th
                                                                    key={`${block.key}-${machine.id}`}
                                                                    className={cn(
                                                                        'min-w-24 border-r border-b border-border px-2 py-1 font-normal whitespace-nowrap',
                                                                        index >
                                                                            0 &&
                                                                            machineIndex ===
                                                                                0 &&
                                                                            groupStart,
                                                                    )}
                                                                >
                                                                    {value(
                                                                        machine,
                                                                    )}
                                                                </th>
                                                            ),
                                                        ),
                                                )}
                                            </tr>
                                        ))}
                                        <tr>
                                            <th className="sticky left-0 z-20 border-r border-b border-border bg-secondary px-2 py-1.5 text-left font-semibold">
                                                TGL
                                            </th>
                                            {shownBlocks.map((block, index) => (
                                                <th
                                                    key={block.key}
                                                    colSpan={span}
                                                    className={cn(
                                                        'border-r border-b border-border px-2 py-1.5 text-[13px] font-semibold text-foreground uppercase',
                                                        BAND[block.key],
                                                        index > 0 && groupStart,
                                                    )}
                                                >
                                                    {block.title}
                                                </th>
                                            ))}
                                        </tr>
                                        <tr className="bg-secondary/60">
                                            <th className="sticky left-0 z-20 border-r border-b border-border bg-secondary px-2 py-1" />
                                            {shownBlocks.map((block, index) => (
                                                <Fragment key={block.key}>
                                                    {machines.map(
                                                        (
                                                            machine,
                                                            machineIndex,
                                                        ) => (
                                                            <th
                                                                key={machine.id}
                                                                className={cn(
                                                                    'border-r border-b border-border px-2 py-1 font-semibold whitespace-nowrap',
                                                                    index > 0 &&
                                                                        machineIndex ===
                                                                            0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {machine.name}
                                                            </th>
                                                        ),
                                                    )}
                                                    <th className="border-r border-b border-border px-2 py-1 font-semibold">
                                                        Unit
                                                    </th>
                                                </Fragment>
                                            ))}
                                        </tr>
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
                                                {shownBlocks.map(
                                                    (block, index) => (
                                                        <Fragment
                                                            key={block.key}
                                                        >
                                                            {machines.map(
                                                                (
                                                                    machine,
                                                                    machineIndex,
                                                                ) => {
                                                                    const value =
                                                                        block
                                                                            .values[
                                                                            machine
                                                                                .id
                                                                        ]?.[
                                                                            day
                                                                        ] ?? 0;

                                                                    return (
                                                                        <td
                                                                            key={
                                                                                machine.id
                                                                            }
                                                                            className={cn(
                                                                                'border-r border-b border-border px-2 py-1 text-right tabular-nums',
                                                                                value <
                                                                                    0 &&
                                                                                    'text-destructive',
                                                                                index >
                                                                                    0 &&
                                                                                    machineIndex ===
                                                                                        0 &&
                                                                                    groupStart,
                                                                            )}
                                                                        >
                                                                            {KWH.format(
                                                                                value,
                                                                            )}
                                                                        </td>
                                                                    );
                                                                },
                                                            )}
                                                            <td className="border-r border-b border-border bg-secondary/60 px-2 py-1 text-right font-medium tabular-nums">
                                                                {KWH.format(
                                                                    block
                                                                        .totals_by_day[
                                                                        day
                                                                    ] ?? 0,
                                                                )}
                                                            </td>
                                                        </Fragment>
                                                    ),
                                                )}
                                            </tr>
                                        ))}
                                        <tr className="bg-secondary font-semibold">
                                            <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                JMH
                                            </td>
                                            {shownBlocks.map((block, index) => (
                                                <Fragment key={block.key}>
                                                    {machines.map(
                                                        (
                                                            machine,
                                                            machineIndex,
                                                        ) => (
                                                            <td
                                                                key={machine.id}
                                                                className={cn(
                                                                    'border-r border-border px-2 py-1.5 text-right tabular-nums',
                                                                    index > 0 &&
                                                                        machineIndex ===
                                                                            0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {KWH.format(
                                                                    block
                                                                        .totals_by_machine[
                                                                        machine
                                                                            .id
                                                                    ] ?? 0,
                                                                )}
                                                            </td>
                                                        ),
                                                    )}
                                                    <td className="border-r border-border px-2 py-1.5 text-right text-primary tabular-nums">
                                                        {KWH.format(
                                                            block.grand_total,
                                                        )}
                                                    </td>
                                                </Fragment>
                                            ))}
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
