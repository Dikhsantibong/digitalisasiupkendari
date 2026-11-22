import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Copy, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { headerRows, referenceRows } from '@/lib/logsheet-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import logsheetTemplate from '@/routes/operator/logsheet-template';

type InputType = 'number' | 'text' | 'choice';

type Parameter = {
    id?: number | null;
    key: string;
    sheet_key: string;
    code: string;
    group_label: string;
    name: string;
    sub_channel: string;
    unit_of_measure: string;
    input_type: InputType;
    options: string;
    normal_value: string;
    alarm_value: string;
    trip_value: string;
    is_active: boolean;
};

type PatrolItem = { key: string; system: string; peralatan: string };

/** A lembar of the form: its own interval label, reading slots and MIN / MAX / RATA2 footer. */
type SheetDef = {
    key: string;
    name: string;
    interval: string | null;
    summary: boolean;
    slots: string[];
};

type Template = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_default: boolean;
    time_slots: string[];
    sheets: SheetDef[];
    patrol_items: { system: string; peralatan: string }[];
    parameters: (Omit<
        Parameter,
        | 'key'
        | 'group_label'
        | 'sub_channel'
        | 'unit_of_measure'
        | 'normal_value'
        | 'alarm_value'
        | 'trip_value'
    > & {
        group_label: string | null;
        sub_channel: string | null;
        unit_of_measure: string | null;
        normal_value: string | null;
        alarm_value: string | null;
        trip_value: string | null;
    })[];
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
    slot_options: string[];
    can_manage: boolean;
};

type Tab = 'parameter' | 'jam' | 'patrol' | 'mesin';

let seq = 0;
const nextKey = () => `k${++seq}`;

const toParameters = (template: Template): Parameter[] =>
    template.parameters.map((p) => ({
        ...p,
        key: nextKey(),
        group_label: p.group_label ?? '',
        sub_channel: p.sub_channel ?? '',
        unit_of_measure: p.unit_of_measure ?? '',
        normal_value: p.normal_value ?? '',
        alarm_value: p.alarm_value ?? '',
        trip_value: p.trip_value ?? '',
    }));

const blankParameter = (sheetKey: string): Parameter => ({
    id: null,
    key: nextKey(),
    sheet_key: sheetKey,
    code: '',
    group_label: '',
    name: '',
    sub_channel: '',
    unit_of_measure: '',
    input_type: 'number',
    options: '',
    normal_value: '',
    alarm_value: '',
    trip_value: '',
    is_active: true,
});

const cellInput =
    'h-8 w-full min-w-16 rounded-none border-0 bg-transparent px-1.5 text-[12.5px] shadow-none focus-visible:ring-0';

/**
 * Template Form Mesin — the logsheet form per jenis mesin (parameters with
 * their header levels and Normal / Alarm / Trip, time slots, Patrol Check
 * items) and the machines that use it. Super Admin edits; others read.
 */
