import ExcelJS from 'exceljs';
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';
import { downloadWorkbook } from '@/lib/jadwal-excel';
import { reportBlocks, sheetName } from '@/lib/report-book';

/**
 * Excel of a laporan laid out exactly like its PDF (user, 2026-11-26: "format
 * excel harus sama persis layoutnya dengan format pdf"). The report document —
 * the HTML the PDF is printed from, with its own styles — is laid out in a
 * hidden page at the PDF's paper width; every table cell, text line, logo and
 * chart is then measured and placed on the sheet at the same position: the
 * column / row edges are the edges of what is drawn, so a cell keeps its width,
 * height, merges, borders, fill, font size, bold, colour and alignment. One
 * sheet per lembar (Sampul, then each section — see reportBlocks), A4 with the
 * lembar's own orientation, fitted to the page width.
 */

/** The printable width of A4 at 96 dpi (10 mm margins): portrait / landscape. */
const PORTRAIT_PX = 718;
const LANDSCAPE_PX = 1047;
const COVER_PX = 794;
/** Edges closer than this (px) are one column / row edge. */
const SNAP_PX = 5;
const PX_TO_PT = 0.75;

type Rect = { left: number; top: number; right: number; bottom: number };

type Side = { style: ExcelJS.BorderStyle; color: string } | null;

type Decoration = {
    rect: Rect;
    fill: string | null;
    top: Side;
    right: Side;
    bottom: Side;
    left: Side;
};

type TextBox = {
    rect: Rect;
    text: string;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    size: number;
    color: string;
    align: 'left' | 'center' | 'right' | 'justify';
    valign: 'top' | 'middle' | 'bottom';
};

type Picture = { rect: Rect; dataUrl: string };

type Layout = {
    width: number;
    height: number;
    decorations: Decoration[];
    texts: TextBox[];
    pictures: Picture[];
};

const SKIP_TAGS = new Set(['STYLE', 'SCRIPT', 'NOSCRIPT', 'TEMPLATE', 'HEAD']);
const PICTURE_TAGS = new Set(['IMG', 'SVG', 'CANVAS']);

/** "rgb(1, 2, 3)" / "rgba(…, a)" → "RRGGBB" over white; null when transparent. */
function hexColor(value: string): string | null {
    const match = value.match(/rgba?\(([^)]+)\)/);

    if (!match) {
        return null;
    }

    const [r, g, b, a = 1] = match[1]
        .split(/[\s,/]+/)
        .filter(Boolean)
        .map(Number);

    if (a <= 0.05) {
        return null;
    }

    const blend = (channel: number) =>
        Math.round(channel * a + 255 * (1 - a))
            .toString(16)
            .padStart(2, '0');

    return `${blend(r)}${blend(g)}${blend(b)}`.toUpperCase();
}

function side(style: CSSStyleDeclaration, name: string): Side {
    const width = parseFloat(style.getPropertyValue(`border-${name}-width`));
    const line = style.getPropertyValue(`border-${name}-style`);
    const color = hexColor(style.getPropertyValue(`border-${name}-color`));

    if (!width || line === 'none' || line === 'hidden' || !color) {
        return null;
    }

    const excelStyle: ExcelJS.BorderStyle =
        line === 'double'
            ? 'double'
            : line === 'dashed'
              ? 'dashed'
              : line === 'dotted'
                ? 'dotted'
                : width >= 2.5
                  ? 'thick'
                  : width >= 1.5
                    ? 'medium'
                    : 'thin';

    return { style: excelStyle, color };
}

