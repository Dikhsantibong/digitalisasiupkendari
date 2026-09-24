import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    Factory,
    FlaskConical,
    Info,
    Package,
    ShieldCheck,
    Users,
    Wrench,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    BarList,
    BarsChart,
    Donut,
    formatValue,
    Gauge,
    Heatmap,
    LevelBars,
    LinesChart,
    Panel,
    Ring,
    SCurve,
    Sparkline,
} from '@/components/dashboard/charts';
import type {
    Datum,
    MultiSeries,
    ValueFormat,
} from '@/components/dashboard/charts';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import units from '@/routes/admin/units';
import type { Tone } from '@/types';

type ModuleKey = 'operasi' | 'har' | 'k3' | 'logistik' | 'pdm';

type Kpi = {
    key: string;
    label: string;
    value: number;
    unit: string | null;
    format: ValueFormat;
    delta: number;
    good: 'up' | 'down';
    spark: number[];
};

type Status = { status: string; status_tone: Tone };

type Props = {
    scope: Record<ModuleKey | 'units', boolean>;
    isDummy: boolean;
    periodLabel: string;
    summary: {
        units: number;
        serviceUnits: number;
        activeUnits: number;
        installedCapacity: number;
        users: number | null;
    };
    moduleHealth: {
        key: ModuleKey;
        label: string;
        value: number;
        caption: string;
    }[];
    moduleActivity: Datum[];
    operasi: {
        kpis: Kpi[];
        daily: MultiSeries;
        engine_status: Datum[];
        gauges: {
            label: string;
            value: number;
            target: number;
            good: 'up' | 'down';
        }[];
        fuel_monthly: Datum[];
        units: ({
            unit: string;
            daya_mampu: number;
            beban_puncak: number;
            produksi: number;
            sfc: number;
            eaf: number;
        } & Status)[];
    } | null;
    har: {
        kpis: Kpi[];
        trend: MultiSeries;
        cost: MultiSeries;
        by_type: Datum[];
        by_status: Datum[];
        backlog: {
            code: string;
            unit: string;
            asset: string;
            task: string;
            priority: string;
            priority_tone: Tone;
            status: string;
            age: number;
        }[];
    } | null;
    k3: {
        kpis: Kpi[];
        s_curve: {
            month_label: string;
            points: { day: number; plan: number; real: number | null }[];
        };
        cert_status: Datum[];
        apar_status: Datum[];
        patrol_top: Datum[];
        compliance: {
            columns: string[];
            rows: { label: string; values: number[] }[];
        };
    } | null;
    logistik: {
        kpis: Kpi[];
        tanks: (Datum & { capacity: number })[];
        fuel_flow: MultiSeries;
        category: Datum[];
        critical: {
            code: string;
            name: string;
            unit: string;
            stock: number;
            min: number;
            uom: string;
        }[];
    } | null;
    pdm: {
        kpis: Kpi[];
        realisasi: MultiSeries;
        condition: Datum[];
        vibration: MultiSeries & { threshold: number };
        assets: ({
            asset: string;
            vibration: number;
            temperature: number;
            oil: string;
        } & Status)[];
    } | null;
    statusBreakdown: {
        status: string;
        label: string;
        tone: Tone;
        total: number;
    }[];
    units: {
        id: number;
        name: string;
        code: string;
        type: string;
        status_label: string;
        status_tone: Tone;
        service_unit: string | null;
        installed_capacity_mw: string | null;
    }[];
    recentActivity: {
        id: number;
        event_label: string;
        tone: Tone;
        description: string;
        user: string | null;
        created_at: string | null;
    }[];
};

const MODULES: Record<
    ModuleKey,
    {
        title: string;
        subtitle: string;
        icon: LucideIcon;
        accent: string;
    }
