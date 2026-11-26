import { Check, ChevronDown, Plus, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import {
    MobileEntryCard,
    MobileFormScreen,
} from '@/components/mobile/entry-screen';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type RowField<R> = {
    key: keyof R & string;
    label: string;
    type?:
        | 'text'
        | 'number'
        | 'date'
        | 'time'
        | 'textarea'
        | 'check'
        | 'select'
        | 'dropdown'
        | 'display';
    options?: string[];
    /** For `select`: text per option and the colour of the selected chip. */
    optionLabels?: Record<string, string>;
    optionTone?: (option: string) => string;
    placeholder?: string;
    /** Section heading shown above this field (e.g. "Status"). */
    group?: string;
    /** Grid width inside the expanded mobile card: `1` (half-row) or `2` (full-row). */
    span?: 1 | 2;
    /** For `display`: the text to show; defaults to the raw value. */
    display?: (row: R) => ReactNode;
    /** Hides the field for this row. */
    hidden?: (row: R) => boolean;
    /** Maps an input value to the row value (e.g. number or 1/0); defaults per type. */
    parse?: (value: string | boolean) => unknown;
};

function resolveFieldSpan<R>(field: RowField<R>): 1 | 2 {
    if (field.span) {
        return field.span;
    }

    if (
        field.type === 'number' ||
        field.type === 'date' ||
        field.type === 'time' ||
        field.type === 'dropdown' ||
        field.type === 'check' ||
        field.type === 'display'
    ) {
        return 1;
    }

    return 2;
}

/**
 * The labelled fields of one row (the open card of MobileRowEditor, or a row's
 * own form screen in the phone entry flow — MobileFormScreen).
 */
/** The entry flow of MobileRowEditor (see its `flow` prop). */
export type RowEditorFlow<R> = {
    /** Saves the page's rows; calls `done` once saved. */
    onSave: (done: () => void) => void;
    saving: boolean;
    dirty: boolean;
    /** Appends a row (it then opens on its own screen); absent = no Tambah. */
    onAdd?: () => void;
    addLabel?: string;
    /** false: the page draws the bottom bar itself (e.g. one bar for several grouped editors). */
    bar?: boolean;
    /** Rows shown on the list (default: all). */
    isFilled?: (row: R) => boolean;
    empty?: ReactNode;
    formSubtitle?: ReactNode;
};

export function MobileRowFields<R>({
    row,
    index,
    fields,
    onChange,
    canWrite,
}: {
    row: R;
    index: number;
    fields: RowField<R>[];
    onChange: (index: number, key: keyof R & string, value: never) => void;
    canWrite: boolean;
}) {
    const set = (index: number, field: RowField<R>, raw: string | boolean) => {
        const value = field.parse
            ? field.parse(raw)
            : field.type === 'number'
              ? raw === ''
                  ? 0
                  : Number(raw)
              : field.type === 'check'
                ? raw
                    ? 1
                    : 0
                : raw;
        onChange(index, field.key, value as never);
    };

    const editor = (row: R, index: number, field: RowField<R>) => {
        const value = row[field.key] as unknown;
        const disabled = !canWrite;

        switch (field.type) {
            case 'display':
                return (
                    <span className="flex min-h-9 items-center rounded-md bg-muted/60 px-2.5 text-xs text-foreground">
                        {field.display
                            ? field.display(row)
                            : String(value ?? '—')}
                    </span>
                );
            case 'textarea':
                return (
                    <Textarea
                        value={String(value ?? '')}
                        onChange={(e) => set(index, field, e.target.value)}
                        rows={2}
                        placeholder={field.placeholder}
                        disabled={disabled}
                        className="text-xs"
                    />
                );
            case 'select':
                return (
                    <ChoiceChips
                        options={field.options ?? []}
                        labels={field.optionLabels}
                        tone={field.optionTone}
                        value={
                            field.display
                                ? String(field.display(row))
                                : String(value ?? '')
                        }
                        onChange={(v) => set(index, field, v)}
                        disabled={disabled}
                    />
                );
            case 'dropdown': {
                const strVal = field.display
                    ? String(field.display(row))
                    : String(value ?? '');
                const baseOptions = field.options ?? [];
                const mergedOptions =
                    strVal !== '' && !baseOptions.includes(strVal)
                        ? [...baseOptions, strVal]
                        : baseOptions;

                return (
                    <Select
                        value={strVal || undefined}
                        onValueChange={(v) => set(index, field, v)}
                        disabled={disabled}
                    >
                        <SelectTrigger className="h-9 w-full min-w-0 text-xs">
                            <SelectValue
                                placeholder={
                                    field.placeholder ?? `Pilih ${field.label}`
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {mergedOptions.map((option) => (
                                <SelectItem
                                    key={option}
                                    value={option}
                                    className="text-xs"
                                >
                                    {field.optionLabels?.[option] ?? option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );
            }
            case 'check': {
                const checked = value === 1 || value === true || value === '1';

                return (
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked={checked}
                        onClick={() => set(index, field, !checked)}
                        disabled={disabled}
                        className={cn(
                            'flex min-h-9 w-full items-center gap-2 rounded-lg border px-2.5 text-xs font-medium transition active:scale-95 disabled:opacity-60',
                            checked
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-4 shrink-0 items-center justify-center rounded border',
                                checked ? 'border-white' : 'border-input',
                            )}
                        >
                            {checked && <Check className="size-3" />}
                        </span>
                        <span className="truncate">{field.label}</span>
                    </button>
                );
            }
            default:
                return (
                    <Input
                        type={field.type ?? 'text'}
                        inputMode={
                            field.type === 'number' ? 'decimal' : undefined
                        }
                        step={field.type === 'number' ? 'any' : undefined}
                        value={
                            value === null ||
                            value === undefined ||
                            (field.type === 'number' && value === 0)
                                ? ''
                                : String(value)
                        }
                        placeholder={
                            field.placeholder ??
                            (field.type === 'number' ? '0' : undefined)
                        }
                        onChange={(e) => set(index, field, e.target.value)}
                        disabled={disabled}
                        className={cn(
                            'h-9 w-full min-w-0 text-xs',
                            (field.type === 'date' || field.type === 'time') &&
                                'px-2',
                        )}
                    />
                );
        }
    };

    const visible = fields.filter((field) => !field.hidden?.(row));

    return (
        <div className="grid grid-cols-2 gap-x-2.5 gap-y-2">
            {visible.map((field, f) => {
                const span = resolveFieldSpan(field);
                const showGroup =
                    field.group && field.group !== visible[f - 1]?.group;

                return (
                    <Fragment key={field.key}>
                        {showGroup && (
                            <p className="col-span-2 mt-1 border-t border-border/50 pt-2 text-[10.5px] font-semibold tracking-wider text-muted-foreground uppercase first:mt-0 first:border-t-0 first:pt-0">
                                {field.group}
                            </p>
                        )}
                        <div
                            className={cn(
                                'flex min-w-0 flex-col gap-1',
                                span === 2 ? 'col-span-2' : 'col-span-1',
                            )}
                        >
                            {field.type !== 'check' && (
                                <span className="truncate text-[11.5px] leading-tight font-medium text-muted-foreground">
                                    {field.label}
                                </span>
                            )}
                            {editor(row, index, field)}
                        </div>
                    </Fragment>
                );
            })}
        </div>
    );
}

/**
 * Phone layout of an editable table (one row per finding / item): each row is a
 * collapsible card whose cells become labelled fields. The page keeps its own
 * row state and handlers; this only renders them without a table.
 */
export function MobileRowEditor<R>({
    rows,
    fields,
    title,
    subtitle,
    onChange,
    onRemove,
    canWrite,
    rowKey,
    removeLabel = 'Hapus baris',
    flow,
}: {
    rows: R[];
    fields: RowField<R>[];
    title: (row: R, index: number) => ReactNode;
    subtitle?: (row: R, index: number) => ReactNode;
    onChange: (index: number, key: keyof R & string, value: never) => void;
    onRemove?: (index: number) => void;
    canWrite: boolean;
    rowKey?: (row: R, index: number) => string | number;
    removeLabel?: string;
    /**
     * The phone entry flow (user, 2026-11-27): the rows as cards with Tambah /
     * Simpan pinned at the bottom; Tambah or a card opens the row on its own
     * screen, whose Simpan saves (onSave) and returns to the list. Without it
     * the rows stay collapsible cards edited in place.
     */
    flow?: RowEditorFlow<R>;
}) {
    const [open, setOpen] = useState<number | null>(
        rows.length === 1 && !flow ? 0 : null,
    );
    // Flow: the row opened on its own screen; a row just added (Tambah here or on the page) opens at once.
    const [screenRow, setScreenRow] = useState<number | null>(null);
    const [knownLength, setKnownLength] = useState(rows.length);
    // The just-added row as it was created: left unchanged with Kembali, it is removed again.
    const [addedRow, setAddedRow] = useState<{
        index: number;
        snapshot: string;
    } | null>(null);

    if (rows.length !== knownLength) {
        setKnownLength(rows.length);

        if (flow && rows.length === knownLength + 1) {
            setScreenRow(rows.length - 1);
            setAddedRow({
                index: rows.length - 1,
                snapshot: JSON.stringify(rows[rows.length - 1]),
            });
        }
    }

    const closeScreen = () => {
        if (
            onRemove &&
            addedRow !== null &&
            screenRow === addedRow.index &&
            JSON.stringify(rows[addedRow.index]) === addedRow.snapshot
        ) {
            onRemove(addedRow.index);
        }

        setAddedRow(null);
        setScreenRow(null);
    };

    if (flow) {
        const filled = flow.isFilled ?? (() => true);
        const listed = rows
            .map((row, index) => ({ row, index }))
            .filter(({ row }) => filled(row));
        const current =
            screenRow !== null && screenRow < rows.length
                ? rows[screenRow]
                : undefined;

        return (
            <>
                {listed.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-card/60 p-8 text-center text-[13px] text-muted-foreground">
                        {flow.empty ??
                            'Belum ada data. Tekan Tambah untuk mengisi.'}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {listed.map(({ row, index }, position) => (
                            <MobileEntryCard
                                key={rowKey?.(row, index) ?? index}
                                leading={position + 1}
                                title={title(row, index)}
                                subtitle={subtitle?.(row, index)}
                                onClick={() => setScreenRow(index)}
                            />
                        ))}
                    </div>
                )}

                {canWrite && flow.bar !== false && (
                    <StickyActionBar>
                        {flow.onAdd && (
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={flow.onAdd}
                            >
                                <Plus className="size-4" />
                                {flow.addLabel ?? 'Tambah'}
                            </Button>
                        )}
                        <Button
                            size="lg"
                            onClick={() => flow.onSave(() => undefined)}
                            disabled={flow.saving || !flow.dirty}
                        >
                            <Save className="size-4" />
                            {flow.saving
                                ? 'Menyimpan…'
                                : flow.dirty
                                  ? 'Simpan'
                                  : 'Tersimpan'}
                        </Button>
                    </StickyActionBar>
                )}

                {screenRow !== null && current !== undefined && (
                    <MobileFormScreen
                        title={title(current, screenRow)}
                        subtitle={flow.formSubtitle}
                        onBack={closeScreen}
                        canWrite={canWrite}
                        onSave={() =>
                            flow.onSave(() => {
                                setAddedRow(null);
                                setScreenRow(null);
                            })
                        }
                        saving={flow.saving}
                        saveDisabled={!flow.dirty}
                        onDelete={
                            onRemove
                                ? () => {
                                      if (
                                          window.confirm(
                                              `${removeLabel}? Tekan Simpan untuk menyimpan perubahan.`,
                                          )
                                      ) {
                                          onRemove(screenRow);
                                          setScreenRow(null);
                                      }
                                  }
                                : undefined
                        }
                        deleteLabel={removeLabel}
                    >
                        <MobileRowFields
                            row={current}
                            index={screenRow}
                            fields={fields}
                            onChange={onChange}
                            canWrite={canWrite}
                        />
                    </MobileFormScreen>
                )}
            </>
        );
    }

    if (rows.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                Belum ada baris. Tekan Tambah untuk mengisi.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            {rows.map((row, index) => {
                const isOpen = open === index;

                return (
                    <div
                        key={rowKey?.(row, index) ?? index}
                        className="rounded-xl border border-border bg-card"
                    >
                        <button
                            type="button"
                            onClick={() => setOpen(isOpen ? null : index)}
                            className="flex w-full items-center gap-3 p-3 text-left"
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[13px] font-bold text-primary">
                                {index + 1}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[14px] font-medium text-foreground">
                                    {title(row, index)}
                                </span>
                                {subtitle && (
                                    <span className="block truncate text-[12px] text-muted-foreground">
                                        {subtitle(row, index)}
                                    </span>
                                )}
                            </span>
                            <ChevronDown
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition',
                                    isOpen && 'rotate-180',
                                )}
                            />
                        </button>
                        {isOpen && (
                            <div className="flex flex-col gap-2 border-t border-border p-3">
                                <MobileRowFields
                                    row={row}
                                    index={index}
                                    fields={fields}
                                    onChange={onChange}
                                    canWrite={canWrite}
                                />
                                {canWrite && onRemove && (
                                    <div className="flex justify-end border-t border-border/60 pt-1.5">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                onRemove(index);
                                                setOpen(null);
                                            }}
                                            className="h-7 gap-1 px-2 text-xs text-destructive"
                                        >
                                            <Trash2 className="size-3.5" />
                                            {removeLabel}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
