import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Printer, RotateCcw, Save } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { MobileDayValuesForm } from '@/components/mobile/day-values-form';
import type { DayValueField } from '@/components/mobile/day-values-form';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { TimelineField } from '@/components/mobile/timeline-form';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { useCompactLayout, useInMobileShell } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import rencanaRealisasi from '@/routes/har/pengusahaan/rencana-realisasi';
import type { IdName } from '@/types';

type Scope = 'har' | 'air' | 'pelumas';
type Plan = 'rencana' | 'realisasi';

type Sheet = {
    days: Record<string, string>;
    durasi: Record<string, string>;
    jam_operasi: string;
    keterangan: string;
    status_note: string;
};

type MachineRow = {
    engine_id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
    rencana: Sheet;
    realisasi: Sheet;
};

type Day = { day: number; dow: string; is_red: boolean; holiday: string | null };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    unit_label: string;
    days: Day[];
    scopes: Record<Scope, MachineRow[]>;
    document: { number: string; revision: string; date: string };
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

type Tab = { key: string; scope: Scope; plan: Plan; label: string };

/** The six sheets, in the order of the paper forms. */
const TABS: Tab[] = [
    { key: 'har-realisasi', scope: 'har', plan: 'realisasi', label: 'Realisasi Pemeliharaan' },
    { key: 'har-rencana', scope: 'har', plan: 'rencana', label: 'Rencana Pemeliharaan' },
    { key: 'air-rencana', scope: 'air', plan: 'rencana', label: 'Rencana Pengukuran Air' },
    { key: 'air-realisasi', scope: 'air', plan: 'realisasi', label: 'Realisasi Pengukuran Air' },
    { key: 'pelumas-rencana', scope: 'pelumas', plan: 'rencana', label: 'Rencana Monitoring Pelumas' },
    { key: 'pelumas-realisasi', scope: 'pelumas', plan: 'realisasi', label: 'Realisasi Monitoring Pelumas' },
];

const HAR_CODES = ['P0', 'P1', 'P2', 'P3', 'P4', 'P5'];

const HAR_LEGEND = ['P0 = 8 - 24 JAM', 'P1 = 125 JAM', 'P2 = 250 JAM', 'P3 = 500 JAM', 'P4 = 1.500 JAM', 'P5 = 3.000 JAM'];

/** Title bar colour and mark colour of each sheet family (as on the paper forms). */
const SCOPE_STYLE: Record<Scope, { bar: string; mark: string; title: (plan: Plan, month: string, year: number) => string; heading: string }> = {
    har: {
        bar: 'bg-[#92d050]',
        mark: '',
        title: (plan, month, year) => `${plan === 'rencana' ? 'RENCANA' : 'REALISASI'} PEMELIHARAAN RUTIN BULAN ${month} ${year}`,
        heading: 'JENIS HAR',
    },
    air: {
        bar: 'bg-[#5b9bd5]',
        mark: 'bg-[#5b9bd5]',
        title: (plan, month, year) => `${plan === 'rencana' ? 'RENCANA' : 'REALISASI'} PENGUKURAN AIR BULAN ${month} ${year}`,
        heading: 'TANGGAL PENGUKURAN KUALITAS AIR',
    },
    pelumas: {
        bar: 'bg-[#ffc000]',
        mark: 'bg-[#ffc000]',
        title: (plan, month, year) => `${plan === 'rencana' ? 'RENCANA' : 'REALISASI'} MONITORING PELUMAS ${month} ${year}`,
        heading: 'TANGGAL PENGUKURAN KUALITAS PELUMAS',
    },
};

