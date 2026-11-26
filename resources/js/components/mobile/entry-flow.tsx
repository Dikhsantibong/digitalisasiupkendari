import { Plus, Save } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import {
    EntryBadge,
    MobileEntryCard,
    MobileFormScreen,
} from '@/components/mobile/entry-screen';
import { MobileRowFields } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export { EntryBadge, MobileEntryCard, MobileFormScreen };

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

/**
 * A page body that is the plain `<div className>` on desktop and the phone
 * form screen (MobileFormScreen) inside the mobile shell — so a page built as
 * one big JSX keeps its desktop markup unchanged.
 */
export function MobileFrame({
    phone,
    className,
    screen,
    children,
}: {
    phone: boolean;
    className?: string;
    screen: Omit<ComponentProps<typeof MobileFormScreen>, 'children'>;
    children: ReactNode;
}) {
    if (phone) {
        return <MobileFormScreen {...screen}>{children}</MobileFormScreen>;
    }

    return <div className={className}>{children}</div>;
}

/** Whether the phone form screen of a server-driven sheet is open (`?form=1`). */
export function formScreenOpen(): boolean {
    return (
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('form') === '1'
    );
}
