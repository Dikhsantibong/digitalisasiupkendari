import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { SummaryCard } from '@/components/summary-card';
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
import { formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import serviceUnits from '@/routes/admin/service-units';
import units from '@/routes/admin/units';
import type { Tone } from '@/types';

type Props = {
    serviceUnit: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        is_active: boolean;
    };
    units: {
        id: number;
        code: string;
        name: string;
        type_label: string;
        status_label: string;
        status_tone: Tone;
        installed_capacity_mw: string | null;
        is_active: boolean;
    }[];
    managers: {
        id: number;
        name: string;
        email: string;
        position: string | null;
        role: string;
    }[];
};

export default function ServiceUnitShow({
    serviceUnit,
    units: unitRows,
    managers,
}: Props) {
    const { can } = usePermissions();

    const totalCapacity = unitRows.reduce(
        (total, unit) => total + Number(unit.installed_capacity_mw ?? 0),
        0,
    );

    return (
        <>
            <Head title={serviceUnit.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={serviceUnit.name}
                    description={serviceUnit.description ?? serviceUnit.code}
                    actions={
                        can('service_unit.update') && (
                            <Button asChild>
                                <Link href={serviceUnits.edit(serviceUnit.id)}>
                                    <Pencil className="size-4" />
                                    Ubah UL
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Unit Pembangkit"
                        value={unitRows.length}
                    />
                    <SummaryCard
                        label="Kapasitas Terpasang"
                        value={formatNumber(totalCapacity)}
                        unit="MW"
                    />
                    <SummaryCard
                        label="Personel Terdaftar"
                        value={managers.length}
                    />
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold text-foreground">
                        Unit Pembangkit
                    </h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        {unitRows.length === 0 ? (
                            <EmptyState
                                title="Belum ada unit"
                                description="Belum ada unit pembangkit di bawah unit layanan ini."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Tipe</TableHead>
                                        <TableHead className="text-right">
                                            Kapasitas (MW)
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {unitRows.map((unit) => (
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
                                            <TableCell>
                                                {unit.type_label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(
                                                    unit.installed_capacity_mw,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    tone={unit.status_tone}
                                                >
                                                    {unit.status_label}
                                                </StatusBadge>
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
                        Personel Tingkat UL
                    </h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        {managers.length === 0 ? (
                            <EmptyState
                                title="Belum ada penugasan"
                                description="Belum ada Manager UL yang ditugaskan pada unit layanan ini."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Role</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {managers.map((manager) => (
                                        <TableRow key={manager.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {manager.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {manager.email}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {manager.position ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {manager.role}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

ServiceUnitShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Layanan', href: serviceUnits.index() },
        { title: 'Detail', href: serviceUnits.index() },
    ],
};
