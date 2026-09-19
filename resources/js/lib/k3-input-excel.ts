import ExcelJS from 'exceljs';
import {
    applyStyle,
    buildDocumentHeader,
    downloadWorkbook,
    mergeCells,
    SPECS,
    XLSX_COLORS,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/** One K3 input table as returned by `k3.input.export.data` (App\Services\K3\K3InputTables). */
export type K3InputTable = {
    key: string;
    title: string;
    document_number: string;
    unit: string;
    period: string;
    meta: [string, string][];
    columns: { label: string; width?: number; align?: 'l' | 'c' | 'r' }[];
    rows: {
        kind: 'data' | 'group' | 'total';
        cells: (string | { image: string; text: string })[];
    }[];
    has_data: boolean;
    dense: boolean;
};

export type K3InputExport = {
    filename: string;
    unit_header: string;
    tables: K3InputTable[];
};

const ALIGN: Record<string, 'left' | 'center' | 'right'> = {
    l: 'left',
    c: 'center',
    r: 'right',
};

/** Excel forbids these characters and names longer than 31 characters. */
const sheetName = (title: string, used: Set<string>): string => {
    const base =
        title
            .replace(/[[\]:*?/\\]/g, ' ')
            .slice(0, 28)
            .trim() || 'Sheet';
    let name = base;
    let n = 2;

    while (used.has(name)) {
        name = `${base.slice(0, 26)} ${n++}`;
    }

    used.add(name);

    return name;
};

/**
 * Build the K3 input workbook — one landscape sheet per table, with the same
 * PLN/MKP kop as the jadwal exports — and download it.
 */
export async function downloadK3InputWorkbook(
    data: K3InputExport,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const used = new Set<string>();

    for (const table of data.tables) {
        const worksheet = workbook.addWorksheet(sheetName(table.title, used), {
            views: [{ showGridLines: false }],
            pageSetup: {
                orientation: 'landscape',
                paperSize: 9,
                fitToPage: true,
                fitToWidth: 1,
                fitToHeight: 0,
            },
        });
        const totalCols = Math.max(table.columns.length, 3);
        const colWidths = table.columns.map((column) => column.width);
        colWidths.forEach((width, index) => {
            worksheet.getColumn(index + 1).width = width ?? 10;
        });

        await buildDocumentHeader(
            { workbook, worksheet },
            {
                totalCols,
                colWidths,
                titleLines: [
                    'JASA PENDUKUNG TEKNIS UP KENDARI 11 & 6 SITE - KIT',
                    data.unit_header,
                    'LAPORAN PROJECT',
                ],
                barTitle: [
                    table.title,
                    table.document_number ? `(${table.document_number})` : '',
                    table.period ? `— ${table.period}` : '',
                ]
                    .filter(Boolean)
                    .join(' '),
            },
        );

        // Row 5 onwards (1-based): meta lines, a blank line, then the table.
        let rowNumber = 5;
        const meta: [string, string][] = [['Unit', table.unit], ...table.meta];

        for (const [label, value] of meta) {
            const cell = worksheet.getRow(rowNumber).getCell(1);
            cell.value = `${label}: ${value}`;
            applyStyle(cell, {
                ...SPECS.note,
                italic: false,
                bold: label === 'Unit',
            });
            mergeCells(
                worksheet,
                rowNumber - 1,
                0,
                rowNumber - 1,
                totalCols - 1,
            );
            rowNumber++;
        }

        rowNumber++;

        const header = worksheet.getRow(rowNumber);
        table.columns.forEach((column, index) => {
            const cell = header.getCell(index + 1);
            cell.value = column.label;
            applyStyle(cell, SPECS.headerGrey);
        });
        header.height = 30;
        rowNumber++;

        for (const row of table.rows) {
            const excelRow = worksheet.getRow(rowNumber);

            if (row.kind === 'group') {
                const cell = excelRow.getCell(1);
                cell.value =
                    typeof row.cells[0] === 'string' ? row.cells[0] : '';
                applyStyle(cell, SPECS.category);
                mergeCells(
                    worksheet,
                    rowNumber - 1,
                    0,
                    rowNumber - 1,
                    table.columns.length - 1,
                );
            } else {
                table.columns.forEach((column, index) => {
                    const value = row.cells[index] ?? '';
                    const cell = excelRow.getCell(index + 1);
                    cell.value = typeof value === 'string' ? value : value.text;

                    const spec: StyleSpec =
                        row.kind === 'total'
                            ? {
                                  ...SPECS.total,
                                  align: ALIGN[column.align ?? 'l'],
                              }
                            : {
                                  align: ALIGN[column.align ?? 'l'],
                                  valign: 'center',
                                  border: true,
                                  wrap: !table.dense,
                              };

                    if (row.kind === 'data' && value === 'R') {
                        spec.fill = XLSX_COLORS.amber;
                    } else if (row.kind === 'data' && value === 'Rl') {
                        spec.fill = XLSX_COLORS.emerald;
                    }

                    applyStyle(cell, spec);
                });
            }

            rowNumber++;
        }

        if (!table.has_data) {
            const cell = worksheet.getRow(rowNumber + 1).getCell(1);
            cell.value = 'Belum ada data yang disimpan untuk periode ini.';
            applyStyle(cell, { italic: true, color: 'DC2626', align: 'left' });
        }
    }

    await downloadWorkbook(workbook, data.filename);
}
