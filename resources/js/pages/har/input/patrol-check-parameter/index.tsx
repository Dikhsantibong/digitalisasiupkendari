import { Head, router } from '@inertiajs/react';
import { Download, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DayStrip } from '@/components/mobile/day-strip';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import parameterRoutes from '@/routes/har/input/patrol-check-parameter';
import type { IdName } from '@/types';

type Column = { key: string; label: string; type: 'text' | 'number'; path: string[]; width: number };
type HeaderCell = { label: string; colspan: number; rowspan: number };
type Day = { key: string; label: string; sub: string | null; is_red: boolean };
type Readings = Record<string, Record<string, string>>;

type Props = {
    sheet: { title: string; columns: Column[]; header_rows: HeaderCell[][]; widths: number[] };
    days: Day[];
    unit: IdName;
    filters: { unit_id: number; month: number; year: number; machine_id: number | null };
    options: { units: IdName[]; years: number[]; machines: IdName[] };
    readings: Readings;
    has_saved: boolean;
    can_write: boolean;
};

function getHeaderCellClass(level: number, startCol: number): string {
    // Col 0..3: TGL, PIC, JAM, LOAD (KW)
    if (startCol <= 3) {
        return 'border border-slate-300 bg-slate-200/90 text-slate-800 dark:border-slate-700 dark:bg-slate-800/90 dark:text-slate-100 font-bold';
    }

    // Col 4..9: ENGINE (Coolant, LO temp, T/C, LO press)
    if (startCol >= 4 && startCol <= 9) {
        if (level === 0) {
            return 'border border-amber-300 bg-amber-200/90 text-amber-950 font-bold dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-100';
        }
        if (level === 1) {
            return 'border border-amber-200 bg-amber-100/90 text-amber-900 font-semibold dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-200';
        }
        if (level === 2) {
            return 'border border-amber-200/80 bg-amber-50 text-amber-900 font-medium dark:border-amber-900/60 dark:bg-amber-950/35 dark:text-amber-200';
        }
        return 'border border-amber-200/60 bg-amber-50/70 text-amber-900 font-medium dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300';
    }

    // Col 10..21: GENERATOR (Bearing, Winding, Ampere, Voltage, Cos Phi, Kvar)
    if (startCol >= 10 && startCol <= 21) {
        if (level === 0) {
            return 'border border-sky-300 bg-sky-200/90 text-sky-950 font-bold dark:border-sky-800 dark:bg-sky-900/60 dark:text-sky-100';
        }
        if (level === 1) {
            return 'border border-sky-200 bg-sky-100/90 text-sky-900 font-semibold dark:border-sky-900/70 dark:bg-sky-950/50 dark:text-sky-200';
        }
        if (level === 2) {
            return 'border border-sky-200/80 bg-sky-50 text-sky-900 font-medium dark:border-sky-900/60 dark:bg-sky-950/35 dark:text-sky-200';
        }
        return 'border border-sky-200/60 bg-sky-50/70 text-sky-900 font-medium dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-300';
    }

    // Col 22..23: TRAFO (Oil Level, Temp)
    if (startCol >= 22 && startCol <= 23) {
        if (level === 0) {
            return 'border border-emerald-300 bg-emerald-200/90 text-emerald-950 font-bold dark:border-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-100';
        }
        return 'border border-emerald-200 bg-emerald-100/90 text-emerald-900 font-semibold dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-200';
    }

    // Col 24: VOLT. BATTERY (VDC)
    return 'border border-violet-300 bg-violet-200/90 text-violet-950 font-bold dark:border-violet-800 dark:bg-violet-900/60 dark:text-violet-100';
}

const cellInput =
    'h-7 w-full min-w-0 bg-transparent px-1 text-center text-xs outline-none focus:bg-background focus:ring-1 focus:ring-primary disabled:cursor-not-allowed';

/**
 * Patrol Check Parameter Mesin — pembacaan harian per mesin (engine,
 * generator, trafo, baterai). Definisi kolom: App\Support\HarPatrolCheckParameter.
 */
