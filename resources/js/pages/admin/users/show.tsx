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
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import users from '@/routes/admin/users';
import type { Tone, UserRow } from '@/types';

type Props = {
    user: UserRow;
    permissions: string[];
    activity: {
        id: number;
        event_label: string;
        tone: Tone;
        description: string;
        created_at: string | null;
    }[];
};

export default function UserShow({ user, permissions, activity }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title={user.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={user.name}
                    description={user.position ?? user.email}
                    actions={
                        can('user.update') && (
                            <Button asChild>
                                <Link href={users.edit(user.id)}>
                                    <Pencil className="size-4" />
                                    Ubah Pengguna
                                </Link>
                            </Button>
                        )
                    }
                />

                <section className="grid gap-4 rounded-md border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Detail label="Email">{user.email}</Detail>
                    <Detail label="NIP">{user.employee_id ?? '—'}</Detail>
                    <Detail label="Telepon">{user.phone ?? '—'}</Detail>
                    <Detail label="Status Akun">
                        <StatusBadge
                            tone={user.is_active ? 'success' : 'neutral'}
                        >
                            {user.is_active ? 'Aktif' : 'Nonaktif'}
                        </StatusBadge>
                    </Detail>
                    <Detail label="Login Terakhir">
                        {formatDateTime(user.last_login_at)}
                    </Detail>
                    <Detail label="Terdaftar Sejak">
                        {formatDateTime(user.created_at)}
                    </Detail>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold text-foreground">
                        Penugasan Role
                    </h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        {user.assignments.length === 0 ? (
                            <EmptyState
                                title="Belum ada penugasan"
                                description="Pengguna ini belum memiliki akses ke unit manapun."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Cakupan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {user.assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell className="font-medium">
                                                {assignment.role}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {assignment.scope_label}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold text-foreground">
                        Permission Efektif ({permissions.length})
                    </h2>
                    <div className="rounded-md border border-border bg-card p-4">
                        {permissions.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">
                                Pengguna belum memiliki permission apa pun.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-1.5">
                                {permissions.map((permission) => (
                                    <code
                                        key={permission}
                                        className="rounded border border-border bg-secondary px-1.5 py-0.5 font-mono text-xs text-foreground"
                                    >
                                        {permission}
                                    </code>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                {activity.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-base font-semibold text-foreground">
                            Aktivitas Terbaru
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

UserShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
        { title: 'Detail', href: users.index() },
    ],
};
