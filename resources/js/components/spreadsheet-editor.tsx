import { useEffect, useRef } from 'react';
import Spreadsheet from 'x-data-spreadsheet';
import {
    gridToSheet,
    sheetToGrid
    
} from '@/lib/spreadsheet';
import type {DocumentGrid} from '@/lib/spreadsheet';

/**
 * An Excel-like editor (x-spreadsheet) for a document grid. Created once on
 * mount; every edit is reported back as a canonical {@link DocumentGrid}.
 */
export function SpreadsheetEditor({
    grid,
    onChange,
    height = 560,
}: {
    grid: DocumentGrid;
    onChange: (grid: DocumentGrid) => void;
    height?: number;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const onChangeRef = useRef(onChange);
    // Snapshot the initial grid so re-renders don't recreate the editor.
    const initialRef = useRef(grid);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const sheet = new Spreadsheet(container, {
            mode: 'edit',
            showToolbar: true,
            showGrid: true,
            showContextmenu: true,
            view: {
                height: () => height,
                width: () => container.offsetWidth || 900,
            },
            row: { len: Math.max(initialRef.current.rows.length + 5, 40), height: 24 },
            col: { len: Math.max(initialRef.current.cols, 10), width: 110, indexWidth: 40, minWidth: 60 },
        });

        sheet.loadData(gridToSheet(initialRef.current));
        sheet.change((data) => onChangeRef.current(sheetToGrid(data)));

        return () => {
            container.innerHTML = '';
        };
    }, [height]);

    return <div ref={containerRef} className="w-full" />;
}
