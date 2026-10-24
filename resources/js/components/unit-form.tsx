import { Form, Link } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import units from '@/routes/admin/units';
import type { FormAction, IdName, Option, UnitRow } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    unit?: UnitRow;
    options: {
        serviceUnits: IdName[];
        types: Option[];
        contracts: Option[];
        statuses: Option[];
    };
    submitLabel: string;
};

const NO_SERVICE_UNIT = 'none';
const NO_CONTRACT = 'none';

export function UnitForm({ action, unit, options, submitLabel }: Props) {
    return (
        <Form
            {...action}
            transform={(data) => ({
                ...data,
                service_unit_id:
                    data.service_unit_id === NO_SERVICE_UNIT
                        ? ''
                        : data.service_unit_id,
                contract: data.contract === NO_CONTRACT ? '' : data.contract,
            })}
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Unit
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Kode Unit"
                                htmlFor="code"
                                required
                                hint="Kode unik, contoh: PLTD-KOLAKA"
                                error={errors.code}
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={unit?.code ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Nama Unit"
                                htmlFor="name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={unit?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Tipe Pembangkit"
                                required
                                error={errors.type}
                            >
                                <Select
                                    name="type"
                                    defaultValue={
                                        unit?.type ?? options.types[0]?.value
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.types.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                                {type.description && (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        — {type.description}
                                                    </span>
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Kontrak"
                                hint="Paket kontrak unit, untuk pembagian tanggung jawab."
                                error={errors.contract}
                            >
                                <Select
                                    name="contract"
                                    defaultValue={unit?.contract ?? NO_CONTRACT}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih kontrak" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_CONTRACT}>
                                            Belum ditentukan
                                        </SelectItem>
                                        {options.contracts.map((contract) => (
                                            <SelectItem
                                                key={contract.value}
                                                value={contract.value}
                                            >
                                                {contract.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Unit Layanan (UL)"
                                hint="Kosongkan bila unit dikelola langsung UP Kendari."
                                error={errors.service_unit_id}
                            >
                                <Select
                                    name="service_unit_id"
                                    defaultValue={
                                        unit?.service_unit_id
                                            ? String(unit.service_unit_id)
                                            : NO_SERVICE_UNIT
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit layanan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SERVICE_UNIT}>
                                            Tanpa UL (UP Kendari)
                                        </SelectItem>
                                        {options.serviceUnits.map(
                                            (serviceUnit) => (
                                                <SelectItem
                                                    key={serviceUnit.id}
                                                    value={String(
                                                        serviceUnit.id,
                                                    )}
                                                >
                                                    {serviceUnit.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Operasional
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Status Operasi"
                                required
                                error={errors.status}
                            >
                                <Select
                                    name="status"
                                    defaultValue={
                                        unit?.status ??
                                        options.statuses[0]?.value
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.statuses.map((status) => (
                                            <SelectItem
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Kapasitas Terpasang (MW)"
                                htmlFor="installed_capacity_mw"
                                error={errors.installed_capacity_mw}
                            >
                                <Input
                                    id="installed_capacity_mw"
                                    name="installed_capacity_mw"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={
                                        unit?.installed_capacity_mw ?? ''
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Lokasi"
                                htmlFor="location"
                                error={errors.location}
                                hint="Contoh: Kolaka, Sulawesi Tenggara"
                            >
                                <Input
                                    id="location"
                                    name="location"
                                    defaultValue={unit?.location ?? ''}
                                />
                            </FormField>

                            <FormField
                                label="Status Data"
                                error={errors.is_active}
                            >
                                <label className="flex h-9 items-center gap-2 rounded-md border border-border bg-secondary px-3">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={unit?.is_active ?? true}
                                    />
                                    <span className="text-[13px]">
                                        Unit aktif digunakan
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">
                                Hak Akses Unit
                            </h2>
                            <p className="text-[13px] text-muted-foreground">
                                Mengatur menu yang dibuka untuk peran lapangan
                                di unit ini. Izin per peran tetap diatur di Role
                                &amp; Akses.
                            </p>
                        </div>
                        <FormField
                            label="Input Pengusahaan Operasi oleh Operator"
                            hint="Matikan untuk unit containerized: operator & project leader unit ini tidak membuka input Pengusahaan Operasi (TL & Staf tidak terpengaruh)."
                            error={errors.operator_pengusahaan}
                        >
                            <label className="flex min-h-9 items-center gap-2 rounded-md border border-border bg-secondary px-3 py-2">
                                <input
                                    type="hidden"
                                    name="operator_pengusahaan"
                                    value="0"
                                />
                                <Checkbox
                                    name="operator_pengusahaan"
                                    value="1"
                                    defaultChecked={
                                        unit?.operator_pengusahaan ?? true
                                    }
                                />
                                <span className="text-[13px]">
                                    Operator mengisi input Pengusahaan Operasi
                                </span>
                            </label>
                        </FormField>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={units.index()}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
