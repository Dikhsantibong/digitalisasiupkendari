import { Form } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import RoleAssignmentController from '@/actions/App/Http/Controllers/Admin/RoleAssignmentController';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
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

const SCOPE_GROUPS: { key: string; label: string }[] = [
    { key: 'unit', label: 'Cakupan Unit Pembangkit' },
    { key: 'service_unit', label: 'Cakupan Unit Layanan (UL)' },
    { key: 'global', label: 'Cakupan Global (UP Kendari)' },
];

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
    const [selectedUnitId, setSelectedUnitId] = useState<string>('');
    const [selectedServiceUnitId, setSelectedServiceUnitId] =
        useState<string>('');
    const [roleSearch, setRoleSearch] = useState<string>('');
    const [unitSearch, setUnitSearch] = useState<string>('');

    const selectedRole = roles.find(
        (role) => String(role.id) === selectedRoleId,
    );

    const assignedRoleIds = useMemo(
        () => new Set(assignments.map((a) => a.role_id)),
        [assignments],
    );

    const filteredRolesByGroup = useMemo(() => {
        const keyword = roleSearch.trim().toLowerCase();

        return SCOPE_GROUPS.map((group) => ({
            ...group,
            items: roles.filter(
                (role) =>
                    role.scope === group.key &&
                    (String(role.id) === selectedRoleId ||
                        keyword === '' ||
                        role.display_name.toLowerCase().includes(keyword) ||
                        role.scope_label.toLowerCase().includes(keyword)),
            ),
        })).filter((group) => group.items.length > 0);
    }, [roles, roleSearch, selectedRoleId]);

    const filteredUnits = useMemo(() => {
        const keyword = unitSearch.trim().toLowerCase();

        return units.filter(
            (unit) =>
                String(unit.id) === selectedUnitId ||
                keyword === '' ||
                unit.name.toLowerCase().includes(keyword),
        );
    }, [units, unitSearch, selectedUnitId]);

    const handleRoleChange = (value: string) => {
        setSelectedRoleId(value);
        setSelectedUnitId('');
        setSelectedServiceUnitId('');
    };

    const isScopeReady =
        selectedRole !== undefined &&
        (selectedRole.scope === 'global' ||
            (selectedRole.scope === 'unit' && selectedUnitId !== '') ||
            (selectedRole.scope === 'service_unit' &&
                selectedServiceUnitId !== ''));

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
                    onSuccess={() => {
                        setSelectedRoleId('');
                        setSelectedUnitId('');
                        setSelectedServiceUnitId('');
                    }}
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
                                        onValueChange={handleRoleChange}
                                        onOpenChange={(open) => {
                                            if (!open) {
                                                setRoleSearch('');
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih role" />
                                        </SelectTrigger>
                                        <SelectContent className="w-[var(--radix-select-trigger-width)] min-w-[18rem]">
                                            <div
                                                className="sticky top-0 z-10 bg-popover p-1.5 pb-1"
                                                onKeyDown={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <div className="relative">
                                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        value={roleSearch}
                                                        onChange={(e) =>
                                                            setRoleSearch(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Cari nama role..."
                                                        className="h-8 pl-8 text-xs"
                                                    />
                                                </div>
                                            </div>
                                            {filteredRolesByGroup.length ===
                                            0 ? (
                                                <div className="px-2 py-4 text-center text-xs text-muted-foreground">
                                                    Role tidak ditemukan.
                                                </div>
                                            ) : (
                                                filteredRolesByGroup.map(
                                                    (group) => (
                                                        <SelectGroup
                                                            key={group.key}
                                                        >
                                                            <SelectLabel className="bg-muted/50 font-semibold text-foreground">
                                                                {group.label}
                                                            </SelectLabel>
                                                            {group.items.map(
                                                                (role) => (
                                                                    <SelectItem
                                                                        key={
                                                                            role.id
                                                                        }
                                                                        value={String(
                                                                            role.id,
                                                                        )}
                                                                    >
                                                                        <span>
                                                                            {
                                                                                role.display_name
                                                                            }
                                                                        </span>
                                                                        <span className="ml-1 text-xs text-muted-foreground">
                                                                            —{' '}
                                                                            {
                                                                                role.scope_label
                                                                            }
                                                                            {assignedRoleIds.has(
                                                                                role.id,
                                                                            )
                                                                                ? ' (aktif)'
                                                                                : ''}
                                                                        </span>
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectGroup>
                                                    ),
                                                )
                                            )}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                {selectedRole?.scope === 'unit' && (
                                    <FormField
                                        label="Unit Pembangkit"
                                        required
                                        error={errors.unit_id}
                                    >
                                        <Select
                                            name="unit_id"
                                            value={selectedUnitId}
                                            onValueChange={setSelectedUnitId}
                                            onOpenChange={(open) => {
                                                if (!open) {
                                                    setUnitSearch('');
                                                }
                                            }}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih unit pembangkit" />
                                            </SelectTrigger>
                                            <SelectContent className="w-[var(--radix-select-trigger-width)] min-w-[16rem]">
                                                <div
                                                    className="sticky top-0 z-10 bg-popover p-1.5 pb-1"
                                                    onKeyDown={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <div className="relative">
                                                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                        <Input
                                                            value={unitSearch}
                                                            onChange={(e) =>
                                                                setUnitSearch(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Cari unit pembangkit..."
                                                            className="h-8 pl-8 text-xs"
                                                        />
                                                    </div>
                                                </div>
                                                {filteredUnits.length === 0 ? (
                                                    <div className="px-2 py-4 text-center text-xs text-muted-foreground">
                                                        Unit tidak ditemukan.
                                                    </div>
                                                ) : (
                                                    filteredUnits.map(
                                                        (unit) => (
                                                            <SelectItem
                                                                key={unit.id}
                                                                value={String(
                                                                    unit.id,
                                                                )}
                                                            >
                                                                {unit.name}
                                                            </SelectItem>
                                                        ),
                                                    )
                                                )}
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
                                        <Select
                                            name="service_unit_id"
                                            value={selectedServiceUnitId}
                                            onValueChange={
                                                setSelectedServiceUnitId
                                            }
                                        >
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
                                    disabled={processing || !isScopeReady}
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
