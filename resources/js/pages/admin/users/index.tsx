import { Head, Link, usePage } from '@inertiajs/react';
import {
    Check,
    Copy,
    Download,
    FileSpreadsheet,
    LogIn,
    Pencil,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import { openAccountSwitcher } from '@/lib/impersonation';
import { downloadK3InputWorkbook } from '@/lib/k3-input-excel';
import type { K3InputExport } from '@/lib/k3-input-excel';
import { dashboard } from '@/routes';
import users from '@/routes/admin/users';
import type { Auth, IdName, Paginated, UserRow } from '@/types';

async function writeTextToClipboard(text: string): Promise<boolean> {
    if (navigator?.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall through to legacy textarea copy when clipboard permission is restricted.
        }
    }

    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textarea);

        return ok;
    } catch {
        return false;
    }
}

function resolveUserScopeTitle(
    user: Pick<UserRow, 'name' | 'assignments'>,
    fallbackUnitName?: string,
): string {
    const unitScope =
        user.assignments.find(
            (a) => a.scope_label && a.scope_label !== 'Global',
        )?.scope_label ??
        fallbackUnitName ??
        user.assignments[0]?.scope_label ??
        'UP Kendari';

    return `Akun KIT-SIPP ${unitScope}`;
}

export function formatUserAccountClipboardText(
    user: Pick<UserRow, 'name' | 'email' | 'assignments'>,
    fallbackUnitName?: string,
): string {
    return [
        resolveUserScopeTitle(user, fallbackUnitName),
        `Email: ${user.email}`,
        `Username: ${user.name}`,
    ].join('\n');
}

type Props = {
    users: Paginated<UserRow>;
    filters: {
        search?: string;
        role_id?: string;
        unit_id?: string;
        regu?: string;
        status?: string;
    };
    options: {
        roles: { id: number; display_name: string }[];
        units: IdName[];
    };
};

