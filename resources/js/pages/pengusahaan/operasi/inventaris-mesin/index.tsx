import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import machines from '@/routes/admin/machines';
import inventaris from '@/routes/operasi/pengusahaan/inventaris-mesin';

type Machine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
    engine_hp: string | null;
    engine_rpm: number | null;
    tahun_pembuatan: number | null;
    generator_merk: string | null;
    generator_type: string | null;
    generator_serial_number: string | null;
    generator_volt: number | null;
    generator_kva: number | null;
    generator_cos_phi: number | null;
    terpasang: number;
};

type Row = {
    no: number;
    machine: Machine;
    auto: { mampu: number; beban: number };
    mampu: number;
    beban: number;
    ket: string;
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    rows: Row[];
    totals: { terpasang: number; mampu: number; beban: number };
    saved_at: string | null;
    can_write: boolean;
    can_manage_machines: boolean;
};

type Values = Record<string, { mampu: string; beban: string; ket: string }>;

const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const toNumber = (value: string) => {
    const parsed = Number(value.replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};
const initial = (rows: Row[]): Values =>
    Object.fromEntries(
        rows.map((row) => [
            row.machine.id,
            {
                mampu: String(row.mampu),
                beban: String(row.beban),
                ket: row.ket,
            },
        ]),
    );
const text = (value: string | number | null) =>
    value === null || value === '' ? '—' : String(value);

/**
 * Daftar Inventarisasi Mesin — Master Mesin data (penggerak & generator) with
 * the month's daya mampu and beban tertinggi from Beban Tertinggi; mampu,
 * beban and the keterangan can be corrected.
 */
export default function InventarisMesinIndex({
    unit,
    units,
    filters,
    period_label,
    rows,
    saved_at,
    can_write,
    can_manage_machines,
}: Props) {
    const [values, setValues] = useState(() => initial(rows));
    const [saving, setSaving] = useState(false);
    const dirty = JSON.stringify(values) !== JSON.stringify(initial(rows));
    const set = (id: number, field: 'mampu' | 'beban' | 'ket', value: string) =>
        setValues((current) => ({
            ...current,
            [id]: { ...current[id], [field]: value },
        }));
    const sum = (field: 'mampu' | 'beban') =>
        rows.reduce(
            (total, row) => total + toNumber(values[row.machine.id][field]),
            0,
        );
    const missing = rows.filter(
        (row) =>
            !row.machine.generator_volt ||
            !row.machine.generator_kva ||
            !row.machine.generator_cos_phi,
    ).length;

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
            inventaris.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };
    const save = () =>
        router.post(
            inventaris.store().url,
            { ...filters, values },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };

    const numberInput = (row: Row, field: 'mampu' | 'beban') => {
        const edited =
            toNumber(values[row.machine.id][field]) !== row.auto[field];

        return (
            <td
                className={cn('px-2 py-1 text-right', edited && 'bg-primary/5')}
                title={`Otomatis (Beban Tertinggi): ${NUMBER.format(row.auto[field])}`}
            >
                <input
                    inputMode="decimal"
                    aria-label={`${field === 'mampu' ? 'Daya mampu' : 'Beban tertinggi'} ${row.machine.name}`}
                    value={values[row.machine.id][field]}
                    readOnly={!can_write}
                    onChange={(e) =>
                        /^\d*([.,]\d{0,2})?$/.test(e.target.value) &&
                        set(row.machine.id, field, e.target.value)
                    }
                    className="h-8 w-20 bg-transparent px-1 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
            </td>
        );
    };

    return (
        <>
            <Head title="Daftar Inventarisasi Mesin" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Daftar Inventarisasi Mesin"
                    description={`${unit.name}, ${period_label}. Data penggerak & generator dari Master Mesin; daya mampu dan beban tertinggi dari menu Beban Tertinggi.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={inventaris.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        inventaris.pdf({
                                            query: { ...query, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh
                                </a>
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={save}
                                    disabled={saving || !dirty}
                                >
                                    {saving ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Simpan
                                </Button>
                            )}
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
                            'Nilai otomatis'
                        )}
                    </p>
                </div>

                {missing > 0 && (
                    <p className="flex flex-wrap items-center gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
                        <span className="flex-1">
                            {missing} mesin belum lengkap data generatornya
                            (Volt, kVA, Cos φ) di Master Mesin.
                        </span>
                        {can_manage_machines && (
                            <Button size="sm" variant="outline" asChild>
                                <Link href={machines.index().url}>
                                    Buka Master Mesin
                                </Link>
                            </Button>
                        )}
                    </p>
                )}

                {rows.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-3 gap-3">
                            <SummaryCard
                                label="Daya terpasang"
                                value={NUMBER.format(
                                    rows.reduce(
                                        (total, row) =>
                                            total + row.machine.terpasang,
                                        0,
                                    ),
                                )}
                                unit="kW"
                            />
                            <SummaryCard
                                label="Daya mampu"
                                value={NUMBER.format(sum('mampu'))}
                                unit="kW"
                            />
                            <SummaryCard
                                label="Beban tertinggi"
                                value={NUMBER.format(sum('beban'))}
                                unit="kW"
                            />
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="bg-secondary text-[12px] text-foreground">
                                            <th
                                                rowSpan={2}
                                                className="border-r border-b border-border px-2 py-2 font-semibold"
                                            >
                                                No
                                            </th>
                                            <th
                                                rowSpan={2}
                                                className="border-r border-b border-border px-2 py-2 text-left font-semibold"
                                            >
                                                Mesin
                                            </th>
                                            <th
                                                colSpan={6}
                                                className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                            >
                                                Penggerak
                                            </th>
                                            <th
                                                colSpan={6}
                                                className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                            >
                                                Generator
                                            </th>
                                            <th
                                                colSpan={3}
                                                className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                            >
                                                Daya (kW)
                                            </th>
                                            <th
                                                rowSpan={2}
                                                className="border-b border-border px-2 py-2 text-left font-semibold"
                                            >
                                                Keterangan
                                            </th>
                                        </tr>
                                        <tr className="bg-secondary text-[12px] whitespace-nowrap text-muted-foreground">
                                            {[
                                                'Merk',
                                                'Type',
                                                'No. Seri',
                                                'HP',
                                                'RPM',
                                                'Thn',
                                                'Merk',
                                                'Type',
                                                'No. Seri',
                                                'Volt',
                                                'kVA',
                                                'Cos φ',
                                                'Terpasang',
                                                'Mampu',
                                                'Beban Tertinggi',
                                            ].map((label, index) => (
                                                <th
                                                    key={index}
                                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                                >
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const m = row.machine;

                                            return (
                                                <tr
                                                    key={m.id}
                                                    className="border-b border-border whitespace-nowrap"
                                                >
                                                    <td className="px-2 py-1 text-right text-muted-foreground tabular-nums">
                                                        {row.no}
                                                    </td>
                                                    <td className="px-2 py-1 font-medium text-foreground">
                                                        {m.name}
                                                    </td>
                                                    {[
                                                        m.merk,
                                                        m.type,
                                                        m.serial_number,
                                                        m.engine_hp,
                                                        m.engine_rpm,
                                                        m.tahun_pembuatan,
                                                        m.generator_merk,
                                                        m.generator_type,
                                                        m.generator_serial_number,
                                                        m.generator_volt,
                                                        m.generator_kva,
                                                        m.generator_cos_phi,
                                                    ].map((value, index) => (
                                                        <td
                                                            key={index}
                                                            className={cn(
                                                                'px-2 py-1 text-center',
                                                                value ===
                                                                    null ||
                                                                    value === ''
                                                                    ? 'text-muted-foreground'
                                                                    : 'text-foreground',
                                                            )}
                                                        >
                                                            {text(value)}
                                                        </td>
                                                    ))}
                                                    <td className="px-2 py-1 text-right tabular-nums">
                                                        {NUMBER.format(
                                                            m.terpasang,
                                                        )}
                                                    </td>
                                                    {numberInput(row, 'mampu')}
                                                    {numberInput(row, 'beban')}
                                                    <td className="px-2 py-1">
                                                        <input
                                                            aria-label={`Keterangan ${m.name}`}
                                                            value={
                                                                values[m.id].ket
                                                            }
                                                            readOnly={
                                                                !can_write
                                                            }
                                                            maxLength={200}
                                                            placeholder={
                                                                can_write
                                                                    ? 'mis. baut pondasi patah'
                                                                    : ''
                                                            }
                                                            onChange={(e) =>
                                                                set(
                                                                    m.id,
                                                                    'ket',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 w-56 rounded-md border border-transparent bg-transparent px-2 outline-none focus:border-border focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        <tr className="bg-secondary font-semibold">
                                            <td
                                                colSpan={14}
                                                className="px-2 py-2 text-right"
                                            >
                                                Jumlah {unit.name}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {NUMBER.format(
                                                    rows.reduce(
                                                        (total, row) =>
                                                            total +
                                                            row.machine
                                                                .terpasang,
                                                        0,
                                                    ),
                                                )}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {NUMBER.format(sum('mampu'))}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {NUMBER.format(sum('beban'))}
                                            </td>
                                            <td />
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
