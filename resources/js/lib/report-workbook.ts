import ExcelJS from 'exceljs';
import { applyStyle, downloadWorkbook } from '@/lib/jadwal-excel';
import { sheetsOf } from '@/lib/report-book';
import type { DocumentGrid } from '@/lib/spreadsheet';

/**
 * "Unduh Excel" of the laporan modul (user, 2026-11-25): writes a grid book
 * (see report-book.ts — Sampul, then one sheet per lembar) to one .xlsx file,
 * one worksheet per sheet with its name, merges, bold / alignment, borders on
 * table cells and column widths. A single grid becomes a one-sheet workbook.
 */

const PX_PER_CHAR = 7;

const sheetTitle = (name: string, used: Set<string>): string => {
    const base = (name.replace(/[\\/?*[\]:]/g, '-').trim() || 'Lembar').slice(
        0,
        31,
    );
    let unique = base;

    for (let n = 2; used.has(unique.toLowerCase()); n++) {
        unique = `${base.slice(0, 31 - String(n).length - 1)} ${n}`;
    }

    used.add(unique.toLowerCase());

    return unique;
};

function writeSheet(
    workbook: ExcelJS.Workbook,
    grid: DocumentGrid,
    name: string,
): void {
    const isCover = name === 'Sampul';
    const sheet = workbook.addWorksheet(name, {
        views: [{ showGridLines: !isCover }],
        pageSetup: {
            paperSize: 9,
            orientation: 'landscape',
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });

    grid.rows.forEach((cells, r) => {
        let lineCount = 1;

        cells.forEach((cell, c) => {
            const target = sheet.getCell(r + 1, c + 1);
            const text = cell.t ?? '';
            const numeric =
                /^-?\d+([.,]\d+)?$/.test(text) &&
                !/^0\d/.test(text) &&
                text.length < 12;
            target.value = numeric ? Number(text.replace(',', '.')) : text;
            applyStyle(target, {
                bold: cell.b,
                align:
                    cell.a === 'c'
                        ? 'center'
                        : cell.a === 'r'
                          ? 'right'
                          : 'left',
                valign: 'center',
                wrap: true,
                size: cell.noborder ? (isCover ? 12 : 10) : 9,
                border: !cell.noborder,
            });
            lineCount = Math.max(lineCount, text.split('\n').length);
        });

        if (lineCount > 1) {
            sheet.getRow(r + 1).height = lineCount * 14;
        }
    });

    for (const [r1, c1, r2, c2] of grid.merges ?? []) {
        if (r1 === r2 && c1 === c2) {
            continue;
        }

        try {
            sheet.mergeCells(r1 + 1, c1 + 1, r2 + 1, c2 + 1);
        } catch {
            // An overlapping merge from irregular markup: keep the cells unmerged.
        }
    }

    (grid.col_widths ?? []).forEach((width, c) => {
        if (width) {
            sheet.getColumn(c + 1).width = Math.max(
                4,
                Math.round(width / PX_PER_CHAR),
            );
        }
    });
}

/** Download a grid book (or a single grid) as one multi-sheet .xlsx file. */
export async function downloadReportWorkbook(
    grid: DocumentGrid,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'KIT-SIPP';
    workbook.created = new Date();
    const used = new Set<string>();

    for (const sheet of sheetsOf(grid)) {
        writeSheet(workbook, sheet, sheetTitle(sheet.name ?? 'Laporan', used));
    }

    await downloadWorkbook(workbook, filename);
}
