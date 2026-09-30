import { Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { Key, ReactNode } from 'react';
import { DayStrip } from '@/components/mobile/day-strip';
import { MobileModeBar } from '@/components/mobile/mode-bar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type TimelineDay = {
    day: number;
    dow: string;
    is_red: boolean;
    holiday?: string | null;
    /** Strip label when it is not the day number (e.g. "M2" for a week column). */
    label?: string;
};

export type TimelineCategory = {
    key: string;
    label: string;
    /** Classes of the toggle / badge when it is on. */
    onClass: string;
};

const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** The dates of a month for pages whose server payload only carries `days_in_month`; weekends are red. */
export function monthTimelineDays(year: number, month: number, count: number): TimelineDay[] {
    return Array.from({ length: count }, (_, index) => {
        const weekday = new Date(year, month - 1, index + 1).getDay();

        return { day: index + 1, dow: DOW[weekday], is_red: weekday === 0 || weekday === 6 };
    });
}

/** The twelve months of a yearly plan as strip "days" (1 … 12). */
export const YEAR_MONTH_DAYS: TimelineDay[] = MONTH_SHORT.map((label, index) => ({ day: index + 1, dow: label, is_red: false }));

/** The 48 week columns (12 months × M1–M4) of a yearly plan, numbered 1 … 48; see {@link weekKeyOf}. */
export const YEAR_WEEK_DAYS: TimelineDay[] = MONTH_SHORT.flatMap((month, index) =>
    [1, 2, 3, 4].map((week) => ({ day: index * 4 + week, dow: month, is_red: false, label: `M${week}` })),
);

/** The `{month}-{week}` key a yearly week-plan page stores for a strip column of {@link YEAR_WEEK_DAYS}. */
export function weekKeyOf(day: number): string {
    return `${Math.ceil(day / 4)}-${((day - 1) % 4) + 1}`;
}

/** The {@link YEAR_WEEK_DAYS} column of a date (week = ⌈date ÷ 7⌉, capped at M4). */
export function weekColumnOf(date: Date): number {
    return date.getMonth() * 4 + Math.min(4, Math.ceil(date.getDate() / 7));
}

