import { Head, router } from '@inertiajs/react';
import { Download, Loader2, Save, Timer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { formatStamp } from '@/components/monitoring/shared';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import jamGangguan from '@/routes/operasi/pengusahaan/jam-gangguan';
import jamOperasi from '@/routes/operasi/pengusahaan/jam-operasi';
import jamPembangkit from '@/routes/operasi/pengusahaan/jam-pembangkit';
import jamPemeliharaan from '@/routes/operasi/pengusahaan/jam-pemeliharaan';
import jamSiapOps from '@/routes/operasi/pengusahaan/jam-siap-ops';

type Jenis = 'operasi' | 'pemeliharaan' | 'mo' | 'gangguan' | 'standby';
type Machine = { id: number; name: string; type: string | null; serial_number: string | null };
/** jenis → machine id → day → hours. */
type Sheets = Record<Jenis, Record<string, Record<string, number | string>>>;
type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    machines: Machine[];
    days_in_month: number;
    jenis: { value: Jenis; label: string }[];
    readings: Sheets;
    star_stop: Sheets;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

const JENIS: Jenis[] = ['operasi', 'pemeliharaan', 'mo', 'gangguan', 'standby'];
/** Column heads of the sheet: Operasi, PO (pemeliharaan), MO, Gangguan, Standby, then their Total. */
const COLUMNS = [...JENIS, 'total'] as const;
const SHORT: Record<Jenis | 'total', string> = { operasi: 'OPS', pemeliharaan: 'PO', mo: 'MO', gangguan: 'GGN', standby: 'STBY', total: 'TOTAL' };
const TONE: Record<Jenis | 'total', string> = {
    operasi: 'bg-emerald-100 dark:bg-emerald-950/50',
    pemeliharaan: 'bg-sky-100 dark:bg-sky-950/50',
    mo: 'bg-orange-100 dark:bg-orange-950/50',
    gangguan: 'bg-rose-100 dark:bg-rose-950/50',
    standby: 'bg-slate-100 dark:bg-slate-800/60',
    total: 'bg-yellow-100 dark:bg-yellow-950/50',
};
const LABEL: Record<Jenis | 'total', string> = {
    operasi: 'Jam Operasi',
    pemeliharaan: 'Jam Pemeliharaan (PO)',
    mo: 'Jam Maintenance Outage (MO)',
    gangguan: 'Jam Gangguan',
    standby: 'Jam Standby',
    total: 'Total (harus 24 jam)',
};
const HOURS = 24;
const YEARS = Array.from({ length: 7 }, (_, i) => new Date().getFullYear() - 4 + i);
const NUMBER = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

const toNumber = (value: number | string | undefined): number => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};
const fmt = (value: number) => (value === 0 ? '' : NUMBER.format(value));

const xlTh = 'border border-black bg-yellow-200 px-1 py-1 text-center text-[11px] font-bold text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTd = 'h-7 border border-black p-0 text-center dark:border-white/60';
const xlInput =
    'h-7 w-full min-w-11 bg-transparent px-1 text-center text-xs tabular-nums outline-none focus:bg-yellow-50 focus:ring-0 disabled:cursor-not-allowed dark:focus:bg-yellow-950/40';

/**
 * Pengusahaan Operasi — Jam Pembangkit: Jam Operasi, Jam Pemeliharaan (PO),
 * Jam MO, Jam Gangguan and Jam Standby of every mesin in one sheet, a column
 * each, with their Total beside them — a filled day must make 24 jam. Phones
 * pick a tanggal and fill the five hours per mesin.
 */
