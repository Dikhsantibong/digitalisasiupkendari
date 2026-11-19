import { Head, Link, usePage } from '@inertiajs/react';
import { KeyRound, Pencil } from 'lucide-react';
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

type ProvisionedAccounts = {
    password: string;
    accounts: { email: string; name: string; role: string }[];
};

export default function UnitShow({ unit, assignments, activity }: Props) {
    const { can } = usePermissions();
    // Shown once, right after the unit was added with its accounts.
    const provisioned = (usePage().flash as Record<string, unknown>)
        ?.provisioned_accounts as ProvisionedAccounts | undefined;

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

                {provisioned && provisioned.accounts.length > 0 && (
                    <section className="flex flex-col gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                        <div className="flex items-start gap-2">
                            <KeyRound className="mt-0.5 size-4 text-amber-700 dark:text-amber-300" />
                            <div>
                                <h2 className="text-base font-semibold text-foreground">
                                    {provisioned.accounts.length} akun unit
                                    dibuat
                                </h2>
                                <p className="text-[13px] text-muted-foreground">
                                    Kata sandi awal semua akun:{' '}
                                    <code className="rounded bg-card px-1.5 py-0.5 font-mono text-[13px] font-semibold text-foreground">
                                        {provisioned.password}
                                    </code>{' '}
                                    — minta pengguna menggantinya setelah
                                    masuk.
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[13px]">
                                <thead>
                                    <tr>
                                        {['Nama', 'E-mail', 'Role'].map(
                                            (column) => (
                                                <th
                                                    key={column}
                                                    className="border border-border bg-card px-2 py-1.5 text-left font-semibold text-foreground"
                                                >
                                                    {column}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {provisioned.accounts.map((account) => (
                                        <tr key={account.email}>
                                            <td className="border border-border bg-card px-2 py-1 text-foreground">
                                                {account.name}
                                            </td>
                                            <td className="border border-border bg-card px-2 py-1 font-mono text-foreground">
                                                {account.email}
                                            </td>
                                            <td className="border border-border bg-card px-2 py-1 text-foreground">
                                                {account.role}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                <section className="grid gap-4 rounded-md border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Detail label="Status Operasi">
                        <StatusBadge tone={unit.status_tone}>
                            {unit.status_label}
                        </StatusBadge>
                    </Detail>
                    <Detail label="Struktur Team Leader">
                        {unit.tl_structure_label ?? '—'}
                    </Detail>
                    <Detail label="Input Operasi oleh Operator">
                        {unit.operator_pengusahaan ? 'Aktif' : 'Tidak aktif'}
                    </Detail>
                    <Detail label="Kontrak">
                        {unit.contract_label ?? 'Belum ditentukan'}
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