/** Lay the report out in a hidden page with the document's styles. */
async function mountReport(
    html: string,
    contentStyles: string,
): Promise<{ frame: HTMLIFrameElement; root: HTMLElement }> {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.setAttribute('sandbox', 'allow-same-origin');
    frame.style.cssText =
        'position:fixed;left:-30000px;top:0;width:1400px;height:900px;opacity:0;pointer-events:none;border:0;';
    frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><style>${contentCss}
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #000; margin: 0; background: #fff; }
        table { border-collapse: collapse; }
        ${contentStyles.replace(/<\/?style[^>]*>/gi, '')}
        .op-cover, .har-cover, .k3-cover { margin: 0; width: 210mm; height: 297mm; }
        </style></head><body><div id="__report">${html}</div></body></html>`;

    await new Promise<void>((resolve) => {
        frame.addEventListener('load', () => resolve(), { once: true });
        document.body.appendChild(frame);
    });

    const doc = frame.contentDocument as Document;
    await Promise.all(
        Array.from(doc.images).map((image) =>
            image.complete
                ? Promise.resolve()
                : new Promise<void>((resolve) => {
                      image.addEventListener('load', () => resolve(), {
                          once: true,
                      });
                      image.addEventListener('error', () => resolve(), {
                          once: true,
                      });
                  }),
        ),
    );
    await doc.fonts?.ready;

    return { frame, root: doc.getElementById('__report') as HTMLElement };
}

const isLandscape = (element: Element): boolean =>
    Array.from(element.classList).some((name) => /landscape/.test(name)) ||
    element.querySelector('[class*="landscape"]') !== null;

/** An `<img>` / `<svg>` / `<canvas>` as a PNG data URL (null when it cannot be read). */
async function pictureUrl(
    element: Element,
    width: number,
    height: number,
): Promise<string | null> {
    const scale = 2;
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    try {
        let source: CanvasImageSource;

        if (element.tagName.toUpperCase() === 'SVG') {
            const markup = new XMLSerializer().serializeToString(element);
            const image = new Image();
            image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(
                markup.includes('xmlns=')
                    ? markup
                    : markup.replace(
                          '<svg',
                          '<svg xmlns="http://www.w3.org/2000/svg"',
                      ),
            )}`;
            await image.decode();
            source = image;
        } else {
            source = element as unknown as CanvasImageSource;
        }

        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/png');
    } catch {
        return null;
    }
}

