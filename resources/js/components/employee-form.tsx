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
import employees from '@/routes/admin/employees';
import type { EmployeeRow, FormAction, IdName } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    employee?: EmployeeRow;
    options: {
        units: IdName[];
    };
    submitLabel: string;
};

const NO_UNIT = 'none';

export function EmployeeForm({ action, employee, options, submitLabel }: Props) {
    return (
        <Form
            {...action}
            transform={(data) => ({
                ...data,
                unit_id: data.unit_id === NO_UNIT ? '' : data.unit_id,
            })}
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Pegawai
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama"
                                htmlFor="name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={employee?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="NID / NIP"
                                htmlFor="nip"
                                hint="Nomor induk pegawai"
                                error={errors.nip}
                            >
                                <Input
                                    id="nip"
                                    name="nip"
                                    defaultValue={employee?.nip ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Unit Pembangkit"
                                hint="Kosongkan bila belum ditempatkan pada unit."
                                error={errors.unit_id}
                            >
                                <Select
                                    name="unit_id"
                                    defaultValue={
                                        employee?.unit_id
                                            ? String(employee.unit_id)
                                            : NO_UNIT
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit pembangkit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_UNIT}>
                                            Tanpa unit
                                        </SelectItem>
                                        {options.units.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Jabatan"
                                htmlFor="position"
                                hint="Contoh: Operator, Site Leader"
                                error={errors.position}
                            >
                                <Input
                                    id="position"
                                    name="position"
                                    defaultValue={employee?.position ?? ''}
                                    autoComplete="off"
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
                                        defaultChecked={
                                            employee?.is_active ?? true
                                        }
                                    />
                                    <span className="text-[13px]">
                                        Pegawai aktif
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={employees.index()}>Batal</Link>
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
