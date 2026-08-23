import { Head } from '@inertiajs/react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexFilters } from '@/hooks/use-index-filters';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import activityLogs from '@/routes/admin/activity-logs';
import type { ActivityRow, IdName, Option, Paginated } from '@/types';

type Props = {
    logs: Paginated<ActivityRow>;
    filters: {
        search?: string;
        event?: string;
        user_id?: string;
        unit_id?: string;
        from?: string;
        to?: string;
    };
    options: {
        events: Option[];
        units: IdName[];
        users: IdName[];
    };
};

export default function ActivityLogsIndex({ logs, filters, options }: Props) {
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        activityLogs.index().url,
        {
            search: filters.search,
            event: filters.event,
            user_id: filters.user_id,
            unit_id: filters.unit_id,
            from: filters.from,
            to: filters.to,
        },
    );

    return (
        <>
            <Head title="Log Aktivitas" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Log Aktivitas"
                    description="Rekaman aksi pengguna untuk kebutuhan audit dan pemantauan."
                />

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <FilterBar
                        searchValue={values.search ?? ''}
                        onSearchChange={(value) => setSearch('search', value)}
                        searchPlaceholder="Cari keterangan atau nama pengguna…"
                        isFiltered={isFiltered}
                        onReset={reset}
                    >
                        <FilterSelect
                            value={values.event}
                            onChange={(value) => setFilter('event', value)}
                            placeholder="Aksi"
                            allLabel="Semua Aksi"
                            options={options.events}
                        />
                        <FilterSelect
                            value={values.user_id}
                            onChange={(value) => setFilter('user_id', value)}
                            placeholder="Pengguna"
                            allLabel="Semua Pengguna"
                            options={options.users.map((user) => ({
                                value: String(user.id),
                                label: user.name,
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
                        <div className="flex items-center gap-1.5">
                            <Label
                                htmlFor="from"
                                className="text-xs text-muted-foreground"
                            >
                                Dari
                            </Label>
                            <Input
                                id="from"
                                type="date"
                                value={values.from ?? ''}
                                onChange={(event) =>
                                    setFilter(
                                        'from',
                                        event.target.value || undefined,
                                    )
                                }
                                className="h-8 w-36 bg-card text-[13px]"
                            />
                            <Label
                                htmlFor="to"
                                className="text-xs text-muted-foreground"
                            >
                                s.d.
                            </Label>
                            <Input
                                id="to"
                                type="date"
                                value={values.to ?? ''}
                                onChange={(event) =>
                                    setFilter(
                                        'to',
                                        event.target.value || undefined,
                                    )
                                }
                                className="h-8 w-36 bg-card text-[13px]"
                            />
                        </div>
                    </FilterBar>

                    {logs.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada aktivitas'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah filter atau rentang tanggal yang digunakan.'
                                    : 'Aktivitas pengguna akan tercatat di sini.'
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
                                    <TableHead className="w-44">
                                        Waktu
                                    </TableHead>
                                    <TableHead>Pengguna</TableHead>
                                    <TableHead>Aksi</TableHead>
                                    <TableHead>Keterangan</TableHead>
                                    <TableHead>Unit</TableHead>
                                    <TableHead>Alamat IP</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDateTime(log.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            {log.user ? (
                                                <>
                                                    <span className="font-medium">
                                                        {log.user.name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {log.user.email}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Sistem
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge tone={log.tone}>
                                                {log.event_label}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>{log.description}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {log.unit ?? '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {log.ip_address ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <DataPagination meta={logs} />
                </div>
            </div>
        </>
    );
}

ActivityLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Log Aktivitas', href: activityLogs.index() },
    ],
};
