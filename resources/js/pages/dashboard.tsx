import { Head, Link } from '@inertiajs/react';
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

type Props = {
    summary: {
        units: number;
        serviceUnits: number;
        activeUnits: number;
        installedCapacity: number;
        users: number | null;
    };
    statusBreakdown: {
        status: string;
        label: string;
        tone: Tone;
        total: number;
    }[];
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

export default function Dashboard({
    summary,
    statusBreakdown,
    units: unitRows,
    recentActivity,
}: Props) {
    const { can, hasGlobalAccess, roles } = usePermissions();

    const scopeDescription = hasGlobalAccess
        ? 'Seluruh unit pembangkit di bawah UP Kendari.'
        : `Unit dalam cakupan penugasan Anda${roles.length > 0 ? ` sebagai ${roles.map((role) => role.display_name).join(', ')}` : ''}.`;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Dashboard" description={scopeDescription} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        label="Unit Pembangkit"
                        value={summary.units}
                        hint={`${summary.activeUnits} unit aktif`}
                    />
                    <SummaryCard
                        label="Unit Layanan"
                        value={summary.serviceUnits}
                    />
                    <SummaryCard
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
                    <SummaryCard
                        label="Pengguna"
                        value={summary.users ?? '—'}
                        hint={
                            summary.users === null
                                ? 'Di luar hak akses Anda'
                                : undefined
                        }
                    />
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold text-foreground">
                        Status Unit
                    </h2>
                    <div className="grid grid-cols-2 divide-x divide-y rounded-md border border-border bg-card sm:grid-cols-4 sm:divide-y-0">
                        {statusBreakdown.map((item) => (
                            <div key={item.status} className="p-4">
                                <StatusBadge tone={item.tone}>
                                    {item.label}
                                </StatusBadge>
                                <p className="mt-2 text-2xl font-semibold text-foreground tabular-nums">
                                    {item.total}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold text-foreground">
                            Daftar Unit
                        </h2>
                        {can('unit.view_any') && (
                            <Link
                                href={units.index()}
                                className="text-[13px] font-medium text-primary hover:underline"
                            >
                                Lihat semua
                            </Link>
                        )}
                    </div>

                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        {unitRows.length === 0 ? (
                            <EmptyState
                                title="Belum ada unit"
                                description="Belum ada unit pembangkit dalam cakupan akses Anda."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Unit Layanan</TableHead>
                                        <TableHead>Tipe</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Kapasitas (MW)
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {unitRows.slice(0, 10).map((unit) => (
                                        <TableRow key={unit.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {unit.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {unit.code}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {unit.service_unit ??
                                                    'UP Kendari'}
                                            </TableCell>
                                            <TableCell>{unit.type}</TableCell>
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
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </section>

                {recentActivity.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-base font-semibold text-foreground">
                            Aktivitas Terbaru
                        </h2>
                        <ul className="divide-y divide-border rounded-md border border-border bg-card">
                            {recentActivity.map((activity) => (
                                <li
                                    key={activity.id}
                                    className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-center gap-2">
                                        <StatusBadge tone={activity.tone}>
                                            {activity.event_label}
                                        </StatusBadge>
                                        <span className="text-[13px]">
                                            {activity.description}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {activity.user ?? 'Sistem'} ·{' '}
                                        {formatDateTime(activity.created_at)}
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
