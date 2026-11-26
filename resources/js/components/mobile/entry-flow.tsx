import { ArrowLeft, ChevronRight, Plus, Save, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { MobileRowFields } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The phone input flow of the operator menus (user, 2026-11-26): a page opens
 * on its list — empty at first, then one rectangular card per entry — with
 * Tambah (left) and Simpan (right) at the bottom. Tambah or a card opens the
 * entry's form on its own screen; Simpan there saves and returns to the list.
 *
 * - MobileEntryList: the list page body (heading, cards / empty state, bar).
 * - MobileEntryCard: one entry on the list.
 * - MobileFormScreen: the form screen (full screen, own top bar & bottom bar).
 */

export function MobileEntryList({
    heading,
    count,
    empty = 'Belum ada data. Tekan Tambah untuk mengisi.',
    children,
    canWrite,
    onAdd,
    onSave,
    saving = false,
    dirty = false,
    addLabel = 'Tambah',
    above,
}: {
    /** Section title above the cards, e.g. "Laporan November 2026". */
    heading?: ReactNode;
    count: number;
    empty?: ReactNode;
    /** The cards (MobileEntryCard). */
    children?: ReactNode;
    canWrite: boolean;
    onAdd?: () => void;
    /** Saves changes still pending on the list (rows edited, removed); "Tersimpan" when none. */
    onSave?: () => void;
    saving?: boolean;
    dirty?: boolean;
    addLabel?: string;
    /** Page controls kept above the list (e.g. a Mesin picker). */
    above?: ReactNode;
}) {
    return (
        <>
            <div
                className={cn(
                    'flex flex-col gap-3 p-4',
                    canWrite ? 'pb-28' : 'pb-8',
                )}
            >
                {above}
                {count > 0 && heading && (
                    <p className="px-0.5 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                        {heading} · {count}
                    </p>
                )}
                {count === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-card/60 p-8 text-center text-[13px] text-muted-foreground">
                        {empty}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">{children}</div>
                )}
            </div>

            {canWrite && (onAdd || onSave) && (
                <StickyActionBar>
                    {onAdd && (
                        <Button size="lg" variant="outline" onClick={onAdd}>
                            <Plus className="size-4" />
                            {addLabel}
                        </Button>
                    )}
                    {onSave && (
                        <Button
                            size="lg"
                            onClick={onSave}
                            disabled={saving || !dirty}
                        >
                            <Save className="size-4" />
                            {saving
                                ? 'Menyimpan…'
                                : dirty
                                  ? 'Simpan'
                                  : 'Tersimpan'}
                        </Button>
                    )}
                </StickyActionBar>
            )}
        </>
    );
}