export default function JamPembangkitIndex({
    unit,
    units,
    filters,
    period_label,
    machines,
    days_in_month,
    readings: saved,
    star_stop,
    catatan,
    saved_at,
    can_write,
}: Props) {
    const compact = useCompactLayout();
    const [sheets, setSheets] = useState<Sheets>(saved);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [shown, setShown] = useState<'all' | number>('all');
    const [selectedDay, setSelectedDay] = useState(() => {
        const today = new Date();

        return today.getMonth() + 1 === filters.month && today.getFullYear() === filters.year ? today.getDate() : 1;
    });

    const dirty = useMemo(() => JSON.stringify(sheets) !== JSON.stringify(saved) || note !== catatan, [sheets, saved, note, catatan]);
    const days = Array.from({ length: days_in_month }, (_, i) => i + 1);
    const visibleMachines = shown === 'all' ? machines : machines.filter((m) => m.id === shown);
    const starStopCells = JENIS.reduce((count, jenis) => count + Object.values(star_stop[jenis] ?? {}).reduce((n, row) => n + Object.keys(row).length, 0), 0);

    const cell = (jenis: Jenis, machineId: number, day: number) => toNumber(sheets[jenis]?.[machineId]?.[day]);
    const used = (machineId: number, day: number) => Math.round(JENIS.reduce((total, jenis) => total + cell(jenis, machineId, day), 0) * 100) / 100;
    const monthTotal = (jenis: Jenis | 'total', machineId: number) =>
        days.reduce((total, day) => total + (jenis === 'total' ? used(machineId, day) : cell(jenis, machineId, day)), 0);

    const setCell = (jenis: Jenis, machineId: number, day: number, value: string) =>
        setSheets((current) => {
            const row = { ...(current[jenis]?.[machineId] ?? {}) };

            if (value === '') {
                delete row[day];
            } else {
                row[day] = value.replace(',', '.');
            }

            return { ...current, [jenis]: { ...(current[jenis] ?? {}), [machineId]: row } };
        });

    /** Fill the empty cells with the hours recorded in Star-Stop (filled cells are kept). */
    const fillFromStarStop = () =>
        setSheets((current) => {
            const next = { ...current };

            for (const jenis of JENIS) {
                const sheet = { ...(next[jenis] ?? {}) };

                for (const [machineId, row] of Object.entries(star_stop[jenis] ?? {})) {
                    const merged = { ...(sheet[machineId] ?? {}) };

                    for (const [day, value] of Object.entries(row)) {
                        if (toNumber(merged[day]) === 0) {
                            merged[day] = value;
                        }
                    }

                    sheet[machineId] = merged;
                }

                next[jenis] = sheet;
            }

            return next;
        });

    const visit = (patch: Partial<Filters>) => {
        if (dirty && can_write && !window.confirm('Perubahan belum disimpan. Pindah halaman dan buang perubahan?')) {
            return;
        }

        router.get(jamPembangkit.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const save = () => {
        const readings: Record<string, Record<string, Record<string, number>>> = {};

        for (const jenis of JENIS) {
            readings[jenis] = {};

            for (const [machineId, row] of Object.entries(sheets[jenis] ?? {})) {
                for (const [day, value] of Object.entries(row)) {
                    const hours = toNumber(value);

                    if (hours > 0) {
                        readings[jenis][machineId] = { ...(readings[jenis][machineId] ?? {}), [day]: hours };
                    }
                }
            }
        }

        setSaving(true);
        setErrors({});
        router.post(
            jamPembangkit.store().url,
            { ...filters, readings, catatan: note },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as unknown as Props;
                    setSheets(props.readings);
                    setNote(props.catatan);
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    /** A filled day whose five hours do not make 24 jam. */
    const offDay = (machineId: number, day: number) => {
        const total = used(machineId, day);

        return total > 0 && Math.abs(total - HOURS) > 0.001;
    };

    /** Put the rest of a filled day's 24 jam into an empty Standby cell. */
    const fillStandby = () =>
        setSheets((current) => {
            const standby = { ...(current.standby ?? {}) };

            for (const machine of machines) {
                for (const day of days) {
                    const others = JENIS.filter((jenis) => jenis !== 'standby').reduce((total, jenis) => total + toNumber(current[jenis]?.[machine.id]?.[day]), 0);

                    if (others > 0 && others < HOURS && toNumber(current.standby?.[machine.id]?.[day]) === 0) {
                        standby[machine.id] = { ...(standby[machine.id] ?? {}), [day]: String(Math.round((HOURS - others) * 100) / 100) };
                    }
                }
            }

            return { ...current, standby };
        });
    const hasRemainder = machines.some((machine) => days.some((day) => used(machine.id, day) > 0 && used(machine.id, day) < HOURS && cell('standby', machine.id, day) === 0));
    const errorList = Object.values(errors);
    const pdfQuery = { query: { unit_id: filters.unit_id, month: filters.month, year: filters.year } };

    const pdfMenu = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-1.5">
                    <Download className="size-4 text-rose-600" />
                    PDF
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => window.open(jamOperasi.pdf(pdfQuery).url, '_blank')}>Jam Operasi</DropdownMenuItem>
                <DropdownMenuItem onClick={() => window.open(jamPemeliharaan.pdf(pdfQuery).url, '_blank')}>Jam Pemeliharaan</DropdownMenuItem>
                <DropdownMenuItem onClick={() => window.open(jamGangguan.pdf(pdfQuery).url, '_blank')}>Jam Gangguan</DropdownMenuItem>
                <DropdownMenuItem onClick={() => window.open(jamSiapOps.pdf(pdfQuery).url, '_blank')}>Jam Siap Operasi</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const errorBox = errorList.length > 0 && (
        <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-[13px] text-destructive">
            {errorList.slice(0, 5).map((message) => (
                <p key={message}>{message}</p>
            ))}
            {errorList.length > 5 && <p>… dan {errorList.length - 5} lainnya.</p>}
        </div>
    );

    if (compact) {
        return (
            <>
                <Head title={`Jam Pembangkit - ${unit.name}`} />
                <div className={cn('flex flex-col gap-3 p-4', can_write && 'pb-28')}>
                    <DayStrip
                        items={days.map((day) => ({
                            key: String(day),
                            label: String(day),
                            sub: 'Tgl',
                            done: machines.some((m) => used(m.id, day) > 0),
                        }))}
                        value={String(selectedDay)}
                        onChange={(key) => setSelectedDay(Number(key))}
                    />

                    <div className="flex items-center justify-between px-0.5">
                        <p className="text-[13px] font-semibold text-foreground">
                            Tanggal {selectedDay} {OPERASI_MONTHS[filters.month - 1]}
                        </p>
                        <div className="flex gap-2">
                            {can_write && hasRemainder && (
                                <Button variant="outline" size="sm" onClick={fillStandby}>
                                    Isi Standby
                                </Button>
                            )}
                            {can_write && starStopCells > 0 && (
                                <Button variant="outline" size="sm" onClick={fillFromStarStop}>
                                    <Timer className="size-4" />
                                    Dari Star-Stop
                                </Button>
                            )}
                        </div>
                    </div>

                    {errorBox}

                    {machines.length === 0 && (
                        <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">Unit ini belum punya mesin aktif.</p>
                    )}

                    {machines.map((machine) => {
                        const total = used(machine.id, selectedDay);
                        const off = offDay(machine.id, selectedDay);

                        return (
                            <div key={machine.id} className={cn('flex flex-col gap-2.5 rounded-xl border bg-card p-3', off ? 'border-destructive' : 'border-border')}>
                                <div className="flex items-center justify-between gap-2">
                                    <p className="font-semibold text-foreground">{machine.name}</p>
                                    <span
                                        className={cn(
                                            'rounded-md px-2 py-0.5 text-[12px] font-semibold',
                                            off ? 'bg-destructive/10 text-destructive' : 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/50 dark:text-yellow-200',
                                        )}
                                    >
                                        Total {NUMBER.format(total)} / 24 jam
                                    </span>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    {JENIS.map((jenis) => (
                                        <label key={jenis} className={cn('flex flex-col gap-1 rounded-lg p-2 text-[11px] font-semibold text-muted-foreground', TONE[jenis])}>
                                            {SHORT[jenis]}
                                            <input
                                                inputMode="decimal"
                                                value={String(sheets[jenis]?.[machine.id]?.[selectedDay] ?? '')}
                                                onChange={(e) => setCell(jenis, machine.id, selectedDay, e.target.value)}
                                                disabled={!can_write}
                                                placeholder="0"
                                                className="h-10 w-full rounded-md border border-input bg-background px-2 text-center text-[15px] font-semibold text-foreground tabular-nums outline-none focus:ring-2 focus:ring-primary"
                                                aria-label={`${LABEL[jenis]} ${machine.name} tanggal ${selectedDay}`}
                                            />
                                        </label>
                                    ))}
                                </div>
                                {off && <p className="text-[12px] text-destructive">Total jam harus 24 jam.</p>}
                            </div>
                        );
                    })}

                    <p className="px-0.5 text-[12px] text-muted-foreground">OPS = operasi · PO = pemeliharaan · MO = maintenance outage · GGN = gangguan · STBY = standby. Totalnya harus 24 jam.</p>
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" onClick={save} disabled={saving || !dirty}>
                            {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={`Jam Pembangkit - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jam Pembangkit"
                    description={`Jam operasi, pemeliharaan (PO), MO, gangguan dan standby tiap mesin per hari, totalnya 24 jam — ${unit.name} · ${period_label}.`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {dirty && <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                            {can_write && hasRemainder && (
                                <Button variant="outline" onClick={fillStandby} title="Isi sel Standby yang kosong dengan sisa jam sampai 24 jam">
                                    Isi Standby (sisa 24 jam)
                                </Button>
                            )}
                            {can_write && starStopCells > 0 && (
                                <Button variant="outline" onClick={fillFromStarStop} className="gap-1.5" title="Isi sel yang masih kosong dengan jam dari Star-Stop">
                                    <Timer className="size-4" />
                                    Isi dari Star-Stop
                                </Button>
                            )}
                            {pdfMenu}
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-56" />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={YEARS.map((y) => ({ value: String(y), label: String(y) }))} className="w-28" />
                    {machines.length > 1 && (
                        <OperasiSelect
                            label="Tampilkan mesin"
                            value={String(shown)}
                            onChange={(v) => setShown(v === 'all' ? 'all' : Number(v))}
                            options={[{ value: 'all', label: 'Semua mesin' }, ...machines.map((m) => ({ value: String(m.id), label: m.name }))]}
                            className="w-48"
                        />
                    )}
                    {saved_at && <span className="ml-auto pb-2 text-[12px] text-muted-foreground">Disimpan {formatStamp(saved_at)}</span>}
                </div>

                <div className="flex flex-wrap gap-3 text-[12px] text-muted-foreground">
                    {COLUMNS.map((key) => (
                        <span key={key} className="flex items-center gap-1.5">
                            <span className={cn('inline-block size-3 rounded-sm border border-black/30', TONE[key])} />
                            <b className="text-foreground">{SHORT[key]}</b> = {LABEL[key]}
                        </span>
                    ))}
                </div>

                {errorBox}

                {machines.length === 0 ? (
                    <p className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Unit ini belum punya mesin aktif.</p>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <table className="w-full border-collapse border-2 border-black text-xs dark:border-white">
                            <thead>
                                <tr>
                                    <th rowSpan={2} className={cn(xlTh, 'sticky left-0 z-20 w-16 min-w-16')}>TGL</th>
                                    {visibleMachines.map((machine) => (
                                        <th key={machine.id} colSpan={COLUMNS.length} className={cn(xlTh, 'border-l-2 border-l-black dark:border-l-white')}>
                                            {machine.name}
                                        </th>
                                    ))}
                                </tr>
                                <tr>
                                    {visibleMachines.map((machine) =>
                                        COLUMNS.map((key, i) => (
                                            <th key={`${machine.id}-${key}`} className={cn(xlTh, TONE[key], 'text-[10px] text-black dark:text-white', i === 0 && 'border-l-2 border-l-black dark:border-l-white')} title={LABEL[key]}>
                                                {SHORT[key]}
                                            </th>
                                        )),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {days.map((day) => (
                                    <tr key={day} className="hover:bg-muted/30">
                                        <td className={cn(xlTd, 'sticky left-0 z-10 bg-yellow-50 font-semibold dark:bg-card')}>{day}</td>
                                        {visibleMachines.map((machine) => {
                                            const off = offDay(machine.id, day);
                                            const total = used(machine.id, day);

                                            return [
                                                ...JENIS.map((jenis, i) => {
                                                    const error = errors[`readings.${jenis}.${machine.id}.${day}`];

                                                    return (
                                                        <td key={`${machine.id}-${jenis}`} className={cn(xlTd, (error || off) && 'bg-destructive/10', i === 0 && 'border-l-2 border-l-black dark:border-l-white')} title={error}>
                                                            <input
                                                                inputMode="decimal"
                                                                value={String(sheets[jenis]?.[machine.id]?.[day] ?? '')}
                                                                onChange={(e) => setCell(jenis, machine.id, day, e.target.value)}
                                                                disabled={!can_write}
                                                                className={xlInput}
                                                                aria-label={`${LABEL[jenis]} ${machine.name} tanggal ${day}`}
                                                            />
                                                        </td>
                                                    );
                                                }),
                                                <td
                                                    key={`${machine.id}-total`}
                                                    className={cn(xlTd, 'px-1 font-semibold tabular-nums', off ? 'bg-destructive/15 text-destructive' : TONE.total)}
                                                    title={off ? 'Total jam harus 24 jam' : 'OPS + PO + MO + GGN + STBY'}
                                                >
                                                    {total > 0 ? NUMBER.format(total) : ''}
                                                </td>,
                                            ];
                                        })}
                                    </tr>
                                ))}
                                <tr className="bg-yellow-200 font-bold text-black dark:bg-yellow-800 dark:text-yellow-50">
                                    <td className={cn(xlTd, 'sticky left-0 z-10 bg-yellow-200 dark:bg-yellow-800')}>JML</td>
                                    {visibleMachines.map((machine) =>
                                        COLUMNS.map((key, i) => (
                                            <td key={`${machine.id}-${key}`} className={cn(xlTd, 'px-1 tabular-nums', i === 0 && 'border-l-2 border-l-black dark:border-l-white')}>
                                                {fmt(monthTotal(key, machine.id))}
                                            </td>
                                        )),
                                    )}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}

                <label className="flex flex-col gap-1 text-[13px] text-muted-foreground">
                    Catatan
                    <Textarea value={note} onChange={(e) => setNote(e.target.value)} disabled={!can_write} rows={2} placeholder="Catatan jam pembangkit bulan ini (opsional)" />
                </label>
            </div>
        </>
    );
}
