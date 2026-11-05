import { Head, router } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import startStop from '@/routes/operator/start-stop';

type Penyebab = 'normal' | 'gangguan';
type TimeKey = 'start_time' | 'cbg_on' | 'cbg_off' | 'stop_time';

type Row = {
    id: number | null;
    key: string;
    tanggal: string;
    engine_id: number | null;
    penyebab_stop: Penyebab;
    keterangan: string;
    input_by: string | null;
} & Record<TimeKey, string>;

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: { unit_id: number; month: number; year: number };
    period_label: string;
    days_in_month: number;
    machines: { id: number; name: string }[];
    rows: (Omit<Row, 'key'> & { id: number })[];
    penyebab: Record<Penyebab, string>;
    today: string;
    can_write: boolean;
};

const TIMES: [TimeKey, string][] = [
    ['start_time', 'START'],
    ['cbg_on', 'CBG ON'],
    ['cbg_off', 'CBG OFF'],
    ['stop_time', 'STOP'],
];
const YEARS = Array.from({ length: 7 }, (_, i) => new Date().getFullYear() - 4 + i);
const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

let nextKey = 0;
const toRow = (row: Props['rows'][number]): Row => ({ ...row, key: `saved-${row.id}` });

/** Hours from start to stop ("HH:MM"); a stop at or before the start is the next day. */
const runHours = (start: string, stop: string): number | null => {
    if (!/^\d{2}:\d{2}$/.test(start) || !/^\d{2}:\d{2}$/.test(stop)) {
        return null;
    }

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = stop.split(':').map(Number);
    let minutes = eh * 60 + em - (sh * 60 + sm);

    if (minutes <= 0) {
        minutes += 24 * 60;
    }

    return Math.round((minutes / 60) * 100) / 100;
};

const xlTh = 'border border-black bg-yellow-200 px-1.5 py-1.5 text-center text-[11px] font-bold text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTd = 'h-8 border border-black p-0 dark:border-white/60';
const xlInput =
    'h-8 w-full bg-transparent px-1.5 text-center text-xs tabular-nums outline-none focus:bg-yellow-50 focus:ring-0 disabled:cursor-not-allowed dark:focus:bg-yellow-950/40';

/**
 * Start Stop Engine (modul Operator): each run of a machine — start, CBG on,
 * CBG off and stop — per tanggal, several a day, with its jam operasi and
 * whether it stopped because of a gangguan (Jumlah Kali Gangguan). Desktop
 * is a spreadsheet of the month; phones pick a tanggal and fill its runs.
 */
