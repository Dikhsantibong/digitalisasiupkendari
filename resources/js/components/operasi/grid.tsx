import { useMemo, useRef   } from 'react';
import type {ClipboardEvent, Key} from 'react';
import DataGrid from 'react-data-grid';
import type { ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';

/** Excel-like styling shared by every operasi date-row grid. */
export const OPERASI_GRID_STYLES = `
.operasi-grid .rdg {
    --rdg-header-row-height: 30px;
    font-size: 12px;
    border: 1px solid var(--rdg-border-color);
    border-radius: 6px;
}
.operasi-grid .rdg-group-header,
.operasi-grid .rdg-sub-header {
    text-align: center;
    justify-content: center;
}
.operasi-grid .rdg-group-header {
    background: #e6eefb;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #1e3a5f;
}
.operasi-grid .rdg-sub-header {
    background: #f4f7fc;
    font-weight: 600;
    color: #334155;
}
.operasi-grid .rdg-derived-header {
    background: #eef0f3;
    color: #64748b;
}
.operasi-grid .rdg-derived-cell {
    background: #f8fafc;
}
`;

/** A leaf column with the only bits the paste logic needs. */
type LeafColumn = { key: string; editable: boolean };

/**
 * Flatten grouped columns into the left-to-right order of their leaf columns,
 * marking which are editable — the order Excel paste maps onto.
 */
function flattenColumns<R>(
    columns: readonly ColumnOrColumnGroup<R>[],
): LeafColumn[] {
    const leaves: LeafColumn[] = [];

    for (const column of columns) {
        if ('children' in column) {
            leaves.push(...flattenColumns(column.children));
        } else {
            leaves.push({
                key: String(column.key),
                editable: Boolean(column.editable),
            });
        }
    }

    return leaves;
}

type SelectedCell = { rowIdx: number; columnKey: string } | null;

/**
 * Excel-style paste for a react-data-grid: paste a block copied from Excel and
 * it fills down and right from the selected cell into the editable columns.
 * Returns the props to spread onto the grid and its container.
 */
export function useExcelPaste<R extends Record<string, unknown>>({
    columns,
    rows,
    onChange,
}: {
    columns: readonly ColumnOrColumnGroup<R>[];
    rows: R[];
    onChange: (rows: R[]) => void;
}) {
    const selectedRef = useRef<SelectedCell>(null);
    const leaves = useMemo(() => flattenColumns(columns), [columns]);

    const onSelectedCellChange = (args: {
        rowIdx: number;
        column: { key: string | number };
    }) => {
        selectedRef.current = {
            rowIdx: args.rowIdx,
            columnKey: String(args.column.key),
        };
    };

    const onPaste = (event: ClipboardEvent<HTMLDivElement>) => {
        const anchor = selectedRef.current;

        if (anchor === null) {
            return;
        }

        const text = event.clipboardData.getData('text/plain');

        if (!text) {
            return;
        }

        const matrix = text
            .replace(/\r/g, '')
            .replace(/\n$/, '')
            .split('\n')
            .map((line) => line.split('\t'));

        // A single cell copy is left to react-data-grid's own editor.
        if (matrix.length === 1 && matrix[0].length === 1) {
            return;
        }

        const anchorCol = leaves.findIndex((c) => c.key === anchor.columnKey);

        if (anchorCol < 0) {
            return;
        }

        event.preventDefault();

        const next = rows.map((row) => ({ ...row }));
        matrix.forEach((cells, rowOffset) => {
            const rowIdx = anchor.rowIdx + rowOffset;

            if (rowIdx < 0 || rowIdx >= next.length) {
                return;
            }

            cells.forEach((value, colOffset) => {
                const column = leaves[anchorCol + colOffset];

                if (!column || !column.editable) {
                    return;
                }

                const trimmed = value.trim();
                (next[rowIdx] as Record<string, unknown>)[column.key] =
                    trimmed === '' ? null : trimmed;
            });
        });

        onChange(next);
    };

    return { onSelectedCellChange, onPaste };
}

/**
 * A themed wrapper around react-data-grid used by the operasi input tabs, so
 * every date-row grid renders identically. Excel paste is wired in.
 */
export function OperasiGrid<R extends Record<string, unknown>>({
    columns,
    rows,
    onRowsChange,
    rowKey,
    height = '60vh',
}: {
    columns: readonly ColumnOrColumnGroup<R>[];
    rows: R[];
    onRowsChange: (rows: R[]) => void;
    rowKey: (row: R) => Key;
    height?: string;
}) {
    const { onSelectedCellChange, onPaste } = useExcelPaste({
        columns,
        rows,
        onChange: onRowsChange,
    });

    return (
        <div
            className="operasi-grid overflow-hidden rounded-md border border-border"
            onPaste={onPaste}
        >
            <style>{OPERASI_GRID_STYLES}</style>
            <DataGrid
                className="rdg-light"
                style={{ blockSize: height }}
                columns={columns}
                rows={rows}
                rowKeyGetter={rowKey}
                onRowsChange={onRowsChange}
                onSelectedCellChange={onSelectedCellChange}
            />
        </div>
    );
}
