import { ChevronDown, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type CompactRow = {
    /** Stable key of the row (index in the page's row state is fine). */
    key: string | number;
    /** Index in the page's row state, passed back to the callbacks. */
    index: number;
    /** Section key the row belongs to (see `sections`). */
    section?: string | null;
    title: ReactNode;
    /** Plain text of the row used by the search box (title + main values). */
    searchText: string;
    subtitle?: ReactNode;
    /** Small badge on the right (status, total …). */
    badge?: ReactNode;
    /** Rows without data are shown dimmed. */
    empty?: boolean;
    /** Controls shown under the collapsed line (e.g. Ya / Tidak ticks), so simple rows need no opening. */
    quick?: ReactNode;
    /** The row has nothing more to edit than `quick`: no expand toggle. */
    quickOnly?: boolean;
};

export type CompactSection = {
    key: string | null;
    title?: ReactNode;
    /** e.g. a "Tambah" button. */
    action?: ReactNode;
    /** e.g. section totals. */
    footer?: ReactNode;
};

/**
 * Light phone layout of a long editable table: every row is one collapsed line
 * (number, title, short summary) and only the opened row renders its inputs —
 * the page's own cell editors, so behaviour stays identical to the desktop
 * table. Rows render in batches ("Tampilkan lagi") with a search box, so a
 * sheet of hundreds of rows stays fast to render and short to scroll.
 */
export function MobileCompactRows({
    rows,
    sections = [{ key: null }],
    renderEditor,
    onRemove,
    canWrite,
    pageSize = 15,
    empty = 'Belum ada baris.',
    removeLabel = 'Hapus baris',
    focus = null,
}: {
    rows: CompactRow[];
    sections?: CompactSection[];
    /** Fields of the opened row. */
    renderEditor: (index: number) => ReactNode;
    onRemove?: (index: number) => void;
    canWrite: boolean;
    pageSize?: number;
    empty?: ReactNode;
    removeLabel?: string;
    /** Opens the row at this index (e.g. the row just added by "Tambah"); a new nonce re-triggers. */
    focus?: { index: number; nonce: number } | null;
}) {
    const [open, setOpen] = useState<string | number | null>(null);
    const [query, setQuery] = useState('');
    const [limits, setLimits] = useState<Record<string, number>>({});
    // Titled sections fold: one open at a time (the first by default).
    const [openSection, setOpenSection] = useState<string | null>(null);
    const [focused, setFocused] = useState<number | null>(null);

    if (focus && focus.nonce !== focused) {
        const target = rows.find((row) => row.index === focus.index);
        setFocused(focus.nonce);

        if (target) {
            setOpen(target.key);
            setQuery('');
            setOpenSection(target.section ?? '__all');
        }
    }

    const term = query.trim().toLowerCase();
    const filtered = useMemo(
        () =>
            term
                ? rows.filter((row) =>
                      row.searchText.toLowerCase().includes(term),
                  )
                : rows,
        [rows, term],
    );
    const sectionOf = (row: CompactRow) =>
        sections.length === 1 && sections[0].key === null
            ? null
            : (row.section ?? null);
    // Row number within its section, computed once per render.
    const position = useMemo(() => {
        const counters = new Map<string | null, number>();
        const map = new Map<string | number, number>();

        for (const row of rows) {
            const section =
                sections.length === 1 && sections[0].key === null
                    ? null
                    : (row.section ?? null);
            const next = (counters.get(section) ?? 0) + 1;
            counters.set(section, next);
            map.set(row.key, next);
        }

        return map;
    }, [rows, sections]);

    return (
        <div className="flex flex-col gap-3">
            {rows.length > 8 && (
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={`Cari di ${rows.length} baris…`}
                        className="h-10 pl-9"
                    />
                </div>
            )}

            {sections.map((section, sectionIndex) => {
                const sectionKey = section.key ?? '__all';
                const collapsible =
                    sections.length > 1 && sections.some((s) => s.title);
                const expanded =
                    !collapsible ||
                    term !== '' ||
                    (openSection ?? sections[0].key ?? '__all') === sectionKey;
                const list = filtered.filter(
                    (row) => sectionOf(row) === section.key,
                );
                const total = rows.filter(
                    (row) => sectionOf(row) === section.key,
                ).length;
                const openRow = list.find((row) => row.key === open);
                const limit = limits[sectionKey] ?? pageSize;
                // Keep the opened row visible even past the current batch.
                const shown = list.slice(0, limit);

                if (openRow && !shown.includes(openRow)) {
                    shown.push(openRow);
                }

                return (
                    <div key={sectionKey} className="flex flex-col gap-2">
                        {(section.title || section.action) && (
                            <div className="flex items-center justify-between gap-2 px-0.5">
                                {collapsible ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOpenSection(
                                                expanded ? '' : sectionKey,
                                            )
                                        }
                                        className="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-border bg-card px-3 py-2.5 text-left"
                                        aria-expanded={expanded}
                                    >
                                        <span className="min-w-0 flex-1 truncate text-[12px] font-semibold tracking-wide text-foreground uppercase">
                                            {section.title ??
                                                `Bagian ${sectionIndex + 1}`}
                                        </span>
                                        <span className="shrink-0 text-[11px] text-muted-foreground">
                                            {total} baris
                                        </span>
                                        <ChevronDown
                                            className={cn(
                                                'size-4 shrink-0 text-muted-foreground transition',
                                                expanded && 'rotate-180',
                                            )}
                                        />
                                    </button>
                                ) : (
                                    <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {section.title}
                                        <span className="ml-1.5 font-normal normal-case">
                                            ({total})
                                        </span>
                                    </p>
                                )}
                                {section.action}
                            </div>
                        )}

                        {expanded && list.length === 0 && (
                            <p className="rounded-lg border border-dashed border-border p-4 text-center text-[12.5px] text-muted-foreground">
                                {term ? 'Tidak ada baris yang cocok.' : empty}
                            </p>
                        )}

                        {expanded &&
                            shown.map((row) => {
                                const isOpen = row.key === open;

                                return (
                                    <div
                                        key={row.key}
                                        className={cn(
                                            'rounded-xl border bg-card',
                                            isOpen
                                                ? 'border-primary/50 shadow-sm'
                                                : 'border-border',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                !row.quickOnly &&
                                                setOpen(isOpen ? null : row.key)
                                            }
                                            className="flex w-full items-center gap-3 p-3 text-left"
                                            aria-expanded={
                                                row.quickOnly
                                                    ? undefined
                                                    : isOpen
                                            }
                                        >
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[13px] font-bold text-primary">
                                                {position.get(row.key)}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span
                                                    className={cn(
                                                        'block truncate text-[14px] font-medium',
                                                        row.empty
                                                            ? 'text-muted-foreground italic'
                                                            : 'text-foreground',
                                                    )}
                                                >
                                                    {row.title}
                                                </span>
                                                {row.subtitle && (
                                                    <span className="block truncate text-[12px] text-muted-foreground">
                                                        {row.subtitle}
                                                    </span>
                                                )}
                                            </span>
                                            {row.badge}
                                            {!row.quickOnly && (
                                                <ChevronDown
                                                    className={cn(
                                                        'size-4 shrink-0 text-muted-foreground transition',
                                                        isOpen && 'rotate-180',
                                                    )}
                                                />
                                            )}
                                        </button>
                                        {row.quick && !isOpen && (
                                            <div className="-mt-1 px-3 pb-3 pl-14">
                                                {row.quick}
                                            </div>
                                        )}
                                        {isOpen && (
                                            <div className="flex flex-col gap-3 border-t border-border p-3">
                                                {renderEditor(row.index)}
                                                {canWrite && onRemove && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setOpen(null);
                                                            onRemove(row.index);
                                                        }}
                                                        className="gap-1.5 self-start text-destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                        {removeLabel}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                        {expanded && list.length > limit && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setLimits((current) => ({
                                        ...current,
                                        [sectionKey]: limit + pageSize,
                                    }))
                                }
                                className="w-full"
                            >
                                Tampilkan{' '}
                                {Math.min(pageSize, list.length - limit)} lagi (
                                {list.length - limit} tersisa)
                            </Button>
                        )}

                        {expanded && section.footer}
                    </div>
                );
            })}
        </div>
    );
}

/** One labelled field of an opened compact row. */
export function CompactField({
    label,
    hint,
    children,
}: {
    label: ReactNode;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[12px] font-medium text-muted-foreground">
                {label}
            </span>
            {children}
            {hint && (
                <span className="text-[11px] text-muted-foreground">
                    {hint}
                </span>
            )}
        </div>
    );
}