export default function StartStopIndex({ unit, units, filters, period_label, days_in_month, machines, rows: savedRows, penyebab, today, can_write }: Props) {
    const compact = useCompactLayout();
    const prefix = `${filters.year}-${String(filters.month).padStart(2, '0')}`;
    const [selectedDay, setSelectedDay] = useState(() => (today.startsWith(prefix) ? Number(today.slice(8, 10)) : 1));
    const [rows, setRows] = useState<Row[]>(() => savedRows.map(toRow));
    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const dateOf = (day: number) => `${prefix}-${String(day).padStart(2, '0')}`;
    const machineName = (id: number | null) => machines.find((m) => m.id === id)?.name ?? '';

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(startStop.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (key: string, patch: Partial<Row>) => {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const addRow = (tanggal: string) => {
        setRows((current) => [
            ...current,
            {
                id: null,
                key: `new-${++nextKey}`,
                tanggal,
                engine_id: machines.length === 1 ? machines[0].id : null,
                start_time: '',
                cbg_on: '',
                cbg_off: '',
                stop_time: '',
                penyebab_stop: 'normal',
                keterangan: '',
                input_by: null,
            },
        ]);
        setDirty(true);
    };

    const removeRow = (row: Row) => {
        if (row.id !== null && !window.confirm(`Hapus data ${machineName(row.engine_id)} tanggal ${Number(row.tanggal.slice(8))}? Terhapus saat Simpan ditekan.`)) {
            return;
        }

        if (row.id !== null) {
            setDeletedIds((current) => [...current, row.id as number]);
        }

        setRows((current) => current.filter((r) => r.key !== row.key));
        setErrors({});
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        setErrors({});
        router.post(
            startStop.sync().url,
            {
                ...filters,
                deleted_ids: deletedIds,
                rows: rows.map((row) => ({
                    id: row.id,
                    tanggal: row.tanggal,
                    engine_id: row.engine_id,
                    start_time: row.start_time,
                    cbg_on: row.cbg_on,
                    cbg_off: row.cbg_off,
                    stop_time: row.stop_time,
                    penyebab_stop: row.penyebab_stop,
                    keterangan: row.keterangan,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setRows((page.props as unknown as Props).rows.map(toRow));
                    setDeletedIds([]);
                    setDirty(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                    toast.error(Object.values(errs)[0] ?? 'Periksa kembali isian.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const indexOf = (row: Row) => rows.indexOf(row);
    const errorOf = (row: Row, field: string) => errors[`rows.${indexOf(row)}.${field}`];
    const totalHours = rows.reduce((total, row) => total + (runHours(row.start_time, row.stop_time) ?? 0), 0);
    const gangguanCount = rows.filter((row) => row.penyebab_stop === 'gangguan').length;

    if (compact) {
        const dayRows = rows.filter((row) => row.tanggal === dateOf(selectedDay));

        return (
            <>
                <Head title="Start Stop Engine" />
                <div className={cn('flex flex-col gap-3 p-4', can_write && 'pb-28')}>
                    <DayStrip
                        items={days.map((day) => ({ key: String(day), label: String(day), sub: 'Tgl', done: rows.some((row) => row.tanggal === dateOf(day)) }))}
                        value={String(selectedDay)}
                        onChange={(key) => setSelectedDay(Number(key))}
                    />
                    <p className="px-0.5 text-[13px] font-semibold text-foreground">
                        Tanggal {selectedDay} {OPERASI_MONTHS[filters.month - 1]} · {dayRows.length} catatan
                    </p>

                    {dayRows.length === 0 && (
                        <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                            Belum ada start/stop mesin tanggal ini.{can_write && ' Tekan Tambah untuk mengisi.'}
                        </p>
                    )}

                    {dayRows.map((row) => {
                        const hours = runHours(row.start_time, row.stop_time);

                        return (
                            <div key={row.key} className={cn('flex flex-col gap-3 rounded-xl border bg-card p-3', row.penyebab_stop === 'gangguan' ? 'border-rose-400' : 'border-border')}>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[11px] font-semibold text-muted-foreground">Mesin</span>
                                    <span className="shrink-0 rounded-md bg-emerald-100 px-2 py-1 text-[12px] font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                        {hours !== null ? `${NUMBER.format(hours)} jam` : '– jam'}
                                    </span>
                                </div>
                                <Select
                                    value={row.engine_id === null ? undefined : String(row.engine_id)}
                                    onValueChange={(value) => update(row.key, { engine_id: Number(value) })}
                                    disabled={!can_write}
                                >
                                    <SelectTrigger
                                        className={cn('-mt-1 h-11 w-full text-[14px] font-semibold', errorOf(row, 'engine_id') && 'border-destructive')}
                                        aria-label="Mesin"
                                    >
                                        <SelectValue placeholder="Pilih mesin…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {machines.map((m) => (
                                            <SelectItem key={m.id} value={String(m.id)} className="py-2.5 text-[14px]">
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errorOf(row, 'engine_id') && <p className="-mt-1 text-[12px] text-destructive">{errorOf(row, 'engine_id')}</p>}
                                <div className="grid grid-cols-2 gap-2">
                                    {TIMES.map(([key, label]) => (
                                        <label key={key} className="flex flex-col gap-1 text-[11px] font-semibold text-muted-foreground">
                                            {label}
                                            <input
                                                type="time"
                                                value={row[key]}
                                                onChange={(e) => update(row.key, { [key]: e.target.value })}
                                                disabled={!can_write}
                                                className={cn('h-10 w-full rounded-md border bg-background px-2 text-[15px] font-semibold tabular-nums', errorOf(row, key) ? 'border-destructive' : 'border-input')}
                                            />
                                        </label>
                                    ))}
                                </div>
                                <div className="flex flex-col gap-1 text-[11px] font-semibold text-muted-foreground">
                                    Penyebab stop
                                    <ChoiceChips
                                        options={Object.keys(penyebab)}
                                        labels={penyebab}
                                        value={row.penyebab_stop}
                                        onChange={(value) => value && update(row.key, { penyebab_stop: value as Penyebab })}
                                        disabled={!can_write}
                                        tone={(value) => (value === 'gangguan' ? 'border-rose-600 bg-rose-600 text-white' : 'border-emerald-600 bg-emerald-600 text-white')}
                                    />
                                </div>
                                <input
                                    value={row.keterangan}
                                    onChange={(e) => update(row.key, { keterangan: e.target.value })}
                                    disabled={!can_write}
                                    placeholder={row.penyebab_stop === 'gangguan' ? 'Keterangan gangguan, mis. trip lube oil pressure' : 'Keterangan (opsional)'}
                                    className="h-10 w-full rounded-md border border-input bg-background px-2 text-[13px]"
                                />
                                {can_write && (
                                    <Button variant="ghost" size="sm" onClick={() => removeRow(row)} className="self-start text-destructive">
                                        <Trash2 className="size-4" />
                                        Hapus
                                    </Button>
                                )}
                            </div>
                        );
                    })}
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" variant="outline" onClick={() => addRow(dateOf(selectedDay))}>
                            <Plus className="size-4" />
                            Tambah
                        </Button>
                        <Button size="lg" onClick={save} disabled={saving || !dirty}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    const sorted = [...rows].sort((a, b) => (a.tanggal === b.tanggal ? 0 : a.tanggal < b.tanggal ? -1 : 1));

    return (
        <>
            <Head title="Start Stop Engine" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Start Stop Engine"
                    description={`Jam start, CBG on, CBG off dan stop setiap mesin — ${unit.name} · ${period_label}. Stop karena gangguan dihitung ke Jumlah Kali Gangguan.`}
                    actions={
                        can_write ? (
                            <div className="flex flex-wrap items-center gap-3">
                                {dirty && <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        ) : undefined
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-56" />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={YEARS.map((y) => ({ value: String(y), label: String(y) }))} className="w-28" />
                    <div className="ml-auto flex gap-4 pb-1.5 text-[13px] text-muted-foreground">
                        <span>
                            Total jam operasi <b className="text-foreground">{NUMBER.format(totalHours)}</b>
                        </span>
                        <span>
                            Stop gangguan <b className="text-rose-600">{gangguanCount}×</b>
                        </span>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1100px] border-collapse border-2 border-black text-xs dark:border-white">
                        <thead>
                            <tr>
                                <th rowSpan={2} className={cn(xlTh, 'w-10')}>NO</th>
                                <th rowSpan={2} className={cn(xlTh, 'w-36')}>TANGGAL</th>
                                <th rowSpan={2} className={cn(xlTh, 'min-w-40')}>MESIN</th>
                                <th colSpan={4} className={xlTh}>JAM OPERASI MESIN</th>
                                <th rowSpan={2} className={cn(xlTh, 'w-20 bg-emerald-200 dark:bg-emerald-800')}>JAM OPERASI</th>
                                <th rowSpan={2} className={cn(xlTh, 'w-28')}>PENYEBAB STOP</th>
                                <th rowSpan={2} className={cn(xlTh, 'min-w-48')}>KETERANGAN</th>
                                {can_write && <th rowSpan={2} className={cn(xlTh, 'w-9')} />}
                            </tr>
                            <tr>
                                {TIMES.map(([key, label]) => (
                                    <th key={key} className={cn(xlTh, 'w-24 bg-yellow-100 dark:bg-yellow-900')}>
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {sorted.length === 0 && (
                                <tr>
                                    <td colSpan={can_write ? 11 : 10} className={cn(xlTd, 'p-6 text-center text-[13px] text-muted-foreground')}>
                                        Belum ada data start stop bulan ini.{can_write && ' Tekan Tambah Baris untuk mengisi.'}
                                    </td>
                                </tr>
                            )}
                            {sorted.map((row, number) => {
                                const hours = runHours(row.start_time, row.stop_time);
                                const gangguan = row.penyebab_stop === 'gangguan';

                                return (
                                    <tr key={row.key} className={cn('hover:bg-muted/30', gangguan && 'bg-rose-50/60 dark:bg-rose-950/20')}>
                                        <td className={cn(xlTd, 'text-center font-medium')}>{number + 1}</td>
                                        <Cell error={errorOf(row, 'tanggal')}>
                                            <input
                                                type="date"
                                                min={dateOf(1)}
                                                max={dateOf(days_in_month)}
                                                value={row.tanggal}
                                                onChange={(e) => update(row.key, { tanggal: e.target.value })}
                                                disabled={!can_write}
                                                className={xlInput}
                                                aria-label="Tanggal"
                                            />
                                        </Cell>
                                        <Cell error={errorOf(row, 'engine_id')}>
                                            <PdmCellSelect
                                                value={machineName(row.engine_id)}
                                                onChange={(name) => update(row.key, { engine_id: machines.find((m) => m.name === name)?.id ?? null })}
                                                options={machines.map((m) => m.name)}
                                                placeholder="Pilih mesin…"
                                                className="h-8 font-semibold"
                                                disabled={!can_write}
                                            />
                                        </Cell>
                                        {TIMES.map(([key, label]) => (
                                            <Cell key={key} error={errorOf(row, key)}>
                                                <input
                                                    type="time"
                                                    value={row[key]}
                                                    onChange={(e) => update(row.key, { [key]: e.target.value })}
                                                    disabled={!can_write}
                                                    className={xlInput}
                                                    aria-label={`${label} ${machineName(row.engine_id)}`}
                                                />
                                            </Cell>
                                        ))}
                                        <td className={cn(xlTd, 'bg-emerald-50 px-1.5 text-center font-semibold text-emerald-900 tabular-nums dark:bg-emerald-950/40 dark:text-emerald-200')}>
                                            {hours !== null ? NUMBER.format(hours) : ''}
                                        </td>
                                        <td className={xlTd}>
                                            <PdmCellSelect
                                                value={penyebab[row.penyebab_stop]}
                                                onChange={(label) => update(row.key, { penyebab_stop: label === penyebab.gangguan ? 'gangguan' : 'normal' })}
                                                options={Object.values(penyebab)}
                                                className={cn('h-8 justify-center font-semibold', gangguan ? 'text-rose-600' : 'text-emerald-700')}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td className={xlTd}>
                                            <input
                                                value={row.keterangan}
                                                onChange={(e) => update(row.key, { keterangan: e.target.value })}
                                                disabled={!can_write}
                                                placeholder={gangguan ? 'Keterangan gangguan…' : ''}
                                                className={cn(xlInput, 'text-left')}
                                                aria-label="Keterangan"
                                            />
                                        </td>
                                        {can_write && (
                                            <td className={cn(xlTd, 'text-center')}>
                                                <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(row)} title="Hapus baris">
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                            {sorted.length > 0 && (
                                <tr className="bg-yellow-200 font-bold text-black dark:bg-yellow-800 dark:text-yellow-50">
                                    <td colSpan={7} className={cn(xlTd, 'px-2 text-right')}>TOTAL JAM OPERASI</td>
                                    <td className={cn(xlTd, 'text-center tabular-nums')}>{NUMBER.format(totalHours)}</td>
                                    <td className={cn(xlTd, 'text-center text-rose-700 dark:text-rose-200')}>{gangguanCount}× gangguan</td>
                                    <td colSpan={can_write ? 2 : 1} className={xlTd} />
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {can_write && (
                    <div>
                        <Button variant="outline" size="sm" className="gap-1.5" onClick={() => addRow(today.startsWith(prefix) ? today : dateOf(1))}>
                            <Plus className="size-4" />
                            Tambah Baris
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

function Cell({ error, children }: { error?: string; children: ReactNode }) {
    return (
        <td className={cn(xlTd, error && 'bg-destructive/10 ring-1 ring-destructive ring-inset')} title={error}>
            {children}
        </td>
    );
}

StartStopIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Start Stop Engine', href: startStop.index() },
    ],
};