> = {
    operasi: {
        title: 'Operasi Pembangkit',
        subtitle: 'Produksi, beban & kesiapan mesin',
        icon: Zap,
        accent: 'text-chart-1',
    },
    har: {
        title: 'Pemeliharaan (HAR)',
        subtitle: 'Work order, service request & biaya',
        icon: Wrench,
        accent: 'text-amber-500',
    },
    k3: {
        title: 'K3 & Keamanan',
        subtitle: 'Kegiatan, inspeksi, sertifikat & patroli',
        icon: ShieldCheck,
        accent: 'text-emerald-500',
    },
    logistik: {
        title: 'Logistik',
        subtitle: 'Persediaan material, BBM & pelumas',
        icon: Package,
        accent: 'text-violet-500',
    },
    pdm: {
        title: 'Predictive Maintenance',
        subtitle: 'Kondisi aset & monitoring prediktif',
        icon: Activity,
        accent: 'text-rose-500',
    },
};

const STATUS_COLORS = ['text-emerald-500', 'text-amber-500', 'text-rose-500'];
const MODULE_ORDER: ModuleKey[] = ['operasi', 'har', 'k3', 'logistik', 'pdm'];

function DummyTag() {
    return (
        <span className="rounded-full border border-amber-300/70 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-700 uppercase dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300">
            Data dummy
        </span>
    );
}

function HeroStat({
    icon: Icon,
    label,
    value,
    unit,
    hint,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    unit?: string;
    hint?: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg bg-white/10 px-3.5 py-3 ring-1 ring-white/15 backdrop-blur-sm">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-white/15">
                <Icon className="size-4.5" />
            </span>
            <div className="min-w-0">
                <p className="truncate text-[11px] font-medium tracking-wide text-white/70 uppercase">
                    {label}
                </p>
                <p className="text-xl leading-tight font-semibold tabular-nums">
                    {value}
                    {unit && (
                        <span className="ml-1 text-xs font-normal text-white/70">
                            {unit}
                        </span>
                    )}
                </p>
                {hint && (
                    <p className="truncate text-[11px] text-white/60">{hint}</p>
                )}
            </div>
        </div>
    );
}

function KpiTile({ kpi, accent }: { kpi: Kpi; accent: string }) {
    const improving = kpi.good === 'up' ? kpi.delta >= 0 : kpi.delta <= 0;
    const Arrow = kpi.delta >= 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <div className="flex min-w-0 flex-col gap-1.5 rounded-lg border border-border bg-card p-3.5 shadow-xs">
            <p className="truncate text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                {kpi.label}
            </p>
            <div className="flex items-end justify-between gap-2">
                <p className="truncate text-[22px] leading-none font-semibold text-foreground tabular-nums">
                    {formatValue(kpi.value, kpi.format)}
                    {kpi.unit && (
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                            {kpi.unit}
                        </span>
                    )}
                </p>
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10.5px] font-semibold tabular-nums',
                        improving
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                    )}
                >
                    <Arrow className="size-3" />
                    {Math.abs(kpi.delta).toLocaleString('id-ID', {
                        maximumFractionDigits: 1,
                    })}
                    %
                </span>
            </div>
            <Sparkline values={kpi.spark} className={accent} />
            <p className="text-[10.5px] text-muted-foreground">vs bulan lalu</p>
        </div>
    );
}

