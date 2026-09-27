import { useState } from 'react';
import type { Key, ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import { MobileModeBar } from '@/components/mobile/mode-bar';
import type { TimelineDay } from '@/components/mobile/timeline-form';
import { Input } from '@/components/ui/input';

export type DayValueField = {
    key: string;
    label: string;
    /** Picked from chips (tapping the active one clears it); otherwise typed. */
    options?: string[];
    numeric?: boolean;
    /** Chip / badge colour of a value. */
    tone?: (value: string) => string;
    /** Text shown for a value (e.g. "✓ Diukur" for "v"). */
    labels?: Record<string, string>;
    /** Shown but not editable (e.g. the plan beside the realisation). */
    readOnly?: boolean;
};

/**
 * Phone layout of a row × date matrix whose cells hold values (P0–P5 codes,
 * durations …): pick a date, then fill each row's fields for that date. With
 * `onEditingChange` it opens read-only — the rows scheduled on the selected
 * date — and switches to the input layout with "Ubah Jadwal".
 */
export function MobileDayValuesForm<R>({
    days,
    rows,
    rowKey,
    title,
    fields,
    value,
    onChange,
    details,
    summary,
    readOnly = false,
    editing = false,
    onEditingChange,
    empty = 'Belum ada baris.',
}: {
    days: TimelineDay[];
    rows: R[];
    rowKey: (row: R, index: number) => Key;
    title: (row: R, index: number) => ReactNode;
    fields: DayValueField[];
    value: (row: R, field: string, day: number) => string;
    onChange: (row: R, field: string, day: number, value: string) => void;
    details?: (row: R, index: number) => ReactNode;
    summary?: (row: R, index: number) => ReactNode;
    readOnly?: boolean;
    editing?: boolean;
    onEditingChange?: (editing: boolean) => void;
    empty?: ReactNode;
}) {
    const [selected, setSelected] = useState(() => {
        const today = new Date().getDate();

        return days.some((d) => d.day === today) ? today : (days[0]?.day ?? 1);
    });
    const day = days.find((d) => d.day === selected) ?? days[0];
    const viewing = !!onEditingChange && (readOnly || !editing);
    const filled = (row: R, d: number) => fields.some((f) => value(row, f.key, d) !== '');
    const visible = viewing && day ? rows.filter((row) => filled(row, day.day)) : rows;

    return (
        <div className="flex flex-col gap-3">
            {onEditingChange && !readOnly && <MobileModeBar editing={editing} onChange={onEditingChange} />}
            <DayStrip
                items={days.map((d) => ({ key: String(d.day), label: String(d.day), sub: d.dow, isRed: d.is_red, done: rows.some((row) => filled(row, d.day)) }))}
                value={String(selected)}
                onChange={(key) => setSelected(Number(key))}
            />
            {day?.is_red && (
                <p className="rounded-lg bg-red-50 px-3 py-2 text-[12px] text-red-700 dark:bg-red-500/10 dark:text-red-300">{day.holiday ? `Libur: ${day.holiday}` : 'Akhir pekan'}</p>
            )}
            {viewing && day && (
                <p className="px-1 text-[12px] font-semibold text-muted-foreground">
                    {day.dow}, tanggal {day.day} · {visible.length} dari {rows.length} terjadwal
                </p>
            )}
            {rows.length === 0 && <p className="rounded-xl border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">{empty}</p>}
            {rows.length > 0 && visible.length === 0 && (
                <p className="rounded-xl border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">Tidak ada jadwal pada tanggal ini.</p>
            )}
            {day &&
                visible.map((row) => {
                    const index = rows.indexOf(row);

                    return (
                        <div key={rowKey(row, index)} className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3">
                            <div className="text-[14px] font-medium text-foreground">{title(row, index)}</div>
                            {viewing ? (
                                <div className="flex flex-wrap gap-1.5">
                                    {fields
                                        .filter((field) => value(row, field.key, day.day) !== '')
                                        .map((field) => {
                                            const cell = value(row, field.key, day.day);

                                            return (
                                                <span key={field.key} className={`rounded-md border px-2 py-0.5 text-[12px] font-semibold ${field.tone?.(cell) ?? 'border-border bg-muted text-foreground'}`}>
                                                    {field.label}: {field.labels?.[cell] ?? cell}
                                                </span>
                                            );
                                        })}
                                </div>
                            ) : (
                                <div className="flex flex-col gap-2.5">
                                    {fields.map((field) => (
                                        <div key={field.key} className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            {field.label}
                                            {field.readOnly ? (
                                                <span className={`w-fit rounded-md border px-2 py-1 text-[13px] font-semibold ${value(row, field.key, day.day) ? (field.tone?.(value(row, field.key, day.day)) ?? 'border-border bg-muted text-foreground') : 'border-dashed border-border'}`}>
                                                    {value(row, field.key, day.day) ? (field.labels?.[value(row, field.key, day.day)] ?? value(row, field.key, day.day)) : '—'}
                                                </span>
                                            ) : field.options ? (
                                                <ChoiceChips
                                                    options={field.options}
                                                    value={value(row, field.key, day.day)}
                                                    onChange={(v) => onChange(row, field.key, day.day, v)}
                                                    tone={field.tone}
                                                    labels={field.labels}
                                                    disabled={readOnly}
                                                />
                                            ) : (
                                                <Input
                                                    value={value(row, field.key, day.day)}
                                                    onChange={(e) => onChange(row, field.key, day.day, e.target.value)}
                                                    inputMode={field.numeric ? 'decimal' : undefined}
                                                    disabled={readOnly}
                                                    className="h-10 text-sm"
                                                />
                                            )}
                                        </div>
                                    ))}
                                    {details && <div className="grid grid-cols-1 gap-2">{details(row, index)}</div>}
                                </div>
                            )}
                            {summary && <div className="text-[12px] text-muted-foreground">{summary(row, index)}</div>}
                        </div>
                    );
                })}
        </div>
    );
}
