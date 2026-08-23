import { Form } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import RoleAssignmentController from '@/actions/App/Http/Controllers/Admin/RoleAssignmentController';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
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
import type { AssignmentRow, IdName } from '@/types';

type RoleOption = {
    id: number;
    display_name: string;
    scope: string;
    scope_label: string;
};

type Props = {
    userId: number;
    assignments: AssignmentRow[];
    roles: RoleOption[];
    serviceUnits: IdName[];
    units: IdName[];
    canAssign: boolean;
};

/**
 * Grants and revokes a user's roles. The scope field shown depends on the role
 * chosen, mirroring the rule enforced on the server.
 */
export function RoleAssignmentManager({
    userId,
    assignments,
    roles,
    serviceUnits,
    units,
    canAssign,
}: Props) {
    const [selectedRoleId, setSelectedRoleId] = useState<string>('');

    const selectedRole = roles.find(
        (role) => String(role.id) === selectedRoleId,
    );

    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
                <h2 className="text-base font-semibold text-foreground">
                    Penugasan Role
                </h2>
                <p className="text-[13px] text-muted-foreground">
                    Satu pengguna dapat memegang beberapa penugasan sekaligus.
                </p>
            </div>

            <div className="overflow-hidden rounded-md border border-border bg-card">
                {assignments.length === 0 ? (
                    <EmptyState
                        title="Belum ada penugasan"
                        description="Pengguna ini belum memiliki akses ke unit manapun."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Cakupan</TableHead>
                                {canAssign && (
                                    <TableHead className="w-16 text-right">
                                        Aksi
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {assignments.map((assignment) => (
                                <TableRow key={assignment.id}>
                                    <TableCell className="font-medium">
                                        {assignment.role}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {assignment.scope_label}
                                    </TableCell>
                                    {canAssign && (
                                        <TableCell className="text-right">
                                            <Form
                                                {...RoleAssignmentController.destroy.form(
                                                    [userId, assignment.id],
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        type="submit"
                                                        disabled={processing}
                                                        className="text-destructive hover:text-destructive"
                                                        aria-label={`Cabut ${assignment.role}`}
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                )}
                                            </Form>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            {canAssign && (
                <Form
                    {...RoleAssignmentController.store.form(userId)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-4 rounded-md border border-border bg-card p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <h3 className="text-sm font-semibold text-foreground">
                                Tambah Penugasan
                            </h3>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Role"
                                    required
                                    error={errors.role_id}
                                >
                                    <Select
                                        name="role_id"
                                        value={selectedRoleId}
                                        onValueChange={setSelectedRoleId}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.id}
                                                    value={String(role.id)}
                                                >
                                                    {role.display_name}
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        — {role.scope_label}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                {selectedRole?.scope === 'unit' && (
                                    <FormField
                                        label="Unit Pembangkit"
                                        required
                                        error={errors.unit_id}
                                    >
                                        <Select name="unit_id">
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih unit" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((unit) => (
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
                                )}

                                {selectedRole?.scope === 'service_unit' && (
                                    <FormField
                                        label="Unit Layanan"
                                        required
                                        error={errors.service_unit_id}
                                    >
                                        <Select name="service_unit_id">
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih unit layanan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {serviceUnits.map(
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
                                )}

                                {selectedRole?.scope === 'global' && (
                                    <div className="flex items-end">
                                        <p className="pb-2 text-[13px] text-muted-foreground">
                                            Role global berlaku untuk seluruh UP
                                            Kendari, tanpa perlu memilih unit.
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || selectedRoleId === ''
                                    }
                                >
                                    <Plus className="size-4" />
                                    Tugaskan Role
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