function ModuleSection({
    moduleKey,
    kpis,
    isDummy,
    children,
}: {
    moduleKey: ModuleKey;
    kpis: Kpi[];
    isDummy: boolean;
    children: ReactNode;
}) {
    const meta = MODULES[moduleKey];
    const Icon = meta.icon;

    return (
        <section
            id={`modul-${moduleKey}`}
            className="flex scroll-mt-20 flex-col gap-3"
        >
            <div className="flex flex-wrap items-center gap-3 border-b border-border pb-2">
                <span
                    className={cn(
                        'flex size-8 items-center justify-center rounded-md bg-current/10',
                        meta.accent,
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <h2 className="text-[15px] leading-tight font-semibold text-foreground">
                        {meta.title}
                    </h2>
                    <p className="text-[11.5px] text-muted-foreground">
                        {meta.subtitle}
                    </p>
                </div>
                {isDummy && (
                    <div className="ml-auto">
                        <DummyTag />
                    </div>
                )}
            </div>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                {kpis.map((kpi) => (
                    <KpiTile key={kpi.key} kpi={kpi} accent={meta.accent} />
                ))}
            </div>
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
                {children}
            </div>
        </section>
    );
}

function CompactTable({
    head,
    children,
}: {
    head: { label: string; align?: 'right' }[];
    children: ReactNode;
}) {
    return (
        <div className="-mx-4 -mb-4 overflow-x-auto border-t border-border">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                        {head.map((h) => (
                            <TableHead
                                key={h.label}
                                className={cn(
                                    'h-8 text-[11px] font-semibold tracking-wide uppercase',
                                    h.align === 'right' && 'text-right',
                                )}
                            >
                                {h.label}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody className="text-[12.5px]">{children}</TableBody>
            </Table>
        </div>
    );
}

export default function Dashboard(props: Props) {
    const {
        scope,
        isDummy,
        periodLabel,
        summary,
        moduleHealth,
        moduleActivity,
        operasi,
        har,
        k3,
        logistik,
        pdm,
        statusBreakdown,
        recentActivity,
    } = props;
    const unitRows = props.units;
    const { can, hasGlobalAccess, roles } = usePermissions();

    const visibleModules = MODULE_ORDER.filter((key) => props[key] !== null);
    const isExecutive = visibleModules.length > 1;
    const roleLabel = roles.map((r) => r.display_name).join(', ');
    const anyData = visibleModules.length > 0 || unitRows.length > 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                {/* Hero */}
                <div className="relative overflow-hidden rounded-xl bg-linear-to-br from-chart-5 via-[#0b6aa2] to-chart-1 p-5 text-white shadow-sm md:p-6">
                    <div className="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-white/10 blur-2xl" />
                    <div className="pointer-events-none absolute -bottom-28 left-1/3 size-72 rounded-full bg-chart-4/20 blur-3xl" />
                    <div className="relative flex flex-col gap-5">
                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div className="flex flex-col gap-1">
                                <p className="text-[11px] font-semibold tracking-[0.18em] text-chart-4 uppercase">
                                    PLN UP Kendari
                                </p>
                                <h1 className="text-2xl font-semibold tracking-tight md:text-[26px]">
                                    {isExecutive
                                        ? 'Dashboard Eksekutif'
                                        : `Dashboard ${visibleModules[0] ? MODULES[visibleModules[0]].title : 'Unit'}`}
                                </h1>
                                <p className="text-[13px] text-white/75">
                                    {hasGlobalAccess
                                        ? 'Ringkasan kinerja seluruh unit pembangkit'
                                        : 'Ringkasan sesuai penugasan Anda'}
                                    {roleLabel && <> · {roleLabel}</>}
                                </p>
                            </div>
                            <div className="flex flex-col items-start gap-1.5 md:items-end">
                                <span className="rounded-full bg-white/15 px-3 py-1 text-[12px] font-medium ring-1 ring-white/20">
                                    Periode {periodLabel}
                                </span>
                                {isDummy && (
                                    <span className="inline-flex items-center gap-1 text-[11px] text-white/70">
                                        <Info className="size-3" />
                                        Grafik modul masih menggunakan data
                                        dummy
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <HeroStat
                                icon={Factory}
                                label="Unit Pembangkit"
                                value={summary.units}
                                hint={`${summary.activeUnits} unit aktif`}
                            />
                            <HeroStat
                                icon={Building2}
                                label="Unit Layanan"
                                value={summary.serviceUnits}
                            />
                            <HeroStat
                                icon={Zap}
                                label="Kapasitas Terpasang"
                                value={summary.installedCapacity.toLocaleString(
                                    'id-ID',
                                )}
                                unit="MW"
                                hint={
                                    summary.installedCapacity === 0
                                        ? 'Data kapasitas belum diisi'
                                        : undefined
                                }
                            />
                            <HeroStat
                                icon={Users}
                                label="Pengguna"
                                value={summary.users ?? '—'}
                                hint={
                                    summary.users === null
                                        ? 'Di luar hak akses Anda'
                                        : undefined
                                }
                            />
                        </div>
                    </div>
                </div>

                {isDummy && visibleModules.length > 0 && (
                    <p className="-mt-2 flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
                        <FlaskConical className="size-3.5 text-amber-500" />
                        Catatan: angka dan grafik pada setiap modul di bawah
                        masih{' '}
                        <span className="font-semibold text-amber-600 dark:text-amber-400">
                            data dummy
                        </span>{' '}
                        untuk pratinjau tampilan, belum terhubung ke data
                        transaksi.
                    </p>
                )}

                {/* Executive overview */}
                {isExecutive && (
                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
                        <Panel
                            title="Indeks Kinerja Modul"
                            subtitle="Skor capaian bulan berjalan (0–100)"
                            className="lg:col-span-8"
                            action={isDummy ? <DummyTag /> : undefined}
                        >
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                                {moduleHealth.map((m) => {
                                    const meta = MODULES[m.key];

                                    return (
                                        <a
                                            key={m.key}
                                            href={`#modul-${m.key}`}
                                            className="group flex flex-col items-center gap-1.5 rounded-lg border border-transparent p-2 text-center transition hover:border-border hover:bg-muted/50"
                                        >
                                            <Ring
                                                value={m.value}
                                                colorClass={meta.accent}
                                            />
                                            <p className="text-[12.5px] font-semibold text-foreground">
                                                {m.label}
                                            </p>
                                            <p className="text-[10.5px] text-muted-foreground">
                                                {m.caption}
                                            </p>
                                        </a>
                                    );
                                })}
                            </div>
                        </Panel>
                        <Panel
                            title="Aktivitas Input Bulan Ini"
                            subtitle="Jumlah entri per jenis data"
                            className="lg:col-span-4"
                        >
                            <BarList data={moduleActivity.slice(0, 7)} />
                        </Panel>
                    </div>
                )}

                {isExecutive && (
                    <nav className="sticky top-2 z-20 -my-1 flex gap-1.5 overflow-x-auto rounded-lg border border-border bg-card/90 p-1.5 shadow-xs backdrop-blur">
                        {visibleModules.map((key) => {
                            const meta = MODULES[key];
                            const Icon = meta.icon;

                            return (
                                <a
                                    key={key}
                                    href={`#modul-${key}`}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                >
                                    <Icon
                                        className={cn('size-3.5', meta.accent)}
                                    />
                                    {meta.title}
                                </a>
                            );
                        })}
                        {scope.units && unitRows.length > 0 && (
                            <a
                                href="#modul-unit"
                                className="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            >
                                <Factory className="size-3.5 text-slate-500" />
                                Unit & Aktivitas
                            </a>
                        )}
                    </nav>
                )}

                {operasi && (
                    <ModuleSection
                        moduleKey="operasi"
                        kpis={operasi.kpis}
                        isDummy={isDummy}
                    >
                        <Panel
                            title="Produksi Energi & Beban Puncak Harian"
                            subtitle={`Bulan ${periodLabel}`}
                            className="lg:col-span-8"
                        >
                            <BarsChart
                                data={operasi.daily}
                                lineLast
                                format="number"
                                lineFormat="decimal"
                                colors={['text-chart-1', 'text-chart-4']}
                            />
                        </Panel>
                        <Panel
                            title="Status Mesin"
                            subtitle="Kondisi seluruh mesin saat ini"
                            className="lg:col-span-4"
                        >
                            <Donut
                                data={operasi.engine_status}
                                centerLabel="Mesin"
                                colors={[
                                    'text-emerald-500',
                                    'text-chart-1',
                                    'text-amber-500',
                                    'text-rose-500',
                                ]}
                            />
                        </Panel>
                        <Panel
                            title="Indikator Kinerja"
                            subtitle="Terhadap target bulanan"
                            className="lg:col-span-5"
                        >
                            <div className="grid grid-cols-3 gap-2">
                                {operasi.gauges.map((g) => (
                                    <Gauge key={g.label} {...g} />
                                ))}
                            </div>
                        </Panel>
                        <Panel
                            title="Pemakaian BBM"
                            subtitle="6 bulan terakhir (kL)"
                            className="lg:col-span-7"
                        >
                            <BarsChart
                                data={{
                                    labels: operasi.fuel_monthly.map(
                                        (d) => d.label,
                                    ),
                                    series: [
                                        {
                                            name: 'BBM (kL)',
                                            values: operasi.fuel_monthly.map(
                                                (d) => d.value,
                                            ),
                                        },
                                    ],
                                }}
                                format="decimal"
                                colors={['text-chart-5']}
                                height="h-40"
                            />
                        </Panel>
                        <Panel
                            title="Kinerja per Unit"
                            subtitle="Ringkasan bulan berjalan"
                            className="lg:col-span-12"
                        >
                            <CompactTable
                                head={[
                                    { label: 'Unit' },
                                    {
                                        label: 'Daya Mampu (MW)',
                                        align: 'right',
                                    },
                                    {
                                        label: 'Beban Puncak (MW)',
                                        align: 'right',
                                    },
                                    { label: 'Produksi (MWh)', align: 'right' },
                                    { label: 'SFC (L/kWh)', align: 'right' },
                                    { label: 'EAF' },
                                    { label: 'Status' },
                                ]}
                            >
                                {operasi.units.map((u) => (
                                    <TableRow key={u.unit}>
                                        <TableCell className="font-medium">
                                            {u.unit}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(
                                                u.daya_mampu,
                                                'decimal',
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(
                                                u.beban_puncak,
                                                'decimal',
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(u.produksi)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(u.sfc, 'decimal3')}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            u.eaf >= 90
                                                                ? 'bg-emerald-500'
                                                                : 'bg-amber-500',
                                                        )}
                                                        style={{
                                                            width: `${u.eaf}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="tabular-nums">
                                                    {formatValue(
                                                        u.eaf,
                                                        'decimal',
                                                    )}
                                                    %
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge tone={u.status_tone}>
                                                {u.status}
                                            </StatusBadge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </CompactTable>
                        </Panel>
                    </ModuleSection>
                )}

                {har && (
                    <ModuleSection
                        moduleKey="har"
                        kpis={har.kpis}
                        isDummy={isDummy}
                    >
                        <Panel
                            title="Tren Work Order & Service Request"
                            subtitle="6 bulan terakhir"
                            className="lg:col-span-7"
                        >
                            <BarsChart
                                data={har.trend}
                                colors={['text-chart-1', 'text-amber-500']}
                            />
                        </Panel>
                        <Panel
                            title="Komposisi WO — Jenis"
                            subtitle="Berdasarkan jenis pemeliharaan"
                            className="lg:col-span-5"
                        >
                            <Donut
                                data={har.by_type}
                                centerLabel="WO"
                                colors={[
                                    'text-chart-1',
                                    'text-rose-500',
                                    'text-violet-500',
                                    'text-amber-500',
                                ]}
                            />
                        </Panel>
                        <Panel
                            title="Biaya Pemeliharaan"
                            subtitle="Jasa vs material, 6 bulan terakhir"
                            className="lg:col-span-7"
                        >
                            <BarsChart
                                data={har.cost}
                                mode="stacked"
                                format="rupiah"
                                colors={['text-chart-5', 'text-chart-3']}
                            />
                        </Panel>
                        <Panel
                            title="Komposisi WO — Status"
                            subtitle="Progres penyelesaian"
                            className="lg:col-span-5"
                        >
                            <Donut
                                data={har.by_status}
                                centerLabel="WO"
                                colors={[
                                    'text-emerald-500',
                                    'text-chart-1',
                                    'text-amber-500',
                                    'text-slate-400',
                                ]}
                            />
                        </Panel>
                        <Panel
                            title="Backlog Work Order Prioritas"
                            subtitle="WO belum selesai, diurutkan prioritas"
                            className="lg:col-span-12"
                        >
                            <CompactTable
                                head={[
                                    { label: 'No. WO' },
                                    { label: 'Unit' },
                                    { label: 'Aset' },
                                    { label: 'Uraian Pekerjaan' },
                                    { label: 'Prioritas' },
                                    { label: 'Status' },
                                    { label: 'Umur', align: 'right' },
                                ]}
                            >
                                {har.backlog.map((w) => (
                                    <TableRow key={w.code}>
                                        <TableCell className="font-mono text-[11.5px] font-medium">
                                            {w.code}
                                        </TableCell>
                                        <TableCell>{w.unit}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {w.asset}
                                        </TableCell>
                                        <TableCell className="max-w-72 truncate">
                                            {w.task}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge tone={w.priority_tone}>
                                                {w.priority}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {w.status}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right tabular-nums',
                                                w.age > 7 &&
                                                    'font-semibold text-rose-600',
                                            )}
                                        >
                                            {w.age} hari
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </CompactTable>
                        </Panel>
                    </ModuleSection>
                )}

                {k3 && (
                    <ModuleSection
                        moduleKey="k3"
                        kpis={k3.kpis}
                        isDummy={isDummy}
                    >
                        <Panel
                            title="Kurva S — Rencana vs Realisasi Kegiatan K3"
                            subtitle={`Kumulatif ${k3.s_curve.month_label}`}
                            className="lg:col-span-8"
                        >
                            <SCurve points={k3.s_curve.points} />
                        </Panel>
                        <Panel
                            title="Status Sertifikat Alat"
                            subtitle="Masa berlaku uji ulang"
                            className="lg:col-span-4"
                        >
                            <Donut
                                data={k3.cert_status}
                                centerLabel="Sertifikat"
                                colors={STATUS_COLORS}
                            />
                        </Panel>
                        <Panel
                            title="Kepatuhan Inspeksi per Unit"
                            subtitle="Persentase realisasi per minggu"
                            className="lg:col-span-4"
                        >
                            <Heatmap
                                columns={k3.compliance.columns}
                                rows={k3.compliance.rows}
                            />
                        </Panel>
                        <Panel
                            title="Patroli per Lokasi"
                            subtitle="Kumulatif scan bulan ini"
                            className="lg:col-span-4"
                        >
                            <BarList
                                data={k3.patrol_top}
                                colorClass="text-emerald-500"
                            />
                        </Panel>
                        <Panel
                            title="Status APAR/APAB"
                            subtitle="Masa berlaku tabung"
                            className="lg:col-span-4"
                        >
                            <Donut
                                data={k3.apar_status}
                                centerLabel="Tabung"
                                colors={STATUS_COLORS}
                            />
                        </Panel>
                    </ModuleSection>
                )}

                {logistik && (
                    <ModuleSection
                        moduleKey="logistik"
                        kpis={logistik.kpis}
                        isDummy={isDummy}
                    >
                        <Panel
                            title="Penerimaan vs Pemakaian BBM"
                            subtitle="6 bulan terakhir (kL)"
                            className="lg:col-span-7"
                        >
                            <BarsChart
                                data={logistik.fuel_flow}
                                colors={['text-violet-500', 'text-chart-4']}
                            />
                        </Panel>
                        <Panel
                            title="Level Tangki Timbun BBM"
                            subtitle="Persentase isi terhadap kapasitas"
                            className="lg:col-span-5"
                        >
                            <LevelBars
                                data={logistik.tanks}
                                suffix={(d) =>
                                    `${(d.capacity ?? 0).toLocaleString('id-ID')} kL`
                                }
                            />
                        </Panel>
                        <Panel
                            title="Komposisi Item Persediaan"
                            subtitle="Berdasarkan kategori"
                            className="lg:col-span-4"
                        >
                            <Donut
                                data={logistik.category}
                                centerLabel="Item"
                                colors={[
                                    'text-violet-500',
                                    'text-chart-1',
                                    'text-chart-4',
                                    'text-emerald-500',
                                    'text-slate-400',
                                ]}
                            />
                        </Panel>
                        <Panel
                            title="Material Stok Kritis"
                            subtitle="Di bawah stok minimum"
                            className="lg:col-span-8"
                        >
                            <CompactTable
                                head={[
                                    { label: 'Kode' },
                                    { label: 'Material' },
                                    { label: 'Unit' },
                                    { label: 'Stok / Min' },
                                    { label: 'Kekurangan', align: 'right' },
                                ]}
                            >
                                {logistik.critical.map((m) => (
                                    <TableRow key={m.code}>
                                        <TableCell className="font-mono text-[11.5px]">
                                            {m.code}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {m.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {m.unit}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-rose-500"
                                                        style={{
                                                            width: `${Math.min(100, (m.stock / m.min) * 100)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="tabular-nums">
                                                    {m.stock} / {m.min} {m.uom}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right font-semibold text-rose-600 tabular-nums">
                                            {m.min - m.stock} {m.uom}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </CompactTable>
                        </Panel>
                    </ModuleSection>
                )}

                {pdm && (
                    <ModuleSection
                        moduleKey="pdm"
                        kpis={pdm.kpis}
                        isDummy={isDummy}
                    >
                        <Panel
                            title="Tren Vibrasi Mesin"
                            subtitle="12 minggu terakhir (mm/s, ISO 10816)"
                            className="lg:col-span-8"
                        >
                            <LinesChart
                                data={pdm.vibration}
                                threshold={pdm.vibration.threshold}
                                thresholdLabel="Batas alarm"
                                colors={[
                                    'text-chart-1',
                                    'text-emerald-500',
                                    'text-rose-500',
                                ]}
                            />
                        </Panel>
                        <Panel
                            title="Kondisi Aset"
                            subtitle="Hasil pengukuran terakhir"
                            className="lg:col-span-4"
                        >
                            <Donut
                                data={pdm.condition}
                                centerLabel="Aset"
                                colors={STATUS_COLORS}
                            />
                        </Panel>
                        <Panel
                            title="Rencana vs Realisasi Pengukuran"
                            subtitle="6 bulan terakhir"
                            className="lg:col-span-5"
                        >
                            <BarsChart
                                data={pdm.realisasi}
                                colors={['text-slate-400', 'text-rose-500']}
                            />
                        </Panel>
                        <Panel
                            title="Kesehatan Aset"
                            subtitle="Parameter kondisi per aset"
                            className="lg:col-span-7"
                        >
                            <CompactTable
                                head={[
                                    { label: 'Aset' },
                                    { label: 'Vibrasi (mm/s)', align: 'right' },
                                    {
                                        label: 'Suhu Bearing (°C)',
                                        align: 'right',
                                    },
                                    { label: 'Kualitas Oli' },
                                    { label: 'Status' },
                                ]}
                            >
                                {pdm.assets.map((a) => (
                                    <TableRow key={a.asset}>
                                        <TableCell className="font-medium">
                                            {a.asset}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right tabular-nums',
                                                a.vibration > 7.1 &&
                                                    'font-semibold text-rose-600',
                                            )}
                                        >
                                            {formatValue(
                                                a.vibration,
                                                'decimal',
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(
                                                a.temperature,
                                                'decimal',
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {a.oil}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge tone={a.status_tone}>
                                                {a.status}
                                            </StatusBadge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </CompactTable>
                        </Panel>
                    </ModuleSection>
                )}

                {(unitRows.length > 0 || recentActivity.length > 0) && (
                    <section
                        id="modul-unit"
                        className="flex scroll-mt-20 flex-col gap-3"
                    >
                        <div className="flex flex-wrap items-center gap-3 border-b border-border pb-2">
                            <span className="flex size-8 items-center justify-center rounded-md bg-slate-500/10 text-slate-500">
                                <Factory className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-[15px] leading-tight font-semibold text-foreground">
                                    Unit & Aktivitas Sistem
                                </h2>
                                <p className="text-[11.5px] text-muted-foreground">
                                    Data aktual dari master unit dan log
                                    aktivitas
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
                            {unitRows.length > 0 && (
                                <Panel
                                    title="Daftar Unit Pembangkit"
                                    subtitle={`${unitRows.length} unit dalam cakupan Anda`}
                                    className={
                                        recentActivity.length > 0
                                            ? 'lg:col-span-8'
                                            : 'lg:col-span-12'
                                    }
                                    action={
                                        can('unit.view_any') ? (
                                            <Link
                                                href={units.index()}
                                                className="shrink-0 text-[12px] font-medium text-primary hover:underline"
                                            >
                                                Lihat semua
                                            </Link>
                                        ) : undefined
                                    }
                                >
                                    {statusBreakdown.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {statusBreakdown.map((s) => (
                                                <span
                                                    key={s.status}
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-border px-2 py-1 text-[11.5px]"
                                                >
                                                    <StatusBadge tone={s.tone}>
                                                        {s.label}
                                                    </StatusBadge>
                                                    <span className="font-semibold tabular-nums">
                                                        {s.total}
                                                    </span>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    <CompactTable
                                        head={[
                                            { label: 'Unit' },
                                            { label: 'Unit Layanan' },
                                            { label: 'Tipe' },
                                            { label: 'Status' },
                                            {
                                                label: 'Kapasitas (MW)',
                                                align: 'right',
                                            },
                                        ]}
                                    >
                                        {unitRows.slice(0, 8).map((unit) => (
                                            <TableRow key={unit.id}>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        {unit.name}
                                                    </span>
                                                    <span className="block text-[11px] text-muted-foreground">
                                                        {unit.code}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {unit.service_unit ??
                                                        'UP Kendari'}
                                                </TableCell>
                                                <TableCell>
                                                    {unit.type}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        tone={unit.status_tone}
                                                    >
                                                        {unit.status_label}
                                                    </StatusBadge>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {unit.installed_capacity_mw ??
                                                        '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </CompactTable>
                                </Panel>
                            )}
                            {recentActivity.length > 0 && (
                                <Panel
                                    title="Aktivitas Terbaru"
                                    subtitle="Log perubahan data"
                                    className={
                                        unitRows.length > 0
                                            ? 'lg:col-span-4'
                                            : 'lg:col-span-12'
                                    }
                                >
                                    <ol className="relative flex flex-col gap-3 border-l border-border pl-4">
                                        {recentActivity.map((activity) => (
                                            <li
                                                key={activity.id}
                                                className="relative"
                                            >
                                                <span className="absolute top-1.5 -left-[21px] size-2.5 rounded-full border-2 border-card bg-primary" />
                                                <div className="flex items-center gap-2">
                                                    <StatusBadge
                                                        tone={activity.tone}
                                                    >
                                                        {activity.event_label}
                                                    </StatusBadge>
                                                    <span className="text-[10.5px] text-muted-foreground">
                                                        {formatDateTime(
                                                            activity.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-[12.5px] text-foreground">
                                                    {activity.description}
                                                </p>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {activity.user ?? 'Sistem'}
                                                </p>
                                            </li>
                                        ))}
                                    </ol>
                                </Panel>
                            )}
                        </div>
                    </section>
                )}

                {!anyData && (
                    <EmptyState
                        title="Belum ada data"
                        description="Belum ada data dalam cakupan akses Anda untuk ditampilkan."
                    />
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
