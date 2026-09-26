import ExcelJS from 'exceljs';

/**
 * Shared styling helpers for the HAR "Jadwal" Excel exports.
 *
 * The exports are built with ExcelJS, which honours cell fills/borders (so the
 * workbook colours match the on-screen table) and can embed the official PLN &
 * MKP logos in the document header — neither of which the community `xlsx`
 * writer supports.
 */

/** Hex colours (RRGGBB) mirroring the Tailwind classes used by the tables. */
export const XLSX_COLORS = {
    orange: 'ED7D31', // print-orange-header (bg-[#ed7d31])
    headerGrey: 'D9D9D9', // thead bg-muted/60
    categoryGrey: 'D0CECE', // category / section rows (bg-muted/40)
    labelGrey: 'F2F2F2', // label columns (bg-muted/10-20)
    totalGrey: 'BFBFBF', // total rows
    red: 'FF0000', // libur cells (bg-[#ff0000])
    red600: 'DC2626', // weekend / off marker (bg-red-600)
    redLight: 'FEE2E2', // soft red weekend background (bg-red-50)
    yellow: 'FFFF00', // ganti pelumas (bg-[#ffff00])
    green: '92D050', // ganti pelumas + cleaning (bg-[#92d050])
    blue: '00B0F0', // patrol piket marker (bg-[#00b0f0])
    amber: 'FEF3C7', // rencana cells (bg-amber-100)
    emerald: 'D1FAE5', // realisasi cells (bg-emerald-100)
    teal: 'CCFBF1', // rencana + realisasi cells (bg-teal-100)
    emeraldSoft: 'ECFDF5', // ready marker (bg-emerald-50)
    slate: 'E2E8F0', // terlaksana marker (bg-slate-200)
    zinc: 'E4E4E7', // piket marker (bg-zinc-200)
    white: 'FFFFFF',
    black: '000000',
} as const;

/**
 * Declarative style description resolved into an ExcelJS cell style by
 * {@link applyStyle}. Keeps the per-page call sites terse.
 */
export type StyleSpec = {
    /** Solid fill colour (RRGGBB). */
    fill?: string;
    bold?: boolean;
    italic?: boolean;
    /** Font colour (RRGGBB). */
    color?: string;
    /** Font size in points. */
    size?: number;
    align?: 'left' | 'center' | 'right';
    valign?: 'top' | 'center' | 'bottom';
    wrap?: boolean;
    /** Draw thin black borders on all four sides. */
    border?: boolean;
};

/** ExcelJS uses ARGB; prefix an opaque alpha when a plain RRGGBB is given. */
const argb = (hex: string): string => (hex.length === 8 ? hex : `FF${hex}`);

const THIN_BORDER = {
    style: 'thin' as const,
    color: { argb: argb(XLSX_COLORS.black) },
};

/** Apply a {@link StyleSpec} to a single ExcelJS cell. */
export function applyStyle(cell: ExcelJS.Cell, spec: StyleSpec): void {
    if (spec.fill) {
        cell.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: argb(spec.fill) },
        };
    }

    if (spec.bold || spec.italic || spec.color || spec.size) {
        cell.font = {
            bold: spec.bold ?? false,
            italic: spec.italic ?? false,
            ...(spec.color ? { color: { argb: argb(spec.color) } } : {}),
            ...(spec.size ? { size: spec.size } : {}),
        };
    }

    cell.alignment = {
        horizontal: spec.align ?? 'left',
        vertical:
            spec.valign === 'center' ? 'middle' : (spec.valign ?? 'middle'),
        wrapText: spec.wrap ?? false,
    };

    if (spec.border) {
        cell.border = {
            top: THIN_BORDER,
            bottom: THIN_BORDER,
            left: THIN_BORDER,
            right: THIN_BORDER,
        };
    }
}

