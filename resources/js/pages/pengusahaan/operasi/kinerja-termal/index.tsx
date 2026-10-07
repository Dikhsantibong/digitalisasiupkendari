import { Head, router } from '@inertiajs/react';
import { Download, Eye, Loader2, RotateCcw, Save } from 'lucide-react';
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
import kinerjaTermal from '@/routes/operasi/pengusahaan/kinerja-termal';

type Field =
    | 'cf'
    | 'of'
    | 'sf'
    | 'eaf'
    | 'pof'
    | 'fof'
    | 'for'
    | 'sof'
    | 'efor'
    | 'sdof'
    | 'eff';

type Row = {
    machine: {
        id: number;
        name: string;
        merk: string | null;
        type: string | null;
        serial_number: string | null;
    };
    operating: boolean;
    kondisi: string;
    source: {
        kwh: number;
        bbm_liter: number;
        jam_operasi: number;
        jam_har: number;
        jam_gangguan: number;
        daya_terpasang: number;
    };
    auto: Record<Field, number>;
    values: Record<Field, number>;
    edited: Field[];
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    rows: Row[];
    averages: Record<Field, number>;
    jam_periode: number;
    constants: {
        kcal_per_kwh: number;
        kcal_per_liter: number;
        density: number;
    };
    saved_at: string | null;
    can_write: boolean;
};

const FIELDS: { key: Field; label: string; hint: string }[] = [
    {
        key: 'cf',
        label: 'CF',
        hint: 'Capacity Factor = kWh / (daya terpasang × jam periode)',
    },
    {
        key: 'of',
        label: 'OF',
        hint: 'Operating Factor = kWh / (daya terpasang × jam operasi)',
    },
    {
        key: 'sf',
        label: 'SF',
        hint: 'Service Factor = jam operasi / jam periode',
    },
    {
        key: 'eaf',
        label: 'EAF',
        hint: 'Equivalent Availability Factor = 100 − FOF − POF',
    },
    {
        key: 'pof',
        label: 'POF',
        hint: 'Planned Outage Factor = jam pemeliharaan / jam periode',
    },
    {
        key: 'fof',
        label: 'FOF',
        hint: 'Forced Outage Factor = jam gangguan / jam periode',
    },
    {
        key: 'for',
        label: 'FOR',
        hint: 'Forced Outage Rate = jam gangguan / (jam gangguan + jam operasi)',
    },
    { key: 'sof', label: 'SOF', hint: 'Scheduled Outage Factor (= POF)' },
    {
        key: 'efor',
        label: 'EFOR',
        hint: 'Equivalent Forced Outage Rate (= FOR bila tidak ada derating)',
    },
    {
        key: 'sdof',
        label: 'SdOF (kali)',
        hint: 'Jumlah kali gangguan (menu Jumlah Kali Gangguan)',
    },
    {
        key: 'eff',
        label: 'EFF',
        hint: 'Efisiensi = kWh × 860 / (liter BBM × 10.289 × 0,949)',
    },
];