export default function HarPatrolCheckParameterPage({ sheet, days, unit, filters, options, readings: savedReadings, has_saved, can_write }: Props) {
    const [readings, setReadings] = useState<Readings>(() => ({ ...savedReadings }));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const compact = useCompactLayout();
    const { can } = usePermissions();
    // Phone layout edits one day at a time; start on today when the month is shown.
    const [selectedDay, setSelectedDay] = useState<string>(() => {
        const now = new Date();
        const today = now.getFullYear() === filters.year && now.getMonth() + 1 === filters.month ? String(now.getDate()) : null;

        return (days.find((d) => d.label === today) ?? days[0])?.key ?? '';
    });
    const machineName = options.machines.find((m) => m.id === filters.machine_id)?.name;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...(filters.machine_id ? { machine_id: filters.machine_id } : {}) };

    const headerRowsWithMeta = useMemo(() => {
        const totalCols = sheet.columns.length + 1;
        const occupied: boolean[][] = Array.from({ length: sheet.header_rows.length }, () =>
            Array(totalCols).fill(false),
        );

        return sheet.header_rows.map((cells, level) => {
            let col = 0;
            return cells.map((cell) => {
                while (col < totalCols && occupied[level]?.[col]) {
                    col++;
                }
                const startCol = col;
                for (let r = level; r < level + cell.rowspan; r++) {
                    for (let c = col; c < col + cell.colspan; c++) {
                        if (occupied[r]) {
                            occupied[r][c] = true;
                        }
                    }
                }
                col += cell.colspan;
                return {
                    ...cell,
                    startCol,
                    colorClass: getHeaderCellClass(level, startCol),
                };
            });
        });
    }, [sheet.header_rows, sheet.columns.length]);

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(parameterRoutes.index().url, { ...query, ...patch }, { preserveScroll: true });
    };

    const setValue = (day: string, key: string, value: string) => {
        setReadings((current) => {
            const next = { ...(current[day] ?? {}) };

            if (value === '') {
                delete next[key];
            } else {
                next[key] = value;
            }

            return { ...current, [day]: next };
        });
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            parameterRoutes.store().url,
            { ...query, readings },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setReadings({ ...(page.props as unknown as Props).readings });
                    setErrors({});
                    setDirty(false);
                },
                onError: (bag) => setErrors(bag),
                onFinish: () => setSaving(false),
            },
        );
    };

    if (compact) {
        const day = days.find((d) => d.key === selectedDay) ?? days[0];
        const groups = sheet.columns.reduce<{ title: string; columns: Column[] }[]>((list, column) => {
            const title = column.path.join(' › ');
            const last = list[list.length - 1];

            if (last && last.title === title) {
                last.columns.push(column);
            } else {
                list.push({ title, columns: [column] });
            }

            return list;
        }, []);

        return (
            <>
                <Head title={`${sheet.title} - ${unit.name}`} />
                <div className={`flex flex-col gap-3 p-4 ${can_write ? 'pb-28' : ''}`}>
                    <PageHeader title={sheet.title} description={`${unit.name} · ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}${machineName ? ` · ${machineName}` : ''}`} />

                    <div className="grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-3">
                        <div className="col-span-2">
                            <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v), machine_id: null })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-full" />
                        </div>
                        <div className="col-span-2">
                            <OperasiSelect label="Mesin" value={String(filters.machine_id ?? '')} onChange={(v) => visit({ machine_id: Number(v) })} options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))} className="w-full" />
                        </div>
                        <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} className="w-full" />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} className="w-full" />
                    </div>

                    {options.machines.length === 0 && <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>}

                    <DayStrip
                        items={days.map((d) => ({ key: d.key, label: d.label, sub: d.sub, isRed: d.is_red, done: Object.keys(readings[d.label] ?? {}).length > 0 }))}
                        value={day?.key ?? ''}
                        onChange={setSelectedDay}
                    />

                    {Object.keys(errors).length > 0 && (
                        <ul className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">
                            {Object.entries(errors).map(([key, message]) => (
                                <li key={key}>{message}</li>
                            ))}
                        </ul>
                    )}

                    {day &&
                        groups.map((group) => {
                            const isEngine = group.title.startsWith('ENGINE');
                            const isGenerator = group.title.startsWith('GENERATOR');
                            const isTrafo = group.title.startsWith('TRAFO');
                            const badgeColor = isEngine
                                ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                : isGenerator
                                ? 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300'
                                : isTrafo
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'border-slate-300 bg-slate-50 text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

                            return (
                                <div key={group.title} className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3">
                                    <div>
                                        <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold tracking-wide uppercase border ${badgeColor}`}>
                                            {group.title}
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2.5">
                                    {group.columns.map((column) => (
                                        <label key={column.key} className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            {column.label}
                                            <Input
                                                inputMode={column.type === 'number' ? 'decimal' : 'text'}
                                                value={readings[day.label]?.[column.key] ?? ''}
                                                onChange={(e) => setValue(day.label, column.key, e.target.value)}
                                                disabled={!can_write || !filters.machine_id}
                                                aria-invalid={errors[`readings.${day.label}.${column.key}`] ? true : undefined}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" onClick={save} disabled={saving || !dirty || !filters.machine_id}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : dirty ? 'Simpan' : 'Tersimpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={`${sheet.title} - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={sheet.title}
                    description={`Pembacaan harian parameter engine, generator, trafo & baterai per mesin — ${unit.name} · ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}${machineName ? ` · ${machineName}` : ''}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can('har.input.view') && (
                                <Button variant="outline" onClick={() => router.get(harInput.index().url)}>
                                    Kembali
                                </Button>
                            )}
                            <Button variant="outline" onClick={() => window.open(parameterRoutes.pdf({ query }).url, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty || !filters.machine_id} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v), machine_id: null })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                        <OperasiSelect label="Mesin" value={String(filters.machine_id ?? '')} onChange={(v) => visit({ machine_id: Number(v) })} options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))} />
                        <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    </div>
                    {dirty ? (
                        <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                    ) : has_saved ? (
                        <StatusBadge tone="success">Tersimpan</StatusBadge>
                    ) : (
                        <StatusBadge tone="neutral">Belum ada data</StatusBadge>
                    )}
                </div>

                {options.machines.length === 0 && <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>}

                <div className="grid grid-cols-[64px_1fr_64px] items-center gap-3 rounded-md border border-border bg-muted/10 p-4 sm:grid-cols-[140px_1fr_140px]">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                    <div className="text-center">
                        <div className="text-xs font-semibold uppercase text-muted-foreground">Jasa Pendukung Teknis 6 KIT</div>
                        <div className="text-xs font-semibold uppercase text-muted-foreground">Laporan Project {unit.name}</div>
                        <div className="text-base font-bold uppercase text-foreground">Patrol Check Pemeliharaan</div>
                        <div className="mt-1 text-xs font-semibold uppercase">
                            {OPERASI_MONTHS[filters.month - 1]} {filters.year}
                            {machineName ? ` · Mesin: ${machineName}` : ''}
                        </div>
                    </div>
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                </div>

                {Object.keys(errors).length > 0 && (
                    <ul className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">
                        {Object.entries(errors).map(([key, message]) => (
                            <li key={key}>{message}</li>
                        ))}
                    </ul>
                )}

                <p className="text-xs text-red-600">Baris merah = akhir pekan / hari libur. Kolom angka menerima desimal koma atau titik.</p>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full min-w-[1400px] table-fixed border-collapse text-xs">
                        <colgroup>
                            {sheet.widths.map((width, index) => (
                                <col key={index} style={{ width: `${width}%` }} />
                            ))}
                        </colgroup>
                        <thead className="text-center">
                            {headerRowsWithMeta.map((cells, level) => (
                                <tr key={level}>
                                    {cells.map((cell, index) => (
                                        <th
                                            key={`${level}-${index}`}
                                            colSpan={cell.colspan}
                                            rowSpan={cell.rowspan}
                                            className={`px-1 py-1.5 text-[10px] tracking-tight transition-colors ${cell.colorClass}`}
                                        >
                                            {cell.label}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {days.map((day) => (
                                <tr key={day.key} className={day.is_red ? 'bg-red-500/25' : ''}>
                                    <td className="border border-border text-center font-semibold" title={day.sub ?? undefined}>
                                        {day.label}
                                    </td>
                                    {sheet.columns.map((column) => (
                                        <td key={column.key} className={`border border-border p-0 ${errors[`readings.${day.label}.${column.key}`] ? 'bg-destructive/20' : ''}`}>
                                            <input
                                                type="text"
                                                inputMode={column.type === 'number' ? 'decimal' : 'text'}
                                                value={readings[day.label]?.[column.key] ?? ''}
                                                onChange={(e) => setValue(day.label, column.key, e.target.value)}
                                                className={cellInput}
                                                disabled={!can_write || !filters.machine_id}
                                                aria-label={`${column.path.join(' ')} ${column.label} tanggal ${day.label}`}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

HarPatrolCheckParameterPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Patrol Check Parameter Mesin', href: parameterRoutes.index() },
    ],
};