/** Everything drawn in one lembar, in px relative to its top-left corner. */
async function measure(block: Element): Promise<Layout> {
    const win = block.ownerDocument.defaultView as Window;
    const origin = block.getBoundingClientRect();
    const rectOf = (rect: DOMRect): Rect => ({
        left: rect.left - origin.left,
        top: rect.top - origin.top,
        right: rect.right - origin.left,
        bottom: rect.bottom - origin.top,
    });
    const layout: Layout = {
        width: origin.width,
        height: origin.height,
        decorations: [],
        texts: [],
        pictures: [],
    };
    const styles = new Map<Element, CSSStyleDeclaration>();
    const styleOf = (element: Element) => {
        let style = styles.get(element);

        if (!style) {
            style = win.getComputedStyle(element);
            styles.set(element, style);
        }

        return style;
    };
    const hidden = (element: Element) => {
        const style = styleOf(element);

        return (
            style.display === 'none' ||
            style.visibility === 'hidden' ||
            hasPageBreak(element)
        );
    };
    // Elements holding block content (tables, divs, pictures) somewhere inside.
    const holdsBlocks = new Map<Element, boolean>();
    const isBlocky = (element: Element): boolean => {
        const tag = element.tagName.toUpperCase();

        if (PICTURE_TAGS.has(tag) || tag === 'TABLE') {
            return true;
        }

        const display = styleOf(element).display;

        return display !== 'inline' && display !== 'contents' && tag !== 'BR';
    };
    const containsBlocks = (element: Element): boolean => {
        const known = holdsBlocks.get(element);

        if (known !== undefined) {
            return known;
        }

        const result = Array.from(element.children).some(
            (child) =>
                !SKIP_TAGS.has(child.tagName.toUpperCase()) &&
                !hidden(child) &&
                (isBlocky(child) || containsBlocks(child)),
        );
        holdsBlocks.set(element, result);

        return result;
    };
    const pending: Promise<void>[] = [];

    const textBox = (
        rect: Rect,
        text: string,
        style: CSSStyleDeclaration,
        prefix = '',
    ) => {
        const cleaned = text
            .split('\n')
            .map((line) => line.replace(/\s+/g, ' ').trim())
            .filter((line) => line !== '')
            .join('\n');

        if (cleaned === '' || rect.right - rect.left < 1) {
            return;
        }

        const align = style.textAlign;
        const valign = style.verticalAlign;
        layout.texts.push({
            rect,
            text: prefix + cleaned,
            bold:
                Number(style.fontWeight) >= 600 || style.fontWeight === 'bold',
            italic: style.fontStyle === 'italic',
            underline: style.textDecorationLine.includes('underline'),
            size: Math.max(
                5,
                Math.round(parseFloat(style.fontSize) * PX_TO_PT * 2) / 2,
            ),
            color: hexColor(style.color) ?? '000000',
            align:
                align === 'center' || align === '-webkit-center'
                    ? 'center'
                    : align === 'right' || align === 'end'
                      ? 'right'
                      : align === 'justify'
                        ? 'justify'
                        : 'left',
            valign:
                valign === 'top'
                    ? 'top'
                    : valign === 'bottom'
                      ? 'bottom'
                      : 'middle',
        });
    };

    const listPrefix = (element: Element): string => {
        if (element.tagName.toUpperCase() !== 'LI') {
            return '';
        }

        const list = element.parentElement;

        if (styleOf(element).listStyleType === 'none') {
            return '';
        }

        if (list?.tagName.toUpperCase() === 'OL') {
            return `${Array.from(list.children).indexOf(element) + 1}. `;
        }

        return '• ';
    };

    const walk = (element: Element) => {
        const tag = element.tagName.toUpperCase();

        if (SKIP_TAGS.has(tag) || hidden(element)) {
            return;
        }

        const style = styleOf(element);
        const rect = rectOf(element.getBoundingClientRect());

        if (PICTURE_TAGS.has(tag)) {
            const width = rect.right - rect.left;
            const height = rect.bottom - rect.top;

            if (width >= 2 && height >= 2) {
                pending.push(
                    pictureUrl(element, width, height).then((dataUrl) => {
                        if (dataUrl) {
                            layout.pictures.push({ rect, dataUrl });
                        }
                    }),
                );
            }

            return;
        }

        const decoration: Decoration = {
            rect,
            fill: hexColor(style.backgroundColor),
            top: side(style, 'top'),
            right: side(style, 'right'),
            bottom: side(style, 'bottom'),
            left: side(style, 'left'),
        };

        if (decoration.fill === 'FFFFFF') {
            decoration.fill = null;
        }

        if (
            decoration.fill ||
            decoration.top ||
            decoration.right ||
            decoration.bottom ||
            decoration.left
        ) {
            layout.decorations.push(decoration);
        }

        if (!containsBlocks(element)) {
            textBox(
                rect,
                (element as HTMLElement).innerText ?? element.textContent ?? '',
                style,
                listPrefix(element),
            );

            return;
        }

        // Mixed content: runs of inline text between the blocks are their own lines.
        let run: Node[] = [];
        const flush = () => {
            if (run.length === 0) {
                return;
            }

            const range = element.ownerDocument.createRange();
            range.setStartBefore(run[0]);
            range.setEndAfter(run[run.length - 1]);
            const only = run.length === 1 && run[0].nodeType === 1;
            textBox(
                rectOf(range.getBoundingClientRect()),
                only
                    ? ((run[0] as HTMLElement).innerText ?? '')
                    : range.toString(),
                only ? styleOf(run[0] as Element) : style,
            );
            run = [];
        };

        element.childNodes.forEach((child) => {
            if (child.nodeType === 3) {
                run.push(child);

                return;
            }

            if (child.nodeType !== 1) {
                return;
            }

            const childElement = child as Element;

            if (
                SKIP_TAGS.has(childElement.tagName.toUpperCase()) ||
                hidden(childElement)
            ) {
                return;
            }

            if (isBlocky(childElement) || containsBlocks(childElement)) {
                flush();
                walk(childElement);
            } else {
                run.push(child);
            }
        });
        flush();
    };

    Array.from(block.children).forEach(walk);

    // Text directly in the block (rare) is kept as well.
    if (!containsBlocks(block)) {
        textBox(
            rectOf(origin),
            (block as HTMLElement).innerText ?? '',
            styleOf(block),
        );
    }

    await Promise.all(pending);

    return layout;
}