export function MobileEntryCard({
    title,
    subtitle,
    badge,
    meta,
    leading,
    onClick,
    footer,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    /** Status chip at the top right. */
    badge?: ReactNode;
    /** Label–value facts under the title (empty values are skipped). */
    meta?: [label: string, value: ReactNode][];
    /** Small square at the start (e.g. the row number). */
    leading?: ReactNode;
    onClick?: () => void;
    footer?: ReactNode;
}) {
    const facts = (meta ?? []).filter(
        ([, value]) =>
            value !== null &&
            value !== undefined &&
            value !== '' &&
            value !== '—',
    );
    const body = (
        <>
            <div className="flex items-start gap-3">
                {leading !== undefined && (
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[13px] font-bold text-primary">
                        {leading}
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <p className="min-w-0 text-[14px] leading-snug font-semibold text-foreground">
                            {title}
                        </p>
                        {badge && <div className="shrink-0">{badge}</div>}
                    </div>
                    {subtitle && (
                        <p className="mt-0.5 line-clamp-2 text-[12.5px] text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {onClick && (
                    <ChevronRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                )}
            </div>
            {facts.length > 0 && (
                <dl className="grid grid-cols-2 gap-x-3 gap-y-1.5 border-t border-border/70 pt-2 text-[12px]">
                    {facts.map(([label, value]) => (
                        <div key={label} className="min-w-0">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="truncate font-medium text-foreground">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            {footer}
        </>
    );

    if (!onClick) {
        return (
            <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3.5">
                {body}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full flex-col gap-2 rounded-xl border border-border bg-card p-3.5 text-left transition active:scale-[0.99] active:bg-muted/50"
        >
            {body}
        </button>
    );
}

/** A small status chip for MobileEntryCard. */
export function EntryBadge({
    children,
    tone = 'neutral',
}: {
    children: ReactNode;
    tone?: 'neutral' | 'open' | 'done' | 'danger' | 'info';
}) {
    const tones = {
        neutral: 'bg-muted text-muted-foreground',
        open: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        done: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
        danger: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
        info: 'bg-primary/10 text-primary',
    };

    return (
        <span
            className={cn(
                'inline-flex rounded-md px-1.5 py-0.5 text-[10.5px] font-bold uppercase',
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}

export function MobileFormScreen({
    title,
    subtitle,
    onBack,
    children,
    onSave,
    saving = false,
    saveDisabled = false,
    saveLabel = 'Simpan',
    formId,
    onDelete,
    deleteLabel = 'Hapus',
    canWrite = true,
    extraAction,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    /** Back to the list (the top-left arrow and the Kembali button). */
    onBack: () => void;
    children: ReactNode;
    /** Simpan: save, then the page returns to the list on success. */
    onSave?: () => void;
    saving?: boolean;
    saveDisabled?: boolean;
    saveLabel?: string;
    /** Instead of onSave: Simpan submits this <form id>. */
    formId?: string;
    onDelete?: () => void;
    deleteLabel?: string;
    canWrite?: boolean;
    /** A second action beside Simpan (e.g. Serahkan on the Lembar Mutasi); Kembali then becomes an icon. */
    extraAction?: ReactNode;
}) {
    // The list page behind must not scroll while the form is open.
    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    return (
        <div
            role="dialog"
            aria-modal="true"
            className="fixed inset-0 z-40 flex flex-col bg-background"
        >
            <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background px-2 pt-[var(--impersonation-h,0px)]">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex size-10 items-center justify-center rounded-full text-foreground active:bg-muted"
                    aria-label="Kembali ke daftar"
                >
                    <ArrowLeft className="size-5" />
                </button>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[15px] leading-tight font-semibold text-foreground">
                        {title}
                    </p>
                    {subtitle && (
                        <p className="truncate text-[11px] text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {canWrite && onDelete && (
                    <button
                        type="button"
                        onClick={onDelete}
                        className="flex size-10 items-center justify-center rounded-full text-destructive active:bg-muted"
                        aria-label={deleteLabel}
                        title={deleteLabel}
                    >
                        <Trash2 className="size-5" />
                    </button>
                )}
            </header>

            <div className="flex-1 overflow-y-auto overscroll-contain bg-muted/40 p-4 pb-28">
                <div className="flex min-w-0 flex-col gap-3 overflow-hidden rounded-xl border border-border bg-card p-3.5">
                    {children}
                </div>
            </div>

            <div
                className={cn(
                    'fixed inset-x-0 bottom-0 z-40 grid gap-2 border-t border-border bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur',
                    extraAction ? 'grid-cols-[auto_1fr_1fr]' : 'grid-cols-2',
                )}
            >
                <Button
                    size="lg"
                    variant="outline"
                    onClick={onBack}
                    aria-label="Kembali"
                    className={extraAction ? 'px-3' : undefined}
                >
                    <ArrowLeft className="size-4" />
                    {!extraAction && 'Kembali'}
                </Button>
                {canWrite && (onSave || formId) ? (
                    <Button
                        size="lg"
                        type={formId ? 'submit' : 'button'}
                        form={formId}
                        onClick={formId ? undefined : onSave}
                        disabled={saving || saveDisabled}
                    >
                        <Save className="size-4" />
                        {saving ? 'Menyimpan…' : saveLabel}
                    </Button>
                ) : (
                    <span />
                )}
                {extraAction}
            </div>
        </div>
    );
}

/**
 * The entry flow for a page that edits a table of rows saved together
 * (PTW, Material & Peralatan …): the filled rows as cards, Tambah opens a
 * row's form (MobileRowFields) on its own screen, Simpan there saves every
 * row and returns to the list.
 */
export function MobileRowFlow<R>({
    rows,
    fields,
    isFilled,
    title,
    subtitle,
    badge,
    meta,
    onChange,
    onRemove,
    removeLabel = 'Hapus baris',
    canWrite,
    onAdd,
    onSave,
    saving,
    dirty,
    heading,
    empty,
    formTitle,
    formSubtitle,
    above,
    rowKey,
}: {
    rows: R[];
    fields: RowField<R>[];
    /** Rows shown on the list (blank padding rows are not). */
    isFilled: (row: R) => boolean;
    title: (row: R, index: number) => ReactNode;
    subtitle?: (row: R, index: number) => ReactNode;
    badge?: (row: R, index: number) => ReactNode;
    meta?: (row: R, index: number) => [string, ReactNode][];
    onChange: (index: number, key: keyof R & string, value: never) => void;
    onRemove?: (index: number) => void;
    removeLabel?: string;
    canWrite: boolean;
    /** Adds (or picks a blank) row and returns its index; absent = no Tambah. */
    onAdd?: () => number;
    /** Saves all rows; calls `done` once saved. */
    onSave: (done: () => void) => void;
    saving: boolean;
    dirty: boolean;
    heading?: ReactNode;
    empty?: ReactNode;
    formTitle: (row: R, index: number, isNew: boolean) => ReactNode;
    formSubtitle?: ReactNode;
    above?: ReactNode;
    rowKey?: (row: R, index: number) => string | number;
}) {
    const [editing, setEditing] = useState<{
        index: number;
        isNew: boolean;
    } | null>(null);
    const listed = rows
        .map((row, index) => ({ row, index }))
        .filter(({ row }) => isFilled(row));
    const row = editing === null ? undefined : rows[editing.index];

    return (
        <>
            <MobileEntryList
                heading={heading}
                count={listed.length}
                empty={empty}
                canWrite={canWrite}
                onAdd={
                    onAdd
                        ? () => setEditing({ index: onAdd(), isNew: true })
                        : undefined
                }
                onSave={() => onSave(() => undefined)}
                saving={saving}
                dirty={dirty}
                above={above}
            >
                {listed.map(({ row: item, index }, position) => (
                    <MobileEntryCard
                        key={rowKey?.(item, index) ?? index}
                        leading={position + 1}
                        title={title(item, index)}
                        subtitle={subtitle?.(item, index)}
                        badge={badge?.(item, index)}
                        meta={meta?.(item, index)}
                        onClick={() => setEditing({ index, isNew: false })}
                    />
                ))}
            </MobileEntryList>

            {editing !== null && row !== undefined && (
                <MobileFormScreen
                    title={formTitle(row, editing.index, editing.isNew)}
                    subtitle={formSubtitle}
                    onBack={() => setEditing(null)}
                    canWrite={canWrite}
                    onSave={() => onSave(() => setEditing(null))}
                    saving={saving}
                    saveDisabled={!dirty}
                    onDelete={
                        onRemove && !editing.isNew
                            ? () => {
                                  if (
                                      window.confirm(
                                          `${removeLabel}? Tekan Simpan di daftar untuk menyimpan perubahan.`,
                                      )
                                  ) {
                                      onRemove(editing.index);
                                      setEditing(null);
                                  }
                              }
                            : undefined
                    }
                    deleteLabel={removeLabel}
                >
                    <MobileRowFields
                        row={row}
                        index={editing.index}
                        fields={fields}
                        onChange={onChange}
                        canWrite={canWrite}
                    />
                </MobileFormScreen>
            )}
        </>
    );
}

const ENTRY_MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

/** "2026-11-04" → "4 Nov 2026" on the entry cards; "" when empty. */
export function entryDate(value: string | null | undefined): string {
    const match = (value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value ?? '';
    }

    return `${Number(match[3])} ${ENTRY_MONTHS[Number(match[2]) - 1]} ${match[1]}`;
}
