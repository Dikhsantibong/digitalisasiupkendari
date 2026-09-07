import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import MachineController from '@/actions/App/Http/Controllers/Admin/MachineController';
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
import { formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import machines from '@/routes/admin/machines';
import type { IdName, MachineRow, Paginated } from '@/types';

const FUEL_TYPE_LABELS: Record<string, string> = {
    hsd_mfo: 'HSD + MFO',
    hsd_only: 'HSD saja',
};

type Props = {
    machines: Paginated<MachineRow>;
    filters: {
        search?: string;
        unit_id?: string;
    };
    options: {
        units: IdName[];
    };
};

export default function MachinesIndex({
    machines: page,
    filters,
    options,
}: Props) {
    const { can } = usePermissions();
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        machines.index().url,
        {
            search: filters.search,
            unit_id: filters.unit_id,
        },
    );

    return (
        <>
            <Head title="Master Mesin" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Master Mesin"
                    description="Data seluruh mesin pembangkit dalam cakupan akses Anda."
                    actions={
                        can('machine.create') && (
                            <Button asChild>
                                <Link href={machines.create()}>
                                    <Plus className="size-4" />
                                    Tambah Mesin
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <FilterBar
                        searchValue={values.search ?? ''}
                        onSearchChange={(value) => setSearch('search', value)}
                        searchPlaceholder="Cari nama, tipe, atau serial number…"
                        isFiltered={isFiltered}
                        onReset={reset}
                    >
                        <FilterSelect
                            value={values.unit_id}
                            onChange={(value) => setFilter('unit_id', value)}
                            placeholder="Unit Pembangkit"
                            allLabel="Semua Unit"
                            options={options.units.map((unit) => ({
                                value: String(unit.id),
                                label: unit.name,
                            }))}
                        />
                    </FilterBar>

                    {page.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada mesin'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah kata kunci atau filter yang digunakan.'
                                    : 'Mesin akan tampil di sini setelah ditambahkan.'
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
                                    <TableHead>Mesin</TableHead>
                                    <TableHead>Unit Pembangkit</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Bahan Bakar</TableHead>
                                    <TableHead>Serial Number</TableHead>
                                    <TableHead className="text-right">
                                        Kapasitas (kW)
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((machine) => (
                                    <TableRow key={machine.id}>
                                        <TableCell>
                                            {can('machine.update') ? (
                                                <Link
                                                    href={machines.edit(
                                                        machine.id,
                                                    )}
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {machine.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium text-foreground">
                                                    {machine.name}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {machine.unit ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {machine.type ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {machine.fuel_type ? (
                                                (FUEL_TYPE_LABELS[
                                                    machine.fuel_type
                                                ] ?? machine.fuel_type)
                                            ) : (
                                                <StatusBadge tone="warning">
                                                    Lengkapi
                                                </StatusBadge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {machine.serial_number ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(machine.capacity_kw)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={
                                                    machine.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {machine.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {can('machine.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={machines.edit(
                                                                machine.id,
                                                            )}
                                                            aria-label={`Ubah ${machine.name}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('machine.delete') && (
                                                    <ConfirmDeleteDialog
                                                        action={MachineController.destroy.form(
                                                            machine.id,
                                                        )}
                                                        title="Hapus mesin?"
                                                        description={`Mesin ${machine.name} akan dihapus permanen.`}
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

MachinesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Mesin', href: machines.index() },
    ],
};
