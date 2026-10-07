import { Head, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import standKwh from '@/routes/operasi/pengusahaan/stand-kwh';

type Row = {
    day: number;
    produksi_awal: number | null;
    produksi_akhir: number | null;
    produksi: number | null;
    ps_awal: number | null;
    ps_akhir: number | null;
    ps: number | null;
    nett: number | null;
};

type Sheet = {
    machine: {
        id: number;
        name: string;
        merk: string | null;
        type: string | null;
        serial_number: string | null;
    };
    faktor_koreksi: number;
    faktor_kali_produksi: number;
    faktor_kali_ps: number;
    stand_awal_produksi: number | null;
    stand_awal_ps: number | null;
    has_previous: boolean;
    rows: Row[];
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    days_in_month: number;
    sheets: Sheet[];
    can_write: boolean;
};

type Meter = 'produksi' | 'ps';
/** machine id → day → meter → typed stand. */
type Stands = Record<string, Record<string, Record<Meter, string>>>;
type Openings = Record<string, Record<Meter, string>>;

const STAND = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const KWH = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

const parse = (value: string | undefined): number | null => {
    if (value === undefined || value.trim() === '') {
        return null;
    }

    // One decimal separator only ("," or "."), no thousand separators.
    const parsed = Number(value.replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : null;
};

const initialStands = (sheets: Sheet[]): Stands =>
    Object.fromEntries(
        sheets.map((sheet) => [
            sheet.machine.id,
            Object.fromEntries(
                sheet.rows.map((row) => [
                    row.day,
                    {
                        produksi:
                            row.produksi_akhir === null
                                ? ''
                                : String(row.produksi_akhir),
                        ps: row.ps_akhir === null ? '' : String(row.ps_akhir),
                    },
                ]),
            ),
        ]),
    );

const initialOpenings = (sheets: Sheet[]): Openings =>
    Object.fromEntries(
        sheets.map((sheet) => [
            sheet.machine.id,
            {
                produksi:
                    sheet.stand_awal_produksi === null
                        ? ''
                        : String(sheet.stand_awal_produksi),
                ps:
                    sheet.stand_awal_ps === null
                        ? ''
                        : String(sheet.stand_awal_ps),
            },
        ]),
    );

/** Live rows of one machine: awal = the latest stand before the day, kWh = (akhir − awal) × koreksi × kali. */
function computeRows(
    sheet: Sheet,
    stands: Stands,
    openings: Openings,
    days: number,
) {
    const rows: {
        day: number;
        awal: Record<Meter, number | null>;
        akhir: Record<Meter, number | null>;
        kwh: Record<Meter, number | null>;
        nett: number | null;
    }[] = [];
    const last: Record<Meter, number | null> = {
        produksi: parse(openings[sheet.machine.id]?.produksi),
        ps: parse(openings[sheet.machine.id]?.ps),
    };
    const kali: Record<Meter, number> = {
        produksi: sheet.faktor_kali_produksi,
        ps: sheet.faktor_kali_ps,
    };

    for (let day = 1; day <= days; day++) {
        const akhir: Record<Meter, number | null> = {
            produksi: parse(stands[sheet.machine.id]?.[day]?.produksi),
            ps: parse(stands[sheet.machine.id]?.[day]?.ps),
        };
        const awal = { ...last };
        const kwh = (meter: Meter) =>
            akhir[meter] === null || awal[meter] === null
                ? null
                : (akhir[meter]! - awal[meter]!) *
                  sheet.faktor_koreksi *
                  kali[meter];
        const produksi = kwh('produksi');
        const ps = kwh('ps');

        rows.push({
            day,
            awal,
            akhir,
            kwh: { produksi, ps },
            nett:
                produksi === null && ps === null
                    ? null
                    : (produksi ?? 0) - (ps ?? 0),
        });
        last.produksi = akhir.produksi ?? last.produksi;
        last.ps = akhir.ps ?? last.ps;
    }

    return rows;
}

export default function StandKwhIndex({
    unit,
    units,
    filters,
    period_label,
    days_in_month,
    sheets,
    can_write,
}: Props) {
    const compact = useCompactLayout();
    const [stands, setStands] = useState<Stands>(() => initialStands(sheets));
    const [openings, setOpenings] = useState<Openings>(() =>
        initialOpenings(sheets),
    );
    const [machineId, setMachineId] = useState<number | null>(
        sheets[0]?.machine.id ?? null,
    );
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
            JSON.stringify(stands) !== JSON.stringify(initialStands(sheets)) ||
            JSON.stringify(openings) !==
                JSON.stringify(initialOpenings(sheets)),
        [stands, openings, sheets],
    );
    const sheet =
        sheets.find((item) => item.machine.id === machineId) ?? sheets[0];
    const rows = useMemo(
        () =>
            sheet ? computeRows(sheet, stands, openings, days_in_month) : [],
        [sheet, stands, openings, days_in_month],
    );
    const totals = rows.reduce(
        (sum, row) => ({
            produksi: sum.produksi + (row.kwh.produksi ?? 0),
            ps: sum.ps + (row.kwh.ps ?? 0),
        }),
        { produksi: 0, ps: 0 },
    );
    const unitNett = useMemo(
        () =>
            sheets.reduce(
                (total, item) =>
                    total +
                    computeRows(item, stands, openings, days_in_month).reduce(
                        (sum, row) => sum + (row.nett ?? 0),
                        0,
                    ),
                0,
            ),
        [sheets, stands, openings, days_in_month],
    );

    const setStand = (day: number, meter: Meter, value: string) =>
        setStands((current) => ({
            ...current,
            [sheet!.machine.id]: {
                ...current[sheet!.machine.id],
                [day]: {
                    ...(current[sheet!.machine.id]?.[day] ?? {
                        produksi: '',
                        ps: '',
                    }),
                    [meter]: value,
                },
            },
        }));

    const visit = (patch: Partial<Filters>) => {
        if (
            dirty &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            standKwh.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () => {
        const toNumber = (value: string) => parse(value);
        const payload = Object.fromEntries(
            Object.entries(stands).map(([id, days]) => [
                id,
                Object.fromEntries(
                    Object.entries(days).map(([day, stand]) => [
                        day,
                        {
                            produksi: toNumber(stand.produksi),
                            ps: toNumber(stand.ps),
                        },
                    ]),
                ),
            ]),
        );
        const opening = Object.fromEntries(
            sheets
                .filter((item) => !item.has_previous)
                .map((item) => [
                    item.machine.id,
                    {
                        produksi: toNumber(
                            openings[item.machine.id]?.produksi ?? '',
                        ),
                        ps: toNumber(openings[item.machine.id]?.ps ?? ''),
                    },
                ]),
        );

        router.post(
            standKwh.store().url,
            { ...filters, stands: payload, opening },
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

    return (
        <>
            <Head title="Stand kWh Harian" />
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title="Stand kWh Harian"
                    description={`Stand kWh meter produksi (PROD) dan pemakaian sendiri (PS) per mesin per hari — ${unit.name}, ${period_label}. Menjadi sumber Transfer Pricing, Energi Dibangkit, dan Energi Pemakaian Sendiri.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={standKwh.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        standKwh.pdf({
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
                    {sheets.length > 0 && (
                        <OperasiSelect
                            label="Mesin"
                            className="w-52"
                            value={String(sheet?.machine.id ?? '')}
                            onChange={(value) => setMachineId(Number(value))}
                            options={sheets.map((item) => ({
                                value: String(item.machine.id),
                                label: item.machine.name,
                            }))}
                        />
                    )}
                    {dirty && (
                        <p className="ml-auto self-center text-[13px] font-medium text-amber-700">
                            Ada perubahan yang belum disimpan
                        </p>
                    )}
                </div>

                {!sheet ? (
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
                                label={`Produksi ${sheet.machine.name}`}
                                value={KWH.format(totals.produksi)}
                                unit="kWh"
                            />
                            <SummaryCard
                                label={`Pemakaian sendiri ${sheet.machine.name}`}
                                value={KWH.format(totals.ps)}
                                unit="kWh"
                            />
                            <SummaryCard
                                label={`kWh nett ${sheet.machine.name}`}
                                value={KWH.format(totals.produksi - totals.ps)}
                                unit="kWh"
                            />
                            <SummaryCard
                                label="kWh nett unit"
                                value={KWH.format(unitNett)}
                                unit="kWh"
                                hint={`${sheets.length} mesin`}
                            />
                        </div>

                        <section className="grid gap-3 rounded-md border border-border bg-card p-4 text-[13px] sm:grid-cols-2 lg:grid-cols-4">
                            {(['produksi', 'ps'] as const).map((meter) => (
                                <label
                                    key={meter}
                                    className="flex flex-col gap-1"
                                >
                                    <span className="font-medium text-foreground">
                                        Stand awal bulan lalu —{' '}
                                        {meter === 'produksi' ? 'PROD' : 'PS'}
                                    </span>
                                    <input
                                        inputMode="decimal"
                                        value={
                                            openings[sheet.machine.id]?.[
                                                meter
                                            ] ?? ''
                                        }
                                        readOnly={
                                            !can_write || sheet.has_previous
                                        }
                                        onChange={(e) =>
                                            setOpenings((current) => ({
                                                ...current,
                                                [sheet.machine.id]: {
                                                    ...(current[
                                                        sheet.machine.id
                                                    ] ?? {
                                                        produksi: '',
                                                        ps: '',
                                                    }),
                                                    [meter]: e.target.value,
                                                },
                                            }))
                                        }
                                        className="h-9 rounded-md border border-border bg-background px-3 text-right tabular-nums read-only:bg-secondary"
                                    />
                                    <span className="text-[12px] text-muted-foreground">
                                        {sheet.has_previous
                                            ? 'Diambil dari stand akhir bulan sebelumnya.'
                                            : 'Belum ada data bulan lalu — isi stand akhir bulan lalu.'}
                                    </span>
                                </label>
                            ))}
                            <div className="flex flex-col gap-1">
                                <span className="font-medium text-foreground">
                                    Faktor koreksi
                                </span>
                                <span className="text-lg font-semibold tabular-nums">
                                    {sheet.faktor_koreksi}
                                </span>
                                <span className="text-[12px] text-muted-foreground">
                                    Dari Faktor Kalibrasi kWh (Data Master
                                    Operasi).
                                </span>
                            </div>
                            <div className="flex flex-col gap-1">
                                <span className="font-medium text-foreground">
                                    Faktor kali PROD / PS
                                </span>
                                <span className="text-lg font-semibold tabular-nums">
                                    {sheet.faktor_kali_produksi} /{' '}
                                    {sheet.faktor_kali_ps}
                                </span>
                                <span className="text-[12px] text-muted-foreground">
                                    Dari Master Mesin.
                                </span>
                            </div>
                        </section>

                        {compact ? (
                            <section className="flex flex-col gap-3">
                                <DayStrip
                                    items={rows.map((row) => ({
                                        key: String(row.day),
                                        label: String(row.day),
                                        done: row.akhir.produksi !== null,
                                        isRed: (row.nett ?? 0) < 0,
                                    }))}
                                    value={String(selectedDay)}
                                    onChange={(key) =>
                                        setSelectedDay(Number(key))
                                    }
                                />
                                {rows
                                    .filter((row) => row.day === selectedDay)
                                    .map((row) => (
                                        <div
                                            key={row.day}
                                            className="grid grid-cols-2 gap-2.5 rounded-xl border border-border bg-card p-3"
                                        >
                                            <p className="col-span-2 text-sm font-semibold text-foreground">
                                                {sheet.machine.name} — tanggal{' '}
                                                {row.day}
                                            </p>
                                            {(['produksi', 'ps'] as const).map(
                                                (meter) => (
                                                    <label
                                                        key={meter}
                                                        className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                                    >
                                                        Stand akhir{' '}
                                                        {meter === 'produksi'
                                                            ? 'PROD'
                                                            : 'PS'}{' '}
                                                        (awal{' '}
                                                        {row.awal[meter] ===
                                                        null
                                                            ? '—'
                                                            : STAND.format(
                                                                  row.awal[
                                                                      meter
                                                                  ]!,
                                                              )}
                                                        )
                                                        <StandInput
                                                            value={
                                                                stands[
                                                                    sheet
                                                                        .machine
                                                                        .id
                                                                ]?.[row.day]?.[
                                                                    meter
                                                                ]
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            onChange={(value) =>
                                                                setStand(
                                                                    row.day,
                                                                    meter,
                                                                    value,
                                                                )
                                                            }
                                                            className="h-10 rounded-md border border-border bg-background text-sm"
                                                            label={`Stand akhir ${meter} tanggal ${row.day}`}
                                                        />
                                                    </label>
                                                ),
                                            )}
                                            <p className="col-span-2 text-[13px] text-muted-foreground tabular-nums">
                                                Produksi{' '}
                                                <b className="text-foreground">
                                                    {row.kwh.produksi === null
                                                        ? '—'
                                                        : KWH.format(
                                                              row.kwh.produksi,
                                                          )}
                                                </b>{' '}
                                                · PS{' '}
                                                <b className="text-foreground">
                                                    {row.kwh.ps === null
                                                        ? '—'
                                                        : KWH.format(
                                                              row.kwh.ps,
                                                          )}
                                                </b>{' '}
                                                · Nett{' '}
                                                <b
                                                    className={cn(
                                                        (row.nett ?? 0) < 0
                                                            ? 'text-red-700'
                                                            : 'text-foreground',
                                                    )}
                                                >
                                                    {row.nett === null
                                                        ? '—'
                                                        : KWH.format(row.nett)}
                                                </b>
                                            </p>
                                        </div>
                                    ))}
                            </section>
                        ) : (
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
                                                    className="w-12 border-r border-b border-border px-2 py-2 font-semibold"
                                                >
                                                    TGL
                                                </th>
                                                <th
                                                    colSpan={2}
                                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                                >
                                                    STAND KWH PROD
                                                </th>
                                                <th
                                                    rowSpan={2}
                                                    className="border-r border-b border-border px-2 py-2 font-semibold"
                                                >
                                                    PRODUKSI
                                                </th>
                                                <th
                                                    colSpan={2}
                                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                                >
                                                    STAND KWH PS
                                                </th>
                                                <th
                                                    rowSpan={2}
                                                    className="border-r border-b border-border px-2 py-2 font-semibold"
                                                >
                                                    PEMAKAIAN SENDIRI
                                                </th>
                                                <th
                                                    rowSpan={2}
                                                    className="border-b border-border bg-emerald-50 px-2 py-2 font-semibold"
                                                >
                                                    KWH NETT
                                                </th>
                                            </tr>
                                            <tr className="bg-secondary text-[12px] text-muted-foreground">
                                                {[
                                                    'AWAL',
                                                    'AKHIR',
                                                    'AWAL',
                                                    'AKHIR',
                                                ].map((label, index) => (
                                                    <th
                                                        key={index}
                                                        className="min-w-32 border-r border-b border-border px-2 py-1 font-semibold"
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => (
                                                <tr
                                                    key={row.day}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="border-r border-b border-border px-2 text-center text-[12px] font-medium text-muted-foreground tabular-nums">
                                                        {row.day}
                                                    </td>
                                                    {(
                                                        [
                                                            'produksi',
                                                            'ps',
                                                        ] as const
                                                    ).map((meter) => (
                                                        <Cells key={meter}>
                                                            <td className="border-r border-b border-border px-2 text-right text-muted-foreground tabular-nums">
                                                                {row.awal[
                                                                    meter
                                                                ] === null
                                                                    ? ''
                                                                    : STAND.format(
                                                                          row
                                                                              .awal[
                                                                              meter
                                                                          ]!,
                                                                      )}
                                                            </td>
                                                            <td className="border-r border-b border-border p-0">
                                                                <StandInput
                                                                    value={
                                                                        stands[
                                                                            sheet
                                                                                .machine
                                                                                .id
                                                                        ]?.[
                                                                            row
                                                                                .day
                                                                        ]?.[
                                                                            meter
                                                                        ]
                                                                    }
                                                                    disabled={
                                                                        !can_write
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        setStand(
                                                                            row.day,
                                                                            meter,
                                                                            value,
                                                                        )
                                                                    }
                                                                    className="h-7 border-0 bg-transparent focus:bg-primary/5"
                                                                    label={`Stand akhir ${meter} tanggal ${row.day}`}
                                                                />
                                                            </td>
                                                            <td
                                                                className={cn(
                                                                    'border-r border-b border-border bg-secondary/40 px-2 text-right font-medium tabular-nums',
                                                                    (row.kwh[
                                                                        meter
                                                                    ] ?? 0) <
                                                                        0 &&
                                                                        'text-red-700',
                                                                )}
                                                            >
                                                                {row.kwh[
                                                                    meter
                                                                ] === null
                                                                    ? ''
                                                                    : KWH.format(
                                                                          row
                                                                              .kwh[
                                                                              meter
                                                                          ]!,
                                                                      )}
                                                            </td>
                                                        </Cells>
                                                    ))}
                                                    <td
                                                        className={cn(
                                                            'border-b border-border bg-emerald-50/60 px-2 text-right font-semibold tabular-nums',
                                                            (row.nett ?? 0) <
                                                                0 &&
                                                                'text-red-700',
                                                        )}
                                                    >
                                                        {row.nett === null
                                                            ? ''
                                                            : KWH.format(
                                                                  row.nett,
                                                              )}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-secondary font-semibold">
                                                <td className="border-r border-border px-2 py-1.5 text-center text-[12px]">
                                                    TOT
                                                </td>
                                                <td
                                                    colSpan={2}
                                                    className="border-r border-border"
                                                />
                                                <td className="border-r border-border px-2 py-1.5 text-right tabular-nums">
                                                    {KWH.format(
                                                        totals.produksi,
                                                    )}
                                                </td>
                                                <td
                                                    colSpan={2}
                                                    className="border-r border-border"
                                                />
                                                <td className="border-r border-border px-2 py-1.5 text-right tabular-nums">
                                                    {KWH.format(totals.ps)}
                                                </td>
                                                <td className="px-2 py-1.5 text-right text-primary tabular-nums">
                                                    {KWH.format(
                                                        totals.produksi -
                                                            totals.ps,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>

            {compact && can_write && (
                <StickyActionBar>{saveButton}</StickyActionBar>
            )}
        </>
    );
}

function Cells({ children }: { children: React.ReactNode }) {
    return <>{children}</>;
}

/** A borderless meter-stand cell: digits with an optional decimal part. */
function StandInput({
    value,
    onChange,
    disabled,
    className,
    label,
}: {
    value: string | undefined;
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
            value={value ?? ''}
            disabled={disabled}
            onChange={(e) => {
                const next = e.target.value.replace(/[^\d.,]/g, '');

                if (/^\d*([.,]\d{0,4})?$/.test(next)) {
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
