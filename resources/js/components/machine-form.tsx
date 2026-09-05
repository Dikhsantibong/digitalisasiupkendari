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
import machines from '@/routes/admin/machines';
import type { FormAction, IdName, MachineRow } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    machine?: MachineRow;
    options: {
        units: IdName[];
    };
    submitLabel: string;
};

export function MachineForm({ action, machine, options, submitLabel }: Props) {
    return (
        <Form {...action} className="flex flex-col gap-6">
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Mesin
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama Mesin"
                                htmlFor="name"
                                required
                                hint="Contoh: MAK #1"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={machine?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Unit Pembangkit"
                                required
                                error={errors.unit_id}
                            >
                                <Select
                                    name="unit_id"
                                    defaultValue={
                                        machine?.unit_id
                                            ? String(machine.unit_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit pembangkit" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                label="Tipe"
                                htmlFor="type"
                                hint="Contoh: 8 M 453 AK"
                                error={errors.type}
                            >
                                <Input
                                    id="type"
                                    name="type"
                                    defaultValue={machine?.type ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Serial Number"
                                htmlFor="serial_number"
                                error={errors.serial_number}
                            >
                                <Input
                                    id="serial_number"
                                    name="serial_number"
                                    defaultValue={machine?.serial_number ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Kapasitas (kW)"
                                htmlFor="capacity_kw"
                                error={errors.capacity_kw}
                            >
                                <Input
                                    id="capacity_kw"
                                    name="capacity_kw"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={machine?.capacity_kw ?? ''}
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
                                        defaultChecked={machine?.is_active ?? true}
                                    />
                                    <span className="text-[13px]">
                                        Mesin aktif digunakan
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={machines.index()}>Batal</Link>
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
