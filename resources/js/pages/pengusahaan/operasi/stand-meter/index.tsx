import { Head, Link, router } from '@inertiajs/react';
import { Download, Loader2, Save, SlidersHorizontal } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const MONTHS = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

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

    const pdfUrl = `/operasi/pengusahaan/stand-meter/pdf?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}&fuel=${encodeURIComponent(fuelName)}`;

    return (
        <div className="space-y-6">
            <Head title={`Stand Flow Meter BBM ${fuelName} - ${unit.name}`} />

            <PageHeader
                title={`Stand Flow Meter BBM (${fuelName})`}
                description="Pencatatan harian stand meter BBM generator, perhitungan pemakaian terkalibrasi, administrasi, dan selisih terhadap realisasi sounding."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge tone={has_saved ? 'success' : 'neutral'}>
                            {has_saved ? 'Tersimpan' : 'Draft / Baru'}
                        </StatusBadge>
                        <a
                            href={pdfUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button
                                size="sm"
                                variant="outline"
                                className="h-9 gap-1.5 text-xs"
                            >
                                <Download className="size-3.5" />
                                Cetak PDF
                            </Button>
                        </a>
                        {can_manage && (
                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={isSaving}
                                className="h-9 gap-1.5 text-xs"
                            >
                                {isSaving ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Save className="size-3.5" />
                                )}
                                Simpan Data
                            </Button>
                        )}
                    </div>
                }
            />

            {/* Sub-header Filter Toolbar */}
            <div className="space-y-4 rounded-lg border border-border bg-card p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* Unit Filter */}
                        <div className="space-y-1">
                            <Label className="text-[11px] font-semibold text-muted-foreground">
                                Unit Pembangkit
                            </Label>
                            <Select
                                value={String(filters.unit_id)}
                                onValueChange={handleUnitChange}
                            >
                                <SelectTrigger className="h-8 w-[180px] text-xs font-semibold">
                                    <SelectValue placeholder="Pilih Unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((u) => (
                                        <SelectItem
                                            key={u.id}
                                            value={String(u.id)}
                                            className="text-xs"
                                        >
                                            {u.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Month Filter */}
                        <div className="space-y-1">
                            <Label className="text-[11px] font-semibold text-muted-foreground">
                                Bulan
                            </Label>
                            <Select
                                value={String(filters.month)}
                                onValueChange={handleMonthChange}
                            >
                                <SelectTrigger className="h-8 w-[130px] text-xs font-semibold">
                                    <SelectValue placeholder="Bulan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {MONTHS.map((m) => (
                                        <SelectItem
                                            key={m.value}
                                            value={String(m.value)}
                                            className="text-xs"
                                        >
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Year Filter */}
                        <div className="space-y-1">
                            <Label className="text-[11px] font-semibold text-muted-foreground">
                                Tahun
                            </Label>
                            <Select
                                value={String(filters.year)}
                                onValueChange={handleYearChange}
                            >
                                <SelectTrigger className="h-8 w-[100px] text-xs font-semibold">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        filters.year - 1,
                                        filters.year,
                                        filters.year + 1,
                                    ].map((y) => (
                                        <SelectItem
                                            key={y}
                                            value={String(y)}
                                            className="text-xs"
                                        >
                                            {y}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Fuel Selector */}
                        <div className="space-y-1">
                            <Label className="text-[11px] font-semibold text-muted-foreground">
                                Jenis Bahan Bakar
                            </Label>
                            <Select
                                value={fuelName}
                                onValueChange={handleFuelChange}
                            >
                                <SelectTrigger className="h-8 w-[180px] text-xs font-semibold">
                                    <SelectValue placeholder="Pilih BBM" />
                                </SelectTrigger>
                                <SelectContent>
                                    {available_fuels.map((f) => (
                                        <SelectItem
                                            key={f.code}
                                            value={f.code}
                                            className="text-xs"
                                        >
                                            {f.name} ({f.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* Formula Legend */}
                <div className="flex flex-wrap items-center gap-2 border-t border-border pt-2 text-xs">
                    <span className="text-[11px] font-semibold text-muted-foreground">
                        Rumus Perhitungan:
                    </span>
                    <Badge
                        variant="outline"
                        className="bg-muted/40 font-mono text-[11px]"
                    >
                        Stand Awal = IF(Akhir=0, 0, Akhir Kemarin)
                    </Badge>
                    <Badge
                        variant="outline"
                        className="bg-muted/40 font-mono text-[11px]"
                    >
                        Pemakaian = (Akhir - Awal) × F. Koreksi × F. Kali
                    </Badge>
                    <Badge
                        variant="outline"
                        className="bg-muted/40 font-mono text-[11px]"
                    >
                        ADM = ∑ Pemakaian Seluruh Mesin
                    </Badge>
                    <Badge
                        variant="outline"
                        className="bg-muted/40 font-mono text-[11px]"
                    >
                        Selisih = Real - ADM
                    </Badge>
                </div>
            </div>

            {available_fuels.length === 0 && (
                <div className="flex flex-wrap items-center gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
                    <span className="flex-1">
                        Unit ini belum punya jenis BBM di Data Master. Jenis BBM
                        diambil dari Tangki BBM aktif unit dan BBM mesin (Master
                        Mesin).
                    </span>
                    {can_manage_master && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={master.index('fuel-tanks').url}>
                                Buka Tangki BBM
                            </Link>
                        </Button>
                    )}
                </div>
            )}

            {/* Section 2: Machine Parameters Configuration Card */}
            <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                <div className="flex items-center justify-between">
                    <h4 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <SlidersHorizontal className="size-4 text-primary" />
                        Parameter Mesin &amp; Stand Awal Bulan Lalu
                    </h4>
                    <span className="text-[11px] text-muted-foreground">
                        {machines.length} Mesin Terdaftar
                    </span>
                </div>

                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    {machines.map((m) => (
                        <div
                            key={m.id}
                            className="space-y-2 rounded-md border border-border bg-muted/20 p-3"
                        >
                            <div className="border-b border-border pb-1 text-xs font-semibold text-foreground">
                                {m.name}
                            </div>
                            <div className="space-y-1.5 text-xs">
                                <div>
                                    <Label className="text-[11px] text-muted-foreground">
                                        Stand Awal Bln Lalu
                                    </Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        value={m.stand_awal_bln_lalu}
                                        onChange={(e) =>
                                            handleUpdateMachine(
                                                m.id,
                                                'stand_awal_bln_lalu',
                                                e.target.value,
                                            )
                                        }
                                        className="h-7 text-right font-mono text-xs"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-1.5">
                                    <div>
                                        <Label className="text-[11px] text-muted-foreground">
                                            F. Koreksi
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.0000001"
                                            value={m.faktor_koreksi}
                                            onChange={(e) =>
                                                handleUpdateMachine(
                                                    m.id,
                                                    'faktor_koreksi',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-7 text-right font-mono text-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-[11px] text-muted-foreground">
                                            F. Kali
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.1"
                                            value={m.faktor_kali}
                                            onChange={(e) =>
                                                handleUpdateMachine(
                                                    m.id,
                                                    'faktor_kali',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-7 text-right font-mono text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Section 3: Daily Stand Meter Table */}
            <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                <div>
                    <h4 className="text-sm font-semibold text-foreground">
                        Tabel Stand Meter Harian (1 - {rows.length})
                    </h4>
                    <p className="text-[12px] text-muted-foreground">
                        Masukkan nilai <strong>AKHIR</strong> stand flowmeter
                        dan <strong>Real</strong> pemakaian fisik. Nilai AWAL,
                        PEMAKAIAN, TOTAL, ADM, dan SELISIH terhitung otomatis
                        secara real-time.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full min-w-[900px] border-collapse text-left text-xs">
                        <thead className="border-b border-border bg-muted/60 text-[11px] font-semibold text-foreground">
                            <tr>
                                <th
                                    rowSpan={2}
                                    className="w-10 border-r border-border p-2 text-center"
                                >
                                    TGL
                                </th>
                                {machines.map((m) => (
                                    <th
                                        key={m.id}
                                        colSpan={3}
                                        className="border-r border-border bg-muted/40 p-2 text-center font-bold"
                                    >
                                        {m.name}
                                    </th>
                                ))}
                                <th
                                    rowSpan={2}
                                    className="min-w-[90px] border-r border-border bg-amber-500/10 p-2 text-right font-bold text-amber-900 dark:text-amber-200"
                                >
                                    TOTAL {unit.name}
                                </th>
                                <th
                                    rowSpan={2}
                                    className="min-w-[85px] border-r border-border bg-blue-500/10 p-2 text-right font-bold text-blue-900 dark:text-blue-200"
                                >
                                    ADM
                                </th>
                                <th
                                    rowSpan={2}
                                    className="min-w-[90px] border-r border-border bg-muted/30 p-2 text-right font-bold"
                                >
                                    Real
                                </th>
                                <th
                                    rowSpan={2}
                                    className="min-w-[85px] bg-rose-500/10 p-2 text-right font-bold text-rose-900 dark:text-rose-200"
                                >
                                    SELISIH
                                </th>
                            </tr>
                            <tr className="border-t border-border bg-muted/30 text-[10px]">
                                {machines.map((m) => (
                                    <React.Fragment key={m.id}>
                                        <th className="w-[75px] border-r border-border p-1 text-center font-medium">
                                            AWAL
                                        </th>
                                        <th className="w-[85px] border-r border-border p-1 text-center font-medium">
                                            AKHIR
                                        </th>
                                        <th className="w-[85px] border-r border-border bg-amber-400/20 p-1 text-center font-bold text-amber-950 dark:text-amber-100">
                                            PEMAKAIAN
                                        </th>
                                    </React.Fragment>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border font-mono text-xs">
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={machines.length * 3 + 5}
                                        className="p-6 text-center font-sans text-muted-foreground"
                                    >
                                        Tidak ada data hari untuk bulan ini.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => {
                                    const dayMachs = row.machines || {};
                                    let dailyUnitSum = 0;
                                    machines.forEach((m) => {
                                        dailyUnitSum +=
                                            Number(dayMachs[m.id]?.pemakaian) ||
                                            0;
                                    });
                                    dailyUnitSum =
                                        Math.round(dailyUnitSum * 100) / 100;

                                    return (
                                        <tr
                                            key={row.tgl}
                                            className="hover:bg-muted/10"
                                        >
                                            <td className="border-r border-border p-1.5 text-center font-sans font-bold text-muted-foreground">
                                                {row.tgl}
                                            </td>
                                            {machines.map((m) => {
                                                const mDay = dayMachs[m.id] || {
                                                    awal: 0,
                                                    akhir: 0,
                                                    pemakaian: 0,
                                                };

                                                return (
                                                    <React.Fragment key={m.id}>
                                                        <td className="border-r border-border p-1.5 text-right text-[11px] text-muted-foreground">
                                                            {mDay.awal > 0
                                                                ? formatNum(
                                                                      mDay.awal,
                                                                  )
                                                                : '-'}
                                                        </td>
                                                        <td className="border-r border-border p-1">
                                                            <Input
                                                                type="number"
                                                                step="any"
                                                                value={
                                                                    mDay.akhir ===
                                                                    0
                                                                        ? ''
                                                                        : mDay.akhir
                                                                }
                                                                onChange={(e) =>
                                                                    handleUpdateCell(
                                                                        row.tgl,
                                                                        m.id,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="0"
                                                                className="h-7 text-right font-mono text-xs"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border bg-amber-400/10 p-1.5 text-right font-semibold text-foreground">
                                                            {mDay.pemakaian > 0
                                                                ? formatNum(
                                                                      mDay.pemakaian,
                                                                  )
                                                                : '-'}
                                                        </td>
                                                    </React.Fragment>
                                                );
                                            })}
                                            <td className="border-r border-border bg-amber-500/10 p-1.5 text-right font-bold text-foreground">
                                                {dailyUnitSum > 0
                                                    ? formatNum(dailyUnitSum)
                                                    : '-'}
                                            </td>
                                            <td className="border-r border-border bg-blue-500/10 p-1.5 text-right font-bold text-foreground">
                                                {row.adm > 0
                                                    ? formatNum(row.adm)
                                                    : '-'}
                                            </td>
                                            <td className="border-r border-border p-1">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={
                                                        row.real === 0
                                                            ? ''
                                                            : row.real
                                                    }
                                                    onChange={(e) =>
                                                        handleUpdateReal(
                                                            row.tgl,
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="0"
                                                    className="h-7 text-right font-mono text-xs"
                                                />
                                            </td>
                                            <td
                                                className={`p-1.5 text-right font-bold ${
                                                    row.selisih < 0
                                                        ? 'bg-rose-500/10 font-semibold text-destructive'
                                                        : row.selisih > 0
                                                          ? 'bg-rose-500/10 text-foreground'
                                                          : 'text-muted-foreground'
                                                }`}
                                            >
                                                {row.selisih < 0
                                                    ? `(${formatNum(Math.abs(row.selisih))})`
                                                    : row.selisih > 0
                                                      ? `+${formatNum(row.selisih)}`
                                                      : '-'}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}

                            {/* Summary TOT Row */}
                            {rows.length > 0 && (
                                <tr className="border-t-2 border-border bg-muted/50 font-bold text-foreground">
                                    <td className="border-r border-border p-2 text-center font-sans font-bold tracking-wide">
                                        TOT
                                    </td>
                                    {machines.map((m) => {
                                        const mTot = computedTotals.machines?.[
                                            m.id
                                        ] || {
                                            awal: 0,
                                            akhir: 0,
                                            pemakaian: 0,
                                        };

                                        return (
                                            <React.Fragment key={m.id}>
                                                <td className="border-r border-border p-2 text-right text-[11px]">
                                                    {mTot.awal > 0
                                                        ? formatNum(mTot.awal)
                                                        : '-'}
                                                </td>
                                                <td className="border-r border-border p-2 text-right text-[11px]">
                                                    {mTot.akhir > 0
                                                        ? formatNum(mTot.akhir)
                                                        : '-'}
                                                </td>
                                                <td className="border-r border-border bg-amber-400/20 p-2 text-right font-bold text-foreground">
                                                    {mTot.pemakaian > 0
                                                        ? formatNum(
                                                              mTot.pemakaian,
                                                          )
                                                        : '-'}
                                                </td>
                                            </React.Fragment>
                                        );
                                    })}
                                    <td className="border-r border-border bg-amber-500/20 p-2 text-right font-bold text-foreground">
                                        {formatNum(
                                            Object.values(
                                                computedTotals.machines || {},
                                            ).reduce(
                                                (acc, item) =>
                                                    acc +
                                                    (Number(item?.pemakaian) ||
                                                        0),
                                                0,
                                            ),
                                        )}
                                    </td>
                                    <td className="border-r border-border bg-blue-500/20 p-2 text-right font-bold text-foreground">
                                        {formatNum(computedTotals.adm)}
                                    </td>
                                    <td className="border-r border-border bg-muted/40 p-2 text-right font-bold text-foreground">
                                        {formatNum(computedTotals.real)}
                                    </td>
                                    <td
                                        className={`bg-rose-500/20 p-2 text-right font-bold ${
                                            computedTotals.selisih < 0
                                                ? 'text-destructive'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {computedTotals.selisih < 0
                                            ? `(${formatNum(Math.abs(computedTotals.selisih))})`
                                            : computedTotals.selisih > 0
                                              ? `+${formatNum(computedTotals.selisih)}`
                                              : '0,00'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Section 4: Catatan Stand Meter */}
            <div className="space-y-2 rounded-lg border border-border bg-card p-4 text-xs">
                <Label className="font-semibold text-foreground">
                    Catatan: * Keterangan atau Catatan Tambahan:
                </Label>
                <textarea
                    rows={3}
                    value={catatan}
                    onChange={(e) => setCatatan(e.target.value)}
                    placeholder="Tuliskan catatan teknis stand meter bila ada (misal: kalibrasi flowmeter, penggantian unit meter, anomali aliran bahan bakar, dsb.)..."
                    className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                />
            </div>
        </div>
    );
}
