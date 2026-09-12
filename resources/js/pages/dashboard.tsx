import { Head, Link } from '@inertiajs/react';
import { BarChart, BarList, ComboChart, DonutChart, LineChart, SCurveChart } from '@/components/dashboard-charts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { SummaryCard } from '@/components/summary-card';
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
import { dashboard } from '@/routes';
import units from '@/routes/admin/units';
import type { Tone } from '@/types';

type DashboardUnit = {
    id: number;
    name: string;
    code: string;
    type: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    service_unit: string | null;
    installed_capacity_mw: string | null;
    is_active: boolean;
};

type Kpi = { key: string; label: string; value: number; hint: string };
type Slice = { type: string; count: number };
type SCurve = { days: number; month_label: string; plan_total: number; real_total: number; points: { day: number; plan: number; real: number }[] };

type Props = {
    scope: { operasi: boolean; har: boolean; k3: boolean; units: boolean };
    periodLabel: string;
    summary: { units: number; serviceUnits: number; activeUnits: number; installedCapacity: number; users: number | null };
    kpis: Kpi[];
    moduleActivity: { label: string; value: number }[];
    operasi: { daily_peak: { month_label: string; points: { label: string; value: number }[] }; logsheet_status: Slice[] } | null;
    har: { trend: { label: string; wo: number; sr: number; cost: number }[]; by_type: Slice[]; by_status: Slice[] } | null;
    k3: { s_curve: SCurve; cert_status: Slice[]; apar_status: Slice[]; patrol_top: { label: string; value: number }[] } | null;
    statusBreakdown: { status: string; label: string; tone: Tone; total: number }[];
    units: DashboardUnit[];
    recentActivity: {
        id: number;
        event_label: string;
        tone: Tone;
        description: string;
        user: string | null;
        created_at: string | null;
    }[];
};

function SectionHeading({ title, accent }: { title: string; accent: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className={`h-4 w-1.5 rounded-full ${accent}`} />
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
        </div>
    );
}

