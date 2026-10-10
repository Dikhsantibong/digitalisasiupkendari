import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Loader2, Save } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import master from '@/routes/operasi/master';
import standMeter from '@/routes/operasi/pengusahaan/stand-meter';

export type MachineParameter = {
    id: number;
    name: string;
    stand_awal_bln_lalu: number;
    faktor_koreksi: number;
    faktor_kali: number;
};

export type DayMachineReading = {
    machine_id: number;
    awal: number;
    akhir: number;
    pemakaian: number;
};

export type DailyStandRow = {
    tgl: number;
    machines: Record<number, DayMachineReading>;
    adm: number;
    real: number;
    selisih: number;
};

export type StandTotals = {
    machines: Record<
        number,
        { awal: number; akhir: number; pemakaian: number }
    >;
    adm: number;
    real: number;
    selisih: number;
};

type Props = {
    unit: { id: number; name: string };
    units: Array<{ id: number; name: string }>;
    filters: { unit_id: number; month: number; year: number; fuel: string };
    fuel_name: string;
    available_fuels: Array<{ code: string; name: string }>;
    machine_parameters: MachineParameter[];
    readings: DailyStandRow[];
    totals: StandTotals;
    catatan: string;
    has_saved: boolean;
    can_manage: boolean;
    can_manage_master: boolean;
};

const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const groupStart = 'border-l-2 border-l-primary/30';

const formatNum = (v: number | string | undefined | null) => {
    const num = Number(v) || 0;

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};

