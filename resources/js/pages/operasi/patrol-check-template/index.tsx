import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckSquare,
    ClipboardCheck,
    Cpu,
    FileText,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import patrolCheckTemplate from '@/routes/operasi/patrol-check-template';

type PatrolFormat = 'monthly' | 'checklist';

type ParamOption = {
    key: string;
    label: string;
};

type TemplateMeta = {
    title: string;
    form_number: string;
    revision: string;
    instruction: string;
};

type RawItem = {
    system: string;
    peralatan: string;
    params?: Record<string, boolean>;
};

type EditableItem = {
    key: string;
    system: string;
    peralatan: string;
    params: Record<string, boolean>;
};

type Template = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_default: boolean;
    format: PatrolFormat;
    meta: TemplateMeta;
    items: RawItem[];
};

type MachineOption = {
    id: number;
    name: string;
    unit: string | null;
    template_id: number | null;
};

type Props = {
    templates: Template[];
    machines: MachineOption[];
    param_options: ParamOption[];
    default_monthly_items_count: number;
    can_manage: boolean;
};

type Tab = 'format' | 'items' | 'mesin';

let seq = 0;
const nextKey = () => `pc-${++seq}`;

const defaultParamsMap = (
    options: ParamOption[],
    raw?: Record<string, boolean>,
): Record<string, boolean> => {
    const out: Record<string, boolean> = {};

    for (const opt of options) {
        out[opt.key] = raw ? Boolean(raw[opt.key]) : true;
    }

    return out;
};

