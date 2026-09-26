import { Check, ChevronDown, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
        | 'display';
    options?: string[];
    /** For `select`: text per option and the colour of the selected chip. */
    optionLabels?: Record<string, string>;
    optionTone?: (option: string) => string;
    placeholder?: string;
    /** Section heading shown above this field (e.g. "Status"). */
    group?: string;
    /** For `display`: the text to show; defaults to the raw value. */
    display?: (row: R) => ReactNode;
    /** Hides the field for this row. */
    hidden?: (row: R) => boolean;
    /** Maps an input value to the row value (e.g. number or 1/0); defaults per type. */
    parse?: (value: string | boolean) => unknown;
};

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
}) {
    const [open, setOpen] = useState<number | null>(
        rows.length === 1 ? 0 : null,
    );

    if (rows.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                Belum ada baris. Tekan Tambah untuk mengisi.
            </p>
        );
    }

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
                    <span className="flex min-h-9 items-center rounded-md bg-muted/60 px-3 text-[13px] text-foreground">
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
                            'flex min-h-10 items-center gap-2 rounded-lg border px-3 text-[13px] font-medium transition active:scale-95 disabled:opacity-60',
                            checked
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-4 items-center justify-center rounded border',
                                checked ? 'border-white' : 'border-input',
                            )}
                        >
                            {checked && <Check className="size-3" />}
                        </span>
                        {field.label}
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
                    />
                );
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {rows.map((row, index) => {
                const isOpen = open === index;
                const visible = fields.filter((field) => !field.hidden?.(row));

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
                            <div className="flex flex-col gap-3 border-t border-border p-3">
                                {visible.map((field, f) => (
                                    <div
                                        key={field.key}
                                        className="flex flex-col gap-1.5"
                                    >
                                        {field.group &&
                                            field.group !==
                                                visible[f - 1]?.group && (
                                                <p className="pt-1 text-[11.5px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                    {field.group}
                                                </p>
                                            )}
                                        {field.type !== 'check' && (
                                            <span className="text-[12px] text-muted-foreground">
                                                {field.label}
                                            </span>
                                        )}
                                        {editor(row, index, field)}
                                    </div>
                                ))}
                                {canWrite && onRemove && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            onRemove(index);
                                            setOpen(null);
                                        }}
                                        className="self-start text-destructive"
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
        </div>
    );
}
