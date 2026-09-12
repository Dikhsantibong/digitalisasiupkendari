import { Form, Link, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
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
import type { FormAction, IdName } from '@/types';

type Option = { value: string | number; label: string };

export type MasterField = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'bool' | 'select' | 'relation';
    required: boolean;
    step?: string;
    default?: boolean;
    options?: Option[];
};

type Row = Record<string, unknown> & { id: number };

type ResourceMeta = {
    slug: string;
    label: string;
    unit_scoped: boolean;
    fields: MasterField[];
};

type Summary = { slug: string; label: string; unit_scoped: boolean };

/** Route/action builders that differ per module (operasi vs har). */
export type MasterRoutes = {
    title: string;
    description: string;
    indexUrl: (slug: string) => string;
    storeAction: (slug: string) => FormAction;
    updateAction: (args: { resource: string; id: number }) => FormAction;
    destroyAction: (args: { resource: string; id: number }) => FormAction;
};

export type MasterScreenProps = {
    resource: ResourceMeta;
    resources: Summary[];
    rows: Row[];
    filters: { unit_id: number | null };
    units: IdName[];
    can_manage: boolean;
    routes: MasterRoutes;
};

const toStr = (value: unknown): string =>
    value === null || value === undefined ? '' : String(value);

function cellValue(field: MasterField, value: unknown) {
    if (field.type === 'bool') {
        return (
            <StatusBadge tone={value ? 'success' : 'neutral'}>
                {value ? 'Ya' : 'Tidak'}
            </StatusBadge>
        );
    }

    if ((field.type === 'select' || field.type === 'relation') && field.options) {
        return (
            field.options.find((o) => String(o.value) === toStr(value))?.label ??
            toStr(value) ??
            '—'
        );
    }

    return toStr(value) || '—';
}

/**
 * The config-driven master CRUD screen shared by every module. The field schema
 * drives the table and the add/edit form; the module supplies its route helpers.
 */
export function MasterScreen({
    resource,
    resources,
    rows,
    filters,
    units,
    can_manage,
    routes,
}: MasterScreenProps) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [open, setOpen] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };
    const openEdit = (row: Row) => {
        setEditing(row);
        setOpen(true);
    };

    return (
        <>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={routes.title}
                    description={routes.description}
                    actions={
                        can_manage && (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {resources.map((r) => (
                        <Button
                            key={r.slug}
                            variant={r.slug === resource.slug ? 'default' : 'secondary'}
                            size="sm"
                            asChild
                        >
                            <Link href={routes.indexUrl(r.slug)}>{r.label}</Link>
                        </Button>
                    ))}
                </div>

                {resource.unit_scoped && (
                    <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                        <OperasiSelect
                            label="Unit"
                            value={filters.unit_id ? String(filters.unit_id) : ''}
                            onChange={(value) =>
                                router.get(
                                    routes.indexUrl(resource.slug),
                                    { unit_id: Number(value) },
                                    { preserveState: true, replace: true },
                                )
                            }
                            options={units.map((u) => ({ value: String(u.id), label: u.name }))}
                        />
                    </div>
                )}

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    {rows.length === 0 ? (
                        <EmptyState
                            title="Belum ada data"
                            description={`Belum ada ${resource.label.toLowerCase()}.`}
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {resource.fields.map((field) => (
                                        <TableHead key={field.key}>{field.label}</TableHead>
                                    ))}
                                    {can_manage && <TableHead className="w-24 text-right">Aksi</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {resource.fields.map((field) => (
                                            <TableCell key={field.key}>
                                                {cellValue(field, row[field.key])}
                                            </TableCell>
                                        ))}
                                        {can_manage && (
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEdit(row)}
                                                        aria-label="Ubah"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <ConfirmDeleteDialog
                                                        action={routes.destroyAction({
                                                            resource: resource.slug,
                                                            id: row.id,
                                                        })}
                                                        title="Hapus data?"
                                                        description="Data master ini akan dihapus permanen."
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            </div>

            {can_manage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogTitle>
                            {editing ? 'Ubah' : 'Tambah'} {resource.label}
                        </DialogTitle>
                        <MasterForm
                            key={editing?.id ?? 'new'}
                            resource={resource}
                            unitId={filters.unit_id}
                            editing={editing}
                            routes={routes}
                            onDone={() => setOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

function MasterForm({
    resource,
    unitId,
    editing,
    routes,
    onDone,
}: {
    resource: ResourceMeta;
    unitId: number | null;
    editing: Row | null;
    routes: MasterRoutes;
    onDone: () => void;
}) {
    const action = editing
        ? routes.updateAction({ resource: resource.slug, id: editing.id })
        : routes.storeAction(resource.slug);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    {resource.unit_scoped && unitId !== null && (
                        <input type="hidden" name="unit_id" value={unitId} />
                    )}

                    {resource.fields.map((field) => (
                        <FieldInput
                            key={field.key}
                            field={field}
                            value={editing ? editing[field.key] : undefined}
                            error={errors[field.key]}
                        />
                    ))}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function FieldInput({
    field,
    value,
    error,
}: {
    field: MasterField;
    value: unknown;
    error?: string;
}) {
    if (field.type === 'bool') {
        const checked = value === undefined ? (field.default ?? true) : Boolean(value);

        return (
            <FormField label={field.label} error={error}>
                <label className="flex h-9 items-center gap-2 rounded-md border border-border bg-secondary px-3">
                    <input type="hidden" name={field.key} value="0" />
                    <Checkbox name={field.key} value="1" defaultChecked={checked} />
                    <span className="text-[13px]">{field.label}</span>
                </label>
            </FormField>
        );
    }

    if (field.type === 'select' || field.type === 'relation') {
        return (
            <FormField label={field.label} required={field.required} error={error}>
                <Select name={field.key} defaultValue={value ? toStr(value) : undefined}>
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder={`Pilih ${field.label.toLowerCase()}`} />
                    </SelectTrigger>
                    <SelectContent>
                        {(field.options ?? []).map((option) => (
                            <SelectItem key={option.value} value={String(option.value)}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>
        );
    }

    const inputType =
        field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';
    const defaultValue =
        field.type === 'date' ? toStr(value).slice(0, 10) : toStr(value);

    return (
        <FormField label={field.label} htmlFor={field.key} required={field.required} error={error}>
            <Input
                id={field.key}
                name={field.key}
                type={inputType}
                step={field.type === 'number' ? field.step : undefined}
                defaultValue={defaultValue}
                autoComplete="off"
            />
        </FormField>
    );
}