/** Reusable style presets shared across the jadwal exports. */
export const SPECS: Record<string, StyleSpec> = {
    title: { bold: true, size: 12, align: 'center', valign: 'center' },
    subtitle: { bold: true, size: 10, align: 'center', valign: 'center' },
    note: { italic: true, size: 9, align: 'left' },
    headerOrange: {
        fill: XLSX_COLORS.orange,
        bold: true,
        color: XLSX_COLORS.black,
        align: 'center',
        valign: 'center',
        wrap: true,
        border: true,
    },
    headerGrey: {
        fill: XLSX_COLORS.headerGrey,
        bold: true,
        align: 'center',
        valign: 'center',
        wrap: true,
        border: true,
    },
    category: {
        fill: XLSX_COLORS.categoryGrey,
        bold: true,
        align: 'left',
        valign: 'center',
        border: true,
    },
    label: {
        fill: XLSX_COLORS.labelGrey,
        bold: true,
        align: 'left',
        valign: 'center',
        border: true,
    },
    labelCenter: {
        fill: XLSX_COLORS.labelGrey,
        bold: true,
        align: 'center',
        valign: 'center',
        border: true,
    },
    total: {
        fill: XLSX_COLORS.totalGrey,
        bold: true,
        align: 'center',
        valign: 'center',
        border: true,
    },
    cell: { align: 'center', valign: 'center', border: true },
    cellLeft: { align: 'left', valign: 'center', border: true },
};

/** A workbook + its single worksheet, ready to be styled and downloaded. */
export type JadwalSheet = {
    workbook: ExcelJS.Workbook;
    worksheet: ExcelJS.Worksheet;
};

/** Create a workbook, add one worksheet, and fill it from a 2D array. */
export function createSheet(
    name: string,
    rows: (string | number)[][],
): JadwalSheet {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet(name.slice(0, 31), {
        views: [{ showGridLines: false }],
    });
    worksheet.addRows(rows);

    return { workbook, worksheet };
}

/**
 * Walk a `rowCount` x `colCount` grid (0-based) and apply the style returned by
 * `styleAt`. Returning `null` leaves the cell untouched.
 */
export function paintSheet(
    worksheet: ExcelJS.Worksheet,
    rowCount: number,
    colCount: number,
    styleAt: (r: number, c: number) => StyleSpec | null,
): void {
    for (let r = 0; r < rowCount; r++) {
        for (let c = 0; c < colCount; c++) {
            const spec = styleAt(r, c);

            if (spec) {
                applyStyle(worksheet.getRow(r + 1).getCell(c + 1), spec);
            }
        }
    }
}

/** Merge the rectangle (r1,c1)-(r2,c2), given as 0-based indices. */
export function mergeCells(
    worksheet: ExcelJS.Worksheet,
    r1: number,
    c1: number,
    r2: number,
    c2: number,
): void {
    worksheet.mergeCells(r1 + 1, c1 + 1, r2 + 1, c2 + 1);
}

/** Set column widths in character units; `undefined` keeps the default. */
export function setColWidths(
    worksheet: ExcelJS.Worksheet,
    widths: (number | undefined)[],
): void {
    widths.forEach((width, index) => {
        if (width) {
            worksheet.getColumn(index + 1).width = width;
        }
    });
}

const base64FromBuffer = (buffer: ArrayBuffer): string => {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }

    return btoa(binary);
};

export const loadImageBase64 = async (url: string): Promise<string | null> => {
    try {
        const response = await fetch(url);

        if (!response.ok) {
            return null;
        }

        return base64FromBuffer(await response.arrayBuffer());
    } catch {
        return null;
    }
};

/** Approximate pixel width of an ExcelJS column given its character width. */
const colPx = (width: number | undefined): number => (width ?? 8) * 7 + 5;

/**
 * How many columns, counted from `fromLeft` ? the left edge : the right edge,
 * are needed to reach at least `targetPx` of width (min 1, capped so a centre
 * band always survives).
 */