export default function PatrolCheckTemplateIndex({
    templates,
    machines,
    param_options,
    default_monthly_items_count,
    can_manage,
}: Props) {
    const selectedFromQuery =
        typeof window !== 'undefined'
            ? Number(
                  new URLSearchParams(window.location.search).get('template'),
              )
            : 0;
    const initial =
        templates.find((t) => t.id === selectedFromQuery) ?? templates[0];
    const [selectedId, setSelectedId] = useState<number>(initial?.id ?? 0);
    const current =
        templates.find((t) => t.id === selectedId) ?? templates[0] ?? null;

    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [copyFrom, setCopyFrom] = useState<string>(
        String(templates[0]?.id ?? ''),
    );
    const [newFormat, setNewFormat] = useState<PatrolFormat>('checklist');
    const [busyCreate, setBusyCreate] = useState(false);

    const submitCreate = () => {
        if (!newName.trim()) {
            return;
        }

        setBusyCreate(true);
        router.post(
            patrolCheckTemplate.store().url,
            {
                name: newName.trim(),
                copy_from: copyFrom === 'none' ? null : Number(copyFrom),
                format: copyFrom === 'none' ? newFormat : null,
            },
            {
                onSuccess: (page) => {
                    const list = (page.props as unknown as Props).templates;
                    const created = list[list.length - 1];

                    if (created) {
                        setSelectedId(created.id);
                    }

                    setCreating(false);
                    setNewName('');
                },
                onFinish: () => setBusyCreate(false),
            },
        );
    };

    const machineCount = (id: number) =>
        machines.filter((m) => m.template_id === id).length;

    return (
        <>
            <Head title="Template Patrol Check" />
            <div className="flex flex-col gap-4 p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">
                            Template Patrol Check
                        </h1>
                        <p className="text-[13px] text-muted-foreground">
                            Atur format form (Bulanan N/T vs Pemeriksaan per
                            Shift) dan komponen cek tiap jenis mesin atau unit.
                            Mesin tanpa template memakai template bawaan.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[270px_minmax(0,1fr)]">
                    {/* Sidebar list */}
                    <aside className="flex flex-col gap-2">
                        {templates.map((t) => {
                            const active = current?.id === t.id;
                            const count = machineCount(t.id);
                            const itemCount =
                                t.items.length > 0
                                    ? t.items.length
                                    : t.format === 'monthly'
                                      ? default_monthly_items_count
                                      : 0;

                            return (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => setSelectedId(t.id)}
                                    className={cn(
                                        'flex flex-col gap-1.5 rounded-lg border p-3 text-left transition',
                                        active
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'border-border bg-card hover:bg-muted/40',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="text-[13.5px] font-semibold">
                                            {t.name}
                                        </span>
                                        {t.is_default && (
                                            <Badge variant="secondary">
                                                Bawaan
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Badge
                                            variant="outline"
                                            className="text-[11px]"
                                        >
                                            {t.format === 'checklist'
                                                ? 'Per Shift / Parameter'
                                                : 'Bulanan N/T'}
                                        </Badge>
                                        <span className="text-[11.5px] text-muted-foreground">
                                            {itemCount} item · {count} mesin
                                        </span>
                                    </div>
                                </button>
                            );
                        })}

                        {can_manage && (
                            <Button
                                variant="outline"
                                onClick={() => setCreating(true)}
                                className="justify-start gap-2"
                            >
                                <Plus className="size-4" />
                                Template Baru
                            </Button>
                        )}
                    </aside>

                    {current && (
                        <TemplateEditor
                            key={current.id}
                            template={current}
                            templates={templates}
                            machines={machines}
                            paramOptions={param_options}
                            defaultMonthlyCount={default_monthly_items_count}
                            canManage={can_manage}
                            onDeleted={() =>
                                setSelectedId(templates[0]?.id ?? 0)
                            }
                        />
                    )}
                </div>
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Template Patrol Check</DialogTitle>
                        <DialogDescription>
                            Buat template kosong baru atau salin daftar item
                            dari template yang sudah ada.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-3">
                        <label className="flex flex-col gap-1 text-[13px] font-medium">
                            Nama Template
                            <Input
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                placeholder="mis. SWD Kolaka / Wärtsilä"
                                autoFocus
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-[13px] font-medium">
                            Salin Dari
                            <Select
                                value={copyFrom}
                                onValueChange={setCopyFrom}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Kosong (mulai dari awal)
                                    </SelectItem>
                                    {templates.map((t) => (
                                        <SelectItem
                                            key={t.id}
                                            value={String(t.id)}
                                        >
                                            {t.name} (
                                            {t.format === 'checklist'
                                                ? 'Per Shift'
                                                : 'Bulanan'}
                                            )
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                        {copyFrom === 'none' && (
                            <label className="flex flex-col gap-1 text-[13px] font-medium">
                                Format Form
                                <Select
                                    value={newFormat}
                                    onValueChange={(v) =>
                                        setNewFormat(v as PatrolFormat)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="checklist">
                                            Per Shift / Parameter
                                            (POA-OPR-CHK-01)
                                        </SelectItem>
                                        <SelectItem value="monthly">
                                            Bulanan 1–31 (N / T)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCreating(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={submitCreate}
                            disabled={busyCreate || !newName.trim()}
                        >
                            {busyCreate ? 'Membuat…' : 'Buat Template'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function TemplateEditor({
    template,
    templates,
    machines,
    paramOptions,
    defaultMonthlyCount,
    canManage,
    onDeleted,
}: {
    template: Template;
    templates: Template[];
    machines: MachineOption[];
    paramOptions: ParamOption[];
    defaultMonthlyCount: number;
    canManage: boolean;
    onDeleted: () => void;
}) {
    const [tab, setTab] = useState<Tab>('items');
    const [name, setName] = useState(template.name);
    const [description, setDescription] = useState(template.description ?? '');
    const [format, setFormat] = useState<PatrolFormat>(template.format);
    const [meta, setMeta] = useState<TemplateMeta>(template.meta);
    const [items, setItems] = useState<EditableItem[]>(() =>
        template.items.map((item) => ({
            key: nextKey(),
            system: item.system,
            peralatan: item.peralatan,
            params: defaultParamsMap(paramOptions, item.params),
        })),
    );
    const [machineIds, setMachineIds] = useState<number[]>(() =>
        machines.filter((m) => m.template_id === template.id).map((m) => m.id),
    );
    const [saving, setSaving] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const readOnly = !canManage;

    const machinesByUnit = useMemo(() => {
        const groups = new Map<string, MachineOption[]>();

        for (const m of machines) {
            const label = m.unit ?? 'Tanpa Unit';
            const list = groups.get(label) ?? [];
            list.push(m);
            groups.set(label, list);
        }

        return Array.from(groups.entries());
    }, [machines]);

    const otherTemplateName = (id: number | null) =>
        id === null || id === template.id
            ? null
            : templates.find((t) => t.id === id)?.name;

    const save = () => {
        setSaving(true);
        setErrors({});
        router.put(
            patrolCheckTemplate.update(template.id).url,
            {
                name,
                description: description || null,
                format,
                meta,
                items: items.map(({ system, peralatan, params }) => ({
                    system,
                    peralatan,
                    params: format === 'checklist' ? params : undefined,
                })),
                machine_ids: machineIds,
            },
            {
                preserveScroll: true,
                onError: (e) => setErrors(e as Record<string, string>),
                onFinish: () => setSaving(false),
            },
        );
    };

    const destroy = () => {
        router.delete(patrolCheckTemplate.destroy(template.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmDelete(false);
                onDeleted();
            },
        });
    };

    const moveItem = (index: number, delta: number) => {
        const target = index + delta;

        if (target < 0 || target >= items.length) {
            return;
        }

        setItems((prev) => {
            const next = [...prev];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const updateItem = (key: string, patch: Partial<EditableItem>) => {
        setItems((prev) =>
            prev.map((it) => (it.key === key ? { ...it, ...patch } : it)),
        );
    };

    const toggleParam = (key: string, paramKey: string, val: boolean) => {
        setItems((prev) =>
            prev.map((it) =>
                it.key === key
                    ? { ...it, params: { ...it.params, [paramKey]: val } }
                    : it,
            ),
        );
    };

    const addItem = () => {
        const lastArea = items[items.length - 1]?.system;
        setItems((prev) => [
            ...prev,
            {
                key: nextKey(),
                system:
                    lastArea || (format === 'checklist' ? 'MESIN' : 'SYSTEM'),
                peralatan: '',
                params: defaultParamsMap(paramOptions),
            },
        ]);
    };

    return (
        <section className="flex min-w-0 flex-col gap-4 rounded-lg border border-border bg-card p-4">
            {/* Top fields & Save */}
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="grid flex-1 gap-3 sm:grid-cols-[240px_1fr]">
                    <label className="flex flex-col gap-1 text-[12px] font-medium text-muted-foreground">
                        Nama Template
                        <Input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            disabled={readOnly}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-[12px] font-medium text-muted-foreground">
                        Keterangan
                        <Input
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            disabled={readOnly}
                            placeholder="Unit atau jenis mesin pemakai"
                        />
                    </label>
                </div>

                {canManage && (
                    <div className="flex items-center gap-2">
                        {!template.is_default && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5 border-destructive/40 text-destructive hover:bg-destructive/10"
                                onClick={() => setConfirmDelete(true)}
                            >
                                <Trash2 className="size-4" />
                                Hapus
                            </Button>
                        )}
                        <Button
                            size="sm"
                            onClick={save}
                            disabled={saving || !name.trim()}
                            className="gap-1.5"
                        >
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan Template'}
                        </Button>
                    </div>
                )}
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="rounded-md border border-destructive/40 bg-destructive/10 p-2.5 text-[12.5px] text-destructive">
                    {Object.values(errors).map((err, i) => (
                        <p key={i}>{err}</p>
                    ))}
                </div>
            )}

            {/* Tabs */}
            <div className="flex flex-wrap gap-1 border-b border-border pb-2">
                {(
                    [
                        [
                            'items',
                            `Komponen & Parameter (${items.length || (format === 'monthly' ? defaultMonthlyCount : 0)})`,
                            CheckSquare,
                        ],
                        ['format', 'Format & Kop Form', FileText],
                        [
                            'mesin',
                            `Mesin Pemakai (${template.is_default ? 'Bawaan' : machineIds.length})`,
                            Cpu,
                        ],
                    ] as const
                ).map(([key, label, Icon]) => (
                    <Button
                        key={key}
                        size="sm"
                        variant={tab === key ? 'default' : 'ghost'}
                        className="gap-1.5"
                        onClick={() => setTab(key)}
                    >
                        <Icon className="size-4" />
                        {label}
                    </Button>
                ))}
            </div>

            {tab === 'format' && (
                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="flex flex-col gap-1.5 text-[12.5px] font-medium">
                            Format Patrol Check
                            <Select
                                value={format}
                                onValueChange={(v) =>
                                    setFormat(v as PatrolFormat)
                                }
                                disabled={readOnly}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">
                                        Bulanan 1–31 (N / T + Pelaksana Shift) —
                                        Containerized
                                    </SelectItem>
                                    <SelectItem value="checklist">
                                        Per Pemeriksaan / Shift (Parameter + N /
                                        TN) — PLTD Poasia
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span className="text-[11.5px] font-normal text-muted-foreground">
                                {format === 'checklist'
                                    ? 'Satu form setiap pemeriksaan (Tanggal, Shift, Jam, Operator) dengan 5 parameter cek dan kolom Catatan / Tindakan Awal.'
                                    : 'Satu lembar matriks per bulan untuk setiap mesin dengan kolom N / T tanggal 1–31 dan inisial Pelaksana Shift.'}
                            </span>
                        </label>
                    </div>

                    {format === 'checklist' && (
                        <div className="grid gap-3 rounded-lg border border-border bg-muted/20 p-4 sm:grid-cols-3">
                            <label className="flex flex-col gap-1 text-[12.5px] font-medium sm:col-span-3">
                                Judul Form
                                <Input
                                    value={meta.title}
                                    onChange={(e) =>
                                        setMeta((m) => ({
                                            ...m,
                                            title: e.target.value,
                                        }))
                                    }
                                    disabled={readOnly}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[12.5px] font-medium">
                                No. Form
                                <Input
                                    value={meta.form_number}
                                    onChange={(e) =>
                                        setMeta((m) => ({
                                            ...m,
                                            form_number: e.target.value,
                                        }))
                                    }
                                    disabled={readOnly}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-[12.5px] font-medium">
                                Revisi
                                <Input
                                    value={meta.revision}
                                    onChange={(e) =>
                                        setMeta((m) => ({
                                            ...m,
                                            revision: e.target.value,
                                        }))
                                    }
                                    disabled={readOnly}
                                />
                            </label>
                            <div className="flex items-end text-[12px] text-muted-foreground">
                                <span className="flex items-center gap-1.5 pb-2">
                                    <ClipboardCheck className="size-4 text-emerald-600" />
                                    Tanda tangan persetujuan dikelola di Laporan
                                    Project.
                                </span>
                            </div>
                            <label className="flex flex-col gap-1 text-[12.5px] font-medium sm:col-span-3">
                                Petunjuk Pengisian Form
                                <Textarea
                                    value={meta.instruction}
                                    onChange={(e) =>
                                        setMeta((m) => ({
                                            ...m,
                                            instruction: e.target.value,
                                        }))
                                    }
                                    rows={2}
                                    disabled={readOnly}
                                />
                            </label>
                        </div>
                    )}
                </div>
            )}

            {tab === 'items' && (
                <div className="flex flex-col gap-3">
                    <p className="text-[12.5px] text-muted-foreground">
                        {format === 'checklist'
                            ? 'Centang parameter yang relevan diperiksa untuk tiap komponen; parameter yang tidak dicentang akan tampil tanda (—) pada form dan PDF.'
                            : 'Daftar komponen untuk format matriks bulanan N/T. Kosongkan daftar untuk memakai 29 item bawaan Cummins.'}
                    </p>

                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead className="bg-muted/60 text-left text-[11.5px] text-muted-foreground uppercase">
                                <tr>
                                    <th className="w-10 px-2 py-2 text-center">
                                        No
                                    </th>
                                    <th className="w-40 px-2 py-2">
                                        {format === 'checklist'
                                            ? 'Area'
                                            : 'Sistem'}
                                    </th>
                                    <th className="min-w-52 px-2 py-2">
                                        Komponen / Peralatan
                                    </th>
                                    {format === 'checklist' &&
                                        paramOptions.map((opt) => (
                                            <th
                                                key={opt.key}
                                                className="w-20 border-l border-border px-1.5 py-2 text-center"
                                            >
                                                {opt.label}
                                            </th>
                                        ))}
                                    {canManage && (
                                        <th className="w-24 border-l border-border px-2 py-2 text-center">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item, idx) => (
                                    <tr
                                        key={item.key}
                                        className="border-t border-border"
                                    >
                                        <td className="px-2 text-center text-muted-foreground tabular-nums">
                                            {idx + 1}
                                        </td>
                                        <td className="border-l border-border p-1">
                                            <Input
                                                value={item.system}
                                                disabled={readOnly}
                                                onChange={(e) =>
                                                    updateItem(item.key, {
                                                        system: e.target.value,
                                                    })
                                                }
                                                className="h-8 border-0 bg-transparent px-1.5 text-[12.5px] shadow-none focus-visible:ring-1"
                                            />
                                        </td>
                                        <td className="border-l border-border p-1">
                                            <Input
                                                value={item.peralatan}
                                                disabled={readOnly}
                                                onChange={(e) =>
                                                    updateItem(item.key, {
                                                        peralatan:
                                                            e.target.value,
                                                    })
                                                }
                                                className="h-8 border-0 bg-transparent px-1.5 text-[12.5px] shadow-none focus-visible:ring-1"
                                            />
                                        </td>
                                        {format === 'checklist' &&
                                            paramOptions.map((opt) => (
                                                <td
                                                    key={opt.key}
                                                    className="border-l border-border text-center"
                                                >
                                                    <Checkbox
                                                        checked={Boolean(
                                                            item.params[
                                                                opt.key
                                                            ],
                                                        )}
                                                        disabled={readOnly}
                                                        aria-label={`${opt.label} untuk ${item.peralatan || `baris ${idx + 1}`}`}
                                                        onCheckedChange={(v) =>
                                                            toggleParam(
                                                                item.key,
                                                                opt.key,
                                                                v === true,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ))}
                                        {canManage && (
                                            <td className="border-l border-border px-1 text-center">
                                                <div className="inline-flex items-center gap-0.5">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        disabled={idx === 0}
                                                        onClick={() =>
                                                            moveItem(idx, -1)
                                                        }
                                                        aria-label="Naik"
                                                    >
                                                        <ArrowUp className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        disabled={
                                                            idx ===
                                                            items.length - 1
                                                        }
                                                        onClick={() =>
                                                            moveItem(idx, 1)
                                                        }
                                                        aria-label="Turun"
                                                    >
                                                        <ArrowDown className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7 text-destructive"
                                                        onClick={() =>
                                                            setItems((prev) =>
                                                                prev.filter(
                                                                    (x) =>
                                                                        x.key !==
                                                                        item.key,
                                                                ),
                                                            )
                                                        }
                                                        aria-label="Hapus"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {items.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={
                                                (format === 'checklist'
                                                    ? 3 + paramOptions.length
                                                    : 3) + (canManage ? 1 : 0)
                                            }
                                            className="px-3 py-6 text-center text-muted-foreground"
                                        >
                                            {format === 'monthly'
                                                ? `Belum ada item khusus — memakai ${defaultMonthlyCount} item bawaan Cummins (Containerized).`
                                                : 'Belum ada item komponen. Klik "+ Tambah Baris" untuk menambahkan.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {canManage && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                onClick={addItem}
                            >
                                <Plus className="size-4" />
                                Tambah Baris
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {tab === 'mesin' && (
                <div className="flex flex-col gap-3">
                    {template.is_default ? (
                        <p className="rounded-md border border-border bg-muted/30 p-3 text-[13px] text-muted-foreground">
                            Template <strong>{template.name}</strong> adalah
                            template bawaan — seluruh mesin yang belum dipilih
                            di template lain otomatis memakai format ini.
                        </p>
                    ) : (
                        <>
                            <p className="text-[12.5px] text-muted-foreground">
                                Centang mesin yang memakai template Patrol Check{' '}
                                <strong>{template.name}</strong>.
                            </p>
                            <div className="grid gap-3 md:grid-cols-2">
                                {machinesByUnit.map(([unitName, list]) => {
                                    const allSelected = list.every((m) =>
                                        machineIds.includes(m.id),
                                    );

                                    return (
                                        <div
                                            key={unitName}
                                            className="flex flex-col gap-2 rounded-md border border-border p-3"
                                        >
                                            <label className="flex items-center gap-2 border-b border-border pb-1.5 text-[13px] font-semibold">
                                                <Checkbox
                                                    checked={allSelected}
                                                    disabled={readOnly}
                                                    onCheckedChange={(v) => {
                                                        const unitIds =
                                                            list.map(
                                                                (m) => m.id,
                                                            );
                                                        setMachineIds((prev) =>
                                                            v === true
                                                                ? Array.from(
                                                                      new Set([
                                                                          ...prev,
                                                                          ...unitIds,
                                                                      ]),
                                                                  )
                                                                : prev.filter(
                                                                      (id) =>
                                                                          !unitIds.includes(
                                                                              id,
                                                                          ),
                                                                  ),
                                                        );
                                                    }}
                                                />
                                                {unitName}
                                            </label>
                                            <div className="flex flex-col gap-1.5">
                                                {list.map((m) => {
                                                    const other =
                                                        otherTemplateName(
                                                            m.template_id,
                                                        );

                                                    return (
                                                        <label
                                                            key={m.id}
                                                            className="flex items-center justify-between gap-2 text-[12.5px]"
                                                        >
                                                            <span className="flex items-center gap-2">
                                                                <Checkbox
                                                                    checked={machineIds.includes(
                                                                        m.id,
                                                                    )}
                                                                    disabled={
                                                                        readOnly
                                                                    }
                                                                    onCheckedChange={(
                                                                        v,
                                                                    ) =>
                                                                        setMachineIds(
                                                                            (
                                                                                prev,
                                                                            ) =>
                                                                                v ===
                                                                                true
                                                                                    ? [
                                                                                          ...prev,
                                                                                          m.id,
                                                                                      ]
                                                                                    : prev.filter(
                                                                                          (
                                                                                              id,
                                                                                          ) =>
                                                                                              id !==
                                                                                              m.id,
                                                                                      ),
                                                                        )
                                                                    }
                                                                />
                                                                {m.name}
                                                            </span>
                                                            {other && (
                                                                <span className="text-[11px] text-amber-600">
                                                                    ({other})
                                                                </span>
                                                            )}
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </div>
            )}

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Template Patrol Check?</DialogTitle>
                        <DialogDescription>
                            Template <strong>{template.name}</strong> akan
                            dihapus. Mesin yang memakainya akan kembali ke
                            template bawaan (Cummins Containerized), dan riwayat
                            data yang sudah tersimpan tetap aman.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Batal
                        </Button>
                        <Button variant="destructive" onClick={destroy}>
                            Hapus Template
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

PatrolCheckTemplateIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Patrol Check', href: patrolCheckTemplate.index() },
    ],
};
