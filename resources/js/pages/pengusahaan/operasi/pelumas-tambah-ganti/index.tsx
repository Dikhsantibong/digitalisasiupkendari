import { Head, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import tambahGanti from '@/routes/operasi/pengusahaan/pelumas-tambah-ganti';
import pemakaianPelumas from '@/routes/operasi/pengusahaan/pemakaian-pelumas';

type Machine = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
};

type Filters = { unit_id: number; month: number; year: number };

/** "{lubricantId}_{machineId}" → day → amount. */
type Readings = Record<string, Record<string, number>>;

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    days_in_month: number;
    groups: { merk: string; machines: Machine[] }[];
    lubricants: {
        id: number;
        name: string;
        code: string | null;
        unit_label: string;
    }[];
    tambah: Readings;
    ganti: Readings;
};

type Jenis = 'tambah' | 'ganti';

const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const format = (value: number) => NUMBER.format(value);
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

/**
 * Pelumas Tambah & Ganti: per mesin per tanggal the pelumas tambah (top-up)
 * or the pelumas ganti (oil change), read-only from Pemakaian Pelumas where
 * the two are filled apart.
 */
export default function PelumasTambahGantiIndex({
    unit,
    units,
    filters,
    period_label,
    days_in_month,
    groups,
    lubricants,
    tambah,
    ganti,
}: Props) {
    const [jenis, setJenis] = useState<Jenis>('tambah');
    const [lubricant, setLubricant] = useState('all');
    const visit = (patch: Partial<Filters>) =>
        router.get(
            tambahGanti.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const machines = groups.flatMap((group) => group.machines);
    const shown =
        lubricant === 'all'
            ? lubricants
            : lubricants.filter((l) => String(l.id) === lubricant);
    const source = jenis === 'tambah' ? tambah : ganti;
    const value = (machineId: number, day: number, from: Readings = source) =>
        shown.reduce(
            (total, l) => total + (from[`${l.id}_${machineId}`]?.[day] ?? 0),
            0,
        );
    const machineTotal = (machineId: number, from: Readings = source) =>
        days.reduce((total, day) => total + value(machineId, day, from), 0);
    const dayTotal = (day: number) =>
        machines.reduce((total, m) => total + value(m.id, day), 0);
    const grandOf = (from: Readings) =>
        machines.reduce((total, m) => total + machineTotal(m.id, from), 0);
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
        jenis,
        ...(lubricant === 'all' ? {} : { lubricant: Number(lubricant) }),
    };

    return (
        <>
            <Head title="Pelumas Tambah & Ganti" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Pelumas Tambah & Ganti"
                    description={`Rincian pemakaian pelumas tambah dan pelumas ganti per mesin per hari — ${unit.name}, ${period_label}. Diisi di menu Pemakaian Pelumas.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={tambahGanti.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        tambahGanti.pdf({
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
                        onChange={(v) => visit({ unit_id: Number(v) })}
                        options={units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={YEARS.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <OperasiSelect
                        label="Pemakaian"
                        className="w-40"
                        value={jenis}
                        onChange={(v) => setJenis(v as Jenis)}
                        options={[
                            { value: 'tambah', label: 'Pelumas Tambah' },
                            { value: 'ganti', label: 'Pelumas Ganti' },
                        ]}
                    />
                    {lubricants.length > 1 && (
                        <OperasiSelect
                            label="Jenis pelumas"
                            className="w-52"
                            value={lubricant}
                            onChange={setLubricant}
                            options={[
                                { value: 'all', label: 'Semua jenis pelumas' },
                                ...lubricants.map((l) => ({
                                    value: String(l.id),
                                    label: l.name,
                                })),
                            ]}
                        />
                    )}
                    <Button variant="link" className="ml-auto" asChild>
                        <a
                            href={
                                pemakaianPelumas.index({
                                    query: {
                                        unit_id: filters.unit_id,
                                        month: filters.month,
                                        year: filters.year,
                                    },
                                }).url
                            }
                        >
                            Ubah di Pemakaian Pelumas
                        </a>
                    </Button>
                </div>

                {machines.length === 0 || lubricants.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin atau jenis pelumas"
                            description="Tambahkan mesin di Master Mesin dan jenis pelumas di Data Master Operasi."
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <SummaryCard
                                label="Pelumas tambah"
                                value={format(grandOf(tambah))}
                                unit="Liter"
                                hint={period_label}
                            />
                            <SummaryCard
                                label="Pelumas ganti"
                                value={format(grandOf(ganti))}
                                unit="Liter"
                                hint={period_label}
                            />
                            <SummaryCard
                                label="Total pemakaian"
                                value={format(grandOf(tambah) + grandOf(ganti))}
                                unit="Liter"
                                hint="Tambah + ganti"
                            />
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="flex items-center justify-between border-b border-border px-4 py-2 text-sm font-semibold">
                                PEMAKAIAN PELUMAS {jenis.toUpperCase()} (LTR)
                                {shown.length === 1 && (
                                    <span className="font-normal text-muted-foreground">
                                        {shown[0].name}
                                    </span>
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead className="text-[12px]">
                                        <tr className="bg-secondary">
                                            <th className="sticky left-0 z-20 w-24 border-r border-b border-border bg-secondary px-2 py-1.5 text-left font-semibold">
                                                MERK
                                            </th>
                                            {groups.map((group) => (
                                                <th
                                                    key={group.merk}
                                                    colSpan={
                                                        group.machines.length
                                                    }
                                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                                >
                                                    {group.merk}
                                                </th>
                                            ))}
                                            <th
                                                rowSpan={4}
                                                className="min-w-24 border-b border-border bg-secondary px-2 py-1.5 font-semibold"
                                            >
                                                TOTAL
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
                                        ).map(([label, text]) => (
                                            <tr
                                                key={label}
                                                className={
                                                    label === 'UNIT'
                                                        ? 'text-foreground'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                <th className="sticky left-0 z-20 border-r border-b border-border bg-card px-2 py-1 text-left font-semibold">
                                                    {label}
                                                </th>
                                                {machines.map((machine) => (
                                                    <th
                                                        key={machine.id}
                                                        className="min-w-24 border-r border-b border-border px-2 py-1 font-normal whitespace-nowrap"
                                                    >
                                                        {text(machine)}
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
                                                {machines.map((machine) => (
                                                    <td
                                                        key={machine.id}
                                                        className="border-r border-b border-border px-2 py-1 text-right tabular-nums"
                                                    >
                                                        {format(
                                                            value(
                                                                machine.id,
                                                                day,
                                                            ),
                                                        )}
                                                    </td>
                                                ))}
                                                <td className="border-b border-border bg-secondary/60 px-2 py-1 text-right font-medium tabular-nums">
                                                    {format(dayTotal(day))}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="bg-secondary font-semibold">
                                            <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                JMH
                                            </td>
                                            {machines.map((machine) => (
                                                <td
                                                    key={machine.id}
                                                    className="border-r border-border px-2 py-1.5 text-right tabular-nums"
                                                >
                                                    {format(
                                                        machineTotal(
                                                            machine.id,
                                                        ),
                                                    )}
                                                </td>
                                            ))}
                                            <td className="px-2 py-1.5 text-right text-primary tabular-nums">
                                                {format(grandOf(source))}
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
