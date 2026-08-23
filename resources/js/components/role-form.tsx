import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
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
import roles from '@/routes/admin/roles';
import type { FormAction, Option, PermissionGroupOption } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    role?: {
        name: string;
        display_name: string;
        scope: string;
        description: string | null;
        is_system: boolean;
        permissions: string[];
    };
    permissionGroups: PermissionGroupOption[];
    scopes: Option[];
    submitLabel: string;
};

export function RoleForm({
    action,
    role,
    permissionGroups,
    scopes,
    submitLabel,
}: Props) {
    const [selected, setSelected] = useState<string[]>(role?.permissions ?? []);
    const isSystem = role?.is_system ?? false;
    const isSuperAdmin = role?.name === 'super_admin';

    const toggle = (permission: string, checked: boolean) => {
        setSelected((current) =>
            checked
                ? [...current, permission]
                : current.filter((name) => name !== permission),
        );
    };

    const toggleGroup = (group: PermissionGroupOption, checked: boolean) => {
        const names = group.permissions.map((permission) => permission.name);

        setSelected((current) =>
            checked
                ? Array.from(new Set([...current, ...names]))
                : current.filter((name) => !names.includes(name)),
        );
    };

    return (
        <Form
            {...action}
            transform={(data) => ({ ...data, permissions: selected })}
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Role
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama Tampilan"
                                htmlFor="display_name"
                                required
                                error={errors.display_name}
                            >
                                <Input
                                    id="display_name"
                                    name="display_name"
                                    defaultValue={role?.display_name ?? ''}
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Nama Sistem"
                                htmlFor="name"
                                required
                                hint={
                                    isSystem
                                        ? 'Role sistem — nama tidak dapat diubah.'
                                        : 'Huruf kecil, angka, dan garis bawah.'
                                }
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={role?.name ?? ''}
                                    readOnly={isSystem}
                                    className={
                                        isSystem ? 'bg-muted' : undefined
                                    }
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Cakupan"
                                required
                                hint={
                                    isSystem
                                        ? 'Role sistem — cakupan tidak dapat diubah.'
                                        : 'Menentukan sampai mana data terlihat.'
                                }
                                error={errors.scope}
                            >
                                <Select
                                    name="scope"
                                    defaultValue={
                                        role?.scope ?? scopes[0]?.value
                                    }
                                    disabled={isSystem}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih cakupan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scopes.map((scope) => (
                                            <SelectItem
                                                key={scope.value}
                                                value={scope.value}
                                            >
                                                {scope.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {isSystem && (
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value={role?.scope ?? ''}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Deskripsi"
                                htmlFor="description"
                                error={errors.description}
                            >
                                <Input
                                    id="description"
                                    name="description"
                                    defaultValue={role?.description ?? ''}
                                />
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-3">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-base font-semibold text-foreground">
                                Matriks Permission
                            </h2>
                            <p className="text-[13px] text-muted-foreground">
                                {isSuperAdmin
                                    ? 'Super Admin selalu memiliki seluruh permission, termasuk yang ditambahkan kemudian. Perubahan di sini tidak membatasi aksesnya.'
                                    : `${selected.length} permission dipilih.`}
                            </p>
                            {errors.permissions && (
                                <p className="text-[13px] text-destructive">
                                    {errors.permissions}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            {permissionGroups.map((group) => {
                                const names = group.permissions.map(
                                    (p) => p.name,
                                );
                                const allChecked = names.every((name) =>
                                    selected.includes(name),
                                );

                                return (
                                    <div
                                        key={group.value}
                                        className="flex flex-col rounded-md border border-border bg-card"
                                    >
                                        <div className="flex items-center justify-between border-b border-border bg-secondary px-3 py-2">
                                            <span className="text-sm font-semibold text-foreground">
                                                {group.label}
                                            </span>
                                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Checkbox
                                                    checked={allChecked}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleGroup(
                                                            group,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Pilih semua ${group.label}`}
                                                />
                                                Pilih semua
                                            </label>
                                        </div>
                                        <ul className="divide-y divide-border">
                                            {group.permissions.map(
                                                (permission) => (
                                                    <li key={permission.name}>
                                                        <label className="flex cursor-pointer items-start gap-2.5 px-3 py-2 transition-colors hover:bg-secondary/60">
                                                            <Checkbox
                                                                className="mt-0.5"
                                                                checked={selected.includes(
                                                                    permission.name,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    toggle(
                                                                        permission.name,
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <span className="flex flex-col">
                                                                <span className="text-[13px] text-foreground">
                                                                    {
                                                                        permission.label
                                                                    }
                                                                </span>
                                                                <span className="font-mono text-xs text-muted-foreground">
                                                                    {
                                                                        permission.name
                                                                    }
                                                                </span>
                                                            </span>
                                                        </label>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={roles.index()}>Batal</Link>
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
