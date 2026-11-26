import { Head, Link } from '@inertiajs/react';
import { Download, FileSpreadsheet, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Admin/EmployeeController';
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
import { downloadK3InputWorkbook } from '@/lib/k3-input-excel';
import type { K3InputExport } from '@/lib/k3-input-excel';
import { dashboard } from '@/routes';
import employees from '@/routes/admin/employees';
import type { EmployeeRow, IdName, Paginated } from '@/types';

type Props = {
    employees: Paginated<EmployeeRow>;
    filters: {
        search?: string;
        unit_id?: string;
        service_unit_id?: string;
        position?: string;
        regu?: string;
    };
    options: {
        units: IdName[];
        service_units?: IdName[];
        positions: string[];
    };
};

export default function EmployeesIndex({
    employees: page,
    filters,
    options,
}: Props) {
    const { can } = usePermissions();
    const [exporting, setExporting] = useState(false);
    const { values, setFilter, setSearch, reset, isFiltered } = useIndexFilters(
        employees.index().url,
        {
            search: filters.search,
            unit_id: filters.unit_id,
            service_unit_id: filters.service_unit_id,
            position: filters.position,
            regu: filters.regu,
        },
    );

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
        window.open(
            `/admin/employees/export/pdf${buildExportQuery()}`,
            '_blank',
        );
    };

    const handleDownloadExcel = async () => {
        setExporting(true);

        try {
            const response = await fetch(
                `/admin/employees/export/data${buildExportQuery()}`,
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
            toast.error('Gagal membuat file Excel data pegawai.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title="Master Pegawai" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Master Pegawai"
                    description="Data pegawai dan penempatannya pada unit pembangkit atau unit layanan."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
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
                            {can('employee.create') && (
                                <Button size="sm" asChild>
                                    <Link href={employees.create()}>
                                        <Plus className="size-4" />
                                        Tambah Pegawai
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
                        searchPlaceholder="Cari nama, NID/NIP, atau jabatan…"
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
                        {options.service_units &&
                            options.service_units.length > 0 && (
                                <FilterSelect
                                    value={values.service_unit_id}
                                    onChange={(value) =>
                                        setFilter('service_unit_id', value)
                                    }
                                    placeholder="Unit Layanan"
                                    allLabel="Semua UL"
                                    options={options.service_units.map(
                                        (su) => ({
                                            value: String(su.id),
                                            label: su.name,
                                        }),
                                    )}
                                />
                            )}
                        <FilterSelect
                            value={values.position}
                            onChange={(value) => setFilter('position', value)}
                            placeholder="Jabatan"
                            allLabel="Semua Jabatan"
                            options={(options.positions ?? []).map(
                                (position) => ({
                                    value: position,
                                    label: position,
                                }),
                            )}
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
                    </FilterBar>

                    {page.data.length === 0 ? (
                        <EmptyState
                            title={
                                isFiltered
                                    ? 'Tidak ada hasil'
                                    : 'Belum ada pegawai'
                            }
                            description={
                                isFiltered
                                    ? 'Ubah kata kunci atau filter yang digunakan.'
                                    : 'Pegawai akan tampil di sini setelah ditambahkan.'
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
                                    <TableHead>Nama</TableHead>
                                    <TableHead>NID/NIP</TableHead>
                                    <TableHead>Penempatan / Unit</TableHead>
                                    <TableHead>Jabatan</TableHead>
                                    <TableHead className="w-28 text-center">
                                        Tanda Tangan
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((employee) => (
                                    <TableRow key={employee.id}>
                                        <TableCell>
                                            {can('employee.update') ? (
                                                <Link
                                                    href={employees.edit(
                                                        employee.id,
                                                    )}
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {employee.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium text-foreground">
                                                    {employee.name}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {employee.nip ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {employee.service_unit ? (
                                                <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                                    <span className="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary">
                                                        UL
                                                    </span>
                                                    {employee.service_unit}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {employee.unit ?? '—'}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                <span>
                                                    {employee.position ?? '—'}
                                                </span>
                                                {employee.regu && (
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        <StatusBadge tone="info">
                                                            Regu {employee.regu}
                                                        </StatusBadge>
                                                        {employee.is_shift_leader && (
                                                            <StatusBadge tone="warning">
                                                                Leader Shift
                                                            </StatusBadge>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {employee.signature_url ? (
                                                <div
                                                    className="inline-flex items-center justify-center rounded border border-border bg-white px-1.5 py-0.5 shadow-xs"
                                                    title={`Tanda tangan ${employee.name}`}
                                                >
                                                    <img
                                                        src={
                                                            employee.signature_url
                                                        }
                                                        alt={`Tanda tangan ${employee.name}`}
                                                        className="h-7 max-w-[80px] object-contain"
                                                    />
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">
                                                    Belum ada
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={
                                                    employee.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {employee.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {can('employee.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={employees.edit(
                                                                employee.id,
                                                            )}
                                                            aria-label={`Ubah ${employee.name}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('employee.delete') && (
                                                    <ConfirmDeleteDialog
                                                        action={EmployeeController.destroy.form(
                                                            employee.id,
                                                        )}
                                                        title="Hapus pegawai?"
                                                        description={`Data pegawai ${employee.name} akan dihapus permanen.`}
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

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Pegawai', href: employees.index() },
    ],
};
