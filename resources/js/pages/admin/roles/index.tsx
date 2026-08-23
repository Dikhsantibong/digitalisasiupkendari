import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
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
import { dashboard } from '@/routes';
import roles from '@/routes/admin/roles';
import type { RoleRow } from '@/types';

type Props = {
    roles: RoleRow[];
    permissionTotal: number;
};

export default function RolesIndex({ roles: rows, permissionTotal }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Role & Akses" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Role & Hak Akses"
                    description={`Terdapat ${permissionTotal} permission yang dapat diatur per role.`}
                    actions={
                        can('role.create') && (
                            <Button asChild>
                                <Link href={roles.create()}>
                                    <Plus className="size-4" />
                                    Tambah Role
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Cakupan</TableHead>
                                <TableHead className="text-right">
                                    Permission
                                </TableHead>
                                <TableHead className="text-right">
                                    Pengguna
                                </TableHead>
                                <TableHead>Jenis</TableHead>
                                <TableHead className="w-24 text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <span className="font-medium text-foreground">
                                            {role.display_name}
                                        </span>
                                        <span className="block font-mono text-xs text-muted-foreground">
                                            {role.name}
                                        </span>
                                        {role.description && (
                                            <span className="mt-0.5 block max-w-md text-xs text-muted-foreground">
                                                {role.description}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {role.scope_label}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {role.name === 'super_admin'
                                            ? `${permissionTotal} (semua)`
                                            : role.permissions_count}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {role.assignments_count}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            tone={
                                                role.is_system
                                                    ? 'info'
                                                    : 'neutral'
                                            }
                                        >
                                            {role.is_system
                                                ? 'Sistem'
                                                : 'Kustom'}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            {can('role.update') && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={roles.edit(
                                                            role.id,
                                                        )}
                                                        aria-label={`Ubah ${role.display_name}`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('role.delete') &&
                                                !role.is_system && (
                                                    <ConfirmDeleteDialog
                                                        action={RoleController.destroy.form(
                                                            role.id,
                                                        )}
                                                        title="Hapus role?"
                                                        description={`Role ${role.display_name} akan dihapus beserta ${role.assignments_count} penugasannya.`}
                                                    />
                                                )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <p className="text-[13px] text-muted-foreground">
                    Role sistem tidak dapat dihapus karena merupakan bagian dari
                    struktur organisasi, tetapi permission-nya tetap dapat
                    diubah. Super Admin selalu memiliki seluruh permission,
                    termasuk yang ditambahkan pada modul berikutnya.
                </p>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Role & Akses', href: roles.index() },
    ],
};