export default function StandMeterIndex({
    unit,
    units,
    filters,
    fuel_name,
    available_fuels,
    machine_parameters: initialMachines = [],
    readings: initialReadings = [],
    catatan: initialCatatan = '',
    has_saved,
    can_manage,
    can_manage_master,
}: Props) {
    const [fuelName, setFuelName] = useState<string>(fuel_name || 'HSD');
    const [machines, setMachines] =
        useState<MachineParameter[]>(initialMachines);
    const [rows, setRows] = useState<DailyStandRow[]>(initialReadings);
    const [catatan, setCatatan] = useState<string>(initialCatatan);
    const [isSaving, setIsSaving] = useState(false);

    // Recalculation Engine implementing:
    // 1. =IF(F17=0; 0; F16) (Stand Awal = Akhir Kemarin jika mesin operasi)
    // 2. =D13+G13+J13+M13+P13 (ADM = Total Pemakaian Seluruh Mesin)
    // 3. =AP13-AO13 (Selisih = Real - ADM)
    const recalculateData = (
        machList: MachineParameter[],
        currentRows: DailyStandRow[],
    ) => {
        const runningAkhir: Record<number, number> = {};
        const machineTotals: Record<
            number,
            { awal: number; akhir: number; pemakaian: number }
        > = {};

        machList.forEach((m) => {
            const startVal = Number(m.stand_awal_bln_lalu) || 0;
            runningAkhir[m.id] = startVal;
            machineTotals[m.id] = { awal: startVal, akhir: 0, pemakaian: 0 };
        });

        let totalAdm = 0;
        let totalReal = 0;

        const updatedRows = currentRows.map((row) => {
            const day = row.tgl;
            let dailyPemakaian = 0;
            const updatedMachines: Record<number, DayMachineReading> = {};

            machList.forEach((m) => {
                const currentMach = row.machines?.[m.id] || {
                    machine_id: m.id,
                    awal: 0,
                    akhir: 0,
                    pemakaian: 0,
                };
                const akhir = Number(currentMach.akhir) || 0;

                // Formula 1: =IF(F17=0; 0; F16)
                let awal = 0;

                if (day === 1) {
                    awal = Number(m.stand_awal_bln_lalu) || 0;
                } else {
                    awal = akhir === 0 ? 0 : runningAkhir[m.id] || 0;
                }

                if (akhir > 0) {
                    runningAkhir[m.id] = akhir;
                    machineTotals[m.id].akhir = akhir;
                }

                // Pemakaian = (akhir - awal) * faktor_kali * faktor_koreksi
                let pemakaian = 0;

                if (akhir > 0 && awal > 0 && akhir >= awal) {
                    const diff = akhir - awal;
                    pemakaian =
                        Math.round(
                            diff *
                                (Number(m.faktor_kali) || 1) *
                                (Number(m.faktor_koreksi) || 1) *
                                100,
                        ) / 100;
                }

                updatedMachines[m.id] = {
                    machine_id: m.id,
                    awal: Math.round(awal * 100) / 100,
                    akhir: Math.round(akhir * 100) / 100,
                    pemakaian,
                };

                dailyPemakaian += pemakaian;
                machineTotals[m.id].pemakaian += pemakaian;
            });

            // Formula 2: ADM = =D13+G13+J13+M13+P13
            const adm = Math.round(dailyPemakaian * 100) / 100;
            const real = Number(row.real) || 0;
            // Formula 3: SELISIH = =AP13-AO13
            const selisih = Math.round((real - adm) * 100) / 100;

            totalAdm += adm;
            totalReal += real;

            return {
                ...row,
                machines: updatedMachines,
                adm,
                real,
                selisih,
            };
        });

        const formattedTotals: Record<
            number,
            { awal: number; akhir: number; pemakaian: number }
        > = {};
        machList.forEach((m) => {
            formattedTotals[m.id] = {
                awal: Math.round(machineTotals[m.id].awal * 100) / 100,
                akhir: Math.round(machineTotals[m.id].akhir * 100) / 100,
                pemakaian:
                    Math.round(machineTotals[m.id].pemakaian * 100) / 100,
            };
        });

        const computedTotals: StandTotals = {
            machines: formattedTotals,
            adm: Math.round(totalAdm * 100) / 100,
            real: Math.round(totalReal * 100) / 100,
            selisih: Math.round((totalReal - totalAdm) * 100) / 100,
        };

        return { rows: updatedRows, totals: computedTotals };
    };

    const computedTotals = useMemo<StandTotals>(() => {
        return recalculateData(machines, rows).totals;
    }, [machines, rows]);

    const handleUpdateMachine = (
        machId: number,
        field: keyof MachineParameter,
        val: string | number,
    ) => {
        const next = machines.map((m) => {
            if (m.id === machId) {
                return { ...m, [field]: Number(val) || 0 };
            }

            return m;
        });
        setMachines(next);
        const { rows: recalculated } = recalculateData(next, rows);
        setRows(recalculated);
    };

    const handleUpdateCell = (
        tgl: number,
        machId: number,
        val: string | number,
    ) => {
        const nextRows = rows.map((r) => {
            if (r.tgl === tgl) {
                const current = r.machines?.[machId] || {
                    machine_id: machId,
                    awal: 0,
                    akhir: 0,
                    pemakaian: 0,
                };

                return {
                    ...r,
                    machines: {
                        ...r.machines,
                        [machId]: {
                            ...current,
                            akhir: Number(val) || 0,
                        },
                    },
                };
            }

            return r;
        });
        const { rows: recalculated } = recalculateData(machines, nextRows);
        setRows(recalculated);
    };

    const handleUpdateReal = (tgl: number, val: string | number) => {
        const nextRows = rows.map((r) => {
            if (r.tgl === tgl) {
                const real = Number(val) || 0;
                const selisih = Math.round((real - r.adm) * 100) / 100;

                return {
                    ...r,
                    real,
                    selisih,
                };
            }

            return r;
        });
        setRows(nextRows);
    };

    const handleFuelChange = (newFuel: string) => {
        setFuelName(newFuel);
        router.get(standMeter.index().url, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
            fuel: newFuel,
        });
    };

    const handleUnitChange = (newUnitId: string) => {
        router.get(standMeter.index().url, {
            unit_id: newUnitId,
            month: filters.month,
            year: filters.year,
            fuel: fuelName,
        });
    };

    const handleMonthChange = (newMonth: string) => {
        router.get(standMeter.index().url, {
            unit_id: filters.unit_id,
            month: newMonth,
            year: filters.year,
            fuel: fuelName,
        });
    };

    const handleYearChange = (newYear: string) => {
        router.get(standMeter.index().url, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: newYear,
            fuel: fuelName,
        });
    };

    const handleSave = () => {
        setIsSaving(true);
        router.post(
            standMeter.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                fuel_name: fuelName,
                machine_parameters: machines,
                readings: rows,
                totals: computedTotals,
                catatan,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    };

    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
        fuel: fuelName,
    };
    const unitTotal = Object.values(computedTotals.machines || {}).reduce(
        (acc, item) => acc + (Number(item?.pemakaian) || 0),
        0,
    );
    const selisihText = (value: number) =>
        value < 0
            ? `(${formatNum(Math.abs(value))})`
            : value > 0
              ? `+${formatNum(value)}`
              : '-';

    return (
        <>
            <Head title={`Stand Flow Meter BBM ${fuelName} - ${unit.name}`} />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Stand Flow Meter BBM (${fuelName})`}
                    description={`Pencatatan harian stand meter BBM generator, pemakaian terkalibrasi, administrasi dan selisih terhadap realisasi sounding — ${unit.name}.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={standMeter.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        standMeter.pdf({
                                            query: { ...query, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh
                                </a>
                            </Button>
                            {can_manage && (
                                <Button
                                    onClick={handleSave}
                                    disabled={isSaving}
                                >
                                    {isSaving ? (
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
                        onChange={handleUnitChange}
                        options={units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={handleMonthChange}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={handleYearChange}
                        options={YEARS.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    {available_fuels.length > 0 && (
                        <OperasiSelect
                            label="Jenis BBM"
                            className="w-56"
                            value={fuelName}
                            onChange={handleFuelChange}
                            options={available_fuels.map((f) => ({
                                value: f.code,
                                label: `${f.name} (${f.code})`,
                            }))}
                        />
                    )}
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {has_saved ? 'Tersimpan' : 'Belum pernah disimpan'}
                    </p>
                </div>

                {available_fuels.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada jenis BBM untuk unit ini"
                            description="Jenis BBM diambil dari Tangki BBM aktif unit (Data Master Operasi → Tangki BBM)."
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
                                label="Total pemakaian unit"
                                value={formatNum(unitTotal)}
                                unit="Liter"
                                hint={`${machines.length} mesin`}
                            />
                            <SummaryCard
                                label="Administrasi (ADM)"
                                value={formatNum(computedTotals.adm)}
                                unit="Liter"
                            />
                            <SummaryCard
                                label="Realisasi (Real)"
                                value={formatNum(computedTotals.real)}
                                unit="Liter"
                            />
                            <SummaryCard
                                label="Selisih (Real − ADM)"
                                value={selisihText(computedTotals.selisih)}
                                unit="Liter"
                            />
                        </div>

                        <p className="text-[13px] text-muted-foreground">
                            Pemakaian = (Akhir − Awal) × F. Koreksi × F. Kali ·
                            Awal = akhir hari sebelumnya (kosong bila akhir hari
                            ini kosong) · ADM = jumlah pemakaian seluruh mesin ·
                            Selisih = Real − ADM.
                        </p>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="border-b border-border px-4 py-2.5 text-sm font-semibold text-foreground">
                                Parameter mesin & stand awal bulan lalu
                            </div>
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="bg-secondary text-[12px] text-foreground">
                                            <th className="border-r border-b border-border px-3 py-2 text-left font-semibold">
                                                Mesin
                                            </th>
                                            <th className="w-48 border-r border-b border-border px-3 py-2 text-right font-semibold">
                                                Stand awal bulan lalu
                                            </th>
                                            <th className="w-36 border-r border-b border-border px-3 py-2 text-right font-semibold">
                                                F. Koreksi
                                            </th>
                                            <th className="w-36 border-b border-border px-3 py-2 text-right font-semibold">
                                                F. Kali
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {machines.map((m) => (
                                            <tr
                                                key={m.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="border-r border-b border-border px-3 py-1.5 font-medium text-foreground">
                                                    {m.name}
                                                </td>
                                                {(
                                                    [
                                                        [
                                                            'stand_awal_bln_lalu',
                                                            'any',
                                                        ],
                                                        [
                                                            'faktor_koreksi',
                                                            '0.0000001',
                                                        ],
                                                        ['faktor_kali', '0.1'],
                                                    ] as const
                                                ).map(([field, step]) => (
                                                    <td
                                                        key={field}
                                                        className="border-r border-b border-border p-0 last:border-r-0"
                                                    >
                                                        <NumberCell
                                                            value={m[field]}
                                                            step={step}
                                                            disabled={
                                                                !can_manage
                                                            }
                                                            label={`${field} ${m.name}`}
                                                            onChange={(value) =>
                                                                handleUpdateMachine(
                                                                    m.id,
                                                                    field,
                                                                    value,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="border-b border-border px-4 py-2.5">
                                <p className="text-sm font-semibold text-foreground">
                                    Stand meter harian (1 – {rows.length})
                                </p>
                                <p className="text-[12px] text-muted-foreground">
                                    Isi stand <b>AKHIR</b> per mesin dan{' '}
                                    <b>Real</b> pemakaian fisik; kolom lain
                                    terhitung otomatis.
                                </p>
                            </div>
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="bg-secondary text-foreground">
                                            <th
                                                rowSpan={2}
                                                className="sticky left-0 z-20 w-14 border-r border-b border-border bg-secondary px-2 py-2 text-center text-[12px] font-semibold"
                                            >
                                                TGL
                                            </th>
                                            {machines.map((m, index) => (
                                                <th
                                                    key={m.id}
                                                    colSpan={3}
                                                    className={cn(
                                                        'border-r border-b border-border px-2 py-2 text-center text-[13px] font-semibold',
                                                        index > 0 && groupStart,
                                                    )}
                                                >
                                                    {m.name}
                                                </th>
                                            ))}
                                            {[
                                                `TOTAL ${unit.name.toUpperCase()}`,
                                                'ADM',
                                                'REAL',
                                                'SELISIH',
                                            ].map((label, index) => (
                                                <th
                                                    key={label}
                                                    rowSpan={2}
                                                    className={cn(
                                                        'min-w-28 border-b border-border bg-secondary px-2 py-2 text-center text-[12px] font-semibold',
                                                        index === 0
                                                            ? groupStart
                                                            : 'border-l',
                                                    )}
                                                >
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                        <tr className="text-[12px] text-muted-foreground">
                                            {machines.map((m, index) => (
                                                <React.Fragment key={m.id}>
                                                    <th
                                                        className={cn(
                                                            'min-w-24 border-r border-b border-border px-2 py-1 font-normal',
                                                            index > 0 &&
                                                                groupStart,
                                                        )}
                                                    >
                                                        AWAL
                                                    </th>
                                                    <th className="min-w-28 border-r border-b border-border px-2 py-1 font-semibold text-foreground">
                                                        AKHIR
                                                    </th>
                                                    <th className="min-w-24 border-r border-b border-border bg-secondary/60 px-2 py-1 font-normal">
                                                        PEMAKAIAN
                                                    </th>
                                                </React.Fragment>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        machines.length * 3 + 5
                                                    }
                                                    className="p-6 text-center text-muted-foreground"
                                                >
                                                    Tidak ada data hari untuk
                                                    bulan ini.
                                                </td>
                                            </tr>
                                        )}
                                        {rows.map((row) => {
                                            const dayMachs = row.machines || {};
                                            const dailyUnitSum =
                                                Math.round(
                                                    machines.reduce(
                                                        (acc, m) =>
                                                            acc +
                                                            (Number(
                                                                dayMachs[m.id]
                                                                    ?.pemakaian,
                                                            ) || 0),
                                                        0,
                                                    ) * 100,
                                                ) / 100;

                                            return (
                                                <tr
                                                    key={row.tgl}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="sticky left-0 z-10 border-r border-b border-border bg-card px-2 text-center text-[12px] font-medium text-muted-foreground tabular-nums">
                                                        {row.tgl}
                                                    </td>
                                                    {machines.map(
                                                        (m, index) => {
                                                            const mDay =
                                                                dayMachs[
                                                                    m.id
                                                                ] || {
                                                                    awal: 0,
                                                                    akhir: 0,
                                                                    pemakaian: 0,
                                                                };

                                                            return (
                                                                <React.Fragment
                                                                    key={m.id}
                                                                >
                                                                    <td
                                                                        className={cn(
                                                                            'border-r border-b border-border px-2 text-right text-muted-foreground tabular-nums',
                                                                            index >
                                                                                0 &&
                                                                                groupStart,
                                                                        )}
                                                                    >
                                                                        {mDay.awal >
                                                                        0
                                                                            ? formatNum(
                                                                                  mDay.awal,
                                                                              )
                                                                            : '-'}
                                                                    </td>
                                                                    <td className="border-r border-b border-border p-0">
                                                                        <NumberCell
                                                                            value={
                                                                                mDay.akhir
                                                                            }
                                                                            disabled={
                                                                                !can_manage
                                                                            }
                                                                            label={`Akhir ${m.name} tanggal ${row.tgl}`}
                                                                            onChange={(
                                                                                value,
                                                                            ) =>
                                                                                handleUpdateCell(
                                                                                    row.tgl,
                                                                                    m.id,
                                                                                    value,
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td className="border-r border-b border-border bg-secondary/60 px-2 text-right font-medium text-foreground tabular-nums">
                                                                        {mDay.pemakaian >
                                                                        0
                                                                            ? formatNum(
                                                                                  mDay.pemakaian,
                                                                              )
                                                                            : '-'}
                                                                    </td>
                                                                </React.Fragment>
                                                            );
                                                        },
                                                    )}
                                                    <td
                                                        className={cn(
                                                            'border-b border-border bg-secondary/60 px-2 text-right font-semibold text-foreground tabular-nums',
                                                            groupStart,
                                                        )}
                                                    >
                                                        {dailyUnitSum > 0
                                                            ? formatNum(
                                                                  dailyUnitSum,
                                                              )
                                                            : '-'}
                                                    </td>
                                                    <td className="border-b border-l border-border px-2 text-right font-medium tabular-nums">
                                                        {row.adm > 0
                                                            ? formatNum(row.adm)
                                                            : '-'}
                                                    </td>
                                                    <td className="border-b border-l border-border p-0">
                                                        <NumberCell
                                                            value={row.real}
                                                            disabled={
                                                                !can_manage
                                                            }
                                                            label={`Real tanggal ${row.tgl}`}
                                                            onChange={(value) =>
                                                                handleUpdateReal(
                                                                    row.tgl,
                                                                    value,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            'border-b border-l border-border px-2 text-right font-medium tabular-nums',
                                                            row.selisih < 0
                                                                ? 'text-destructive'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {selisihText(
                                                            row.selisih,
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {rows.length > 0 && (
                                            <tr className="bg-secondary font-semibold text-foreground">
                                                <td className="sticky left-0 z-10 border-r border-border bg-secondary px-2 py-1.5 text-center text-[12px]">
                                                    JMH
                                                </td>
                                                {machines.map((m, index) => {
                                                    const mTot = computedTotals
                                                        .machines?.[m.id] || {
                                                        awal: 0,
                                                        akhir: 0,
                                                        pemakaian: 0,
                                                    };

                                                    return (
                                                        <React.Fragment
                                                            key={m.id}
                                                        >
                                                            <td
                                                                className={cn(
                                                                    'border-r border-border px-2 py-1.5 text-right font-normal text-muted-foreground tabular-nums',
                                                                    index > 0 &&
                                                                        groupStart,
                                                                )}
                                                            >
                                                                {mTot.awal > 0
                                                                    ? formatNum(
                                                                          mTot.awal,
                                                                      )
                                                                    : '-'}
                                                            </td>
                                                            <td className="border-r border-border px-2 py-1.5 text-right font-normal text-muted-foreground tabular-nums">
                                                                {mTot.akhir > 0
                                                                    ? formatNum(
                                                                          mTot.akhir,
                                                                      )
                                                                    : '-'}
                                                            </td>
                                                            <td className="border-r border-border px-2 py-1.5 text-right tabular-nums">
                                                                {mTot.pemakaian >
                                                                0
                                                                    ? formatNum(
                                                                          mTot.pemakaian,
                                                                      )
                                                                    : '-'}
                                                            </td>
                                                        </React.Fragment>
                                                    );
                                                })}
                                                <td
                                                    className={cn(
                                                        'px-2 py-1.5 text-right text-primary tabular-nums',
                                                        groupStart,
                                                    )}
                                                >
                                                    {formatNum(unitTotal)}
                                                </td>
                                                <td className="border-l border-border px-2 py-1.5 text-right tabular-nums">
                                                    {formatNum(
                                                        computedTotals.adm,
                                                    )}
                                                </td>
                                                <td className="border-l border-border px-2 py-1.5 text-right tabular-nums">
                                                    {formatNum(
                                                        computedTotals.real,
                                                    )}
                                                </td>
                                                <td
                                                    className={cn(
                                                        'border-l border-border px-2 py-1.5 text-right tabular-nums',
                                                        computedTotals.selisih <
                                                            0 &&
                                                            'text-destructive',
                                                    )}
                                                >
                                                    {selisihText(
                                                        computedTotals.selisih,
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
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
                        value={catatan}
                        onChange={(e) => setCatatan(e.target.value)}
                        readOnly={!can_manage}
                        placeholder={
                            can_manage
                                ? 'Catatan teknis stand meter (kalibrasi flowmeter, penggantian meter, anomali aliran BBM, dsb.)'
                                : 'Tidak ada catatan'
                        }
                    />
                </section>
            </div>
        </>
    );
}

/** A borderless number cell; an empty cell is 0. */
function NumberCell({
    value,
    onChange,
    disabled,
    label,
    step = 'any',
}: {
    value: number;
    onChange: (value: string) => void;
    disabled: boolean;
    label: string;
    step?: string;
}) {
    return (
        <input
            type="number"
            step={step}
            min={0}
            aria-label={label}
            value={value === 0 ? '' : value}
            placeholder="0"
            disabled={disabled}
            onChange={(e) => onChange(e.target.value)}
            className="h-7 w-full [appearance:textfield] border-0 bg-transparent px-2 text-right tabular-nums outline-none placeholder:text-muted-foreground/50 focus:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-default [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
        />
    );
}
