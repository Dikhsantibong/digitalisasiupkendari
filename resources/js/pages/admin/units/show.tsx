import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import units from '@/routes/admin/units';
import type { Tone, UnitRow } from '@/types';

type Props = {
    unit: UnitRow;
    assignments: {
        id: number;
        user: {
            id: number;
            name: string;
            email: string;
            position: string | null;
            is_active: boolean;
        };
        role: string;
    }[];
    activity: {
        id: number;
        event_label: string;
        tone: Tone;
        description: string;
        user: string | null;
        created_at: string | null;
    }[];
};

export default function UnitShow({ unit, assignments, activity }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title={unit.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={unit.name}
                    description={`${unit.code} · ${unit.type_label}`}
                    actions={
                        can('unit.update') && (
                            <Button asChild>
                                <Link href={units.edit(unit.id)}>
                                    <Pencil className="size-4" />
                                    Ubah Unit
                                </Link>
                            </Button>
                        )
                    }
                />

                <section className="grid gap-4 rounded-md border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Detail label="Status Operasi">
                        <StatusBadge tone={unit.status_tone}>
                            {unit.status_label}
                        </StatusBadge>
                    </Detail>
                    <Detail label="Unit Layanan">
                        {unit.service_unit ?? 'UP Kendari (langsung)'}
                    </Detail>
                    <Detail label="Kapasitas Terpasang">
                        <span className="tabular-nums">
                            {formatNumber(unit.installed_capacity_mw)} MW
                        </span>
                    </Detail>
                    <Detail label="Lokasi">{unit.location ?? '—'}</Detail>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold text-foreground">
                        Penugasan Personel
                    </h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        {assignments.length === 0 ? (
                            <EmptyState
                                title="Belum ada penugasan"
                                description="Belum ada personel yang ditugaskan pada unit ini."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status Akun</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {assignment.user.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {assignment.user.email}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {assignment.user.position ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                {assignment.role}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    tone={
                                                        assignment.user
                                                            .is_active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {assignment.user.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </StatusBadge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </section>

                {activity.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-base font-semibold text-foreground">
                            Riwayat Aktivitas
                        </h2>
                        <ul className="divide-y divide-border rounded-md border border-border bg-card">
                            {activity.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-center gap-2">
                                        <StatusBadge tone={item.tone}>
                                            {item.event_label}
                                        </StatusBadge>
                                        <span className="text-[13px]">
                                            {item.description}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {item.user ?? 'Sistem'} ·{' '}
                                        {formatDateTime(item.created_at)}
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

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className="text-sm text-foreground">{children}</span>
        </div>
    );
}

UnitShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Pembangkit', href: units.index() },
        { title: 'Detail', href: units.index() },
    ],
};