export default function UsersIndex({ users: page, filters, options }: Props) {
    const { can } = usePermissions();
    // Pindah Akun (Super Admin): open the switcher with this user chosen; the server checks the rest.
    const auth = usePage().props.auth as Auth | undefined;
    const canImpersonate = auth?.canImpersonate ?? false;
    const authUserId = auth?.user.id;
    const [exporting, setExporting] = useState(false);
    const [copyingAll, setCopyingAll] = useState(false);
    const [copiedUserId, setCopiedUserId] = useState<number | null>(null);
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        users.index().url,
        {
            search: filters.search,
            role_id: filters.role_id,
            unit_id: filters.unit_id,
            regu: filters.regu,
            status: filters.status,
        },
    );

    const selectedUnitName = values.unit_id
        ? options.units.find((u) => String(u.id) === String(values.unit_id))
              ?.name
        : undefined;

    const buildExportQuery = (): string => {
        const params = new URLSearchParams();

        Object.entries(values).forEach(([key, val]) => {
            if (
                val !== undefined &&
                val !== null &&
                String(val).trim() !== ''
            ) {
                params.set(key, String(val).trim());
            }
        });

        const qs = params.toString();

        return qs ? `?${qs}` : '';
    };

    const handleOpenPdf = () => {
        window.open(`/admin/users/export/pdf${buildExportQuery()}`, '_blank');
    };

    const handleDownloadExcel = async () => {
        setExporting(true);

        try {
            const response = await fetch(
                `/admin/users/export/data${buildExportQuery()}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const payload = (await response.json()) as K3InputExport;
            await downloadK3InputWorkbook(payload);
        } catch {
            toast.error('Gagal membuat file Excel data pengguna.');
        } finally {
            setExporting(false);
        }
    };

    const handleCopySingleUser = async (user: UserRow) => {
        const text = formatUserAccountClipboardText(user, selectedUnitName);
        const ok = await writeTextToClipboard(text);

        if (ok) {
            setCopiedUserId(user.id);
            toast.success(`Data akun ${user.name} disalin ke clipboard.`);
            window.setTimeout(() => {
                setCopiedUserId((prev) => (prev === user.id ? null : prev));
            }, 2000);
        } else {
            toast.error('Gagal menyalin data akun ke clipboard.');
        }
    };

    const handleCopyAllFilteredAccounts = async () => {
        setCopyingAll(true);

        try {
            const response = await fetch(
                `/admin/users/export/data${buildExportQuery()}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const payload = (await response.json()) as K3InputExport & {
                accounts?: UserRow[];
            };
            const list =
                payload.accounts && payload.accounts.length > 0
                    ? payload.accounts
                    : page.data;

            if (list.length === 0) {
                toast.info('Tidak ada data akun untuk disalin.');

                return;
            }

            const text = list
                .map((u) => formatUserAccountClipboardText(u, selectedUnitName))
                .join('\n\n');
            const ok = await writeTextToClipboard(text);

            if (ok) {
                toast.success(
                    `${list.length} data akun berhasil disalin ke clipboard.`,
                );
            } else {
                toast.error('Gagal menyalin daftar akun ke clipboard.');
            }
        } catch {
            toast.error('Gagal memuat data akun untuk disalin.');
        } finally {
            setCopyingAll(false);
        }
    };

    return (
        <>
            <Head title="Pengguna" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pengguna"
                    description="Akun beserta role dan penempatannya di dalam organisasi."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleCopyAllFilteredAccounts}
                                disabled={copyingAll}
                                className="gap-1.5"
                                title="Salin seluruh data akun sesuai filter ke clipboard"
                            >
                                <Copy className="size-4 text-primary" />
                                {copyingAll ? 'Menyalin…' : 'Salin Akun'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleDownloadExcel}
                                disabled={exporting}
                                className="gap-1.5"
                            >
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleOpenPdf}
                                className="gap-1.5"
                            >
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can('user.create') && (
                                <Button size="sm" asChild>
                                    <Link href={users.create()}>
                                        <Plus className="size-4" />
                                        Tambah Pengguna
                                    </Link>
                                </Button>
                            )}
                        </div>
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
                            value={values.regu}
                            onChange={(value) => setFilter('regu', value)}
                            placeholder="Regu Shift"
                            allLabel="Semua Regu"
                            options={[
                                { value: 'A', label: 'Regu A' },
                                { value: 'B', label: 'Regu B' },
                                { value: 'C', label: 'Regu C' },
                                { value: 'D', label: 'Regu D' },
                                { value: 'leader', label: 'Leader Shift' },
                            ]}
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
                                    <TableHead className="w-32 text-right">
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
                                            <div className="flex flex-col gap-1">
                                                {user.assignments.length ===
                                                0 ? (
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
                                                {user.regu && (
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        <StatusBadge tone="info">
                                                            Regu {user.regu}
                                                        </StatusBadge>
                                                        {user.is_shift_leader && (
                                                            <StatusBadge tone="warning">
                                                                Leader Shift
                                                            </StatusBadge>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
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
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleCopySingleUser(
                                                            user,
                                                        )
                                                    }
                                                    aria-label={`Salin akun ${user.name}`}
                                                    title="Salin data akun ke clipboard"
                                                >
                                                    {copiedUserId ===
                                                    user.id ? (
                                                        <Check className="size-4 text-emerald-600" />
                                                    ) : (
                                                        <Copy className="size-4" />
                                                    )}
                                                </Button>
                                                {canImpersonate &&
                                                    user.is_active &&
                                                    user.id !== authUserId && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                openAccountSwitcher(
                                                                    {
                                                                        id: user.id,
                                                                        name: user.name,
                                                                        email: user.email,
                                                                    },
                                                                )
                                                            }
                                                            aria-label={`Masuk sebagai ${user.name}`}
                                                            title="Masuk sebagai pengguna ini"
                                                        >
                                                            <LogIn className="size-4" />
                                                        </Button>
                                                    )}
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
