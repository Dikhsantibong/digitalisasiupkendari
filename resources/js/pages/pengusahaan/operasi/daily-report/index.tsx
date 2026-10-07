import { Head, router } from '@inertiajs/react';
import { Info, Loader2, RotateCcw, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import dailyReport from '@/routes/operasi/pengusahaan/daily-report';

type MachineItem = {
    id: number;
    name: string;
    fuel_type: string | null;
    uses_mfo: boolean;
    capacity_kw?: number | null;
};

type LubricantItem = {
    id: number;
    name: string;
    code: string | null;
    unit_of_measure: string;
};

/** A jenis BBM of the unit (master Tangki BBM → Jenis BBM). */
type FuelItem = { code: string; name: string };

type InventoryRow = {
    bbm: Record<string, number>;
    lubricants: Record<string, number>;
};

type InventoryKey =
    | 'persediaan_awal'
    | 'penerimaan'
    | 'penerimaan_sewa_smp'
    | 'pemakaian_non_operasi'
    | 'pengiriman';

type Merged = {
    summary: {
        kwh_pemakaian_sendiri: number;
        beban_puncak_pagi_kw: number;
        beban_puncak_malam_kw: number;
        jam_jalan_perhari: number;
    };
    mesins: Record<
        string,
        {
            kwh_dibangkit: number;
            jam_jalan: number;
            t_kalor: number;
            bbm: Record<string, number>;
            pemakaian_pelumas: Record<string, number>;
        }
    >;
    inventory: Record<InventoryKey, InventoryRow>;
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    filters: Filters;
    unit: { id: number; name: string };
    machines: MachineItem[];
    lubricants: LubricantItem[];
    fuels: FuelItem[];
    daysInMonth: number;
    ikhtisar: Merged;
    /** Automatic value of every computed field, by path. */
    auto: Record<string, number>;
    edited: string[];
    saved_at: string | null;
    period: { total_days: number; locked: boolean };
    options: { units: { id: number; name: string }[]; years: number[] };
    can_write: boolean;
};

/** path → typed value. */
type Values = Record<string, string>;

const MESIN_FIELDS = ['kwh_dibangkit', 'jam_jalan', 't_kalor'] as const;
const INVENTORY_ROWS: { key: InventoryKey; label: string; source: string }[] = [
    {
        key: 'persediaan_awal',
        label: 'Persediaan awal',
        source: 'Persediaan akhir bulan lalu',
    },
    {
        key: 'penerimaan',
        label: 'Penerimaan',
        source: 'Penerimaan BBM & pelumas',
    },
    {
        key: 'penerimaan_sewa_smp',
        label: 'Penerimaan sewa SMP',
        source: 'Isian manual',
    },
    {
        key: 'pemakaian_non_operasi',
        label: 'Pemakaian non operasi',
        source: 'Isian manual',
    },
    { key: 'pengiriman', label: 'Pengiriman', source: 'Isian manual' },
];

const toNumber = (value: string | undefined) => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};
const format = (value: number, decimals = 2) =>
    value.toLocaleString('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

function flatten(
    merged: Merged,
    machines: MachineItem[],
    lubricants: LubricantItem[],
    fuels: FuelItem[],
): Values {
    const values: Values = {};

    for (const [field, value] of Object.entries(merged.summary)) {
        values[`summary.${field}`] = String(value ?? 0);
    }

    for (const machine of machines) {
        const row = merged.mesins[machine.id];

        for (const field of MESIN_FIELDS) {
            values[`mesins.${machine.id}.${field}`] = String(row?.[field] ?? 0);
        }

        for (const fuel of fuels) {
            values[`mesins.${machine.id}.bbm.${fuel.code}`] = String(
                row?.bbm?.[fuel.code] ?? 0,
            );
        }

        for (const lubricant of lubricants) {
            values[`mesins.${machine.id}.pemakaian_pelumas.${lubricant.id}`] =
                String(row?.pemakaian_pelumas?.[lubricant.id] ?? 0);
        }
    }

    for (const { key } of INVENTORY_ROWS) {
        const row = merged.inventory[key];

        for (const fuel of fuels) {
            values[`inventory.${key}.bbm.${fuel.code}`] = String(
                row?.bbm?.[fuel.code] ?? 0,
            );
        }

        for (const lubricant of lubricants) {
            values[`inventory.${key}.lubricants.${lubricant.id}`] = String(
                row?.lubricants?.[lubricant.id] ?? 0,
            );
        }
    }

    return values;
}

/**
 * Ikhtisar Sentral — not typed in: kWh, jam jalan, BBM (per jenis BBM of the
 * unit's master), pelumas, beban puncak malam and the receipts come from the
 * other Pengusahaan Operasi sheets. Every computed figure can be corrected
 * (marked, with a reset); beban puncak pagi, jam jalan per hari, sewa SMP,
 * non operasi and pengiriman stay manual.
 */
export default function IkhtisarSentralIndex({
    filters,
    unit,
    machines,
    lubricants,
    fuels,
    daysInMonth,
    ikhtisar,
    auto,
    saved_at,
    period,
    options,
    can_write,
}: Props) {
    const initial = useMemo(
        () => flatten(ikhtisar, machines, lubricants, fuels),
        [ikhtisar, machines, lubricants, fuels],
    );
    const [values, setValues] = useState<Values>(initial);
    const [saving, setSaving] = useState(false);
    const editable = can_write && !period.locked;
    const dirty = JSON.stringify(values) !== JSON.stringify(initial);

    const v = (path: string) => toNumber(values[path]);
    const set = (path: string, value: string) =>
        setValues((current) => ({ ...current, [path]: value }));

    const machineRow = (id: number) => {
        const kwh = v(`mesins.${id}.kwh_dibangkit`);
        const bbm = fuels.reduce(
            (sum, f) => sum + v(`mesins.${id}.bbm.${f.code}`),
            0,
        );
        const pelumas = lubricants.reduce(
            (sum, l) => sum + v(`mesins.${id}.pemakaian_pelumas.${l.id}`),
            0,
        );

        return {
            kwh,
            sfc: kwh > 0 ? bbm / kwh : 0,
            slc: kwh > 0 ? (pelumas * 1000) / kwh : 0,
        };
    };

    const totals = useMemo(() => {
        const sum = (field: string) =>
            machines.reduce(
                (total, m) =>
                    total + toNumber(values[`mesins.${m.id}.${field}`]),
                0,
            );
        const kwh = sum('kwh_dibangkit');
        const bbm = Object.fromEntries(
            fuels.map((f) => [f.code, sum(`bbm.${f.code}`)]),
        );
        const allBbm = Object.values(bbm).reduce((a, b) => a + b, 0);
        const pelumas = Object.fromEntries(
            lubricants.map((l) => [l.id, sum(`pemakaian_pelumas.${l.id}`)]),
        );
        const allPelumas = Object.values(pelumas).reduce((a, b) => a + b, 0);
        const kalor =
            kwh > 0
                ? machines.reduce(
                      (total, m) =>
                          total +
                          toNumber(values[`mesins.${m.id}.t_kalor`]) *
                              toNumber(values[`mesins.${m.id}.kwh_dibangkit`]),
                      0,
                  ) / kwh
                : 0;

        return {
            kwh,
            jam: sum('jam_jalan'),
            bbm,
            pelumas,
            sfc: kwh > 0 ? allBbm / kwh : 0,
            slc: kwh > 0 ? (allPelumas * 1000) / kwh : 0,
            kalor,
        };
    }, [machines, lubricants, fuels, values]);

    const ps = v('summary.kwh_pemakaian_sendiri');
    const disalurkan = Math.max(0, totals.kwh - ps);

    const inv = (key: InventoryKey, column: string) =>
        v(`inventory.${key}.${column}`);
    const columns = [
        ...fuels.map((f) => `bbm.${f.code}`),
        ...lubricants.map((l) => `lubricants.${l.id}`),
    ];
    const pemakaianMesin = (column: string) => {
        const [group, key] = column.split('.');

        return group === 'bbm'
            ? (totals.bbm[key] ?? 0)
            : (totals.pelumas[key] ?? 0);
    };
    const jumlahPersediaan = (column: string) =>
        inv('persediaan_awal', column) +
        inv('penerimaan', column) +
        inv('penerimaan_sewa_smp', column);
    const jumlahPemakaian = (column: string) =>
        pemakaianMesin(column) +
        inv('pemakaian_non_operasi', column) +
        inv('pengiriman', column);

    const changeFilter = (patch: Partial<Filters>) => {
        if (
            dirty &&
            editable &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            dailyReport.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () => {
        const inventoryPayload = (key: InventoryKey) => ({
            bbm: Object.fromEntries(
                fuels.map((f) => [f.code, inv(key, `bbm.${f.code}`)]),
            ),
            lubricants: Object.fromEntries(
                lubricants.map((l) => [l.id, inv(key, `lubricants.${l.id}`)]),
            ),
        });

        router.post(
            dailyReport.store().url,
            {
                ...filters,
                summary: {
                    kwh_dibangkit: totals.kwh,
                    kwh_pemakaian_sendiri: ps,
                    kwh_disalurkan: disalurkan,
                    beban_puncak_pagi_kw: v('summary.beban_puncak_pagi_kw'),
                    beban_puncak_malam_kw: v('summary.beban_puncak_malam_kw'),
                    jam_jalan_perhari: v('summary.jam_jalan_perhari'),
                },
                mesins: machines.map((m) => {
                    const { sfc, slc } = machineRow(m.id);

                    return {
                        engine_id: m.id,
                        kwh_dibangkit: v(`mesins.${m.id}.kwh_dibangkit`),
                        jam_jalan: v(`mesins.${m.id}.jam_jalan`),
                        t_kalor: v(`mesins.${m.id}.t_kalor`),
                        sfc: Number(sfc.toFixed(4)),
                        slc: Number(slc.toFixed(3)),
                        bbm: Object.fromEntries(
                            fuels.map((f) => [
                                f.code,
                                v(`mesins.${m.id}.bbm.${f.code}`),
                            ]),
                        ),
                        pemakaian_pelumas: Object.fromEntries(
                            lubricants.map((l) => [
                                l.id,
                                v(`mesins.${m.id}.pemakaian_pelumas.${l.id}`),
                            ]),
                        ),
                    };
                }),
                inventory: Object.fromEntries(
                    INVENTORY_ROWS.map(({ key }) => [
                        key,
                        inventoryPayload(key),
                    ]),
                ),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const cell = (path: string, width = 'w-24') => (
        <ValueCell
            key={path}
            path={path}
            value={values[path] ?? ''}
            auto={auto[path]}
            editable={editable}
            onChange={set}
            width={width}
        />
    );
    const editedCount = Object.keys(auto).filter(
        (path) => Math.abs(v(path) - auto[path]) > 0.005,
    ).length;

    return (
        <>
            <Head title={`Ikhtisar Sentral - ${unit.name}`} />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Ikhtisar Sentral"
                    description={`${unit.name}, ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}. Terisi otomatis dari Stand kWh, Jam Operasi, Beban Tertinggi, Pemakaian Bahan Bakar, Pemakaian Pelumas, dan Penerimaan BBM — nilai bisa dikoreksi.`}
                    actions={
                        <>
                            {period.locked && (
                                <StatusBadge tone="warning">
                                    Periode dikunci
                                </StatusBadge>
                            )}
                            {editable && (
                                <Button
                                    onClick={save}
                                    disabled={
                                        saving || (!dirty && saved_at !== null)
                                    }
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
                        onChange={(value) =>
                            changeFilter({ unit_id: Number(value) })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={(value) =>
                            changeFilter({ month: Number(value) })
                        }
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={(value) =>
                            changeFilter({ year: Number(value) })
                        }
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {dirty && editable ? (
                            <span className="font-medium text-amber-700">
                                Ada perubahan yang belum disimpan
                            </span>
                        ) : saved_at ? (
                            `Tersimpan ${formatStamp(saved_at)}`
                        ) : (
                            'Belum disimpan — nilai otomatis'
                        )}
                    </p>
                </div>

                <p className="flex items-start gap-2 rounded-md border border-border bg-card px-4 py-3 text-[13px] text-muted-foreground">
                    <Info className="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>
                        Angka berlatar biru sudah dikoreksi dari nilai otomatis
                        ({editedCount} sel) — arahkan kursor untuk melihat nilai
                        otomatisnya, klik{' '}
                        <RotateCcw className="inline size-3" /> untuk
                        mengembalikan. Kolom BBM mengikuti jenis BBM unit di
                        Data Master (Tangki BBM). Beban puncak pagi, jam jalan
                        per hari, sewa SMP, pemakaian non operasi, dan
                        pengiriman diisi manual.
                    </span>
                </p>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <SummaryCard
                        label="kWh dibangkit"
                        value={format(totals.kwh, 0)}
                        unit="kWh"
                        hint={`${format(totals.kwh / (daysInMonth || 30), 0)} kWh/hari`}
                    />
                    <SummaryCard
                        label="kWh pemakaian sendiri"
                        value={format(ps, 0)}
                        unit="kWh"
                        hint={`${format(totals.kwh > 0 ? (ps / totals.kwh) * 100 : 0)}% dari dibangkit`}
                    />
                    <SummaryCard
                        label="kWh disalurkan"
                        value={format(disalurkan, 0)}
                        unit="kWh"
                    />
                    <SummaryCard
                        label="SFC unit"
                        value={format(totals.sfc, 4)}
                        unit="L/kWh"
                        hint={`SLC ${format(totals.slc, 3)} cc/kWh`}
                    />
                </div>

                <Panel title="Ringkasan sentral">
                    <table
                        className="w-full border-collapse text-[13px]"
                        data-keep-table
                    >
                        <thead>
                            <tr className="border-b border-border bg-secondary text-left text-[12px] text-muted-foreground">
                                <th className="px-3 py-2 font-semibold">
                                    Item
                                </th>
                                <th className="px-3 py-2 text-right font-semibold">
                                    Jumlah
                                </th>
                                <th className="px-3 py-2 font-semibold">
                                    Satuan
                                </th>
                                <th className="px-3 py-2 text-right font-semibold">
                                    Rata-rata / hari
                                </th>
                                <th className="px-3 py-2 font-semibold">
                                    Sumber
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <SummaryRow
                                label="kWh dibangkit"
                                unit="kWh"
                                perDay={totals.kwh / daysInMonth}
                                source="Jumlah kWh mesin (Stand kWh Harian)"
                            >
                                <span className="px-2 font-semibold tabular-nums">
                                    {format(totals.kwh)}
                                </span>
                            </SummaryRow>
                            <SummaryRow
                                label="kWh pemakaian sendiri"
                                unit="kWh"
                                perDay={ps / daysInMonth}
                                source="Stand kWh Harian (PS)"
                            >
                                {cell('summary.kwh_pemakaian_sendiri', 'w-32')}
                            </SummaryRow>
                            <SummaryRow
                                label="kWh disalurkan"
                                unit="kWh"
                                perDay={disalurkan / daysInMonth}
                                source={`Dibangkit − PS · PS ${format(totals.kwh > 0 ? (ps / totals.kwh) * 100 : 0)}%`}
                                strong
                            >
                                <span className="px-2 font-semibold text-primary tabular-nums">
                                    {format(disalurkan)}
                                </span>
                            </SummaryRow>
                            <SummaryRow
                                label="Beban puncak pagi"
                                unit="kW"
                                source="Isian manual"
                            >
                                {cell('summary.beban_puncak_pagi_kw', 'w-32')}
                            </SummaryRow>
                            <SummaryRow
                                label="Beban puncak malam"
                                unit="kW"
                                source="Beban Tertinggi (puncak unit)"
                            >
                                {cell('summary.beban_puncak_malam_kw', 'w-32')}
                            </SummaryRow>
                            <SummaryRow
                                label="Jam jalan per hari"
                                unit="jam"
                                source="Isian manual"
                            >
                                {cell('summary.jam_jalan_perhari', 'w-32')}
                            </SummaryRow>
                        </tbody>
                    </table>
                </Panel>

                <Panel title="Produksi & pemakaian BBM / pelumas per mesin">
                    {machines.length === 0 ? (
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    ) : (
                        <table
                            className="w-full border-collapse text-[13px]"
                            data-keep-table
                        >
                            <thead>
                                <tr className="bg-secondary text-[12px] text-foreground">
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b border-border px-3 py-2 text-left font-semibold"
                                    >
                                        Mesin
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b border-border px-2 py-2 font-semibold"
                                    >
                                        kWh dibangkit
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b border-border px-2 py-2 font-semibold"
                                    >
                                        Jam jalan
                                    </th>
                                    <th
                                        colSpan={fuels.length + 3}
                                        className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                    >
                                        Pemakaian BBM
                                    </th>
                                    {lubricants.length > 0 && (
                                        <th
                                            colSpan={lubricants.length}
                                            className="border-b border-border px-2 py-1.5 font-semibold"
                                        >
                                            Pemakaian pelumas
                                        </th>
                                    )}
                                </tr>
                                <tr className="bg-secondary text-[12px] whitespace-nowrap text-muted-foreground">
                                    {fuels.map((f) => (
                                        <th
                                            key={f.code}
                                            className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                            title={f.name}
                                        >
                                            {f.code} (L)
                                        </th>
                                    ))}
                                    {[
                                        'SFC (L/kWh)',
                                        'T. kalor (kkal/kWh)',
                                        'SLC (cc/kWh)',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                    {lubricants.map((l) => (
                                        <th
                                            key={l.id}
                                            className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                        >
                                            {l.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {machines.map((m) => {
                                    const row = machineRow(m.id);

                                    return (
                                        <tr
                                            key={m.id}
                                            className="border-b border-border"
                                        >
                                            <td className="border-r border-border px-3 py-1 font-medium whitespace-nowrap text-foreground">
                                                {m.name}
                                            </td>
                                            {cell(
                                                `mesins.${m.id}.kwh_dibangkit`,
                                                'w-28',
                                            )}
                                            {cell(
                                                `mesins.${m.id}.jam_jalan`,
                                                'w-20',
                                            )}
                                            {fuels.map((f) =>
                                                cell(
                                                    `mesins.${m.id}.bbm.${f.code}`,
                                                    'w-24',
                                                ),
                                            )}
                                            <td className="border-r border-border bg-secondary/50 px-2 text-right tabular-nums">
                                                {format(row.sfc, 4)}
                                            </td>
                                            {cell(
                                                `mesins.${m.id}.t_kalor`,
                                                'w-24',
                                            )}
                                            <td className="border-r border-border bg-secondary/50 px-2 text-right tabular-nums">
                                                {format(row.slc, 3)}
                                            </td>
                                            {lubricants.map((l) =>
                                                cell(
                                                    `mesins.${m.id}.pemakaian_pelumas.${l.id}`,
                                                    'w-20',
                                                ),
                                            )}
                                        </tr>
                                    );
                                })}
                                <tr className="bg-secondary font-semibold">
                                    <td className="border-r border-border px-3 py-2">
                                        Jumlah
                                    </td>
                                    <td className="border-r border-border px-2 py-2 text-right tabular-nums">
                                        {format(totals.kwh)}
                                    </td>
                                    <td className="border-r border-border px-2 py-2 text-right tabular-nums">
                                        {format(totals.jam)}
                                    </td>
                                    {fuels.map((f) => (
                                        <td
                                            key={f.code}
                                            className="border-r border-border px-2 py-2 text-right tabular-nums"
                                        >
                                            {format(totals.bbm[f.code] ?? 0)}
                                        </td>
                                    ))}
                                    <td className="border-r border-border px-2 py-2 text-right tabular-nums">
                                        {format(totals.sfc, 4)}
                                    </td>
                                    <td className="border-r border-border px-2 py-2 text-right tabular-nums">
                                        {format(totals.kalor)}
                                    </td>
                                    <td className="border-r border-border px-2 py-2 text-right tabular-nums">
                                        {format(totals.slc, 3)}
                                    </td>
                                    {lubricants.map((l) => (
                                        <td
                                            key={l.id}
                                            className="border-r border-border px-2 py-2 text-right tabular-nums"
                                        >
                                            {format(totals.pelumas[l.id] ?? 0)}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    )}
                </Panel>

                <Panel title="Persediaan BBM & pelumas">
                    {columns.length === 0 ? (
                        <EmptyState
                            title="Belum ada jenis BBM atau pelumas"
                            description="Atur Tangki BBM dan Jenis Pelumas unit di Data Master Operasi."
                        />
                    ) : (
                        <table
                            className="w-full border-collapse text-[13px]"
                            data-keep-table
                        >
                            <thead>
                                <tr className="bg-secondary text-[12px] whitespace-nowrap text-muted-foreground">
                                    <th className="border-r border-b border-border px-3 py-2 text-left font-semibold">
                                        Uraian
                                    </th>
                                    {fuels.map((f) => (
                                        <th
                                            key={f.code}
                                            className="border-r border-b border-border px-2 py-2 font-semibold"
                                            title={f.name}
                                        >
                                            {f.code} (L)
                                        </th>
                                    ))}
                                    {lubricants.map((l) => (
                                        <th
                                            key={l.id}
                                            className="border-r border-b border-border px-2 py-2 font-semibold"
                                        >
                                            {l.name}
                                        </th>
                                    ))}
                                    <th className="border-b border-border px-3 py-2 text-left font-semibold">
                                        Sumber
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {INVENTORY_ROWS.slice(0, 3).map((row) => (
                                    <tr
                                        key={row.key}
                                        className="border-b border-border"
                                    >
                                        <td className="border-r border-border px-3 py-1 whitespace-nowrap text-foreground">
                                            {row.label}
                                        </td>
                                        {columns.map((column) =>
                                            cell(
                                                `inventory.${row.key}.${column}`,
                                            ),
                                        )}
                                        <td className="px-3 py-1 text-[12px] text-muted-foreground">
                                            {row.source}
                                        </td>
                                    </tr>
                                ))}
                                <ComputedRow
                                    label="Jumlah persediaan"
                                    columns={columns}
                                    value={jumlahPersediaan}
                                    source="Awal + penerimaan + sewa SMP"
                                />
                                <ComputedRow
                                    label="Pemakaian mesin"
                                    columns={columns}
                                    value={pemakaianMesin}
                                    source="Dari tabel per mesin"
                                    muted
                                />
                                {INVENTORY_ROWS.slice(3).map((row) => (
                                    <tr
                                        key={row.key}
                                        className="border-b border-border"
                                    >
                                        <td className="border-r border-border px-3 py-1 whitespace-nowrap text-foreground">
                                            {row.label}
                                        </td>
                                        {columns.map((column) =>
                                            cell(
                                                `inventory.${row.key}.${column}`,
                                            ),
                                        )}
                                        <td className="px-3 py-1 text-[12px] text-muted-foreground">
                                            {row.source}
                                        </td>
                                    </tr>
                                ))}
                                <ComputedRow
                                    label="Jumlah pemakaian"
                                    columns={columns}
                                    value={jumlahPemakaian}
                                    source="Mesin + non operasi + pengiriman"
                                />
                                <ComputedRow
                                    label="Persediaan akhir"
                                    columns={columns}
                                    value={(column) =>
                                        jumlahPersediaan(column) -
                                        jumlahPemakaian(column)
                                    }
                                    source="Jumlah persediaan − jumlah pemakaian"
                                    strong
                                />
                            </tbody>
                        </table>
                    )}
                </Panel>
            </div>
        </>
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="overflow-hidden rounded-md border border-border bg-card">
            <h2 className="border-b border-border px-4 py-3 text-base font-semibold text-foreground">
                {title}
            </h2>
            <div className="overflow-x-auto">{children}</div>
        </section>
    );
}

function SummaryRow({
    label,
    unit,
    perDay,
    source,
    strong,
    children,
}: {
    label: string;
    unit: string;
    perDay?: number;
    source: string;
    strong?: boolean;
    children: ReactNode;
}) {
    return (
        <tr className={cn('border-b border-border', strong && 'bg-primary/5')}>
            <td
                className={cn(
                    'px-3 py-1.5',
                    strong ? 'font-semibold text-primary' : 'text-foreground',
                )}
            >
                {label}
            </td>
            <td className="px-1 py-1 text-right">{children}</td>
            <td className="px-3 py-1.5 text-muted-foreground">{unit}</td>
            <td className="px-3 py-1.5 text-right text-muted-foreground tabular-nums">
                {perDay === undefined ? '—' : format(perDay)}
            </td>
            <td className="px-3 py-1.5 text-[12px] text-muted-foreground">
                {source}
            </td>
        </tr>
    );
}

function ComputedRow({
    label,
    columns,
    value,
    source,
    strong,
    muted,
}: {
    label: string;
    columns: string[];
    value: (column: string) => number;
    source: string;
    strong?: boolean;
    muted?: boolean;
}) {
    return (
        <tr
            className={cn(
                'border-b border-border',
                strong ? 'bg-secondary font-semibold' : 'bg-secondary/40',
                muted && 'text-muted-foreground',
            )}
        >
            <td className="border-r border-border px-3 py-1.5 whitespace-nowrap">
                {label}
            </td>
            {columns.map((column) => (
                <td
                    key={column}
                    className={cn(
                        'border-r border-border px-2 py-1.5 text-right tabular-nums',
                        value(column) < 0 && 'text-red-700',
                    )}
                >
                    {format(value(column))}
                </td>
            ))}
            <td className="px-3 py-1.5 text-[12px] font-normal text-muted-foreground">
                {source}
            </td>
        </tr>
    );
}

/** An editable number; when it has an automatic value, a different value is marked with a reset. */
function ValueCell({
    path,
    value,
    auto,
    editable,
    onChange,
    width,
}: {
    path: string;
    value: string;
    auto: number | undefined;
    editable: boolean;
    onChange: (path: string, value: string) => void;
    width: string;
}) {
    const edited =
        auto !== undefined && Math.abs(toNumber(value) - auto) > 0.005;

    return (
        <td
            className={cn(
                'border-r border-border px-1 py-0.5',
                edited && 'bg-primary/5',
            )}
            title={
                auto === undefined
                    ? 'Isian manual'
                    : `Otomatis: ${format(auto)}`
            }
        >
            <div className="flex items-center justify-end gap-0.5">
                <input
                    inputMode="decimal"
                    aria-label={path}
                    value={value}
                    readOnly={!editable}
                    onChange={(e) =>
                        /^-?\d*([.,]\d{0,4})?$/.test(e.target.value) &&
                        onChange(path, e.target.value)
                    }
                    className={cn(
                        'h-8 bg-transparent px-1 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        width,
                        auto === undefined &&
                            editable &&
                            'rounded-sm border border-dashed border-border',
                    )}
                />
                {edited && editable && (
                    <button
                        type="button"
                        onClick={() => onChange(path, String(auto))}
                        className="rounded-sm p-0.5 text-primary hover:bg-primary/10"
                        aria-label="Kembalikan ke nilai otomatis"
                    >
                        <RotateCcw className="size-3" />
                    </button>
                )}
            </div>
        </td>
    );
}
