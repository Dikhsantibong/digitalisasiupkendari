import { useMemo, useState } from 'react';
import type { Key, ReactNode } from 'react';
import type { ColumnOrColumnGroup } from 'react-data-grid';
import { DayStrip } from '@/components/mobile/day-strip';
import { Input } from '@/components/ui/input';

type Leaf<R> = {
    key: string;
    label: string;
    group: string | null;
    editable: boolean | ((row: R) => boolean);
};

const text = (value: ReactNode, fallback: string) =>
    typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : fallback;

function leaves<R>(
    columns: readonly ColumnOrColumnGroup<R>[],
    group: string | null = null,
): Leaf<R>[] {
    return columns.flatMap((column) => {
        if ('children' in column) {
            const name = text(column.name, '');

            return leaves(
                column.children,
                group && name ? `${group} · ${name}` : name || group,
            );
        }

        return [
            {
                key: String(column.key),
                label: text(column.name, String(column.key)),
                group,
                editable: (column.editable ?? false) as Leaf<R>['editable'],
            },
        ];
    });
}

/**
 * Phone layout of a date-row grid (react-data-grid columns & rows): pick a row
 * from the strip, then fill its editable columns as a form, grouped like the
 * grid's column groups. Read-only columns show their value. Rows are handed
 * back through `onRowsChange` exactly as the grid would.
 */
export function MobileGridForm<R extends Record<string, unknown>>({
    columns,
    rows,
    onRowsChange,
    rowKey,
    rowLabel,
    rowSub,
    isRowDone,
    readOnly = false,
}: {
    columns: readonly ColumnOrColumnGroup<R>[];
    rows: R[];
    onRowsChange: (rows: R[]) => void;
    rowKey: (row: R) => Key;
    /** Big label of a row in the strip; defaults to the first column's value. */
    rowLabel?: (row: R) => string;
    /** Small label above it (e.g. the day name). */
    rowSub?: (row: R) => string | null;
    isRowDone?: (row: R) => boolean;
    readOnly?: boolean;
}) {
    const fields = useMemo(() => leaves(columns), [columns]);
    const [first, ...rest] = fields;
    const labelOf =
        rowLabel ?? ((row: R) => text(row[first?.key ?? ''] as ReactNode, ''));
    const [selected, setSelected] = useState<string>(() => {
        const today = String(new Date().getDate());
        const match = rows.find((row) => labelOf(row) === today) ?? rows[0];

        return match ? String(rowKey(match)) : '';
    });

    const index = Math.max(
        0,
        rows.findIndex((row) => String(rowKey(row)) === selected),
    );
    const row = rows[index];
    const canEdit = (leaf: Leaf<R>) =>
        !readOnly &&
        (typeof leaf.editable === 'function'
            ? leaf.editable(row)
            : leaf.editable);
    const done =
        isRowDone ??
        ((r: R) =>
            rest.some(
                (leaf) =>
                    leaf.editable &&
                    r[leaf.key] !== null &&
                    r[leaf.key] !== undefined &&
                    r[leaf.key] !== '',
            ));
    const groups = rest.reduce<{ title: string | null; fields: Leaf<R>[] }[]>(
        (list, leaf) => {
            const last = list[list.length - 1];

            if (last && last.title === leaf.group) {
                last.fields.push(leaf);
            } else {
                list.push({ title: leaf.group, fields: [leaf] });
            }

            return list;
        },
        [],
    );

    const setValue = (key: string, value: string) => {
        onRowsChange(
            rows.map((r, i) => (i === index ? { ...r, [key]: value } : r)),
        );
    };

    if (!row) {
        return (
            <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                Belum ada baris untuk diisi.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <DayStrip
                items={rows.map((r) => ({
                    key: String(rowKey(r)),
                    label: labelOf(r),
                    sub: rowSub?.(r) ?? null,
                    done: done(r),
                }))}
                value={String(rowKey(row))}
                onChange={setSelected}
            />
            {groups.map((group, g) => (
                <div
                    key={`${group.title}-${g}`}
                    className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3"
                >
                    {group.title && (
                        <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.title}
                        </p>
                    )}
                    <div className="grid grid-cols-2 gap-2.5">
                        {group.fields.map((leaf) => {
                            const value = row[leaf.key];

                            return (
                                <label
                                    key={leaf.key}
                                    className="flex flex-col gap-1 text-[12px] text-muted-foreground"
                                >
                                    {leaf.label}
                                    {canEdit(leaf) ? (
                                        <Input
                                            value={
                                                value === null ||
                                                value === undefined
                                                    ? ''
                                                    : String(value)
                                            }
                                            onChange={(e) =>
                                                setValue(
                                                    leaf.key,
                                                    e.target.value,
                                                )
                                            }
                                            inputMode={
                                                typeof value === 'number' ||
                                                value === null
                                                    ? 'decimal'
                                                    : undefined
                                            }
                                        />
                                    ) : (
                                        <span className="flex h-9 items-center rounded-md bg-muted/60 px-3 text-[13px] text-foreground tabular-nums">
                                            {value === null ||
                                            value === undefined ||
                                            value === ''
                                                ? '—'
                                                : String(value)}
                                        </span>
                                    )}
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
