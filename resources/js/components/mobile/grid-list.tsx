import type { Key, ReactNode } from 'react';
import type { ColumnOrColumnGroup } from 'react-data-grid';
import { leaves } from '@/components/mobile/grid-form';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';

/**
 * Phone layout of a list grid (react-data-grid columns & rows, one row per
 * item rather than per date): each row becomes a collapsible card, the first
 * column its title, editable columns text fields and the rest read-only.
 * Rows are handed back through `onRowsChange` exactly as the grid would.
 */
export function MobileGridList<R extends Record<string, unknown>>({
    columns,
    rows,
    onRowsChange,
    rowKey,
    readOnly = false,
    title,
    subtitle,
    onRemove,
    removeLabel,
    empty,
}: {
    columns: readonly ColumnOrColumnGroup<R>[];
    rows: R[];
    onRowsChange: (rows: R[]) => void;
    rowKey: (row: R) => Key;
    readOnly?: boolean;
    /** Card title; defaults to the first column's value. */
    title?: (row: R, index: number) => ReactNode;
    subtitle?: (row: R, index: number) => ReactNode;
    onRemove?: (row: R) => void;
    removeLabel?: string;
    empty?: ReactNode;
}) {
    const fieldsOf = leaves(columns).filter((leaf) => rows.length === 0 || leaf.key in rows[0]);
    const [first] = fieldsOf;

    if (rows.length === 0 && empty) {
        return <>{empty}</>;
    }

    const fields: RowField<R>[] = fieldsOf.map((leaf) => {
        const editable = typeof leaf.editable === 'function' ? true : leaf.editable;

        return {
            key: leaf.key as keyof R & string,
            label: leaf.label,
            group: leaf.group ?? undefined,
            type: editable ? 'text' : 'display',
            display: (row: R) => {
                const value = row[leaf.key];

                return value === null || value === undefined || value === '' ? '—' : String(value);
            },
            parse: (value: string | boolean) => value,
        };
    });

    return (
        <MobileRowEditor<R>
            rows={rows}
            fields={fields}
            title={title ?? ((row) => String(row[first?.key ?? ''] ?? '—'))}
            subtitle={subtitle}
            onChange={(index, key, value) => onRowsChange(rows.map((r, i) => (i === index ? { ...r, [key]: value } : r)))}
            onRemove={onRemove ? (index) => onRemove(rows[index]) : undefined}
            canWrite={!readOnly}
            rowKey={(row) => String(rowKey(row))}
            removeLabel={removeLabel}
        />
    );
}
