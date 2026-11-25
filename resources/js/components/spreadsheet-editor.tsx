import { useEffect, useRef } from 'react';
import Spreadsheet from 'x-data-spreadsheet';
import { gridToSheets, sheetsToGrid } from '@/lib/spreadsheet';
import type {DocumentGrid} from '@/lib/spreadsheet';

const SHEET_TABS_CSS = `
.x-spreadsheet-bottombar { overflow-x: auto; overflow-y: hidden; }
.x-spreadsheet-bottombar .x-spreadsheet-menu { display: flex; flex-wrap: nowrap; white-space: nowrap; }
.x-spreadsheet-bottombar .x-spreadsheet-menu > li { flex: 0 0 auto; }
`;

/**
 * An Excel-like editor (x-spreadsheet) for a document grid. Created once on
 * mount; every edit is reported back as a canonical {@link DocumentGrid}. A
 * book (`grid.sheets`, one lembar per sheet) opens as sheet tabs at the bottom.
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

        const book = initialRef.current;
        const sheets = book.sheets && book.sheets.length > 0 ? book.sheets : [book];
        const sheet = new Spreadsheet(container, {
            mode: 'edit',
            showToolbar: true,
            showGrid: true,
            showContextmenu: true,
            view: {
                height: () => height,
                width: () => container.offsetWidth || 900,
            },
            showBottomBar: true,
            row: { len: Math.max(...sheets.map((s) => s.rows.length + 5), 40), height: 24 },
            col: { len: Math.max(...sheets.map((s) => s.cols), 10), width: 110, indexWidth: 40, minWidth: 60 },
        });

        sheet.loadData(gridToSheets(initialRef.current) as unknown as Record<string, unknown>);
        // Report every sheet, not only the one being edited.
        sheet.change(() => onChangeRef.current(sheetsToGrid(sheet.getData())));

        return () => {
            container.innerHTML = '';
        };
    }, [height]);

    return (
        <>
            {/* Many lembar: the sheet tabs scroll sideways instead of wrapping out of the editor. */}
            <style>{SHEET_TABS_CSS}</style>
            <div ref={containerRef} className="w-full" />
        </>
    );
}
