import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ServiceUnitController from '@/actions/App/Http/Controllers/Admin/ServiceUnitController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
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
import { dashboard } from '@/routes';
import serviceUnits from '@/routes/admin/service-units';
import type { Paginated, ServiceUnitRow } from '@/types';

type Props = {
    serviceUnits: Paginated<ServiceUnitRow>;
    filters: { search?: string };
    unassignedUnits: number;
};

export default function ServiceUnitsIndex({
    serviceUnits: page,
    filters,
    unassignedUnits,
}: Props) {
    const { can } = usePermissions();
    const { values, setSearch, reset, isFiltered } = useIndexFilters(
        serviceUnits.index().url,
        { search: filters.search },
    );

    return (
        <>
            <Head title="Unit Layanan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Unit Layanan (UL)"
                    description="Kelompok unit pembangkit yang dipimpin oleh seorang Manager UL."
                    actions={
                        can('service_unit.create') && (
                            <Button asChild>
                                <Link href={serviceUnits.create()}>
                                    <Plus className="size-4" />
                                    Tambah UL
                                </Link>
                            </Button>
                        )
                    }
                />

                {unassignedUnits > 0 && (
                    <div className="rounded-md border border-border bg-secondary px-4 py-3 text-[13px] text-foreground">
                        <span className="font-medium">
                            {unassignedUnits} unit pembangkit
                        </span>{' '}
                        belum dipetakan ke unit layanan manapun dan saat ini
                        dikelola langsung oleh UP Kendari.
                    </div>
                )}

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <FilterBar
                        searchValue={values.search ?? ''}
                        onSearchChange={(value) => setSearch('search', value)}
                        searchPlaceholder="Cari nama atau kode UL…"
                        isFiltered={isFiltered}
                        onReset={reset}
                    />

                    {page.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada unit layanan'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah kata kunci pencarian yang digunakan.'
                                    : 'Unit layanan akan tampil di sini setelah ditambahkan.'
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
                                    <TableHead>Unit Layanan</TableHead>
                                    <TableHead>Deskripsi</TableHead>
                                    <TableHead className="text-right">
                                        Jumlah Unit
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((serviceUnit) => (
                                    <TableRow key={serviceUnit.id}>
                                        <TableCell>
                                            <Link
                                                href={serviceUnits.show(
                                                    serviceUnit.id,
                                                )}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {serviceUnit.name}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                {serviceUnit.code}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {serviceUnit.description ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {serviceUnit.units_count}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={
                                                    serviceUnit.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {serviceUnit.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {can('service_unit.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={serviceUnits.edit(
                                                                serviceUnit.id,
                                                            )}
                                                            aria-label={`Ubah ${serviceUnit.name}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('service_unit.delete') && (
                                                    <ConfirmDeleteDialog
                                                        action={ServiceUnitController.destroy.form(
                                                            serviceUnit.id,
                                                        )}
                                                        title="Hapus unit layanan?"
                                                        description={`${serviceUnit.name} akan dihapus. ${serviceUnit.units_count} unit pembangkit di bawahnya akan menjadi tanpa induk.`}
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

ServiceUnitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Layanan', href: serviceUnits.index() },
    ],
};
