import { useState } from 'react';
import type { ReactNode } from 'react';
import { DayStrip } from '@/components/mobile/day-strip';
import { cn } from '@/lib/utils';

export type DaySheetRow = {
    key: string | number;
    label: ReactNode;
    /** Small text under the label (type, no. seri …). */
    sub?: ReactNode;
    /** The value of the row on a day: text, or an input on an editable sheet. */
    value: (day: number) => ReactNode;
};

export type DaySheetSection = {
    key: string;
    title?: ReactNode;
    rows: DaySheetRow[];
    /** Total of the section on the day (e.g. JUMLAH / TOTAL). */
    total?: (day: number) => ReactNode;
    totalLabel?: ReactNode;
};

/**
 * Phone layout of a day × machine sheet (the "TGL" grids of Pengusahaan
 * Operasi): a date strip, then per section one line per machine with its value
 * on the selected day and the day total. The month figures (JMH) sit below.
 * Desktop keeps its wide table; this is used behind useCompactLayout().
 */
export function MobileDaySheet({
    days,
    sections,
    done,
    monthSummary,
    initialDay,
}: {
    days: number[];
    sections: DaySheetSection[];
    /** Marks a date in the strip as filled. */
    done?: (day: number) => boolean;
    /** Month totals (JMH) under the day view. */
    monthSummary?: ReactNode;
    initialDay?: number;
}) {
    const [day, setDay] = useState(() => {
        const today = new Date().getDate();

        return initialDay ?? (days.includes(today) ? today : (days[0] ?? 1));
    });

    return (
        <div className="flex flex-col gap-3">
            <DayStrip
                items={days.map((d) => ({
                    key: String(d),
                    label: String(d),
                    done: done?.(d) ?? false,
                }))}
                value={String(day)}
                onChange={(key) => setDay(Number(key))}
            />
            {sections.map((section) => (
                <section
                    key={section.key}
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    {section.title && (
                        <div className="flex items-baseline justify-between gap-2 border-b border-border bg-secondary px-3 py-2 text-sm font-semibold text-foreground">
                            <span className="min-w-0 truncate">
                                {section.title}
                            </span>
                            <span className="shrink-0 text-[12px] font-normal text-muted-foreground">
                                Tgl {day}
                            </span>
                        </div>
                    )}
                    <ul className="divide-y divide-border">
                        {section.rows.map((row) => (
                            <li
                                key={row.key}
                                className="flex items-center justify-between gap-3 px-3 py-2"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium text-foreground">
                                        {row.label}
                                    </p>
                                    {row.sub && (
                                        <p className="truncate text-[11px] text-muted-foreground">
                                            {row.sub}
                                        </p>
                                    )}
                                </div>
                                <div className="shrink-0 text-right text-[13px] tabular-nums">
                                    {row.value(day)}
                                </div>
                            </li>
                        ))}
                    </ul>
                    {section.total && (
                        <div className="flex items-center justify-between gap-3 border-t border-border bg-secondary/60 px-3 py-2 text-[13px] font-semibold">
                            <span>{section.totalLabel ?? 'Jumlah'}</span>
                            <span className="tabular-nums">
                                {section.total(day)}
                            </span>
                        </div>
                    )}
                </section>
            ))}
            {monthSummary}
        </div>
    );
}

/** A compact "label · value" list for month totals (JMH) under a day sheet. */
export function MobileMonthSummary({
    title = 'Jumlah bulan ini (JMH)',
    rows,
}: {
    title?: ReactNode;
    rows: {
        key: string | number;
        label: ReactNode;
        value: ReactNode;
        strong?: boolean;
    }[];
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-3">
            <p className="mb-2 text-[12px] font-semibold text-muted-foreground uppercase">
                {title}
            </p>
            <dl className="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[13px]">
                {rows.map((row) => (
                    <div
                        key={row.key}
                        className={cn(
                            'col-span-2 flex items-center justify-between gap-2',
                            row.strong &&
                                'border-t border-border pt-1.5 font-semibold',
                        )}
                    >
                        <dt className="truncate text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd className="tabular-nums">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
