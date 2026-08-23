import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
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
import { useIndexFilters } from '@/hooks/use-index-filters';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import users from '@/routes/admin/users';
import type { IdName, Paginated, UserRow } from '@/types';

type Props = {
    users: Paginated<UserRow>;
    filters: {
        search?: string;
        role_id?: string;
        unit_id?: string;
        status?: string;
    };
    options: {
        roles: { id: number; display_name: string }[];
        units: IdName[];
    };
};

export default function UsersIndex({ users: page, filters, options }: Props) {
    const { can } = usePermissions();
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        users.index().url,
        {
            search: filters.search,
            role_id: filters.role_id,
            unit_id: filters.unit_id,
            status: filters.status,
        },
    );

    return (
        <>
            <Head title="Pengguna" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pengguna"
                    description="Akun beserta role dan penempatannya di dalam organisasi."
                    actions={
                        can('user.create') && (
                            <Button asChild>
                                <Link href={users.create()}>
                                    <Plus className="size-4" />
                                    Tambah Pengguna
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <FilterBar
                        searchValue={values.search ?? ''}
                        onSearchChange={(value) => setSearch('search', value)}
                        searchPlaceholder="Cari nama, email, atau NIP…"
                        isFiltered={isFiltered}
                        onReset={reset}
                    >
                        <FilterSelect
                            value={values.role_id}
                            onChange={(value) => setFilter('role_id', value)}
                            placeholder="Role"
                            allLabel="Semua Role"
                            options={options.roles.map((role) => ({
                                value: String(role.id),
                                label: role.display_name,
                            }))}
                        />
                        <FilterSelect
                            value={values.unit_id}
                            onChange={(value) => setFilter('unit_id', value)}
                            placeholder="Unit"
                            allLabel="Semua Unit"
                            options={options.units.map((unit) => ({
                                value: String(unit.id),
                                label: unit.name,
                            }))}
                        />
                        <FilterSelect
                            value={values.status}
                            onChange={(value) => setFilter('status', value)}
                            placeholder="Status"
                            allLabel="Semua Status"
                            options={[
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ]}
                        />
                    </FilterBar>

                    {page.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada pengguna'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah kata kunci atau filter yang digunakan.'
                                    : 'Tambahkan pengguna untuk mulai mengatur akses.'
                            }
                            action={
                                isFiltered ? (
                                    <Button variant="secondary" onClick={reset}>
                                        Reset Filter
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pengguna</TableHead>
                                    <TableHead>NIP</TableHead>
                                    <TableHead>Penugasan</TableHead>
                                    <TableHead>Login Terakhir</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <Link
                                                href={users.show(user.id)}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {user.name}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {user.employee_id ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {user.assignments.length === 0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Belum ditugaskan
                                                </span>
                                            ) : (
                                                <ul className="flex flex-col gap-0.5">
                                                    {user.assignments.map(
                                                        (assignment) => (
                                                            <li
                                                                key={
                                                                    assignment.id
                                                                }
                                                                className="text-[13px]"
                                                            >
                                                                <span className="font-medium">
                                                                    {
                                                                        assignment.role
                                                                    }
                                                                </span>
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    ·{' '}
                                                                    {
                                                                        assignment.scope_label
                                                                    }
                                                                </span>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatDateTime(user.last_login_at)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={
                                                    user.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {user.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {can('user.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={users.edit(
                                                                user.id,
                                                            )}
                                                            aria-label={`Ubah ${user.name}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('user.delete') && (
                                                    <ConfirmDeleteDialog
                                                        action={UserController.destroy.form(
                                                            user.id,
                                                        )}
                                                        title="Hapus pengguna?"
                                                        description={`Akun ${user.name} beserta seluruh penugasan role-nya akan dihapus permanen.`}
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <DataPagination meta={page} />
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
    ],
};