const spanForWidth = (
    colWidths: (number | undefined)[],
    totalCols: number,
    targetPx: number,
    fromLeft: boolean,
): number => {
    let span = 0;
    let acc = 0;
    const cap = Math.max(1, Math.floor(totalCols / 2) - 1);

    while (span < totalCols && acc < targetPx) {
        const index = fromLeft ? span : totalCols - 1 - span;
        acc += colPx(colWidths[index]);
        span++;
    }

    return Math.min(Math.max(span, 1), cap);
};

/**
 * Build the standard, bordered document header shared by every jadwal export:
 *
 *   [ PLN logo | three centred title lines | MKP logo ]  (3 rows)
 *   [ full-width orange bar with the report title ]      (1 row)
 *
 * It occupies the first four worksheet rows (0-3), so callers must leave those
 * rows blank in their data and index their table from row 4 (0-based). Missing
 * logo files are skipped silently rather than breaking the export.
 */
export async function buildDocumentHeader(
    { workbook, worksheet }: JadwalSheet,
    options: {
        totalCols: number;
        colWidths: (number | undefined)[];
        titleLines: [string, string, string];
        barTitle: string;
    },
): Promise<void> {
    const { totalCols, colWidths, titleLines, barTitle } = options;

    const leftSpan = spanForWidth(colWidths, totalCols, 150, true);
    const rightSpan = spanForWidth(colWidths, totalCols, 150, false);
    const centerStart = leftSpan; // 0-based
    const centerEnd = totalCols - rightSpan - 1; // 0-based (inclusive)
    const rightStart = centerEnd + 1; // 0-based

    // Thin border on every header cell first, so merged ranges keep a full
    // outline (ExcelJS renders the outline from the underlying cells).
    for (let r = 0; r < 4; r++) {
        for (let c = 0; c < totalCols; c++) {
            worksheet.getRow(r + 1).getCell(c + 1).border = {
                top: THIN_BORDER,
                bottom: THIN_BORDER,
                left: THIN_BORDER,
                right: THIN_BORDER,
            };
        }
    }

    // Logo cells (left + right) span the three title rows.
    mergeCells(worksheet, 0, 0, 2, leftSpan - 1);
    mergeCells(worksheet, 0, rightStart, 2, totalCols - 1);

    // Three centred title lines.
    titleLines.forEach((line, i) => {
        const cell = worksheet.getRow(i + 1).getCell(centerStart + 1);
        cell.value = line;
        applyStyle(cell, {
            bold: true,
            size: i === 0 ? 11 : 10,
            align: 'center',
            valign: 'center',
            border: true,
        });
        mergeCells(worksheet, i, centerStart, i, centerEnd);
    });

    // Full-width orange report bar.
    const bar = worksheet.getRow(4).getCell(1);
    bar.value = barTitle;
    applyStyle(bar, { ...SPECS.headerOrange, size: 11 });
    mergeCells(worksheet, 3, 0, 3, totalCols - 1);

    worksheet.getRow(1).height = 18;
    worksheet.getRow(2).height = 18;
    worksheet.getRow(3).height = 18;
    worksheet.getRow(4).height = 20;

    const pln = await loadImageBase64('/logo/sidebar-logo.png');

    if (pln) {
        const id = workbook.addImage({ base64: pln, extension: 'png' });
        worksheet.addImage(id, {
            tl: { col: 0.12, row: 0.18 },
            ext: { width: 165, height: 44 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');

    if (mkp) {
        const id = workbook.addImage({ base64: mkp, extension: 'jpeg' });
        worksheet.addImage(id, {
            tl: { col: rightStart + 0.2, row: 0.22 },
            ext: { width: 62, height: 44 },
        });
    }
}

/** Write the workbook and trigger a browser download. */
export async function downloadWorkbook(
    workbook: ExcelJS.Workbook,
    filename: string,
): Promise<void> {
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer as BlobPart], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
}
