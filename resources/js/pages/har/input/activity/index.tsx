import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ActivityController from '@/actions/App/Http/Controllers/Har/ActivityController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { dashboard } from '@/routes';
import activityRoutes from '@/routes/har/input/activity';
import type { IdName } from '@/types';

type Task = { task_description: string };
type Material = {
    material_name: string;
    part_number: string;
    quantity: string;
    unit_of_measure: string;
};

type Activity = {
    id: number;
    activity_date: string | null;
    engine_id: number | null;
    engine_name: string | null;
    maintenance_type_id: number | null;
    wo_id: number | null;
    wonum: string | null;
    work_result: string | null;
    no_lh05: string | null;
    no_sr: string | null;
    no_tug9: string | null;
    keterangan: string | null;
    tasks: Task[];
    materials: {
        material_name: string;
        part_number: string | null;
        quantity: string | null;
        unit_of_measure: string | null;
    }[];
    tasks_count: number;
    materials_count: number;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    activities: Activity[];
    options: {
        units: IdName[];
        years: number[];
        machines: IdName[];
        maintenance_types: { id: number; code: string; name: string }[];
        work_orders: { id: number; wonum: string }[];
    };
    can_write: boolean;
};

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function ActivitiesInput({ filters, activities, options, can_write }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Activity | null>(null);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            activityRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };
    const openEdit = (activity: Activity) => {
        setEditing(activity);
        setOpen(true);
    };

    return (
        <>
            <Head title="Input HAR — Log Kegiatan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Log Kegiatan HARMES"
                    description="Catatan kegiatan pemeliharaan harian beserta uraian pekerjaan dan material terpakai."
                    actions={
                        can_write && (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Tambah Kegiatan
                            </Button>
                        )
                    }
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
                        options={MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    {activities.length === 0 ? (
                        <EmptyState
                            title="Belum ada kegiatan"
                            description="Tambahkan kegiatan HARMES untuk periode ini."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Mesin</TableHead>
                                    <TableHead>WO</TableHead>
                                    <TableHead>Hasil</TableHead>
                                    <TableHead className="text-right">Uraian</TableHead>
                                    <TableHead className="text-right">Material</TableHead>
                                    {can_write && <TableHead className="w-24 text-right">Aksi</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell>{a.activity_date}</TableCell>
                                        <TableCell>{a.engine_name ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{a.wonum ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{a.work_result ?? '—'}</TableCell>
                                        <TableCell className="text-right tabular-nums">{a.tasks_count}</TableCell>
                                        <TableCell className="text-right tabular-nums">{a.materials_count}</TableCell>
                                        {can_write && (
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(a)} aria-label="Ubah">
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <ConfirmDeleteDialog
                                                        action={ActivityController.destroy.form(a.id)}
                                                        title="Hapus kegiatan?"
                                                        description="Kegiatan beserta uraian & material akan dihapus permanen."
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

            {can_write && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                        <DialogTitle>{editing ? 'Ubah' : 'Tambah'} Kegiatan</DialogTitle>
                        <ActivityForm
                            key={editing?.id ?? 'new'}
                            filters={filters}
                            options={options}
                            editing={editing}
                            onDone={() => setOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

function ActivityForm({
    filters,
    options,
    editing,
    onDone,
}: {
    filters: Props['filters'];
    options: Props['options'];
    editing: Activity | null;
    onDone: () => void;
}) {
    const [form, setForm] = useState({
        activity_date: editing?.activity_date ?? '',
        engine_id: editing?.engine_id ? String(editing.engine_id) : '',
        maintenance_type_id: editing?.maintenance_type_id ? String(editing.maintenance_type_id) : '',
        wo_id: editing?.wo_id ? String(editing.wo_id) : '',
        work_result: editing?.work_result ?? '',
        no_lh05: editing?.no_lh05 ?? '',
        no_sr: editing?.no_sr ?? '',
        no_tug9: editing?.no_tug9 ?? '',
        keterangan: editing?.keterangan ?? '',
    });
    const [tasks, setTasks] = useState<Task[]>(
        editing?.tasks.length ? editing.tasks : [{ task_description: '' }],
    );
    const [materials, setMaterials] = useState<Material[]>(
        editing?.materials.map((m) => ({
            material_name: m.material_name,
            part_number: m.part_number ?? '',
            quantity: m.quantity ?? '',
            unit_of_measure: m.unit_of_measure ?? '',
        })) ?? [],
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const set = (key: keyof typeof form, value: string) =>
        setForm((f) => ({ ...f, [key]: value }));

    const submit = () => {
        setSaving(true);
        const data = {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
            ...form,
            engine_id: form.engine_id ? Number(form.engine_id) : null,
            maintenance_type_id: form.maintenance_type_id ? Number(form.maintenance_type_id) : null,
            wo_id: form.wo_id ? Number(form.wo_id) : null,
            tasks: tasks.filter((t) => t.task_description.trim() !== ''),
            materials: materials.filter((m) => m.material_name.trim() !== ''),
        };

        const opts = {
            preserveScroll: true,
            onSuccess: onDone,
            onError: (e: Record<string, string>) => setErrors(e),
            onFinish: () => setSaving(false),
        };

        if (editing) {
            router.put(activityRoutes.update(editing.id).url, data, opts);
        } else {
            router.post(activityRoutes.store().url, data, opts);
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField label="Tanggal" htmlFor="activity_date" required error={errors.activity_date}>
                    <Input id="activity_date" type="date" value={form.activity_date} onChange={(e) => set('activity_date', e.target.value)} />
                </FormField>
                <FormField label="Mesin" error={errors.engine_id}>
                    <Select value={form.engine_id || undefined} onValueChange={(v) => set('engine_id', v)}>
                        <SelectTrigger className="w-full"><SelectValue placeholder="Pilih mesin" /></SelectTrigger>
                        <SelectContent>
                            {options.machines.map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField label="Jenis HAR" error={errors.maintenance_type_id}>
                    <Select value={form.maintenance_type_id || undefined} onValueChange={(v) => set('maintenance_type_id', v)}>
                        <SelectTrigger className="w-full"><SelectValue placeholder="Pilih jenis" /></SelectTrigger>
                        <SelectContent>
                            {options.maintenance_types.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>{t.code} · {t.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField label="Work Order" error={errors.wo_id}>
                    <Select value={form.wo_id || undefined} onValueChange={(v) => set('wo_id', v)}>
                        <SelectTrigger className="w-full"><SelectValue placeholder="Kaitkan WO (opsional)" /></SelectTrigger>
                        <SelectContent>
                            {options.work_orders.map((w) => (
                                <SelectItem key={w.id} value={String(w.id)}>{w.wonum}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField label="Hasil Pekerjaan" htmlFor="work_result" error={errors.work_result}>
                    <Input id="work_result" value={form.work_result} onChange={(e) => set('work_result', e.target.value)} autoComplete="off" />
                </FormField>
                <FormField label="No. LH-05" htmlFor="no_lh05" error={errors.no_lh05}>
                    <Input id="no_lh05" value={form.no_lh05} onChange={(e) => set('no_lh05', e.target.value)} autoComplete="off" />
                </FormField>
                <FormField label="No. SR" htmlFor="no_sr" error={errors.no_sr}>
                    <Input id="no_sr" value={form.no_sr} onChange={(e) => set('no_sr', e.target.value)} autoComplete="off" />
                </FormField>
                <FormField label="No. TUG-9" htmlFor="no_tug9" error={errors.no_tug9}>
                    <Input id="no_tug9" value={form.no_tug9} onChange={(e) => set('no_tug9', e.target.value)} autoComplete="off" />
                </FormField>
            </div>
            <FormField label="Keterangan" htmlFor="keterangan" error={errors.keterangan}>
                <Input id="keterangan" value={form.keterangan} onChange={(e) => set('keterangan', e.target.value)} autoComplete="off" />
            </FormField>

            <SubList
                title="Uraian Kegiatan"
                addLabel="Tambah uraian"
                onAdd={() => setTasks((t) => [...t, { task_description: '' }])}
            >
                {tasks.map((task, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Input
                            value={task.task_description}
                            placeholder={`Uraian ${index + 1}`}
                            onChange={(e) =>
                                setTasks((current) =>
                                    current.map((t, i) => (i === index ? { task_description: e.target.value } : t)),
                                )
                            }
                            autoComplete="off"
                        />
                        <RemoveButton onClick={() => setTasks((current) => current.filter((_, i) => i !== index))} />
                    </div>
                ))}
            </SubList>

            <SubList
                title="Material Terpakai"
                addLabel="Tambah material"
                onAdd={() =>
                    setMaterials((m) => [...m, { material_name: '', part_number: '', quantity: '', unit_of_measure: '' }])
                }
            >
                {materials.map((material, index) => (
                    <div key={index} className="grid grid-cols-[1fr_1fr_80px_80px_auto] items-center gap-2">
                        <Input
                            value={material.material_name}
                            placeholder="Nama material"
                            onChange={(e) => updateMaterial(setMaterials, index, 'material_name', e.target.value)}
                            autoComplete="off"
                        />
                        <Input
                            value={material.part_number}
                            placeholder="No. part"
                            onChange={(e) => updateMaterial(setMaterials, index, 'part_number', e.target.value)}
                            autoComplete="off"
                        />
                        <Input
                            value={material.quantity}
                            placeholder="Jml"
                            type="number"
                            onChange={(e) => updateMaterial(setMaterials, index, 'quantity', e.target.value)}
                        />
                        <Input
                            value={material.unit_of_measure}
                            placeholder="Satuan"
                            onChange={(e) => updateMaterial(setMaterials, index, 'unit_of_measure', e.target.value)}
                            autoComplete="off"
                        />
                        <RemoveButton onClick={() => setMaterials((current) => current.filter((_, i) => i !== index))} />
                    </div>
                ))}
            </SubList>

            <div className="flex justify-end">
                <Button onClick={submit} disabled={saving}>
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            </div>
        </div>
    );
}

function updateMaterial(
    setMaterials: React.Dispatch<React.SetStateAction<Material[]>>,
    index: number,
    key: keyof Material,
    value: string,
) {
    setMaterials((current) => current.map((m, i) => (i === index ? { ...m, [key]: value } : m)));
}

function SubList({
    title,
    addLabel,
    onAdd,
    children,
}: {
    title: string;
    addLabel: string;
    onAdd: () => void;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-md border border-border p-3">
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-[13px] font-semibold">{title}</h3>
                <Button variant="secondary" size="sm" onClick={onAdd}>
                    <Plus className="size-4" />
                    {addLabel}
                </Button>
            </div>
            <div className="flex flex-col gap-2">{children}</div>
        </div>
    );
}

function RemoveButton({ onClick }: { onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} aria-label="Hapus" className="text-destructive">
            <Trash2 className="size-4" />
        </button>
    );
}

ActivitiesInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Log Kegiatan', href: activityRoutes.index() },
    ],
};
