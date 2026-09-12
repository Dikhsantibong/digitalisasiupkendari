import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes';
import logsheet from '@/routes/operator/logsheet';
import type { IdName } from '@/types';

type Parameter = { id: number; name: string; sub_channel: string | null; label: string; unit_of_measure: string | null };
type Values = Record<string, number | string | null>;
type Row = { time_slot: string; values: Values; filled: boolean };

type Stat = { label: string; value: string; unit?: string; hint?: string };

type Props = {
    filters: { unit_id: number; engine_id: number | null; log_date: string };
    stats: Stat[];
    header: { shift: string | null; status: string };
    parameters: Parameter[];
    rows: Row[];
    slots: string[];
    shifts: string[];
    options: { units: IdName[]; machines: IdName[] };
    can_write: boolean;
    is_submitted: boolean;
};

type Group = { name: string; members: Parameter[]; hasSub: boolean };

const toStr = (v: number | string | null | undefined) => (v === null || v === undefined ? '' : String(v));

export default function LogsheetInput({ filters, stats, header, parameters, rows, slots, shifts, options, can_write, is_submitted }: Props) {
    const [shift, setShift] = useState(header.shift ?? '');
    const [open, setOpen] = useState(false);
    const [slot, setSlot] = useState<string>(slots[0] ?? '');
    const [form, setForm] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const groups = useMemo<Group[]>(() => {
        const out: Group[] = [];

        for (const p of parameters) {
            const last = out[out.length - 1];

            if (last && last.name === p.name) {
                last.members.push(p);
            } else {
                out.push({ name: p.name, members: [p], hasSub: false });
            }
        }

        for (const g of out) {
            g.hasSub = g.members.some((m) => m.sub_channel !== null);
        }

        return out;
    }, [parameters]);

    // Bearing Generator Temperatur is shown as its own block below the main
    // table (as in the Excel), not as an inline column.
    const BEARING = 'Bearing Generator Temperatur';
    const mainGroups = useMemo(() => groups.filter((g) => g.name !== BEARING), [groups]);
    const mainParameters = useMemo(() => parameters.filter((p) => p.name !== BEARING), [parameters]);
    const bearingParam = useMemo(() => parameters.find((p) => p.name === BEARING) ?? null, [parameters]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            logsheet.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Build the form values for a given time slot from whatever is already saved
    // for that hour (empty when the hour has no data yet).
    const valuesForSlot = (slotValue: string): Record<string, string> => {
        const row = rows.find((r) => r.time_slot === slotValue);
        const next: Record<string, string> = {};

        for (const p of parameters) {
            next[`p_${p.id}`] = toStr(row?.values[`p_${p.id}`]);
        }

        return next;
    };

    // When the operator picks an hour in the dialog, load that hour's saved data.
    const selectSlot = (slotValue: string) => {
        setSlot(slotValue);
        setForm(valuesForSlot(slotValue));
    };

    const openFor = (row?: Row) => {
        const target = row?.time_slot ?? slots.find((s) => !rows.find((r) => r.time_slot === s)?.filled) ?? slots[0] ?? '';
        setSlot(target);
        setForm(valuesForSlot(target));
        setOpen(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            logsheet.store().url,
            {
                unit_id: filters.unit_id,
                engine_id: filters.engine_id,
                log_date: filters.log_date,
                shift: shift || null,
                time_slot: slot,
                values: form,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const submitSheet = () => {
        router.post(
            logsheet.submit().url,
            { unit_id: filters.unit_id, engine_id: filters.engine_id, log_date: filters.log_date },
            { preserveScroll: true },
        );
    };

    const noEngine = filters.engine_id === null;

    return (
        <>
            <Head title="Logsheet Operator" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Logsheet Operator"
                    description="Pembacaan parameter mesin per jam. Satu lembar per mesin per hari. Klik Isi Data untuk menambah/mengubah data pada jam tertentu."
                    actions={
                        can_write && !noEngine ? (
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => openFor()}>
                                    <Plus className="size-4" />
                                    Isi Data
                                </Button>
                                <Button variant="secondary" onClick={submitSheet}>
                                    <Send className="size-4" />
                                    Kirim
                                </Button>
                            </div>
                        ) : (
                            is_submitted && <StatusBadge tone="success">Terkirim (terkunci)</StatusBadge>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value), engine_id: null })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Mesin"
                        value={filters.engine_id ? String(filters.engine_id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                    />
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Hari/Tanggal</span>
                        <Input type="date" value={filters.log_date} onChange={(e) => visit({ log_date: e.target.value })} className="w-40" />
                    </label>
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Shift</span>
                        <Select value={shift} onValueChange={setShift} disabled={!can_write}>
                            <SelectTrigger className="w-28">
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                {shifts.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        Shift {s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                </div>

                {!noEngine && stats.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <SummaryCard key={stat.label} label={stat.label} value={stat.value} unit={stat.unit} hint={stat.hint} />
                        ))}
                    </div>
                )}

                {noEngine ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Unit ini belum punya mesin aktif.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead rowSpan={2} className="border-r border-border text-center align-middle">Jam</TableHead>
                                    {mainGroups.map((g) =>
                                        g.hasSub ? (
                                            <TableHead key={g.name} colSpan={g.members.length} className="border-r border-border text-center">
                                                {g.name}
                                            </TableHead>
                                        ) : (
                                            <TableHead key={g.name} rowSpan={2} className="border-r border-border text-center align-middle whitespace-nowrap">
                                                {g.members[0].unit_of_measure ? `${g.name} (${g.members[0].unit_of_measure})` : g.name}
                                            </TableHead>
                                        ),
                                    )}
                                    {can_write && <TableHead rowSpan={2} className="text-center align-middle">Aksi</TableHead>}
                                </TableRow>
                                <TableRow>
                                    {mainGroups
                                        .filter((g) => g.hasSub)
                                        .flatMap((g) => g.members.map((m) => (
                                            <TableHead key={m.id} className="border-r border-border text-center whitespace-nowrap">
                                                {m.sub_channel}
                                            </TableHead>
                                        )))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.time_slot} className={row.filled ? undefined : 'text-muted-foreground'}>
                                        <TableCell className="border-r border-border text-center font-medium">{row.time_slot}</TableCell>
                                        {mainParameters.map((p) => (
                                            <TableCell key={p.id} className="border-r border-border text-center tabular-nums">
                                                {toStr(row.values[`p_${p.id}`])}
                                            </TableCell>
                                        ))}
                                        {can_write && (
                                            <TableCell className="text-center">
                                                <Button variant="ghost" size="sm" onClick={() => openFor(row)} aria-label={`Isi jam ${row.time_slot}`}>
                                                    <Pencil className="size-4" />
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {!noEngine && bearingParam && (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <div className="border-b border-border bg-muted/40 px-3 py-2 text-[13px] font-semibold text-foreground">
                            BEARING GENERATOR TEMPERATUR{bearingParam.unit_of_measure ? ` (${bearingParam.unit_of_measure})` : ''}
                        </div>
                        <table className="w-full border-collapse text-[13px]">
                            <tbody>
                                <tr className="bg-muted/40">
                                    <th className="whitespace-nowrap border border-border px-2 py-1 text-center font-medium">JAM</th>
                                    {rows.map((row) => (
                                        <th key={row.time_slot} className="whitespace-nowrap border border-border px-2 py-1 text-center font-medium">
                                            {row.time_slot}
                                        </th>
                                    ))}
                                </tr>
                                <tr>
                                    <td className="whitespace-nowrap border border-border px-2 py-1 text-center font-medium">Suhu</td>
                                    {rows.map((row) => (
                                        <td key={row.time_slot} className="border border-border px-2 py-1 text-center tabular-nums">
                                            {toStr(row.values[`p_${bearingParam.id}`])}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {can_write && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                        <DialogTitle>Isi Data Logsheet</DialogTitle>
                        <div className="flex flex-col gap-4">
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">Jam</span>
                                <Select value={slot} onValueChange={selectSlot}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue placeholder="Pilih jam" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {slots.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {s}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>

                            <div className="grid gap-3 sm:grid-cols-2">
                                {groups.map((g) => (
                                    <div key={g.name} className="rounded-md border border-border p-2">
                                        <div className="mb-1 text-[12px] font-medium text-foreground">
                                            {g.name}
                                            {g.members[0].unit_of_measure ? ` (${g.members[0].unit_of_measure})` : ''}
                                        </div>
                                        <div className={g.members.length > 1 ? 'grid grid-cols-3 gap-2' : ''}>
                                            {g.members.map((m) => (
                                                <label key={m.id} className="flex flex-col gap-1 text-[12px]">
                                                    {m.sub_channel && <span className="text-muted-foreground">{m.sub_channel}</span>}
                                                    <Input
                                                        type="number"
                                                        step="any"
                                                        inputMode="decimal"
                                                        value={form[`p_${m.id}`] ?? ''}
                                                        onChange={(e) => setForm((current) => ({ ...current, [`p_${m.id}`]: e.target.value }))}
                                                    />
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button variant="secondary" type="button" onClick={() => setOpen(false)}>
                                    Batal
                                </Button>
                                <Button type="button" onClick={save} disabled={saving || slot === ''}>
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

LogsheetInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Logsheet Operator', href: logsheet.index() },
    ],
};