export const RENC_REAL: TimelineCategory[] = [
    { key: 'rencana', label: 'Rencana (RENC)', onClass: 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-black' },
    { key: 'realisasi', label: 'Realisasi (REAL)', onClass: 'border-emerald-600 bg-emerald-500 text-black' },
];

/**
 * Phone layout of a row × date "tick" matrix (jadwal, time frame …): pick a
 * date from the strip, then tick each row's categories for that date. The
 * page keeps its own state; this only reads & toggles through the callbacks.
 *
 * Given `onEditingChange`, it opens read-only — the selected date's schedule —
 * and switches to the tick layout with "Ubah Jadwal".
 */
export function MobileTimelineForm<R>({
    days,
    rows,
    rowKey,
    title,
    isOn,
    onToggle,
    categories = RENC_REAL,
    lockRedDays = false,
    details,
    summary,
    onRemove,
    readOnly = false,
    empty = 'Belum ada baris kegiatan.',
    sectionOf,
    editing = false,
    onEditingChange,
    dayLabel = (d) => `${d.dow}, tanggal ${d.day}`,
    initialDay,
}: {
    days: TimelineDay[];
    rows: R[];
    rowKey: (row: R, index: number) => Key;
    title: (row: R, index: number, editing: boolean) => ReactNode;
    isOn: (row: R, category: string, day: number) => boolean;
    onToggle: (row: R, category: string, day: number) => void;
    categories?: TimelineCategory[];
    /** Weekend / holiday dates can't be ticked (the desktop grid paints them red). */
    lockRedDays?: boolean;
    /** Editable fields of the row (see {@link TimelineField}). */
    details?: (row: R, index: number) => ReactNode;
    summary?: (row: R, index: number) => ReactNode;
    onRemove?: (row: R, index: number) => void;
    readOnly?: boolean;
    empty?: ReactNode;
    /** Group heading of a row; rows must already be ordered by group. Numbering restarts per group. */
    sectionOf?: (row: R) => string;
    /** View / edit mode; without `onEditingChange` the form is always the tick layout. */
    editing?: boolean;
    onEditingChange?: (editing: boolean) => void;
    /** Heading of the selected date in view mode (e.g. a month name for a yearly plan). */
    dayLabel?: (day: TimelineDay) => string;
    /** Column selected first; defaults to today's date. */
    initialDay?: number;
}) {
    const [selected, setSelected] = useState(() => {
        const today = initialDay ?? new Date().getDate();

        return days.some((d) => d.day === today) ? today : (days[0]?.day ?? 1);
    });
    const day = days.find((d) => d.day === selected) ?? days[0];
    const locked = lockRedDays && !!day?.is_red;
    const viewing = !!onEditingChange && (readOnly || !editing);
    const scheduled = (row: R) => !!day && categories.some((c) => isOn(row, c.key, day.day));

    // A checklist (one category) lists every row; a plan (RENC/REAL) only the rows scheduled that date.
    const visible = viewing && categories.length > 1 ? rows.filter(scheduled) : rows;

    const numbered = visible.map((row, position) => {
        const section = sectionOf?.(row) ?? '';
        const first = position === 0 || (sectionOf?.(visible[position - 1]) ?? '') !== section;
        const number = visible.slice(0, position + 1).filter((other) => (sectionOf?.(other) ?? '') === section).length;

        return { row, index: rows.indexOf(row), section: first && sectionOf ? section : null, number };
    });

    return (
        <div className="flex flex-col gap-3">
            {onEditingChange && !readOnly && <MobileModeBar editing={editing} onChange={onEditingChange} />}
            <DayStrip
                items={days.map((d) => ({
                    key: String(d.day),
                    label: d.label ?? String(d.day),
                    sub: d.dow,
                    isRed: d.is_red,
                    done: rows.some((row) => categories.some((c) => isOn(row, c.key, d.day))),
                }))}
                value={String(selected)}
                onChange={(key) => setSelected(Number(key))}
            />
            {day?.is_red && (
                <p className="rounded-lg bg-red-50 px-3 py-2 text-[12px] text-red-700 dark:bg-red-500/10 dark:text-red-300">
                    {day.holiday ? `Libur: ${day.holiday}` : 'Akhir pekan'}
                    {locked && !viewing ? ' — tanggal ini tidak diisi.' : ''}
                </p>
            )}
            {viewing && day && (
                <p className="px-1 text-[12px] font-semibold text-muted-foreground">
                    {dayLabel(day)} · {rows.filter(scheduled).length} dari {rows.length} kegiatan {categories.length > 1 ? 'terjadwal' : 'terlaksana'}
                </p>
            )}
            {rows.length === 0 && <p className="rounded-xl border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">{empty}</p>}
            {rows.length > 0 && visible.length === 0 && (
                <p className="rounded-xl border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">Tidak ada jadwal pada tanggal ini.</p>
            )}
            {numbered.map(({ row, index, section, number }) => (
                <Fragment key={rowKey(row, index)}>
                    {section !== null && <p className="mt-1 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">{section}</p>}
                    {viewing ? (
                        <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                            <div className="flex items-start gap-2">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary/10 text-[11px] font-bold text-primary">{number}</span>
                                <div className="min-w-0 flex-1 text-[14px] font-medium text-foreground">{title(row, index, false)}</div>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {categories.map((category) => {
                                    const on = !!day && isOn(row, category.key, day.day);

                                    return (
                                        <span
                                            key={category.key}
                                            className={`rounded-md border px-2 py-0.5 text-[12px] font-semibold ${on ? category.onClass : 'border-dashed border-border text-muted-foreground'}`}
                                        >
                                            {on ? '✓ ' : 'Belum '}
                                            {category.label}
                                        </span>
                                    );
                                })}
                            </div>
                            {summary && <div className="text-[12px] text-muted-foreground">{summary(row, index)}</div>}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3">
                            <div className="flex items-start gap-2">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[12px] font-bold text-primary">{number}</span>
                                <div className="min-w-0 flex-1 pt-1 text-[14px] font-medium text-foreground">{title(row, index, true)}</div>
                                {onRemove && !readOnly && (
                                    <Button type="button" variant="ghost" size="icon" onClick={() => onRemove(row, index)} className="size-9 shrink-0 text-muted-foreground hover:text-destructive" aria-label="Hapus baris">
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>
                            <div className={`grid gap-2 ${categories.length > 1 ? 'grid-cols-2' : 'grid-cols-1'}`}>
                                {categories.map((category) => {
                                    const on = !!day && isOn(row, category.key, day.day);

                                    return (
                                        <button
                                            key={category.key}
                                            type="button"
                                            disabled={readOnly || locked || !day}
                                            onClick={() => day && onToggle(row, category.key, day.day)}
                                            className={`min-h-11 rounded-lg border text-[13px] font-semibold transition active:scale-95 disabled:opacity-60 ${on ? category.onClass : 'border-border bg-background text-foreground'}`}
                                        >
                                            {on ? '✓ ' : ''}
                                            {category.label}
                                        </button>
                                    );
                                })}
                            </div>
                            {details && <div className="grid grid-cols-1 gap-2">{details(row, index)}</div>}
                            {summary && <div className="text-[12px] text-muted-foreground">{summary(row, index)}</div>}
                        </div>
                    )}
                </Fragment>
            ))}
        </div>
    );
}

/** A labelled text / number input for {@link MobileTimelineForm} details. */
export function TimelineField({
    label,
    value,
    onChange,
    readOnly = false,
    type = 'text',
    placeholder,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    readOnly?: boolean;
    type?: 'text' | 'number';
    placeholder?: string;
}) {
    return (
        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
            {label}
            <Input
                type={type}
                inputMode={type === 'number' ? 'decimal' : undefined}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={readOnly}
                placeholder={placeholder}
                className="h-10 text-sm"
            />
        </label>
    );
}