const PCT = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const toNumber = (value: string) => {
    const parsed = Number(value.replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};
const initial = (rows: Row[]) =>
    Object.fromEntries(
        rows.map((row) => [
            row.machine.id,
            Object.fromEntries(
                FIELDS.map(({ key }) => [key, String(row.values[key])]),
            ),
        ]),
    ) as Record<string, Record<Field, string>>;

/**
 * Data Kinerja Pembangkit Termal — percentages per machine computed from
 * Kinerja Unit Mesin, Stand kWh Harian, Pemakaian Bahan Bakar and Jumlah Kali
 * Gangguan; every figure can be corrected and reset.
 */
export default function KinerjaTermalIndex({
    unit,
    units,
    filters,
    period_label,
    rows,
    jam_periode,
    constants,
    saved_at,
    can_write,
}: Props) {
    const [values, setValues] = useState(() => initial(rows));
    const [saving, setSaving] = useState(false);
    const dirty = JSON.stringify(values) !== JSON.stringify(initial(rows));
    const set = (id: number, field: Field, value: string) =>
        setValues((current) => ({
            ...current,
            [id]: { ...current[id], [field]: value },
        }));
    const operating = rows.filter((row) => row.operating);
    const average = (field: Field) =>
        operating.length === 0
            ? 0
            : operating.reduce(
                  (sum, row) => sum + toNumber(values[row.machine.id][field]),
                  0,
              ) / operating.length;

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
            kinerjaTermal.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };
    const save = () =>
        router.post(
            kinerjaTermal.store().url,
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

    return (
        <>
            <Head title="Data Kinerja Pembangkit Termal" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Data Kinerja Pembangkit Termal"
                    description={`${unit.name}, ${period_label} (jam periode ${NUMBER.format(jam_periode)} jam). Dihitung dari Kinerja Unit Mesin, Stand kWh Harian, Pemakaian Bahan Bakar, dan Jumlah Kali Gangguan — angka bisa dikoreksi.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={kinerjaTermal.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        kinerjaTermal.pdf({
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

                {rows.length === 0 ? (
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
                                label="EAF rata-rata"
                                value={PCT.format(average('eaf'))}
                                unit="%"
                                hint={`${operating.length} mesin beroperasi`}
                            />
                            <SummaryCard
                                label="CF rata-rata"
                                value={PCT.format(average('cf'))}
                                unit="%"
                            />
                            <SummaryCard
                                label="FOR rata-rata"
                                value={PCT.format(average('for'))}
                                unit="%"
                            />
                            <SummaryCard
                                label="EFF rata-rata"
                                value={PCT.format(average('eff'))}
                                unit="%"
                            />
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="border-b border-border bg-secondary text-[12px] whitespace-nowrap text-muted-foreground">
                                            <th className="px-3 py-2 text-left font-semibold">
                                                Mesin
                                            </th>
                                            <th className="px-3 py-2 text-left font-semibold">
                                                Merk / Type / Seri
                                            </th>
                                            {FIELDS.map((field) => (
                                                <th
                                                    key={field.key}
                                                    className="px-2 py-2 text-right font-semibold"
                                                    title={field.hint}
                                                >
                                                    {field.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={row.machine.id}
                                                className="border-b border-border"
                                            >
                                                <td
                                                    className="px-3 py-1.5 font-medium whitespace-nowrap text-foreground"
                                                    title={`kWh ${NUMBER.format(row.source.kwh)} · BBM ${NUMBER.format(row.source.bbm_liter)} L · jam operasi ${NUMBER.format(row.source.jam_operasi)}`}
                                                >
                                                    {row.machine.name}
                                                </td>
                                                <td className="px-3 py-1.5 text-[12px] whitespace-nowrap text-muted-foreground">
                                                    {[
                                                        row.machine.merk,
                                                        row.machine.type,
                                                        row.machine
                                                            .serial_number,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || '—'}
                                                </td>
                                                {row.operating ? (
                                                    FIELDS.map(({ key }) => {
                                                        const edited =
                                                            toNumber(
                                                                values[
                                                                    row.machine
                                                                        .id
                                                                ][key],
                                                            ) !== row.auto[key];

                                                        return (
                                                            <td
                                                                key={key}
                                                                className={cn(
                                                                    'px-1 py-1',
                                                                    edited &&
                                                                        'bg-primary/5',
                                                                )}
                                                                title={`Otomatis: ${PCT.format(row.auto[key])}`}
                                                            >
                                                                <div className="flex items-center justify-end gap-0.5">
                                                                    <input
                                                                        inputMode="decimal"
                                                                        aria-label={`${key.toUpperCase()} ${row.machine.name}`}
                                                                        value={
                                                                            values[
                                                                                row
                                                                                    .machine
                                                                                    .id
                                                                            ][
                                                                                key
                                                                            ]
                                                                        }
                                                                        readOnly={
                                                                            !can_write
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            /^\d*([.,]\d{0,2})?$/.test(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ) &&
                                                                            set(
                                                                                row
                                                                                    .machine
                                                                                    .id,
                                                                                key,
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="h-8 w-16 bg-transparent px-1 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                                    />
                                                                    {edited &&
                                                                        can_write && (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    set(
                                                                                        row
                                                                                            .machine
                                                                                            .id,
                                                                                        key,
                                                                                        String(
                                                                                            row
                                                                                                .auto[
                                                                                                key
                                                                                            ],
                                                                                        ),
                                                                                    )
                                                                                }
                                                                                className="rounded-sm p-0.5 text-primary hover:bg-primary/10"
                                                                                aria-label="Kembalikan ke nilai otomatis"
                                                                            >
                                                                                <RotateCcw className="size-3" />
                                                                            </button>
                                                                        )}
                                                                </div>
                                                            </td>
                                                        );
                                                    })
                                                ) : (
                                                    <td
                                                        colSpan={FIELDS.length}
                                                        className="px-3 py-1.5 text-center text-[12px] font-semibold tracking-wide text-muted-foreground"
                                                    >
                                                        ATTB HAR ({row.kondisi})
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                        <tr className="bg-secondary font-semibold">
                                            <td
                                                colSpan={2}
                                                className="px-3 py-2 text-right"
                                            >
                                                Rata-rata
                                            </td>
                                            {FIELDS.map(({ key }) => (
                                                <td
                                                    key={key}
                                                    className="px-2 py-2 text-right tabular-nums"
                                                >
                                                    {PCT.format(average(key))}
                                                </td>
                                            ))}
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="rounded-md border border-border bg-card p-4 text-[13px]">
                            <h2 className="mb-2 text-base font-semibold text-foreground">
                                Keterangan rumus
                            </h2>
                            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                                {FIELDS.map((field) => (
                                    <div key={field.key} className="flex gap-2">
                                        <dt className="w-20 shrink-0 font-semibold text-foreground">
                                            {field.label}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {field.hint}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="mt-2 text-[12px] text-muted-foreground">
                                Jam periode = jumlah hari × 24 (
                                {NUMBER.format(jam_periode)} jam). Konstanta
                                EFF: {constants.kcal_per_kwh} kkal/kWh,{' '}
                                {NUMBER.format(constants.kcal_per_liter)}{' '}
                                kkal/liter, faktor {constants.density}. Mesin
                                berkondisi Tidak Operasi (Kinerja Unit Mesin)
                                ditampilkan ATTB HAR dan tidak masuk rata-rata.
                            </p>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}
