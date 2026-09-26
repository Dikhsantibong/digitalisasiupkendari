import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type StripItem = {
    key: string;
    /** Big text, e.g. the date number or "M1". */
    label: string;
    /** Small text above, e.g. the day name. */
    sub?: string | null;
    /** Weekend / holiday. */
    isRed?: boolean;
    /** Shows a dot when the item already has data. */
    done?: boolean;
};

/**
 * Horizontal, scroll-snapping picker of dates (or weeks / columns) for the
 * phone input layouts. The selected item is scrolled into view.
 */
export function DayStrip({ items, value, onChange }: { items: StripItem[]; value: string; onChange: (key: string) => void }) {
    const selectedRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        selectedRef.current?.scrollIntoView({ inline: 'center', block: 'nearest' });
    }, [value]);

    return (
        <div className="-mx-4 flex snap-x gap-1.5 overflow-x-auto px-4 pb-1">
            {items.map((item) => {
                const selected = item.key === value;

                return (
                    <button
                        key={item.key}
                        ref={selected ? selectedRef : undefined}
                        type="button"
                        onClick={() => onChange(item.key)}
                        className={cn(
                            'flex min-w-12 shrink-0 snap-center flex-col items-center gap-0.5 rounded-xl border px-2 py-2 transition',
                            selected
                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                : item.isRed
                                  ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300'
                                  : 'border-border bg-card text-foreground',
                        )}
                    >
                        {item.sub && <span className="text-[10px] font-medium opacity-80">{item.sub}</span>}
                        <span className="text-[15px] leading-none font-bold tabular-nums">{item.label}</span>
                        <span className={cn('size-1.5 rounded-full', item.done ? (selected ? 'bg-white' : 'bg-emerald-500') : 'bg-transparent')} />
                    </button>
                );
            })}
        </div>
    );
}
