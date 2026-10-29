import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Circle, FileDown, FileSpreadsheet, Plus, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
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
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { downloadLogsheetWorkbook } from '@/lib/operator-logsheet-excel';
import { cn } from '@/lib/utils';
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

/** Spreadsheet cells of the logsheet (same grid as the Patrol Check sheet). */
const sheetTh = 'border border-black bg-yellow-300 px-1.5 py-1 text-[11px] font-bold text-black uppercase dark:border-white dark:bg-yellow-700 dark:text-yellow-50';
const sheetSubTh = 'bg-yellow-200 text-[10px] dark:bg-yellow-800';
const sheetJamTh = 'bg-yellow-400 dark:bg-yellow-600';
const sheetTd = 'h-7 border border-black px-1.5 py-0.5 dark:border-white/60';
const sheetInput =
    'h-7 w-full min-w-12 border-0 bg-transparent px-1 text-center text-xs tabular-nums outline-none focus:bg-sky-50 focus:ring-2 focus:ring-primary focus:ring-inset dark:focus:bg-sky-950';

/** Every hour's values as text, keyed by time slot then parameter key. */
const gridOf = (rows: Row[], parameters: Parameter[]): Record<string, Record<string, string>> =>
    Object.fromEntries(rows.map((row) => [row.time_slot, Object.fromEntries(parameters.map((p) => [`p_${p.id}`, toStr(row.values[`p_${p.id}`])]))]));

