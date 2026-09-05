import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import UnitController from '@/actions/App/Http/Controllers/Admin/UnitController';
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
import units from '@/routes/admin/units';
import type { IdName, Option, Paginated, UnitRow } from '@/types';

type Props = {
    units: Paginated<UnitRow>;
    filters: {
        search?: string;
        type?: string;
        status?: string;
        service_unit_id?: string;
    };
    options: {
        serviceUnits: IdName[];
        types: Option[];
        statuses: Option[];
    };
};

export default function UnitsIndex({ units: page, filters, options }: Props) {
    const { can } = usePermissions();
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        units.index().url,
        {
            search: filters.search,
            type: filters.type,
            status: filters.status,
            service_unit_id: filters.service_unit_id,
        },
    );

    return (
        <>
            <Head title="Unit Pembangkit" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Unit Pembangkit"
                    description="Data seluruh unit pembangkit dalam cakupan akses Anda."
                    actions={
                        can('unit.create') && (
                            <Button asChild>
                                <Link href={units.create()}>
                                    <Plus className="size-4" />
                                    Tambah Unit
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <FilterBar
                        searchValue={values.search ?? ''}
                        onSearchChange={(value) => setSearch('search', value)}
                        searchPlaceholder="Cari nama, kode, atau lokasi…"
                        isFiltered={isFiltered}
                        onReset={reset}
                    >
                        <FilterSelect
                            value={values.service_unit_id}
                            onChange={(value) =>
                                setFilter('service_unit_id', value)
                            }
                            placeholder="Unit Layanan"
                            allLabel="Semua UL"
                            options={options.serviceUnits.map(
                                (serviceUnit) => ({
                                    value: String(serviceUnit.id),
                                    label: serviceUnit.name,
                                }),
                            )}
                        />
                        <FilterSelect
                            value={values.type}
                            onChange={(value) => setFilter('type', value)}
                            placeholder="Tipe"
                            allLabel="Semua Tipe"
                            options={options.types}
                        />
                        <FilterSelect
                            value={values.status}
                            onChange={(value) => setFilter('status', value)}
                            placeholder="Status"
                            allLabel="Semua Status"
                            options={options.statuses}
                        />
                    </FilterBar>

                    {page.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada unit pembangkit'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah kata kunci atau filter yang digunakan.'
                                    : 'Unit pembangkit akan tampil di sini setelah ditambahkan.'
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
                                    <TableHead>Unit</TableHead>
                                    <TableHead>Unit Layanan</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Lokasi</TableHead>
                                    <TableHead className="text-right">
                                        Jumlah Mesin
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Kapasitas (MW)
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((unit) => (
                                    <TableRow key={unit.id}>
                                        <TableCell>
                                            <Link
                                                href={units.show(unit.id)}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {unit.name}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                {unit.code}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {unit.service_unit ?? 'UP Kendari'}
                                        </TableCell>
                                        <TableCell>{unit.type_label}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {unit.location ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {unit.machines_count ? (
                                                <Link
                                                    href={machines.index({
                                                        query: {
                                                            unit_id: String(
                                                                unit.id,
                                                            ),
                                                        },
                                                    })}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {unit.machines_count}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(unit.machines_capacity)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={unit.status_tone}
                                            >
                                                {unit.status_label}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {can('unit.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={units.edit(
                                                                unit.id,
                                                            )}
                                                            aria-label={`Ubah ${unit.name}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('unit.delete') && (
                                                    <ConfirmDeleteDialog
                                                        action={UnitController.destroy.form(
                                                            unit.id,
                                                        )}
                                                        title="Hapus unit pembangkit?"
                                                        description={`Unit ${unit.name} akan dihapus permanen beserta keterkaitan penugasannya.`}
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

UnitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Pembangkit', href: units.index() },
    ],
};