export default function Dashboard({
    periodLabel,
    summary,
    kpis,
    moduleActivity,
    operasi,
    har,
    k3,
    statusBreakdown,
    units: unitRows,
    recentActivity,
}: Props) {
    const { can, hasGlobalAccess, roles } = usePermissions();

    const scopeDescription = hasGlobalAccess
        ? `Ringkasan seluruh unit pembangkit UP Kendari · ${periodLabel}`
        : `Ringkasan sesuai penugasan Anda${roles.length > 0 ? ` (${roles.map((r) => r.display_name).join(', ')})` : ''} · ${periodLabel}`;

    const anyData = operasi !== null || har !== null || k3 !== null || unitRows.length > 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Dashboard" description={scopeDescription} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard label="Unit Pembangkit" value={summary.units} hint={`${summary.activeUnits} unit aktif`} />
                    <SummaryCard label="Unit Layanan" value={summary.serviceUnits} />
                    <SummaryCard
                        label="Kapasitas Terpasang"
                        value={summary.installedCapacity.toLocaleString('id-ID')}
                        unit="MW"
                        hint={summary.installedCapacity === 0 ? 'Data kapasitas belum diisi' : undefined}
                    />
                    <SummaryCard label="Pengguna" value={summary.users ?? '—'} hint={summary.users === null ? 'Di luar hak akses Anda' : undefined} />
                </div>

                {kpis.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {kpis.map((kpi) => (
                            <SummaryCard key={kpi.key} label={kpi.label} value={kpi.value} hint={kpi.hint} />
                        ))}
                    </div>
                )}

                {moduleActivity.length > 0 && (
                    <BarList title="Aktivitas Input Bulan Ini" subtitle="Jumlah entri per modul" data={moduleActivity} />
                )}

                {operasi !== null && (
                    <section className="flex flex-col gap-3">
                        <SectionHeading title="Operasi" accent="bg-sky-500" />
                        <div className="grid gap-4 lg:grid-cols-2">
                            <LineChart
                                title="Beban Puncak Harian"
                                subtitle={operasi.daily_peak.month_label}
                                points={operasi.daily_peak.points}
                                unit="kW"
                            />
                            <DonutChart title="Status Logsheet" subtitle="Bulan berjalan" data={operasi.logsheet_status} />
                        </div>
                    </section>
                )}

                {har !== null && (
                    <section className="flex flex-col gap-3">
                        <SectionHeading title="Pemeliharaan (HAR)" accent="bg-amber-500" />
                        <div className="grid gap-4 lg:grid-cols-2">
                            <ComboChart data={har.trend} />
                            <BarChart
                                title="Biaya Pemeliharaan"
                                subtitle="6 bulan terakhir"
                                data={har.trend.map((t) => ({ label: t.label, value: t.cost }))}
                                format="rupiah"
                            />
                            <DonutChart title="Komposisi WO — Jenis" subtitle="Berdasarkan jenis pemeliharaan" data={har.by_type} />
                            <DonutChart title="Komposisi WO — Status" subtitle="Berdasarkan status WO" data={har.by_status} />
                        </div>
                    </section>
                )}

                {k3 !== null && (
                    <section className="flex flex-col gap-3">
                        <SectionHeading title="K3 & Keamanan" accent="bg-emerald-500" />
                        <SCurveChart points={k3.s_curve.points} monthLabel={k3.s_curve.month_label} />
                        <div className="grid gap-4 lg:grid-cols-3">
                            <DonutChart title="Status Sertifikat" subtitle="Uji ulang alat" data={k3.cert_status} />
                            <DonutChart title="Status APAR/APAB" subtitle="Masa berlaku" data={k3.apar_status} />
                            <BarList title="Patroli per Lokasi" subtitle="Kumulatif scan bulan ini" data={k3.patrol_top} />
                        </div>
                    </section>
                )}

                {statusBreakdown.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <SectionHeading title="Status Unit" accent="bg-violet-500" />
                        <div className="grid grid-cols-2 divide-x divide-y rounded-md border border-border bg-card sm:grid-cols-4 sm:divide-y-0">
                            {statusBreakdown.map((item) => (
                                <div key={item.status} className="p-4">
                                    <StatusBadge tone={item.tone}>{item.label}</StatusBadge>
                                    <p className="mt-2 text-2xl font-semibold text-foreground tabular-nums">{item.total}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {unitRows.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <div className="flex items-center justify-between">
                            <SectionHeading title="Daftar Unit" accent="bg-slate-400" />
                            {can('unit.view_any') && (
                                <Link href={units.index()} className="text-[13px] font-medium text-primary hover:underline">
                                    Lihat semua
                                </Link>
                            )}
                        </div>

                        <div className="overflow-hidden rounded-md border border-border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Unit Layanan</TableHead>
                                        <TableHead>Tipe</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Kapasitas (MW)</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {unitRows.slice(0, 10).map((unit) => (
                                        <TableRow key={unit.id}>
                                            <TableCell>
                                                <span className="font-medium">{unit.name}</span>
                                                <span className="block text-xs text-muted-foreground">{unit.code}</span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{unit.service_unit ?? 'UP Kendari'}</TableCell>
                                            <TableCell>{unit.type}</TableCell>
                                            <TableCell>
                                                <StatusBadge tone={unit.status_tone}>{unit.status_label}</StatusBadge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{unit.installed_capacity_mw ?? '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                )}

                {!anyData && (
                    <EmptyState title="Belum ada data" description="Belum ada data dalam cakupan akses Anda untuk ditampilkan." />
                )}

                {recentActivity.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <SectionHeading title="Aktivitas Terbaru" accent="bg-slate-400" />
                        <ul className="divide-y divide-border rounded-md border border-border bg-card">
                            {recentActivity.map((activity) => (
                                <li
                                    key={activity.id}
                                    className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-center gap-2">
                                        <StatusBadge tone={activity.tone}>{activity.event_label}</StatusBadge>
                                        <span className="text-[13px]">{activity.description}</span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {activity.user ?? 'Sistem'} · {formatDateTime(activity.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
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