export default function LogsheetTemplatePage({
    templates,
    machines,
    slot_options,
    can_manage,
}: Props) {
    const initialId =
        Number(
            new URLSearchParams(
                typeof window === 'undefined' ? '' : window.location.search,
            ).get('template'),
        ) || templates[0]?.id;
    const [selectedId, setSelectedId] = useState<number | undefined>(
        templates.find((t) => t.id === initialId)?.id ?? templates[0]?.id,
    );
    const selected = templates.find((t) => t.id === selectedId) ?? templates[0];

    return (
        <>
            <Head title="Template Form Mesin" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Template Form Mesin"
                    description="Form logsheet operator (dan item Patrol Check Mesin) per jenis mesin. Setiap mesin memakai satu template; mesin tanpa template memakai template bawaan."
                />
                <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
                    <TemplateList
                        templates={templates}
                        machines={machines}
                        selectedId={selected?.id}
                        onSelect={setSelectedId}
                        canManage={can_manage}
                    />
                    {selected ? (
                        <TemplateEditor
                            key={selected.id}
                            template={selected}
                            machines={machines}
                            slotOptions={slot_options}
                            canManage={can_manage}
                        />
                    ) : (
                        <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                            Belum ada template.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function TemplateList({
    templates,
    machines,
    selectedId,
    onSelect,
    canManage,
}: {
    templates: Template[];
    machines: MachineOption[];
    selectedId?: number;
    onSelect: (id: number) => void;
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [copyFrom, setCopyFrom] = useState<string>(
        selectedId ? String(selectedId) : '',
    );
    const defaultId = templates.find((t) => t.is_default)?.id;

    const create = () =>
        router.post(
            logsheetTemplate.store().url,
            { name, copy_from: copyFrom ? Number(copyFrom) : null },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setOpen(false);
                    setName('');
                    const created = Number(
                        new URL(
                            page.url,
                            window.location.origin,
                        ).searchParams.get('template'),
                    );

                    if (created) {
                        onSelect(created);
                    }
                },
            },
        );

    return (
        <div className="flex flex-col gap-2">
            {templates.map((t) => {
                const count = machines.filter(
                    (m) =>
                        m.template_id === t.id ||
                        (t.id === defaultId && m.template_id === null),
                ).length;

                return (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => onSelect(t.id)}
                        className={cn(
                            'rounded-md border p-3 text-left transition',
                            t.id === selectedId
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'border-border bg-card hover:bg-muted/50',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-[13.5px] font-semibold">
                                {t.name}
                            </span>
                            {t.is_default && (
                                <Badge variant="secondary">Bawaan</Badge>
                            )}
                        </div>
                        <div className="mt-1 text-[12px] text-muted-foreground">
                            {t.parameters.filter((p) => p.is_active).length}{' '}
                            parameter ·{' '}
                            {t.sheets.length > 1
                                ? `${t.sheets.length} lembar`
                                : `${t.time_slots.length} jam`}{' '}
                            · {count} mesin
                        </div>
                    </button>
                );
            })}
            {canManage && (
                <Button variant="outline" onClick={() => setOpen(true)}>
                    <Plus className="size-4" /> Template Baru
                </Button>
            )}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>Template Baru</DialogTitle>
                    <div className="flex flex-col gap-3">
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">
                                Nama jenis mesin / form
                            </span>
                            <Input
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="mis. SWD (PLTD Kolaka)"
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">
                                Salin parameter dari
                            </span>
                            <select
                                value={copyFrom}
                                onChange={(e) => setCopyFrom(e.target.value)}
                                className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                            >
                                <option value="">— Kosong —</option>
                                {templates.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={create}
                                disabled={name.trim() === ''}
                            >
                                <Copy className="size-4" /> Buat
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function TemplateEditor({
    template,
    machines,
    slotOptions,
    canManage,
}: {
    template: Template;
    machines: MachineOption[];
    slotOptions: string[];
    canManage: boolean;
}) {
    const [tab, setTab] = useState<Tab>('parameter');
    const [name, setName] = useState(template.name);
    const [description, setDescription] = useState(template.description ?? '');
    const [parameters, setParameters] = useState<Parameter[]>(() =>
        toParameters(template),
    );
    const [sheets, setSheets] = useState<SheetDef[]>(() =>
        template.sheets.map((sheet) => ({ ...sheet })),
    );
    const [activeSheet, setActiveSheet] = useState<string>(
        template.sheets[0]?.key ?? 'utama',
    );
    const multiSheet = sheets.length > 1;
    const currentSheet =
        sheets.find((sheet) => sheet.key === activeSheet) ?? sheets[0];
    const slots = currentSheet?.slots ?? [];
    const setSlots = (next: string[] | ((list: string[]) => string[])) =>
        setSheets((list) =>
            list.map((sheet) =>
                sheet.key === currentSheet?.key
                    ? {
                          ...sheet,
                          slots:
                              typeof next === 'function'
                                  ? next(sheet.slots)
                                  : next,
                      }
                    : sheet,
            ),
        );
    const updateSheet = (key: string, patch: Partial<SheetDef>) =>
        setSheets((list) =>
            list.map((sheet) =>
                sheet.key === key ? { ...sheet, ...patch } : sheet,
            ),
        );
    const addSheet = () => {
        let n = sheets.length + 1;

        while (sheets.some((sheet) => sheet.key === `lembar-${n}`)) {
            n++;
        }

        const key = `lembar-${n}`;
        setSheets((list) => [
            ...list,
            {
                key,
                name: `Lembar ${n}`,
                interval: 'Setiap Jam',
                summary: false,
                slots: slotOptions.filter((slot) => slot.endsWith(':00')),
            },
        ]);
        setActiveSheet(key);
    };
    const removeSheet = (key: string) => {
        const rest = sheets.filter((sheet) => sheet.key !== key);

        if (
            rest.length === 0 ||
            !window.confirm(
                'Hapus lembar ini? Parameternya pindah ke lembar pertama.',
            )
        ) {
            return;
        }

        setSheets(rest);
        setParameters((list) =>
            list.map((p) =>
                p.sheet_key === key ? { ...p, sheet_key: rest[0].key } : p,
            ),
        );
        setActiveSheet(rest[0].key);
    };
    const [patrol, setPatrol] = useState<PatrolItem[]>(() =>
        template.patrol_items.map((item) => ({ ...item, key: nextKey() })),
    );
    const [machineIds, setMachineIds] = useState<number[]>(() =>
        machines.filter((m) => m.template_id === template.id).map((m) => m.id),
    );
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const readOnly = !canManage;

    // With several lembar, the table & preview show the selected lembar only.
    const sheetParameters = useMemo(
        () =>
            multiSheet
                ? parameters.filter((p) => p.sheet_key === currentSheet?.key)
                : parameters,
        [parameters, multiSheet, currentSheet],
    );
    const active = useMemo(
        () =>
            sheetParameters.filter((p) => p.is_active && p.name.trim() !== ''),
        [sheetParameters],
    );
    const preview = useMemo(
        () =>
            active.map((p) => ({
                ...p,
                group_label: p.group_label || null,
                sub_channel: p.sub_channel || null,
            })),
        [active],
    );
    const head = useMemo(() => headerRows(preview), [preview]);
    const references = useMemo(() => referenceRows(preview), [preview]);

    const update = (key: string, patch: Partial<Parameter>) =>
        setParameters((list) =>
            list.map((p) => (p.key === key ? { ...p, ...patch } : p)),
        );
    // Swap with the neighbour shown in the same lembar.
    const move = (index: number, delta: number) => {
        const from = sheetParameters[index];
        const to = sheetParameters[index + delta];

        if (!from || !to) {
            return;
        }

        setParameters((list) => {
            const next = [...list];
            const a = next.findIndex((p) => p.key === from.key);
            const b = next.findIndex((p) => p.key === to.key);
            [next[a], next[b]] = [next[b], next[a]];

            return next;
        });
    };

    const save = () => {
        setSaving(true);
        setErrors({});
        router.put(
            logsheetTemplate.update(template.id).url,
            {
                name,
                description,
                time_slots: sheets[0]?.slots ?? [],
                sheets: sheets.map((sheet) => ({
                    key: sheet.key,
                    name: sheet.name,
                    interval: sheet.interval,
                    summary: sheet.summary,
                    slots: sheet.slots,
                })),
                patrol_items: patrol.map(({ system, peralatan }) => ({
                    system,
                    peralatan,
                })),
                parameters: parameters.map((p) => ({
                    id: p.id ?? null,
                    sheet_key: p.sheet_key,
                    code: p.code,
                    group_label: p.group_label,
                    name: p.name,
                    sub_channel: p.sub_channel,
                    unit_of_measure: p.unit_of_measure,
                    input_type: p.input_type,
                    options: p.options,
                    normal_value: p.normal_value,
                    alarm_value: p.alarm_value,
                    trip_value: p.trip_value,
                    is_active: p.is_active,
                })),
                machine_ids: machineIds,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Fresh ids for the parameters just created, so a second save updates them.
                    const saved = (
                        page.props as unknown as Props
                    ).templates.find((t) => t.id === template.id);

                    if (saved) {
                        setParameters(toParameters(saved));
                        setSheets(saved.sheets.map((sheet) => ({ ...sheet })));
                    }
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    const destroy = () => {
        if (
            window.confirm(
                `Hapus template ${template.name}? Mesin yang memakainya kembali ke template bawaan.`,
            )
        ) {
            router.delete(logsheetTemplate.destroy(template.id).url, {
                preserveScroll: true,
            });
        }
    };

    const units = useMemo(() => {
        const map = new Map<string, MachineOption[]>();
        machines.forEach((m) =>
            map.set(m.unit ?? '-', [...(map.get(m.unit ?? '-') ?? []), m]),
        );

        return [...map.entries()];
    }, [machines]);

    const tabs: [Tab, string][] = [
        ['parameter', `Parameter Logsheet (${active.length})`],
        [
            'jam',
            multiSheet
                ? `Lembar & Jam (${sheets.length} lembar)`
                : `Lembar & Jam (${slots.length} jam)`,
        ],
        ['patrol', `Item Patrol Check (${patrol.length || 'bawaan'})`],
        ['mesin', 'Mesin'],
    ];

    const sheetPicker = multiSheet ? (
        <div
            className="flex flex-wrap gap-2"
            role="tablist"
            aria-label="Lembar"
        >
            {sheets.map((sheet) => (
                <button
                    key={sheet.key}
                    type="button"
                    role="tab"
                    aria-selected={sheet.key === currentSheet?.key}
                    onClick={() => setActiveSheet(sheet.key)}
                    className={cn(
                        'rounded-md border px-3 py-1.5 text-left text-[12.5px]',
                        sheet.key === currentSheet?.key
                            ? 'border-primary bg-primary/10 font-semibold text-primary'
                            : 'border-border hover:bg-muted/50',
                    )}
                >
                    {sheet.name || '(tanpa nama)'}
                    {sheet.interval && (
                        <span className="ml-1 font-normal text-muted-foreground">
                            · {sheet.interval}
                        </span>
                    )}
                </button>
            ))}
        </div>
    ) : null;
    const errorList = Object.values(errors);

    return (
        <div className="flex min-w-0 flex-col gap-3 rounded-md border border-border bg-card p-4">
            <div className="flex flex-wrap items-end gap-3">
                <label className="flex min-w-60 flex-1 flex-col gap-1 text-[13px]">
                    <span className="text-muted-foreground">
                        Nama template (jenis mesin)
                    </span>
                    <Input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        disabled={readOnly}
                    />
                </label>
                <label className="flex min-w-60 flex-[2] flex-col gap-1 text-[13px]">
                    <span className="text-muted-foreground">Keterangan</span>
                    <Input
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        disabled={readOnly}
                    />
                </label>
                {canManage && (
                    <div className="flex gap-2">
                        {!template.is_default && (
                            <Button variant="outline" onClick={destroy}>
                                <Trash2 className="size-4" /> Hapus
                            </Button>
                        )}
                        <Button onClick={save} disabled={saving}>
                            <Save className="size-4" />{' '}
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </div>
                )}
            </div>

            {errorList.length > 0 && (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-2 text-[12.5px] text-destructive">
                    {errorList.slice(0, 4).join(' · ')}
                </div>
            )}

            <div className="flex flex-wrap gap-1 border-b border-border">
                {tabs.map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setTab(value)}
                        className={cn(
                            '-mb-px border-b-2 px-3 py-2 text-[13px] font-medium',
                            tab === value
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'parameter' && (
                <div className="flex flex-col gap-3">
                    <p className="text-[12.5px] text-muted-foreground">
                        Judul kolom bertingkat: <b>Grup</b> → <b>Nama</b> →{' '}
                        <b>Sub</b> (mis. Temperature Stud Main Bearing → Bank A
                        → 1). Kolom berurutan dengan judul sama digabung
                        otomatis. Kode <b>LOAD</b> = beban (dipakai Beban
                        Tertinggi Operasi), <b>KWH_METER</b> = kWh meter.
                        Parameter yang sudah berisi data hanya dinonaktifkan
                        bila dihapus.
                    </p>
                    {sheetPicker}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead className="bg-muted/60 text-left text-[11.5px] text-muted-foreground uppercase">
                                <tr>
                                    {[
                                        '#',
                                        'Grup',
                                        'Nama',
                                        'Sub',
                                        'Satuan',
                                        'Jenis Input',
                                        'Pilihan',
                                        'Normal',
                                        'Min / Alarm',
                                        'Max / Trip',
                                        'Kode',
                                        'Aktif',
                                        '',
                                    ].map((h) => (
                                        <th
                                            key={h}
                                            className="border-b border-border px-1.5 py-2 font-semibold whitespace-nowrap"
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sheetParameters.map((p, i) => (
                                    <tr
                                        key={p.key}
                                        className={cn(
                                            'border-b border-border',
                                            !p.is_active && 'opacity-50',
                                        )}
                                    >
                                        <td className="px-1.5 text-muted-foreground tabular-nums">
                                            {i + 1}
                                        </td>
                                        {(
                                            [
                                                'group_label',
                                                'name',
                                                'sub_channel',
                                                'unit_of_measure',
                                            ] as const
                                        ).map((field) => (
                                            <td
                                                key={field}
                                                className="border-l border-border p-0"
                                            >
                                                <Input
                                                    value={p[field]}
                                                    onChange={(e) =>
                                                        update(p.key, {
                                                            [field]:
                                                                e.target.value,
                                                        })
                                                    }
                                                    disabled={readOnly}
                                                    className={cellInput}
                                                />
                                            </td>
                                        ))}
                                        <td className="border-l border-border p-0">
                                            <select
                                                value={p.input_type}
                                                onChange={(e) =>
                                                    update(p.key, {
                                                        input_type: e.target
                                                            .value as InputType,
                                                    })
                                                }
                                                disabled={readOnly}
                                                className="h-8 bg-transparent px-1 text-[12.5px]"
                                            >
                                                <option value="number">
                                                    Angka
                                                </option>
                                                <option value="text">
                                                    Teks
                                                </option>
                                                <option value="choice">
                                                    Pilihan
                                                </option>
                                            </select>
                                        </td>
                                        <td className="border-l border-border p-0">
                                            <Input
                                                value={p.options}
                                                onChange={(e) =>
                                                    update(p.key, {
                                                        options: e.target.value,
                                                    })
                                                }
                                                disabled={
                                                    readOnly ||
                                                    p.input_type !== 'choice'
                                                }
                                                placeholder={
                                                    p.input_type === 'choice'
                                                        ? 'L, N, H'
                                                        : ''
                                                }
                                                className={cellInput}
                                            />
                                        </td>
                                        {(
                                            [
                                                'normal_value',
                                                'alarm_value',
                                                'trip_value',
                                                'code',
                                            ] as const
                                        ).map((field) => (
                                            <td
                                                key={field}
                                                className="border-l border-border p-0"
                                            >
                                                <Input
                                                    value={p[field]}
                                                    onChange={(e) =>
                                                        update(p.key, {
                                                            [field]:
                                                                e.target.value,
                                                        })
                                                    }
                                                    disabled={readOnly}
                                                    placeholder={
                                                        field === 'code'
                                                            ? 'otomatis'
                                                            : ''
                                                    }
                                                    className={cellInput}
                                                />
                                            </td>
                                        ))}
                                        <td className="border-l border-border px-2 text-center">
                                            <Checkbox
                                                checked={p.is_active}
                                                onCheckedChange={(v) =>
                                                    update(p.key, {
                                                        is_active: v === true,
                                                    })
                                                }
                                                disabled={readOnly}
                                            />
                                        </td>
                                        <td className="border-l border-border px-1 whitespace-nowrap">
                                            {canManage && (
                                                <>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        onClick={() =>
                                                            move(i, -1)
                                                        }
                                                        aria-label="Naik"
                                                    >
                                                        <ArrowUp className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        onClick={() =>
                                                            move(i, 1)
                                                        }
                                                        aria-label="Turun"
                                                    >
                                                        <ArrowDown className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7 text-destructive"
                                                        onClick={() =>
                                                            setParameters(
                                                                (list) =>
                                                                    list.filter(
                                                                        (x) =>
                                                                            x.key !==
                                                                            p.key,
                                                                    ),
                                                            )
                                                        }
                                                        aria-label="Hapus"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {canManage && (
                        <div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setParameters((list) => [
                                        ...list,
                                        blankParameter(
                                            currentSheet?.key ?? 'utama',
                                        ),
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Tambah Parameter
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-col gap-1.5">
                        <span className="text-[12px] font-semibold text-muted-foreground uppercase">
                            Pratinjau judul tabel logsheet
                        </span>
                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="border-collapse text-center text-[11px]">
                                <thead>
                                    {head.map((cells, level) => (
                                        <tr key={level}>
                                            {level === 0 && (
                                                <th
                                                    rowSpan={head.length}
                                                    className="border border-black bg-yellow-400 px-2 py-1 dark:border-white dark:bg-yellow-700"
                                                >
                                                    JAM
                                                </th>
                                            )}
                                            {cells.map((cell, k) => (
                                                <th
                                                    key={k}
                                                    colSpan={cell.colspan}
                                                    rowSpan={cell.rowspan}
                                                    className="border border-black bg-yellow-200 px-2 py-1 font-semibold uppercase dark:border-white dark:bg-yellow-800"
                                                >
                                                    {cell.label}
                                                </th>
                                            ))}
                                        </tr>
                                    ))}
                                </thead>
                                <tbody>
                                    {references.map((reference) => (
                                        <tr key={reference.field}>
                                            <td className="border border-black px-2 py-0.5 font-semibold whitespace-nowrap dark:border-white">
                                                {reference.label}
                                            </td>
                                            {preview.map((p) => (
                                                <td
                                                    key={p.key}
                                                    className="border border-black px-2 py-0.5 whitespace-nowrap dark:border-white"
                                                >
                                                    {p[reference.field] ?? ''}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'jam' && (
                <div className="flex flex-col gap-3">
                    <p className="text-[12.5px] text-muted-foreground">
                        Satu form bisa punya beberapa <b>lembar</b> dengan
                        interval sendiri (mis. Lokal 02 setiap jam, Lokal 03
                        setiap 2 jam). Operator memilih lembar di halaman
                        logsheet; template dengan satu lembar tampil seperti
                        biasa.
                    </p>
                    {sheetPicker}
                    {currentSheet && (
                        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border p-3">
                            <label className="flex min-w-48 flex-1 flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Nama lembar
                                </span>
                                <Input
                                    value={currentSheet.name}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        updateSheet(currentSheet.key, {
                                            name: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="flex w-40 flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">
                                    Interval
                                </span>
                                <Input
                                    value={currentSheet.interval ?? ''}
                                    placeholder="Setiap 2 Jam"
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        updateSheet(currentSheet.key, {
                                            interval: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="flex h-9 items-center gap-2 text-[13px]">
                                <Checkbox
                                    checked={currentSheet.summary}
                                    disabled={readOnly}
                                    onCheckedChange={(v) =>
                                        updateSheet(currentSheet.key, {
                                            summary: v === true,
                                        })
                                    }
                                />
                                Baris MIN / MAX / RATA2
                            </label>
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    {multiSheet && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                removeSheet(currentSheet.key)
                                            }
                                        >
                                            <Trash2 className="size-4" /> Hapus
                                            Lembar
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={addSheet}
                                    >
                                        <Plus className="size-4" /> Tambah
                                        Lembar
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                    <div className="flex flex-wrap items-center gap-2 text-[12.5px] text-muted-foreground">
                        Jam pembacaan lembar ini.
                        {canManage && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSlots(
                                            slotOptions.filter((s) =>
                                                s.endsWith(':00'),
                                            ),
                                        )
                                    }
                                >
                                    Setiap jam
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSlots(
                                            slotOptions.filter(
                                                (s) =>
                                                    s.endsWith(':00') ||
                                                    [
                                                        '17:30',
                                                        '18:30',
                                                        '19:30',
                                                        '20:30',
                                                        '21:30',
                                                    ].includes(s),
                                            ),
                                        )
                                    }
                                >
                                    Setiap jam + beban puncak
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSlots(
                                            slotOptions.filter((s) => {
                                                const hour = Number(
                                                    s.slice(0, 2),
                                                );

                                                return (
                                                    s.endsWith(':00') &&
                                                    hour % 2 === 1
                                                );
                                            }),
                                        )
                                    }
                                >
                                    Setiap 2 jam (01, 03 … 23)
                                </Button>
                            </>
                        )}
                    </div>
                    <div className="grid grid-cols-4 gap-1.5 sm:grid-cols-8">
                        {slotOptions.map((slot) => (
                            <label
                                key={slot}
                                className={cn(
                                    'flex items-center gap-1.5 rounded border px-2 py-1 text-[12.5px] tabular-nums',
                                    slots.includes(slot)
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border',
                                )}
                            >
                                <Checkbox
                                    checked={slots.includes(slot)}
                                    disabled={readOnly}
                                    onCheckedChange={(v) =>
                                        setSlots((list) =>
                                            v === true
                                                ? [...list, slot]
                                                : list.filter(
                                                      (s) => s !== slot,
                                                  ),
                                        )
                                    }
                                />
                                {slot}
                            </label>
                        ))}
                    </div>
                </div>
            )}

            {tab === 'patrol' && (
                <div className="flex flex-col gap-3">
                    <p className="text-[12.5px] text-muted-foreground">
                        Daftar awal Patrol Check Mesin untuk mesin bertemplate
                        ini (lembar bulan baru). Kosongkan untuk memakai daftar
                        bawaan. Lembar yang sudah tersimpan tidak berubah.
                    </p>
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead className="bg-muted/60 text-left text-[11.5px] text-muted-foreground uppercase">
                                <tr>
                                    <th className="w-10 px-2 py-2">No</th>
                                    <th className="px-2 py-2">Sistem</th>
                                    <th className="px-2 py-2">Peralatan</th>
                                    <th className="w-10" />
                                </tr>
                            </thead>
                            <tbody>
                                {patrol.map((item, i) => (
                                    <tr
                                        key={item.key}
                                        className="border-t border-border"
                                    >
                                        <td className="px-2 text-muted-foreground tabular-nums">
                                            {i + 1}
                                        </td>
                                        {(['system', 'peralatan'] as const).map(
                                            (field) => (
                                                <td
                                                    key={field}
                                                    className="border-l border-border p-0"
                                                >
                                                    <Input
                                                        value={item[field]}
                                                        disabled={readOnly}
                                                        onChange={(e) =>
                                                            setPatrol((list) =>
                                                                list.map((x) =>
                                                                    x.key ===
                                                                    item.key
                                                                        ? {
                                                                              ...x,
                                                                              [field]:
                                                                                  e
                                                                                      .target
                                                                                      .value,
                                                                          }
                                                                        : x,
                                                                ),
                                                            )
                                                        }
                                                        className={cellInput}
                                                    />
                                                </td>
                                            ),
                                        )}
                                        <td className="border-l border-border px-1">
                                            {canManage && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-7 text-destructive"
                                                    onClick={() =>
                                                        setPatrol((list) =>
                                                            list.filter(
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
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {patrol.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-2 py-4 text-center text-muted-foreground"
                                        >
                                            Memakai daftar Patrol Check bawaan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {canManage && (
                        <div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setPatrol((list) => [
                                        ...list,
                                        {
                                            key: nextKey(),
                                            system:
                                                list[list.length - 1]?.system ??
                                                '',
                                            peralatan: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Tambah Item
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {tab === 'mesin' && (
                <div className="flex flex-col gap-3">
                    {template.is_default ? (
                        <p className="text-[12.5px] text-muted-foreground">
                            Template bawaan dipakai oleh semua mesin yang belum
                            diberi template lain:{' '}
                            {machines
                                .filter((m) => m.template_id === null)
                                .map((m) => `${m.name} (${m.unit})`)
                                .join(', ') || '—'}
                            .
                        </p>
                    ) : (
                        <p className="text-[12.5px] text-muted-foreground">
                            Centang mesin yang memakai form ini. Mesin yang
                            tidak dicentang kembali ke template bawaan.
                        </p>
                    )}
                    {!template.is_default && (
                        <div className="grid gap-3 md:grid-cols-2">
                            {units.map(([unit, list]) => (
                                <div
                                    key={unit}
                                    className="rounded-md border border-border p-3"
                                >
                                    <div className="mb-2 text-[13px] font-semibold">
                                        {unit}
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        {list.map((m) => (
                                            <label
                                                key={m.id}
                                                className="flex items-center gap-2 text-[13px]"
                                            >
                                                <Checkbox
                                                    checked={machineIds.includes(
                                                        m.id,
                                                    )}
                                                    disabled={readOnly}
                                                    onCheckedChange={(v) =>
                                                        setMachineIds((ids) =>
                                                            v === true
                                                                ? [...ids, m.id]
                                                                : ids.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          m.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                {m.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

LogsheetTemplatePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Form Mesin', href: logsheetTemplate.index() },
    ],
};