const hasPageBreak = (element: Element): boolean =>
    element.classList.contains('page-break');

/** Sorted edges, those within SNAP_PX of each other made one. */
function edges(values: number[]): number[] {
    const sorted = values
        .filter((value) => Number.isFinite(value))
        .sort((a, b) => a - b);
    const result: number[] = [];

    for (const value of sorted) {
        if (
            result.length === 0 ||
            value - result[result.length - 1] > SNAP_PX
        ) {
            result.push(value);
        }
    }

    return result;
}

/** Index of the edge nearest to `value`. */
function nearest(list: number[], value: number): number {
    let low = 0;
    let high = list.length - 1;

    while (low < high) {
        const middle = (low + high) >> 1;

        if (list[middle] < value) {
            low = middle + 1;
        } else {
            high = middle;
        }
    }

    if (low > 0 && value - list[low - 1] < list[low] - value) {
        return low - 1;
    }

    return low;
}

/** Excel column width (characters of the default font) for a width in px. */
const columnWidth = (px: number): number =>
    Math.max(0.1, Math.round((px >= 12 ? (px - 5) / 7 : px / 12) * 100) / 100);

function writeLayout(
    workbook: ExcelJS.Workbook,
    layout: Layout,
    name: string,
    landscape: boolean,
): void {
    const sheet = workbook.addWorksheet(name, {
        views: [{ showGridLines: false }],
        pageSetup: {
            paperSize: 9,
            orientation: landscape ? 'landscape' : 'portrait',
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
            horizontalCentered: true,
            margins: {
                left: 0.4,
                right: 0.4,
                top: 0.4,
                bottom: 0.4,
                header: 0.2,
                footer: 0.2,
            },
        },
    });
    const boxes: Rect[] = [
        ...layout.texts.map((box) => box.rect),
        ...layout.decorations.map((box) => box.rect),
        ...layout.pictures.map((box) => box.rect),
    ];
    const xs = edges([
        0,
        layout.width,
        ...boxes.flatMap((rect) => [rect.left, rect.right]),
    ]);
    const ys = edges([0, ...boxes.flatMap((rect) => [rect.top, rect.bottom])]);
    const cellRange = (rect: Rect) => {
        const c1 = nearest(xs, rect.left);
        const r1 = nearest(ys, rect.top);

        return {
            r1,
            c1,
            r2: Math.max(r1, nearest(ys, rect.bottom) - 1),
            c2: Math.max(c1, nearest(xs, rect.right) - 1),
        };
    };

    for (let c = 0; c < xs.length - 1; c++) {
        sheet.getColumn(c + 1).width = columnWidth(xs[c + 1] - xs[c]);
    }

    for (let r = 0; r < ys.length - 1; r++) {
        sheet.getRow(r + 1).height = Math.max(
            1,
            Math.round((ys[r + 1] - ys[r]) * PX_TO_PT * 10) / 10,
        );
    }

    // Fills and borders, outer elements first so inner ones are drawn over them.
    const borders = new Map<string, Partial<ExcelJS.Borders>>();
    const borderAt = (r: number, c: number) => {
        const key = `${r}:${c}`;
        let border = borders.get(key);

        if (!border) {
            border = {};
            borders.set(key, border);
        }

        return border;
    };
    const line = (value: Side): Partial<ExcelJS.Border> | undefined =>
        value
            ? { style: value.style, color: { argb: `FF${value.color}` } }
            : undefined;

    for (const decoration of layout.decorations) {
        const { r1, c1, r2, c2 } = cellRange(decoration.rect);

        if (decoration.fill) {
            for (let r = r1; r <= r2; r++) {
                for (let c = c1; c <= c2; c++) {
                    sheet.getCell(r + 1, c + 1).fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: `FF${decoration.fill}` },
                    };
                }
            }
        }

        for (let c = c1; c <= c2; c++) {
            if (decoration.top) {
                borderAt(r1, c).top = line(decoration.top);
            }

            if (decoration.bottom) {
                borderAt(r2, c).bottom = line(decoration.bottom);
            }
        }

        for (let r = r1; r <= r2; r++) {
            if (decoration.left) {
                borderAt(r, c1).left = line(decoration.left);
            }

            if (decoration.right) {
                borderAt(r, c2).right = line(decoration.right);
            }
        }
    }

    borders.forEach((border, key) => {
        const [r, c] = key.split(':').map(Number);
        sheet.getCell(r + 1, c + 1).border = border;
    });

    // Text: each box in the cells it covers, merged when it spans several.
    const owner = new Set<string>();

    for (const box of layout.texts) {
        const { r1, c1, r2, c2 } = cellRange(box.rect);
        let free = true;

        for (let r = r1; r <= r2 && free; r++) {
            for (let c = c1; c <= c2; c++) {
                if (owner.has(`${r}:${c}`)) {
                    free = false;
                    break;
                }
            }
        }

        if (owner.has(`${r1}:${c1}`)) {
            continue;
        }

        const target = sheet.getCell(r1 + 1, c1 + 1);
        target.value = box.text;
        target.font = {
            name: 'Arial',
            size: box.size,
            bold: box.bold,
            italic: box.italic,
            underline: box.underline,
            color: { argb: `FF${box.color}` },
        };
        target.alignment = {
            horizontal: box.align,
            vertical: box.valign,
            wrapText: true,
        };

        if (free && (r2 > r1 || c2 > c1)) {
            for (let r = r1; r <= r2; r++) {
                for (let c = c1; c <= c2; c++) {
                    owner.add(`${r}:${c}`);
                }
            }

            try {
                // Keep the merged range's border on every edge cell (ExcelJS keeps the top-left style only).
                const kept = new Map<string, Partial<ExcelJS.Borders>>();

                for (let r = r1; r <= r2; r++) {
                    for (let c = c1; c <= c2; c++) {
                        const border = borders.get(`${r}:${c}`);

                        if (border) {
                            kept.set(`${r}:${c}`, border);
                        }
                    }
                }

                sheet.mergeCells(r1 + 1, c1 + 1, r2 + 1, c2 + 1);
                kept.forEach((border, key) => {
                    const [r, c] = key.split(':').map(Number);
                    sheet.getCell(r + 1, c + 1).border = border;
                });
            } catch {
                // An overlapping range from irregular markup: the text stays in its first cell.
            }
        } else {
            owner.add(`${r1}:${c1}`);
        }
    }

    for (const picture of layout.pictures) {
        const { r1, c1 } = cellRange(picture.rect);
        const imageId = workbook.addImage({
            base64: picture.dataUrl,
            extension: 'png',
        });
        sheet.addImage(imageId, {
            tl: { col: c1, row: r1 } as ExcelJS.Anchor,
            ext: {
                width: picture.rect.right - picture.rect.left,
                height: picture.rect.bottom - picture.rect.top,
            },
            editAs: 'oneCell',
        });
    }
}

/** Download the report document as an .xlsx laid out like its PDF. */
export async function downloadReportLayoutWorkbook(
    html: string,
    contentStyles: string,
    filename: string,
): Promise<void> {
    const mounted = await mountReport(html, contentStyles);

    try {
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'KIT-SIPP';
        workbook.created = new Date();
        const used = new Set<string>();
        const blocks = reportBlocks(mounted.root);

        // Each lembar at its paper width, as the PDF prints it.
        blocks.forEach(({ element, isCover }) => {
            const target = element as HTMLElement;
            target.style.boxSizing = 'border-box';
            target.style.width = `${isCover ? COVER_PX : isLandscape(element) ? LANDSCAPE_PX : PORTRAIT_PX}px`;
            target.style.margin = '0';
        });

        for (const block of blocks) {
            const layout = await measure(block.element);

            if (layout.texts.length === 0 && layout.pictures.length === 0) {
                continue;
            }

            writeLayout(
                workbook,
                layout,
                sheetName(block.name, used),
                !block.isCover && isLandscape(block.element),
            );
        }

        if (workbook.worksheets.length === 0) {
            workbook.addWorksheet('Laporan');
        }

        await downloadWorkbook(workbook, filename);
    } finally {
        mounted.frame.remove();
    }
}
