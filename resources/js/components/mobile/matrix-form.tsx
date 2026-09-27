import { useState } from 'react';
import type { Key, ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import type { StripItem } from '@/components/mobile/day-strip';
import { MobileModeBar } from '@/components/mobile/mode-bar';
import { Input } from '@/components/ui/input';

/**
 * Phone layout of a row × date matrix (jadwal, patrol, time frame …): pick a
 * date (column) from the strip, then fill that date for every row. The page
 * keeps its own state; this only reads & writes cells through the callbacks.
 * Given `onEditingChange`, it opens read-only (the selected date's values per
 * row) and switches to the input layout with "Ubah Jadwal".
 */
export function MobileMatrixForm<R>({
    columns,
    rows,
    rowKey,
    rowLabel,
    rowSub,
    value,
    onChange,
    options,
    optionLabels,
    optionTone,
    readOnly = false,
    initial,
    empty = 'Belum ada baris untuk diisi.',
    footer,
    editing = false,
    onEditingChange,
    viewNotes,
}: {
    /** The matrix columns (dates / weeks), in order. */
    columns: StripItem[];
    rows: R[];
    rowKey: (row: R, index: number) => Key;
    rowLabel: (row: R, index: number, editing: boolean) => ReactNode;
    rowSub?: (row: R, index: number) => ReactNode;
    value: (row: R, column: string) => string;
    onChange: (rowIndex: number, column: string, value: string) => void;
    /** When given, a cell is picked from these chips instead of typed. */
    options?: string[];
    optionLabels?: Record<string, string>;
    optionTone?: (option: string) => string;
    readOnly?: boolean;
    /** Column selected first; defaults to today's date number, else the first column. */
    initial?: string;
    empty?: ReactNode;
    /** Extra content under the rows of the selected column (e.g. its total). */
    footer?: (column: string) => ReactNode;
    /** View / edit mode; without `onEditingChange` the matrix is always the input layout. */
    editing?: boolean;
    onEditingChange?: (editing: boolean) => void;
    /** Read-only mode: short description shown next to a value (e.g. a shift's hours). */
    viewNotes?: Record<string, string>;
}) {
    const [selected, setSelected] = useState<string>(() => {
        const today = String(new Date().getDate());

        return (
            initial ??
            columns.find((c) => c.label === today)?.key ??
            columns[0]?.key ??
            ''
        );
    });
    const column = columns.find((c) => c.key === selected) ?? columns[0];
    const viewing = !!onEditingChange && (readOnly || !editing);
    const modeBar =
        onEditingChange && !readOnly ? (
            <MobileModeBar editing={editing} onChange={onEditingChange} />
        ) : null;

    if (!column || rows.length === 0) {
        return (
            <div className="flex flex-col gap-3">
                {modeBar}
                <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                    {empty}
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            {modeBar}
            <DayStrip
                items={columns.map((c) => ({
                    ...c,
                    done:
                        c.done ?? rows.some((row) => value(row, c.key) !== ''),
                }))}
                value={column.key}
                onChange={setSelected}
            />
            {viewing ? (
                <div className="flex flex-col divide-y divide-border rounded-xl border border-border bg-card">
                    <div className="px-3 py-2 text-[12px] font-semibold text-muted-foreground">
                        {column.sub ? `${column.sub}, ` : ''}tanggal{' '}
                        {column.label} ·{' '}
                        {
                            rows.filter((row) => value(row, column.key) !== '')
                                .length
                        }{' '}
                        dari {rows.length} terisi
                    </div>
                    {rows.map((row, index) => {
                        const cell = value(row, column.key);

                        return (
                            <div
                                key={rowKey(row, index)}
                                className="flex items-center gap-3 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="text-[14px] font-medium text-foreground">
                                        {rowLabel(row, index, false)}
                                    </div>
                                    {rowSub && (
                                        <div className="text-[12px] text-muted-foreground">
                                            {rowSub(row, index)}
                                        </div>
                                    )}
                                </div>
                                {cell ? (
                                    <div className="flex shrink-0 flex-col items-end gap-0.5">
                                        <span
                                            className={`rounded-lg border px-2.5 py-1 text-[13px] font-semibold ${optionTone?.(cell) ?? 'border-primary bg-primary text-primary-foreground'}`}
                                        >
                                            {optionLabels?.[cell] ?? cell}
                                        </span>
                                        {viewNotes?.[cell] && (
                                            <span className="text-[11px] text-muted-foreground">
                                                {viewNotes[cell]}
                                            </span>
                                        )}
                                    </div>
                                ) : (
                                    <span className="shrink-0 text-[13px] text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="flex flex-col divide-y divide-border rounded-xl border border-border bg-card">
                    {rows.map((row, index) => (
                        <div
                            key={rowKey(row, index)}
                            className="flex flex-col gap-2 p-3"
                        >
                            <div>
                                <div className="text-[14px] font-medium text-foreground">
                                    {rowLabel(row, index, true)}
                                </div>
                                {rowSub && (
                                    <div className="text-[12px] text-muted-foreground">
                                        {rowSub(row, index)}
                                    </div>
                                )}
                            </div>
                            {options ? (
                                <ChoiceChips
                                    options={options}
                                    labels={optionLabels}
                                    tone={optionTone}
                                    value={value(row, column.key)}
                                    onChange={(v) =>
                                        onChange(index, column.key, v)
                                    }
                                    disabled={readOnly}
                                />
                            ) : (
                                <Input
                                    value={value(row, column.key)}
                                    onChange={(e) =>
                                        onChange(
                                            index,
                                            column.key,
                                            e.target.value,
                                        )
                                    }
                                    disabled={readOnly}
                                    aria-label={`${String(column.label)} baris ${index + 1}`}
                                />
                            )}
                        </div>
                    ))}
                </div>
            )}
            {footer?.(column.key)}
        </div>
    );
}
