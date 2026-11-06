import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Generic Excel export of an input page: every visible table of the page (as
 * it is on screen — merged headers, typed values, ticks) under the standard
 * kop, one after another, each with the heading shown above it. Used by the
 * pages that have no export of their own (GenericExcelButton).
 */

const HEADER: StyleSpec = { fill: 'FEF08A', bold: true, align: 'center', valign: 'center', wrap: true, border: true };
const CELL: StyleSpec = { align: 'left', valign: 'top', wrap: true, border: true };
const CELL_CENTER: StyleSpec = { align: 'center', valign: 'top', wrap: true, border: true };
const CAPTION: StyleSpec = { bold: true, size: 11, align: 'left' };

const visible = (el: Element): boolean => {
    const box = el.getBoundingClientRect();
    const style = window.getComputedStyle(el);

    return box.width > 0 && box.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
};

/** The page's own tables: shown, not a phone card list, not nested in another table. */
export function exportableTables(root: ParentNode): HTMLTableElement[] {
    return [...root.querySelectorAll('table')].filter(
        (table) => visible(table) && !table.hasAttribute('data-mc') && !table.closest('[data-mc]') && !table.parentElement?.closest('table'),
    );
}

/** A cell as text: typed values instead of the inputs, ✓ for a ticked box, no icons or action buttons. */
function cellText(cell: HTMLElement): string {
    const clone = cell.cloneNode(true) as HTMLElement;
    const originals = [...cell.querySelectorAll('input, textarea, select, [role="checkbox"], [data-slot="select-trigger"]')];
    const copies = [...clone.querySelectorAll('input, textarea, select, [role="checkbox"], [data-slot="select-trigger"]')];

    copies.forEach((copy, i) => {
        const source = originals[i] as HTMLElement;
        let text = '';

        if (source instanceof HTMLInputElement) {
            text = source.type === 'checkbox' || source.type === 'radio' ? (source.checked ? '✓' : '') : source.type === 'file' || source.type === 'hidden' ? '' : source.value;
        } else if (source instanceof HTMLTextAreaElement) {
            text = source.value;
        } else if (source instanceof HTMLSelectElement) {
            text = source.selectedOptions[0]?.text ?? '';
        } else if (source.getAttribute('role') === 'checkbox') {
            text = source.getAttribute('aria-checked') === 'true' || source.getAttribute('data-state') === 'checked' ? '✓' : '';
        } else {
            text = source.hasAttribute('data-placeholder') ? '' : (source.textContent ?? '');
        }

        copy.replaceWith(document.createTextNode(` ${text} `));
    });
    clone.querySelectorAll('svg, img, button, [hidden], .sr-only').forEach((node) => node.remove());

    return (clone.textContent ?? '').replace(/[^\S\n]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
}

/** The heading shown just above a table (a few levels up), if any. */
function captionOf(table: HTMLElement): string {
    let node: HTMLElement | null = table;

    for (let depth = 0; node && depth < 4; depth++, node = node.parentElement) {
        let sibling = node.previousElementSibling as HTMLElement | null;

        while (sibling && !visible(sibling)) {
            sibling = sibling.previousElementSibling as HTMLElement | null;
        }

        const text = sibling?.innerText?.trim() ?? '';

        if (sibling && text && text.length <= 90 && (/^H[1-4]$/.test(sibling.tagName) || /font-(semibold|bold)/.test(sibling.className))) {
            return text;
        }
    }

    return '';
}

const asValue = (text: string): string | number => (/^-?\d+(\.\d+)?$/.test(text) && text.length < 15 ? Number(text) : text);

/**
 * Lays one table out from `top` (1-based row); returns the next free row and
 * its width per column (characters) for the column sizing.
 */
function writeTable(sheet: ExcelJS.Worksheet, table: HTMLTableElement, top: number, widths: number[]): number {
    const taken: boolean[][] = [];
    const rows = [...table.rows].filter((tr) => visible(tr));
    // The action column (delete buttons) has an empty heading: it is left out.
    const lastHead = rows[0]?.cells[rows[0].cells.length - 1];
    const dropLast = !!lastHead && cellText(lastHead) === '' && lastHead.colSpan === 1;
    let r = top;
    let right = 1;

    for (const row of rows) {
        let c = 1;
        const header = row.parentElement?.tagName === 'THEAD';
        let cells = [...row.cells].filter((td) => window.getComputedStyle(td).display !== 'none');

        if (dropLast && cells.length > 1 && cellText(cells[cells.length - 1]) === '' && cells[cells.length - 1].colSpan === 1) {
            cells = cells.slice(0, -1);
        }

        if (!header) {
            sheet.getRow(r).height = Math.max(sheet.getRow(r).height ?? 0, 20);
        }

        for (const cell of cells) {
            while (taken[r]?.[c]) {
                c++;
            }

            const text = cellText(cell);
            const rowSpan = Math.max(1, cell.rowSpan);
            const colSpan = Math.max(1, cell.colSpan);
            const isHeader = header || cell.tagName === 'TH';
            const target = sheet.getRow(r).getCell(c);
            target.value = isHeader ? text : asValue(text);

            for (let dr = 0; dr < rowSpan; dr++) {
                for (let dc = 0; dc < colSpan; dc++) {
                    (taken[r + dr] ??= [])[c + dc] = true;
                    applyStyle(sheet.getRow(r + dr).getCell(c + dc), isHeader ? HEADER : /text-(center|right)/.test(cell.className) ? CELL_CENTER : CELL);
                }
            }

            if (rowSpan > 1 || colSpan > 1) {
                sheet.mergeCells(r, c, r + rowSpan - 1, c + colSpan - 1);
            }

            if (colSpan === 1) {
                const longest = Math.max(...text.split('\n').map((line) => line.length), 0);
                widths[c - 1] = Math.max(widths[c - 1] ?? 0, Math.min(longest, 48));
            }

            c += colSpan;
            right = Math.max(right, c - 1);
        }

        r++;
    }

    frame(sheet, top, r - 1, right);

    return r;
}

/** A medium black outline around a table, keeping the thin inner grid. */
function frame(sheet: ExcelJS.Worksheet, top: number, bottom: number, right: number): void {
    const thin = { style: 'thin' as const, color: { argb: 'FF000000' } };
    const medium = { style: 'medium' as const, color: { argb: 'FF000000' } };

    for (let r = top; r <= bottom; r++) {
        for (let c = 1; c <= right; c++) {
            const cell = sheet.getRow(r).getCell(c);
            cell.border = {
                top: r === top ? medium : thin,
                bottom: r === bottom ? medium : thin,
                left: c === 1 ? medium : thin,
                right: c === right ? medium : thin,
            };
        }
    }
}

export async function downloadPageTablesWorkbook(root: ParentNode, filename: string): Promise<boolean> {
    const tables = exportableTables(root);

    if (tables.length === 0) {
        return false;
    }

    // "Judul - Unit - Aplikasi" (the unit only when the page title names it).
    const parts = document.title.split(' - ');
    const title = parts[0];
    const unitName = parts.length >= 3 ? parts[1] : '';
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet((title || 'Data').replace(/[\\/?*[\]:]/g, ' ').slice(0, 31), {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    // The tables first (from row 7) so the kop knows how wide the page is.
    const widths: number[] = [];
    let row = 7;

    for (const table of tables) {
        const caption = captionOf(table);

        if (caption && tables.length > 1) {
            const cell = sheet.getRow(row).getCell(1);
            cell.value = caption;
            applyStyle(cell, CAPTION);
            row++;
        }

        row = writeTable(sheet, table, row, widths) + 1;
    }

    const totalCols = Math.max(widths.length, 6);
    const colWidths = Array.from({ length: totalCols }, (_, i) => Math.max(6, Math.min((widths[i] ?? 8) + 2, 50)));
    colWidths.forEach((width, i) => (sheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet: sheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT', unitName ? `LAPORAN PROJECT ${unitName.toUpperCase()}` : 'LAPORAN PROJECT', (title ?? '').toUpperCase()],
        barTitle: (title ?? '').toUpperCase(),
    });

    await downloadWorkbook(workbook, filename);

    return true;
}
