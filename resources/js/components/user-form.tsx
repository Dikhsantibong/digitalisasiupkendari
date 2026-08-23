import { Form, Link } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import users from '@/routes/admin/users';
import type { FormAction, UserRow } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    user?: UserRow;
    submitLabel: string;
};

export function UserForm({ action, user, submitLabel }: Props) {
    const isEditing = user !== undefined;

    return (
        <Form {...action} className="flex flex-col gap-6">
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama Lengkap"
                                htmlFor="name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={user?.name ?? ''}
                                    autoComplete="name"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="NIP"
                                htmlFor="employee_id"
                                hint="Nomor induk pegawai, bila ada."
                                error={errors.employee_id}
                            >
                                <Input
                                    id="employee_id"
                                    name="employee_id"
                                    defaultValue={user?.employee_id ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Email"
                                htmlFor="email"
                                required
                                hint="Digunakan sebagai akun masuk."
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={user?.email ?? ''}
                                    autoComplete="email"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Jabatan"
                                htmlFor="position"
                                error={errors.position}
                            >
                                <Input
                                    id="position"
                                    name="position"
                                    defaultValue={user?.position ?? ''}
                                />
                            </FormField>

                            <FormField
                                label="Nomor Telepon"
                                htmlFor="phone"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    defaultValue={user?.phone ?? ''}
                                    autoComplete="tel"
                                />
                            </FormField>

                            <FormField
                                label="Status Akun"
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
                                        defaultChecked={user?.is_active ?? true}
                                    />
                                    <span className="text-[13px]">
                                        Akun aktif dan dapat masuk
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-base font-semibold text-foreground">
                                Kata Sandi
                            </h2>
                            {isEditing && (
                                <p className="text-[13px] text-muted-foreground">
                                    Biarkan kosong bila kata sandi tidak diubah.
                                </p>
                            )}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Kata Sandi"
                                htmlFor="password"
                                required={!isEditing}
                                error={errors.password}
                            >
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    required={!isEditing}
                                />
                            </FormField>

                            <FormField
                                label="Konfirmasi Kata Sandi"
                                htmlFor="password_confirmation"
                                required={!isEditing}
                            >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    required={!isEditing}
                                />
                            </FormField>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={users.index()}>Batal</Link>
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