export default function LogsheetInput({ filters, stats, header, parameters, rows, slots, shifts, options, can_write, is_submitted }: Props) {
    const [shift, setShift] = useState(header.shift ?? '');
    const [open, setOpen] = useState(false);
    const [slot, setSlot] = useState<string>(slots[0] ?? '');
    const [form, setForm] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    // Desktop: typed straight into the table; only the hours changed are sent on Simpan.
    const [grid, setGrid] = useState(() => gridOf(rows, parameters));
    const [dirtySlots, setDirtySlots] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const setCell = (slotValue: string, key: string, value: string) => {
        setGrid((current) => ({ ...current, [slotValue]: { ...current[slotValue], [key]: value } }));
        setDirtySlots((current) => (current.includes(slotValue) ? current : [...current, slotValue]));
    };

    const saveGrid = () => {
        setSaving(true);
        setErrors({});
        router.post(
            logsheet.sheet().url,
            {
                unit_id: filters.unit_id,
                engine_id: filters.engine_id,
                log_date: filters.log_date,
                shift: shift || null,
                sheet: Object.fromEntries(dirtySlots.map((slotValue) => [slotValue, grid[slotValue]])),
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as unknown as Props;
                    setGrid(gridOf(props.rows, props.parameters));
                    setDirtySlots([]);
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

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
        if (dirtySlots.length > 0 && !window.confirm('Isian yang belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(logsheet.index().url, { ...filters, ...patch }, { preserveScroll: true, replace: true });
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

    const noEngine = filters.engine_id === null;
    const [exporting, setExporting] = useState(false);
    const engineName = options.machines.find((m) => m.id === filters.engine_id)?.name ?? '';
    const unitName = options.units.find((u) => u.id === filters.unit_id)?.name ?? '';
    const dateLabel = new Date(`${filters.log_date}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const exportName = `Logsheet_${unitName}_${engineName}_${filters.log_date.replaceAll('-', '')}`.replace(/[\s#]+/g, '_');

    /** Excel of the saved sheet (same content as the PDF). */
    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadLogsheetWorkbook(
                { unit: unitName, engine: engineName, hariTanggal: dateLabel, shift: header.shift, status: is_submitted ? 'Terkirim' : 'Draft', parameters, rows, stats },
                `${exportName}.xlsx`,
            );
        } finally {
            setExporting(false);
        }
    };
    const filledCount = (row: Row) => parameters.filter((p) => toStr(row.values[`p_${p.id}`]) !== '').length;

    return (
        <>
            <Head title="Logsheet Operator" />
            <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', compact && 'gap-3 pb-28')}>
                {!compact && (
                <PageHeader
                    title="Logsheet Operator"
                    description="Pembacaan parameter mesin per jam. Satu lembar per mesin per hari. Ketik langsung di tabel, lalu tekan Simpan."
                    actions={
                        can_write && !noEngine ? (
                            <div className="flex flex-wrap items-center gap-3">
                                {dirtySlots.length > 0 && <span className="text-[13px] text-amber-600">{dirtySlots.length} jam belum disimpan</span>}
                                <Button onClick={saveGrid} disabled={saving || dirtySlots.length === 0}>
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        ) : undefined
                    }
                />
                )}

                {!noEngine && !compact && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" onClick={() => window.open(logsheet.pdf({ query: { unit_id: filters.unit_id, engine_id: filters.engine_id, log_date: filters.log_date } }).url, '_blank')} className="gap-1.5">
                            <FileDown className="size-4 text-rose-600" />
                            Export PDF
                        </Button>
                        <Button variant="outline" size="sm" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                            <FileSpreadsheet className="size-4 text-emerald-600" />
                            {exporting ? 'Menyiapkan…' : 'Export Excel'}
                        </Button>
                    </div>
                )}

                {compact ? (
                    <OperasiSelect
                        label="Mesin"
                        value={filters.engine_id ? String(filters.engine_id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                        className="w-full"
                    />
                ) : (
                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value), engine_id: null })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className={compact ? 'w-full' : undefined}
                    />
                    <OperasiSelect
                        label="Mesin"
                        value={filters.engine_id ? String(filters.engine_id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                        className={compact ? 'w-full' : undefined}
                    />
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Hari/Tanggal</span>
                        <Input type="date" value={filters.log_date} onChange={(e) => visit({ log_date: e.target.value })} className={compact ? 'w-full' : 'w-40'} />
                    </label>
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Shift</span>
                        <Select value={shift} onValueChange={setShift} disabled={!can_write}>
                            <SelectTrigger className={compact ? 'w-full' : 'w-28'}>
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
                )}

                {!noEngine && !compact && stats.length > 0 && (
                    <div className={compact ? 'grid grid-cols-2 gap-3' : 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4'}>
                        {stats.map((stat) => (
                            <SummaryCard key={stat.label} label={stat.label} value={stat.value} unit={stat.unit} hint={stat.hint} />
                        ))}
                    </div>
                )}

                {noEngine ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Unit ini belum punya mesin aktif.
                    </div>
                ) : compact ? (
                    <div className="flex flex-col gap-2">
                        <p className="flex items-center justify-between text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                            Jam Pembacaan
                            <span className="rounded-full bg-emerald-600/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 normal-case dark:text-emerald-400">
                                {rows.filter((r) => r.filled).length}/{rows.length} jam terisi
                            </span>
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            {rows.map((row) => {
                                const filled = filledCount(row);

                                return (
                                    <button
                                        key={row.time_slot}
                                        type="button"
                                        disabled={!can_write}
                                        onClick={() => openFor(row)}
                                        className={cn(
                                            'relative flex items-center gap-2.5 rounded-xl border p-3 text-left transition active:scale-[0.98] disabled:active:scale-100',
                                            row.filled
                                                ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'
                                                : 'border-border bg-card',
                                        )}
                                    >
                                        {row.filled ? (
                                            <CheckCircle2 className="size-6 shrink-0 fill-emerald-600 text-white" />
                                        ) : (
                                            <Circle className="size-6 shrink-0 text-muted-foreground/40" />
                                        )}
                                        <span className="min-w-0">
                                            <span
                                                className={cn(
                                                    'block text-[15px] font-semibold tabular-nums',
                                                    row.filled ? 'text-emerald-800 dark:text-emerald-300' : 'text-foreground',
                                                )}
                                            >
                                                {row.time_slot}
                                            </span>
                                            <span
                                                className={cn(
                                                    'block text-[11px]',
                                                    row.filled ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground',
                                                )}
                                            >
                                                {row.filled ? `Sudah diisi · ${filled}/${parameters.length}` : 'Belum diisi'}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        {/* Spreadsheet look like the Patrol Check sheet: black grid, sticky JAM column, compact cells. */}
                        <table className="w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr>
                                    <th rowSpan={2} className={cn(sheetTh, sheetJamTh, 'sticky left-0 z-20 w-16 min-w-16')}>
                                        JAM
                                    </th>
                                    {mainGroups.map((g) =>
                                        g.hasSub ? (
                                            <th key={g.name} colSpan={g.members.length} className={sheetTh}>
                                                {g.name}
                                            </th>
                                        ) : (
                                            <th key={g.name} rowSpan={2} className={cn(sheetTh, 'min-w-20 whitespace-nowrap')}>
                                                {g.name}
                                                {g.members[0].unit_of_measure && (
                                                    <span className="block text-[10px] font-medium normal-case">({g.members[0].unit_of_measure})</span>
                                                )}
                                            </th>
                                        ),
                                    )}
                                </tr>
                                <tr>
                                    {mainGroups
                                        .filter((g) => g.hasSub)
                                        .flatMap((g) =>
                                            g.members.map((m) => (
                                                <th key={m.id} className={cn(sheetTh, sheetSubTh, 'min-w-12 whitespace-nowrap')}>
                                                    {m.sub_channel}
                                                </th>
                                            )),
                                        )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => {
                                    const filledNow = Object.values(grid[row.time_slot] ?? {}).some((value) => value !== '');

                                    return (
                                        <tr key={row.time_slot} className="group hover:bg-muted/40">
                                            <td
                                                className={cn(
                                                    sheetTd,
                                                    'sticky left-0 z-10 font-semibold tabular-nums',
                                                    filledNow ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-50 dark:bg-card',
                                                )}
                                            >
                                                {row.time_slot}
                                                {dirtySlots.includes(row.time_slot) && <span className="ml-0.5 text-amber-600">•</span>}
                                            </td>
                                            {mainParameters.map((p) => {
                                                const key = `p_${p.id}`;
                                                const error = errors[`sheet.${row.time_slot}.${key}`];

                                                return (
                                                    <td key={p.id} className={cn(sheetTd, 'p-0', error && 'bg-destructive/10')} title={error}>
                                                        {can_write ? (
                                                            <input
                                                                inputMode="decimal"
                                                                value={grid[row.time_slot]?.[key] ?? ''}
                                                                onChange={(e) => setCell(row.time_slot, key, e.target.value.replace(',', '.'))}
                                                                aria-label={`${p.label} jam ${row.time_slot}`}
                                                                className={sheetInput}
                                                            />
                                                        ) : (
                                                            <span className="block px-1.5 tabular-nums">{toStr(row.values[key])}</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {!noEngine && !compact && bearingParam && (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <table className="w-full border-collapse border-2 border-black text-center text-xs dark:border-white">
                            <thead>
                                <tr>
                                    <th colSpan={rows.length + 1} className={cn(sheetTh, 'text-left')}>
                                        BEARING GENERATOR TEMPERATUR{bearingParam.unit_of_measure ? ` (${bearingParam.unit_of_measure})` : ''}
                                    </th>
                                </tr>
                                <tr>
                                    <th className={cn(sheetTh, sheetJamTh, 'sticky left-0 z-20 w-16 min-w-16')}>JAM</th>
                                    {rows.map((row) => (
                                        <th key={row.time_slot} className={cn(sheetTh, sheetSubTh, 'min-w-12')}>
                                            {row.time_slot}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td className={cn(sheetTd, 'sticky left-0 z-10 bg-card font-semibold')}>Suhu</td>
                                    {rows.map((row) => (
                                        <td key={row.time_slot} className={cn(sheetTd, 'p-0')}>
                                            {can_write ? (
                                                <input
                                                    inputMode="decimal"
                                                    value={grid[row.time_slot]?.[`p_${bearingParam.id}`] ?? ''}
                                                    onChange={(e) => setCell(row.time_slot, `p_${bearingParam.id}`, e.target.value.replace(',', '.'))}
                                                    aria-label={`Bearing jam ${row.time_slot}`}
                                                    className={sheetInput}
                                                />
                                            ) : (
                                                <span className="block px-1.5 tabular-nums">{toStr(row.values[`p_${bearingParam.id}`])}</span>
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {compact && can_write && !noEngine && (
                <div className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-1 gap-2 border-t border-border bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
                    <Button size="lg" onClick={() => openFor()}>
                        <Plus className="size-4" />
                        Isi Data
                    </Button>
                </div>
            )}

            {can_write && compact && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                        <DialogTitle>Isi Data Logsheet</DialogTitle>
                        <div className="flex flex-col gap-4">
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">Jam</span>
                                <Select value={slot} onValueChange={selectSlot}>
                                    <SelectTrigger className={compact ? 'w-full' : 'w-40'}>
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

                            <div className={cn('flex justify-end gap-2', compact && 'sticky -bottom-6 -mx-6 border-t border-border bg-background px-6 py-3 [&>button]:flex-1')}>
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
