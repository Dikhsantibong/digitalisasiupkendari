import { Form, Head, router } from '@inertiajs/react';
import FuelReceiptController from '@/actions/App/Http/Controllers/Operasi/FuelReceiptController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
import type { IdName, Option } from '@/types';

type Receipt = {
    id: number;
    report_date: string;
    fuel_type: string;
    fuel_type_label: string;
    supplier: string | null;
    do_number: string | null;
    volume_liter: string;
    price_per_liter: string | null;
    keterangan: string | null;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    receipts: Receipt[];
    totals: { hsd: number; mfo: number };
    options: { units: IdName[]; years: number[]; fuel_types: Option[] };
    can_write: boolean;
};

export default function FuelReceipts({
    filters,
    receipts,
    totals,
    options,
    can_write,
}: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            fuelReceipt.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Input Operasi — Penerimaan BBM" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Penerimaan BBM"
                    description="Register penerimaan bahan bakar dari pemasok (input manual)."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-md border border-border bg-card p-3">
                        <p className="text-[13px] text-muted-foreground">Total HSD (L)</p>
                        <p className="text-lg font-semibold tabular-nums">
                            {formatNumber(String(totals.hsd))}
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-3">
                        <p className="text-[13px] text-muted-foreground">Total MFO (L)</p>
                        <p className="text-lg font-semibold tabular-nums">
                            {formatNumber(String(totals.mfo))}
                        </p>
                    </div>
                </div>

                {can_write && (
                    <AddReceiptForm
                        unitId={filters.unit_id}
                        fuelTypes={options.fuel_types}
                    />
                )}

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    {receipts.length === 0 ? (
                        <EmptyState
                            title="Belum ada penerimaan"
                            description="Tambahkan penerimaan BBM untuk periode terpilih."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Pemasok</TableHead>
                                    <TableHead>No. DO</TableHead>
                                    <TableHead className="text-right">Volume (L)</TableHead>
                                    <TableHead className="text-right">Harga/L</TableHead>
                                    {can_write && <TableHead className="w-12" />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.map((receipt) => (
                                    <TableRow key={receipt.id}>
                                        <TableCell>{receipt.report_date}</TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={receipt.fuel_type === 'hsd' ? 'info' : 'warning'}
                                            >
                                                {receipt.fuel_type_label}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {receipt.supplier ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {receipt.do_number ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(receipt.volume_liter)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(receipt.price_per_liter)}
                                        </TableCell>
                                        {can_write && (
                                            <TableCell>
                                                <ConfirmDeleteDialog
                                                    action={FuelReceiptController.destroy.form(
                                                        receipt.id,
                                                    )}
                                                    title="Hapus penerimaan?"
                                                    description="Data penerimaan BBM ini akan dihapus permanen."
                                                />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            </div>
        </>
    );
}

function AddReceiptForm({
    unitId,
    fuelTypes,
}: {
    unitId: number;
    fuelTypes: Option[];
}) {
    return (
        <Form
            {...FuelReceiptController.store.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="rounded-md border border-border bg-card p-4"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="unit_id" value={unitId} />
                    <h2 className="mb-3 text-base font-semibold text-foreground">
                        Tambah Penerimaan
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                        <FormField label="Tanggal" htmlFor="report_date" required error={errors.report_date}>
                            <Input id="report_date" name="report_date" type="date" required />
                        </FormField>
                        <FormField label="Jenis BBM" required error={errors.fuel_type}>
                            <Select name="fuel_type">
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih jenis" />
                                </SelectTrigger>
                                <SelectContent>
                                    {fuelTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Volume (L)" htmlFor="volume_liter" required error={errors.volume_liter}>
                            <Input id="volume_liter" name="volume_liter" type="number" step="0.01" min="0" required />
                        </FormField>
                        <FormField label="Pemasok" htmlFor="supplier" error={errors.supplier}>
                            <Input id="supplier" name="supplier" autoComplete="off" />
                        </FormField>
                        <FormField label="No. DO" htmlFor="do_number" error={errors.do_number}>
                            <Input id="do_number" name="do_number" autoComplete="off" />
                        </FormField>
                        <FormField label="Tgl Bongkar" htmlFor="unloading_date" error={errors.unloading_date}>
                            <Input id="unloading_date" name="unloading_date" type="date" />
                        </FormField>
                        <FormField label="Harga/L" htmlFor="price_per_liter" error={errors.price_per_liter}>
                            <Input id="price_per_liter" name="price_per_liter" type="number" step="0.01" min="0" />
                        </FormField>
                        <div className="flex items-end">
                            <Button type="submit" disabled={processing}>
                                Tambah
                            </Button>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

FuelReceipts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penerimaan BBM', href: fuelReceipt.index() },
    ],
};