const RED = 'bg-[#ff0000]';
const GREEN = 'bg-[#92d050]';
const cell = 'border border-black p-0 text-center align-middle dark:border-neutral-500';
const labelCell = 'border border-black px-1.5 py-0.5 text-[11px] whitespace-nowrap dark:border-neutral-500';

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
    .print-container * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-container select, .print-container input, .print-container textarea { border: none !important; background: transparent !important; appearance: none !important; -webkit-appearance: none !important; }
}
`;

const emptySheet = (): Sheet => ({ days: {}, durasi: {}, jam_operasi: '', keterangan: '', status_note: '' });

const normalize = (scopes: Props['scopes']): Record<Scope, MachineRow[]> => {
    const fix = (sheet: Partial<Sheet> | undefined): Sheet => ({ ...emptySheet(), ...sheet, days: { ...(sheet?.days ?? {}) }, durasi: { ...(sheet?.durasi ?? {}) } });

    return {
        har: (scopes.har ?? []).map((r) => ({ ...r, rencana: fix(r.rencana), realisasi: fix(r.realisasi) })),
        air: (scopes.air ?? []).map((r) => ({ ...r, rencana: fix(r.rencana), realisasi: fix(r.realisasi) })),
        pelumas: (scopes.pelumas ?? []).map((r) => ({ ...r, rencana: fix(r.rencana), realisasi: fix(r.realisasi) })),
    };
};

/**
 * Akses 2 — Pengusahaan: Rencana & Realisasi pemeliharaan rutin, pengukuran
 * air and monitoring pelumas. Six sheets (tabs) laid out like the paper
 * forms; every sheet is edited in place and all are saved together. The
 * signatures come later with the Laporan.
 */
export default function RencanaRealisasiPage({ filters, unit_label, days, scopes: initialScopes, document, options, can_write }: Props) {
    const compact = useCompactLayout();
    const inShell = useInMobileShell();
    const [tabKey, setTabKey] = useState<string>(TABS[0].key);
    const [scopes, setScopes] = useState(() => normalize(initialScopes));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setScopes(normalize(initialScopes));
        setDirty(false);
    }

    const tab = TABS.find((t) => t.key === tabKey) ?? TABS[0];
    const other: Plan = tab.plan === 'rencana' ? 'realisasi' : 'rencana';
    const rows = scopes[tab.scope];
    const style = SCOPE_STYLE[tab.scope];
    const monthName = (OPERASI_MONTHS[filters.month - 1] ?? '').toUpperCase();
    const isHar = tab.scope === 'har';

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah periode?')) {
            return;
        }

        router.get(rencanaRealisasi.index().url, { ...filters, ...patch }, { preserveScroll: true, replace: true });
    };

    const updateSheet = (engineId: number, change: (sheet: Sheet) => Sheet, plan: Plan = tab.plan) => {
        setScopes((prev) => ({
            ...prev,
            [tab.scope]: prev[tab.scope].map((row) => (row.engine_id === engineId ? { ...row, [plan]: change(row[plan]) } : row)),
        }));
        setDirty(true);
    };

    const setDay = (engineId: number, field: 'days' | 'durasi', day: number, value: string) =>
        updateSheet(engineId, (sheet) => {
            const next = { ...sheet[field] };

            if (value.trim() === '') {
                delete next[String(day)];
            } else {
                next[String(day)] = value.trim();
            }

            return { ...sheet, [field]: next };
        });

    const setText = (engineId: number, field: 'jam_operasi' | 'keterangan' | 'status_note', value: string) =>
        updateSheet(engineId, (sheet) => ({ ...sheet, [field]: value }));

    const reset = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setScopes(normalize(initialScopes));
            setDirty(false);
        }
    };

    const save = () => {
        setSaving(true);
        router.post(
            rencanaRealisasi.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                sheets: TABS.map((t) => ({
                    scope: t.scope,
                    plan_type: t.plan,
                    rows: scopes[t.scope].map((row) => ({
                        engine_id: row.engine_id,
                        days: row[t.plan].days,
                        durasi: row[t.plan].durasi,
                        jam_operasi: row[t.plan].jam_operasi,
                        keterangan: row[t.plan].keterangan,
                        status_note: row[t.plan].status_note,
                    })),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    /** A code cell (P0–P5): editable select or plain text. */
    const codeCell = (row: MachineRow, plan: Plan, day: Day, editable: boolean) => {
        const value = row[plan].days[String(day.day)] ?? '';

        return (
            <td key={day.day} className={cn(cell, 'h-6 min-w-7 text-[10px] font-bold', day.is_red && RED)}>
                {editable && can_write ? (
                    <select
                        value={value}
                        onChange={(e) => setDay(row.engine_id, 'days', day.day, e.target.value)}
                        className="h-6 w-full cursor-pointer appearance-none bg-transparent text-center text-[10px] font-bold focus:bg-background focus:outline-none"
                        aria-label={`${row.name} ${plan} tanggal ${day.day}`}
                    >
                        <option value="" />
                        {HAR_CODES.map((code) => (
                            <option key={code} value={code}>
                                {code}
                            </option>
                        ))}
                    </select>
                ) : (
                    value
                )}
            </td>
        );
    };

    /** A mark cell (air / pelumas): coloured when planned, ✓ when realised. */
    const markCell = (row: MachineRow, plan: Plan, day: Day, editable: boolean) => {
        const on = (row[plan].days[String(day.day)] ?? '') !== '';
        const colour = on ? (plan === 'rencana' ? style.mark : GREEN) : day.is_red ? RED : '';

        return (
            <td
                key={day.day}
                onClick={editable && can_write ? () => setDay(row.engine_id, 'days', day.day, on ? '' : 'v') : undefined}
                className={cn(cell, 'h-7 min-w-7 text-[12px] font-bold select-none', colour, editable && can_write && 'cursor-pointer hover:ring-2 hover:ring-primary/60 hover:ring-inset')}
                title={editable && can_write ? `Tanggal ${day.day}: klik untuk ${on ? 'hapus' : 'tandai'}` : undefined}
            >
                {on && plan === 'realisasi' ? '✓' : ''}
            </td>
        );
    };

    const noteCell = (row: MachineRow, rowSpan: number) => (
        <td colSpan={days.length} rowSpan={rowSpan} className={cn(cell, 'px-2 text-[11px] font-semibold uppercase')}>
            {row[tab.plan].status_note}
        </td>
    );

    const keteranganCell = (row: MachineRow, rowSpan: number) => (
        <td rowSpan={rowSpan} className={cn(cell, 'min-w-44 p-1 text-[11px]')}>
            {can_write ? (
                <textarea
                    value={row[tab.plan].keterangan}
                    onChange={(e) => setText(row.engine_id, 'keterangan', e.target.value)}
                    rows={rowSpan}
                    className="w-full resize-none bg-transparent text-center text-[11px] uppercase focus:bg-background focus:outline-none"
                    aria-label={`Keterangan ${row.name}`}
                />
            ) : (
                <span className="uppercase">{row[tab.plan].keterangan}</span>
            )}
        </td>
    );

    const harTable = (
        <table className="w-full border-collapse text-[11px]">
            <thead>
                <tr className="font-bold">
                    <th rowSpan={2} className={cn(labelCell, 'w-40 text-center')}>
                        MESIN /TIPE/S.N
                    </th>
                    <th className={cn(labelCell, 'w-16 text-center')}>{monthName}</th>
                    <th colSpan={days.length} className={cn(labelCell, 'text-center')}>
                        {style.heading}
                    </th>
                    <th rowSpan={2} className={cn(labelCell, 'w-24 text-center')}>
                        JAM OPERASI
                    </th>
                    <th rowSpan={2} className={cn(labelCell, 'w-48 text-center')}>
                        KETERANGAN
                    </th>
                </tr>
                <tr className="font-bold">
                    <th className={cn(labelCell, 'text-center')}>{filters.year}</th>
                    {days.map((d) => (
                        <th key={d.day} className={cn(cell, 'text-[10px]', d.is_red && RED)} title={d.holiday ?? d.dow}>
                            {d.day}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => {
                    const note = row[tab.plan].status_note.trim() !== '';

                    return (
                        <Fragment key={row.engine_id}>
                            <tr>
                                <td className={cn(labelCell, 'text-center font-bold')}>
                                    {row.name}
                                    {row.type ? ` / ${row.type}` : ''}
                                </td>
                                <td className={cn(labelCell, tab.plan === 'realisasi' && 'bg-neutral-300 dark:bg-neutral-700')}>RENC</td>
                                {days.map((d) => codeCell(row, 'rencana', d, tab.plan === 'rencana'))}
                                <td rowSpan={3} className={cn(cell, 'p-1')}>
                                    {can_write ? (
                                        <input
                                            value={row[tab.plan].jam_operasi}
                                            onChange={(e) => setText(row.engine_id, 'jam_operasi', e.target.value)}
                                            placeholder="-"
                                            className="w-full bg-transparent text-center text-[11px] focus:bg-background focus:outline-none"
                                            aria-label={`Jam operasi ${row.name}`}
                                        />
                                    ) : (
                                        row[tab.plan].jam_operasi || '-'
                                    )}
                                </td>
                                {keteranganCell(row, 3)}
                            </tr>
                            <tr>
                                <td className={cn(labelCell, 'text-center font-bold')}>{row.serial_number ? `s.n. ${row.serial_number}` : ''}</td>
                                <td className={cn(labelCell, tab.plan === 'realisasi' && GREEN)}>REAL</td>
                                {days.map((d) => codeCell(row, 'realisasi', d, tab.plan === 'realisasi'))}
                            </tr>
                            <tr>
                                <td className={cn(labelCell, 'text-center font-bold')}>WAKTU</td>
                                <td className={labelCell}>DURASI</td>
                                {note
                                    ? noteCell(row, 1)
                                    : days.map((d) => (
                                          <td key={d.day} className={cn(cell, 'h-6 text-[10px]', d.is_red && RED)}>
                                              {tab.plan === 'realisasi' && can_write ? (
                                                  <input
                                                      value={row.realisasi.durasi[String(d.day)] ?? ''}
                                                      onChange={(e) => setDay(row.engine_id, 'durasi', d.day, e.target.value)}
                                                      inputMode="decimal"
                                                      className="h-6 w-full bg-transparent text-center text-[10px] focus:bg-background focus:outline-none"
                                                      aria-label={`Durasi ${row.name} tanggal ${d.day}`}
                                                  />
                                              ) : (
                                                  (row.realisasi.durasi[String(d.day)] ?? '')
                                              )}
                                          </td>
                                      ))}
                            </tr>
                        </Fragment>
                    );
                })}
            </tbody>
        </table>
    );

    const markTable = (
        <table className="w-full border-collapse text-[11px]">
            <thead>
                <tr className="font-bold">
                    <th rowSpan={2} className={cn(labelCell, 'w-36 bg-neutral-400 text-center dark:bg-neutral-600')}>
                        MESIN
                    </th>
                    <th colSpan={days.length} className={cn(labelCell, 'text-center')}>
                        {style.heading}
                    </th>
                    <th rowSpan={2} className={cn(labelCell, 'w-56 text-center')}>
                        KETERANGAN
                    </th>
                </tr>
                <tr className="font-bold">
                    {days.map((d) => (
                        <th key={d.day} className={cn(cell, 'text-[10px]', d.is_red && RED)} title={d.holiday ?? d.dow}>
                            {d.day}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => {
                    const note = row[tab.plan].status_note.trim() !== '';

                    return (
                        <Fragment key={row.engine_id}>
                            <tr>
                                <td className={cn(labelCell, 'text-center font-medium uppercase')}>{row.name}</td>
                                {note ? noteCell(row, 2) : days.map((d) => markCell(row, 'rencana', d, tab.plan === 'rencana'))}
                                {keteranganCell(row, 2)}
                            </tr>
                            <tr>
                                <td className={cn(labelCell, GREEN, 'text-center font-medium')}>REALISASI</td>
                                {!note && days.map((d) => markCell(row, 'realisasi', d, tab.plan === 'realisasi'))}
                            </tr>
                        </Fragment>
                    );
                })}
            </tbody>
        </table>
    );

    const mobileFields: DayValueField[] = isHar
        ? [
              {
                  key: 'rencana',
                  label: 'Rencana',
                  options: HAR_CODES,
                  readOnly: tab.plan !== 'rencana',
                  tone: () => 'border-amber-500 bg-amber-400 text-black',
              },
              ...(tab.plan === 'realisasi'
                  ? [
                        { key: 'realisasi', label: 'Realisasi', options: HAR_CODES, tone: () => 'border-emerald-600 bg-emerald-500 text-black' },
                        { key: 'durasi', label: 'Durasi (jam)', numeric: true },
                    ]
                  : []),
          ]
        : [
              {
                  key: 'rencana',
                  label: 'Rencana',
                  options: ['v'],
                  labels: { v: 'Terjadwal' },
                  readOnly: tab.plan !== 'rencana',
                  tone: () => (tab.scope === 'air' ? 'border-sky-600 bg-[#5b9bd5] text-white' : 'border-amber-500 bg-[#ffc000] text-black'),
              },
              ...(tab.plan === 'realisasi'
                  ? [{ key: 'realisasi', label: 'Realisasi', options: ['v'], labels: { v: '✓ Diukur' }, tone: () => 'border-emerald-600 bg-emerald-500 text-black' }]
                  : []),
          ];

    const actions = (
        <>
            {dirty && (
                <Button variant="outline" size={compact ? 'default' : 'sm'} onClick={reset} disabled={saving} className="gap-1.5">
                    <RotateCcw className="size-4" />
                    Reset
                </Button>
            )}
            {!compact && (
                <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5">
                    <Printer className="size-4" />
                    Cetak
                </Button>
            )}
            {can_write && (
                <Button size={compact ? 'default' : 'sm'} onClick={save} disabled={saving || !dirty} className="gap-1.5">
                    <Save className="size-4" />
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            )}
        </>
    );

    return (
        <>
            <Head title={`Rencana & Realisasi Pemeliharaan — ${unit_label}`} />
            <style>{PRINT_CSS}</style>

            <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', compact && 'pb-28')}>
                <div className="no-print flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        {!inShell && (
                            <Button variant="outline" size="icon" onClick={() => router.get(harPengusahaan.index('input').url)} title="Kembali ke Input Pengusahaan Pemeliharaan" className="shrink-0">
                                <ArrowLeft className="size-4" />
                            </Button>
                        )}
                        <div className="min-w-0">
                            <h1 className="text-lg font-bold text-foreground md:text-xl">Rencana &amp; Realisasi Pemeliharaan</h1>
                            <p className="text-xs text-muted-foreground">
                                Pemeliharaan rutin (P0–P5), pengukuran air dan monitoring pelumas per mesin. Semua tab disimpan sekaligus.
                            </p>
                        </div>
                    </div>
                    {!compact && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
                </div>

                <div className="no-print flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(v) => visit({ unit_id: Number(v) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className="w-full sm:w-48"
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                        className="w-full sm:w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                        className="w-full sm:w-32"
                    />
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Tabs */}
                <div className="no-print -mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                    {TABS.map((t, index) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTabKey(t.key)}
                            className={cn(
                                'shrink-0 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                t.key === tab.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            {index + 1}. {t.label}
                        </button>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Unit ini belum memiliki mesin aktif.</p>
                ) : compact ? (
                    <div className="flex flex-col gap-3">
                        <p className="rounded-lg bg-muted/50 px-3 py-2 text-[12px] font-semibold text-foreground">{style.title(tab.plan, monthName, filters.year)}</p>
                        <MobileDayValuesForm<MachineRow>
                            key={tab.key}
                            days={days}
                            rows={rows}
                            rowKey={(row) => row.engine_id}
                            title={(row) => `${row.name}${row.type ? ` / ${row.type}` : ''}`}
                            fields={mobileFields}
                            value={(row, field, day) =>
                                field === 'durasi' ? (row.realisasi.durasi[String(day)] ?? '') : (row[field as Plan].days[String(day)] ?? '')
                            }
                            onChange={(row, field, day, value) =>
                                field === 'durasi' ? setDay(row.engine_id, 'durasi', day, value) : setDay(row.engine_id, 'days', day, value)
                            }
                            details={(row) => (
                                <>
                                    <TimelineField
                                        label="Kondisi mesin (jika tidak beroperasi)"
                                        value={row[tab.plan].status_note}
                                        onChange={(v) => setText(row.engine_id, 'status_note', v)}
                                        readOnly={!can_write}
                                        placeholder="mis. Gangguan crankshaft"
                                    />
                                    {isHar && (
                                        <TimelineField label="Jam Operasi" value={row[tab.plan].jam_operasi} onChange={(v) => setText(row.engine_id, 'jam_operasi', v)} readOnly={!can_write} />
                                    )}
                                    <TimelineField label="Keterangan" value={row[tab.plan].keterangan} onChange={(v) => setText(row.engine_id, 'keterangan', v)} readOnly={!can_write} />
                                </>
                            )}
                            summary={(row) => (row[tab.plan].status_note ? `Tidak beroperasi: ${row[tab.plan].status_note}` : `${Object.keys(row[tab.plan].days).length} tanggal terisi bulan ini`)}
                            readOnly={!can_write}
                        />
                    </div>
                ) : (
                    <>
                        <div className="print-container overflow-x-auto rounded-md border border-border bg-white p-2 text-black">
                            <div className="min-w-[1100px]">
                                {/* Kop */}
                                <table className="w-full border-collapse">
                                    <tbody>
                                        <tr>
                                            <td className="w-48 border border-b-0 border-black p-2">
                                                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-10 object-contain" />
                                            </td>
                                            <td className="border-y border-black p-2 text-center">
                                                <div className="text-base font-bold">PT. PLN NUSANTARA POWER</div>
                                                <div className="text-base font-bold">UNIT PEMBANGKITAN KENDARI</div>
                                            </td>
                                            <td className="w-48 border border-b-0 border-black p-2 text-right">
                                                <img src="/logo/k3.png" alt="K3" className="ml-auto max-h-10 object-contain" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <table className="w-full border-collapse">
                                    <tbody>
                                        <tr>
                                            <td className={cn('border border-black p-4 text-center text-base font-bold', style.bar)}>{style.title(tab.plan, monthName, filters.year)}</td>
                                            <td className="w-72 border border-black p-2 text-[11px]">
                                                <dl className="grid grid-cols-[88px_1fr] gap-y-0.5">
                                                    <dt>No. Dokumen</dt>
                                                    <dd>: {document.number}</dd>
                                                    <dt>No. revisi</dt>
                                                    <dd>: {document.revision}</dd>
                                                    <dt>Tanggal</dt>
                                                    <dd>: {document.date}</dd>
                                                    <dt>Halaman</dt>
                                                    <dd>:</dd>
                                                </dl>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colSpan={2} className="border border-black px-2 py-1.5 text-[12px] font-bold">
                                                {isHar ? unit_label : `ULPLTD : ${unit_label.replace(/^ULPLTD\s*/, '')}`}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                {isHar ? harTable : markTable}

                                <Legend>
                                    {isHar ? (
                                        <>
                                            <span className="font-bold">KET :</span>
                                            {HAR_LEGEND.map((line) => (
                                                <span key={line}>{line}</span>
                                            ))}
                                        </>
                                    ) : (
                                        <>
                                            <span className="flex items-center gap-1.5">
                                                <span className={cn('inline-block h-3 w-8 border border-black', style.mark)} /> : RENCANA
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <span className={cn('inline-block h-3 w-8 border border-black text-center text-[9px] leading-3', GREEN)}>✓</span> : REALISASI
                                            </span>
                                        </>
                                    )}
                                    <span className="flex items-center gap-1.5">
                                        <span className={cn('inline-block h-3 w-8 border border-black', RED)} /> : Sabtu, Minggu &amp; Libur Nasional
                                    </span>
                                </Legend>
                            </div>
                        </div>

                        {/* Machine condition notes */}
                        <div className="no-print rounded-lg border border-border bg-card p-3">
                            <h2 className="text-sm font-semibold text-foreground">Kondisi Mesin Tidak Beroperasi</h2>
                            <p className="text-xs text-muted-foreground">
                                Jika diisi, baris tanggal mesin tersebut pada tab ini diganti catatan (mis. GANGGUAN CRANKSHAFT CYLINDER NO. 15 &amp; 16).
                            </p>
                            <div className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                                {rows.map((row) => (
                                    <label key={row.engine_id} className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        {row.name}
                                        <input
                                            value={row[tab.plan].status_note}
                                            onChange={(e) => setText(row.engine_id, 'status_note', e.target.value)}
                                            disabled={!can_write}
                                            placeholder="Kosongkan bila mesin beroperasi normal"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground uppercase placeholder:normal-case focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                                        />
                                    </label>
                                ))}
                            </div>
                            {tab.plan === 'realisasi' && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Baris RENC {isHar ? '' : '(warna rencana) '}diambil dari tab rencana ({other === 'rencana' ? 'Rencana' : 'Realisasi'}); ubah rencana di tab tersebut.
                                </p>
                            )}
                        </div>
                    </>
                )}
            </div>

            {compact && <StickyActionBar>{actions}</StickyActionBar>}
        </>
    );
}

function Legend({ children }: { children: ReactNode }) {
    return <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px]">{children}</div>;
}

RencanaRealisasiPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pengusahaan Pemeliharaan', href: harPengusahaan.index('input') },
        { title: 'Rencana & Realisasi', href: rencanaRealisasi.index() },
    ],
};
