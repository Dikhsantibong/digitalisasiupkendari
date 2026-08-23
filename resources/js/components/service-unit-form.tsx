import { Form, Link } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import serviceUnits from '@/routes/admin/service-units';
import type { FormAction, ServiceUnitRow } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    serviceUnit?: Pick<
        ServiceUnitRow,
        'code' | 'name' | 'description' | 'is_active'
    >;
    submitLabel: string;
};

export function ServiceUnitForm({ action, serviceUnit, submitLabel }: Props) {
    return (
        <Form {...action} className="flex flex-col gap-6">
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Kode UL"
                                htmlFor="code"
                                required
                                hint="Kode unik, contoh: UL-KOLAKA"
                                error={errors.code}
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={serviceUnit?.code ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Nama UL"
                                htmlFor="name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={serviceUnit?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Deskripsi"
                                htmlFor="description"
                                error={errors.description}
                            >
                                <Input
                                    id="description"
                                    name="description"
                                    defaultValue={
                                        serviceUnit?.description ?? ''
                                    }
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
                                            serviceUnit?.is_active ?? true
                                        }
                                    />
                                    <span className="text-[13px]">
                                        Unit layanan aktif
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={serviceUnits.index()}>Batal</Link>
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
