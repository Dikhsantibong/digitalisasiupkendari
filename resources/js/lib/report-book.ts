import type { DocumentGrid, GridCell } from '@/lib/spreadsheet';

/**
 * Laporan modul as an Excel book (user, 2026-11-25): the report document — the
 * same HTML the PDF is printed from — split into one sheet per lembar. The
 * cover (Sampul) is its own sheet, then every section (`*-section`: op-, har-,
 * k3-, lg-, pdm-, ul-section …) gets its own sheet, named after its input (the
 * Daftar Isi entry pointing at the section, else its kop / title), shortened
 * to Excel's 31 characters. Tables keep colspan / rowspan as merges and their
 * borders; text lines (kop, titles, paragraphs) are borderless rows spanning
 * the sheet; images are left out. Used by the Excel mode of DocumentEditor and
 * the "Unduh Excel" download (report-workbook.ts).
 */

type SheetRow =
    | { kind: 'text'; text: string; bold?: boolean; center?: boolean }
    | { kind: 'table'; table: HTMLTableElement };

type SheetPart = {
    id: string;
    name: string;
    isCover: boolean;
    rows: SheetRow[];
};

// A lembar: op-section, op-p-section (Laporan Operasi), section-box (Laporan K3 & KAM) …
const SECTION_CLASS = /^(?:[a-z0-9]+(?:-[a-z0-9]+)*-section|section-box)$/;
const COVER_CLASS = /^[a-z0-9]+-cover$/;
const SKIP_TAGS = new Set([
    'STYLE',
    'SCRIPT',
    'SVG',
    'IMG',
    'NOSCRIPT',
    'TEMPLATE',
]);
const BLOCK_TAGS = new Set([
    'DIV',
    'P',
    'SECTION',
    'ARTICLE',
    'HEADER',
    'FOOTER',
    'H1',
    'H2',
    'H3',
    'H4',
    'H5',
    'H6',
    'UL',
    'OL',
    'LI',
    'TABLE',
    'BLOCKQUOTE',
    'PRE',
    'HR',
]);
const MAX_COLS = 80;
/** Pixels per character for the column widths (x-spreadsheet works in px). */
const PX_PER_CHAR = 7;

const hasClass = (element: Element, pattern: RegExp): boolean =>
    Array.from(element.classList).some((name) => pattern.test(name));

const clean = (text: string): string => text.replace(/\s+/g, ' ').trim();

/** Visible text of a node, `<br>` kept as a line break. */
function textOf(node: Node): string {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent ?? '';
    }

    if (
        !(node instanceof Element) ||
        SKIP_TAGS.has(node.tagName.toUpperCase())
    ) {
        return '';
    }

    if (node.tagName === 'BR') {
        return '\n';
    }

    return Array.from(node.childNodes).map(textOf).join('');
}

const lines = (node: Node): string =>
    textOf(node)
        .split('\n')
        .map(clean)
        .filter((line) => line !== '')
        .join('\n');

const isBlock = (node: Node): node is Element =>
    node instanceof Element &&
    (BLOCK_TAGS.has(node.tagName.toUpperCase()) ||
        node.querySelector('table, div, p') !== null);

/** Daftar Isi: `#anchor` → the uraian on that row. */
function tocTitles(root: ParentNode): Map<string, string> {
    const titles = new Map<string, string>();

    root.querySelectorAll('a[href^="#"]').forEach((link) => {
        const id = (link.getAttribute('href') ?? '').slice(1);
        const row = link.closest('tr');

        if (!id || !row || titles.has(id)) {
            return;
        }

        const label = Array.from(row.querySelectorAll('td, th'))
            .filter((cell) => !cell.contains(link))
            .map((cell) => clean(cell.textContent ?? ''))
            .filter(
                (text) => text !== '' && !/^[\divxlcIVXLC.\s-]+$/.test(text),
            )
            .sort((a, b) => b.length - a.length)[0];

        if (label) {
            titles.set(id, label);
        }
    });

    return titles;
}

/** The section's own heading: its kop title, sub / part title, or first heading. */
/** A heading's first line ("DAFTAR ISI<br><span>unit</span>" → "DAFTAR ISI"). */
const firstLine = (element: Element): string => {
    const holder = element.ownerDocument.createElement('div');
    holder.innerHTML = element.innerHTML.split(/<br\s*\/?>/i)[0] ?? '';

    return clean(holder.textContent ?? '');
};

function headingOf(section: Element): string | null {
    const selectors = [
        '[class*="kop-title"]',
        '[class*="sub-title"]',
        '[class*="section-title"]',
        '[class*="part-title"]',
        'h1',
        'h2',
        'h3',
        '[class*="title"]',
    ];

    for (const selector of selectors) {
        const found = Array.from(section.querySelectorAll(selector))
            .map(firstLine)
            .find((text) => text !== '');

        if (found) {
            return found;
        }
    }

    return null;
}

/** Short codes kept in capitals in sheet names. */
const ACRONYMS = new Set([
    'FLM',
    'IK',
    'PTW',
    'BBM',
    'HSD',
    'MFO',
    'SR',
    'WO',
    'PM',
    'CM',
    'K3',
    'K3L',
    'K3LH',
    'APD',
    'APAR',
    'APAB',
    'CCTV',
    'LB3',
    'TPS',
    'UL',
    'KWH',
    'HAR',
    'SLA',
    'MKP',
    'PLN',
    'ROT',
    'ROB',
    'ROM',
    '5S',
    '5R',
    '5S5R',
    'KIT',
    'P0',
    'SOP',
    'PLTD',
    'PLTM',
    'PLTG',
    'PLTU',
    'PLTMG',
]);

/** A dangling connector or punctuation at the end of a shortened name. */
const TRAILING = /(\s*[,;:·—–-]|\s+(&|Dan|Atau|Dengan|Untuk|Bersama|\/))+\s*$/i;

/** The words of `text` that fit in `max` characters. */
function fitWords(text: string, max: number): string {
    let short = '';

    for (const word of text.split(' ')) {
        if ((short ? `${short} ${word}` : word).length > max) {
            break;
        }

        short = short ? `${short} ${word}` : word;
    }

    return short || text.slice(0, Math.max(max, 1));
}

/** Short, valid and unique sheet name ("Jadwal Pelaksanaan Performance Test Mesin" → "Jadwal Performance Test Mesin"). */
export function sheetName(raw: string, used: Set<string>): string {
    let name = clean(raw)
        .replace(/^[-–—•·*\s]+/, '')
        .replace(/^([IVXLC]+|\d+(\.\d+)*|[A-Z])[.)]\s+/i, '')
        .replace(/\([^)]*\)/g, '')
        .replace(/[\\/?*[\]:]/g, '-');

    // Word casing: ALL-CAPS words become Capitalised, known short codes stay upper.
    name = name
        .split(' ')
        .map((word) => {
            const letters = word.replace(/[^A-Za-z]/g, '');

            if (ACRONYMS.has(word.toUpperCase().replace(/[^A-Z0-9]/g, ''))) {
                return word.toUpperCase();
            }

            if (letters.length >= 3 && letters === letters.toUpperCase()) {
                return word.charAt(0) + word.slice(1).toLowerCase();
            }

            return word.charAt(0).toUpperCase() + word.slice(1);
        })
        .join(' ')
        .replace(
            /-([a-z])/g,
            (_, letter: string) => `-${letter.toUpperCase()}`,
        );

    name = clean(
        name
            .replace(/^Laporan\s+(?=\S+\s+\S+)/i, '')
            .replace(/\bPelaksanaan\s+/i, '')
            .replace(/\s+dan\s+/gi, ' & ')
            .replace(/\s+Pembangkit\b/gi, '')
            .replace(
                /Unsafe Action & Unsafe Condition/i,
                'Unsafe Action & Condition',
            )
            .replace(TRAILING, ''),
    );

    if (name.length > 31) {
        // Generic lead words go first ("Pengukuran Kualitas Pelumas …" → "Kualitas Pelumas …").
        name = name.replace(/^(Pengukuran|Formulir|Form|Laporan)\s+/i, '');
    }

    if (name.length > 31) {
        // "Patrol Check Pemeliharaan — MIRRLEES #1": keep what tells the lembar apart.
        const [head, ...rest] = name.split(/\s+[—–]\s+/);
        const tail = rest.join(' ');
        name = tail
            ? `${fitWords(head, 31 - tail.length - 1).replace(TRAILING, '')} ${tail}`.trim()
            : fitWords(name, 31);
        name = name.length > 31 ? fitWords(name, 31) : name;
    }

    name = clean(name.replace(TRAILING, ''));

    name = name || 'Lembar';
    let unique = name;

    for (let n = 2; used.has(unique.toLowerCase()); n++) {
        const suffix = ` ${n}`;
        unique = `${name.slice(0, 31 - suffix.length)}${suffix}`;
    }

    used.add(unique.toLowerCase());

    return unique;
}

/** Text rows and tables of a section, in reading order. */
function collectRows(node: Element, rows: SheetRow[]): void {
    if (
        SKIP_TAGS.has(node.tagName.toUpperCase()) ||
        hasClass(node, /^page-break$/)
    ) {
        return;
    }

    if (node instanceof HTMLTableElement) {
        if (node.querySelector('img')) {
            // The kop (logo table): keep its text lines, centred.
            node.querySelectorAll('td, th').forEach((cell) => {
                const text = lines(cell);

                if (text !== '') {
                    rows.push({ kind: 'text', text, bold: true, center: true });
                }
            });

            return;
        }

        rows.push({ kind: 'table', table: node });

        return;
    }

    if (!Array.from(node.children).some(isBlock)) {
        const text = lines(node);

        if (text !== '') {
            rows.push({
                kind: 'text',
                text,
                bold: /^H[1-6]$/.test(node.tagName) || hasClass(node, /title/),
            });
        }

        return;
    }

    let inline = '';
    const flush = () => {
        const text = inline
            .split('\n')
            .map(clean)
            .filter((line) => line !== '')
            .join('\n');

        if (text !== '') {
            rows.push({ kind: 'text', text });
        }

        inline = '';
    };

    node.childNodes.forEach((child) => {
        if (isBlock(child)) {
            flush();
            collectRows(child, rows);
        } else {
            inline += textOf(child);
        }
    });
    flush();
}

/**
 * A report without section wrappers (e.g. Laporan Pemeliharaan pengusahaan):
 * its top-level blocks grouped into lembar, a new one at every `break-before`
 * element or `page-break`; the whole report when it has no page breaks.
 */
function pageGroups(root: Element): Element[] {
    const groups: Element[] = [];
    let current: Element | null = null;

    for (const node of Array.from(root.childNodes)) {
        const element = node.nodeType === 1 ? (node as Element) : null;

        if (element && hasClass(element, /^page-break$/)) {
            current = null;
            continue;
        }

        if (
            current === null ||
            (element && hasClass(element, /^break-before$/))
        ) {
            current = root.ownerDocument.createElement('div');
            current.id = element?.id ?? '';
            groups.push(current);
        }

        current.appendChild(node);
    }

    return groups.length > 0 ? groups : [root];
}

/** Split the report into its lembar (cover first, then every section). */
function reportParts(html: string): SheetPart[] {
    const doc = new DOMParser().parseFromString(
        `<div id="__report">${html}</div>`,
        'text/html',
    );
    const root = doc.getElementById('__report') ?? doc.body;
    const toc = tocTitles(root);
    const insideSection = (element: Element): boolean => {
        for (
            let parent = element.parentElement;
            parent && parent !== root;
            parent = parent.parentElement
        ) {
            if (hasClass(parent, SECTION_CLASS)) {
                return true;
            }
        }

        return false;
    };
    // Pages laid out as top-level `break-before` blocks: one lembar per page.
    const paged = Array.from(root.children).some((element) =>
        hasClass(element, /^break-before$/),
    );
    const insideCover = (element: Element): boolean => {
        for (
            let parent = element.parentElement;
            parent && parent !== root;
            parent = parent.parentElement
        ) {
            if (hasClass(parent, COVER_CLASS)) {
                return true;
            }
        }

        return false;
    };
    const blocks: Element[] = paged
        ? []
        : Array.from(root.querySelectorAll('*')).filter(
              (element) =>
                  hasClass(element, SECTION_CLASS) && !insideSection(element),
          );

    if (blocks.length === 0) {
        blocks.push(...pageGroups(root));
    }

    // A cover drawn outside any section is its own lembar too.
    Array.from(root.querySelectorAll('*'))
        .filter(
            (element) =>
                hasClass(element, COVER_CLASS) &&
                !blocks.some((block) => block.contains(element)) &&
                !insideCover(element),
        )
        .reverse()
        .forEach((cover) => blocks.unshift(cover));

    return blocks.map((block, index) => {
        const isCover =
            hasClass(block, COVER_CLASS) ||
            Array.from(block.querySelectorAll('[class]')).some((element) =>
                hasClass(element, COVER_CLASS),
            );
        const rows: SheetRow[] = [];
        collectRows(block, rows);

        return {
            id: block.id,
            name: isCover
                ? 'Sampul'
                : (toc.get(block.id) ??
                  headingOf(block) ??
                  `Lembar ${index + 1}`),
            isCover,
            rows,
        };
    });
}

/** One lembar as a grid: borderless text rows over the width, tables cell by cell. */
function partToGrid(part: SheetPart, name: string): DocumentGrid {
    const rows: GridCell[][] = [];
    const merges: [number, number, number, number][] = [];
    const widths: number[] = [];
    const textRows: number[] = [];
    let cols = 1;

    for (const item of part.rows) {
        if (item.kind === 'text') {
            textRows.push(rows.length);
            rows.push([
                {
                    t: item.text,
                    b: item.bold || part.isCover || undefined,
                    a: item.center || part.isCover ? 'c' : undefined,
                    noborder: true,
                },
            ]);
            continue;
        }

        const origin = rows.length;
        const occupied = new Set<string>();
        const placed: GridCell[][] = [];
        let rowIndex = 0;

        for (const tr of Array.from(item.table.rows).filter(
            (row) => row.closest('table') === item.table,
        )) {
            let col = 0;

            for (const cell of Array.from(tr.cells)) {
                while (occupied.has(`${rowIndex}:${col}`)) {
                    col++;
                }

                if (col >= MAX_COLS) {
                    break;
                }

                const colspan = Math.min(
                    Math.max(1, cell.colSpan || 1),
                    MAX_COLS - col,
                );
                const rowspan = Math.max(1, cell.rowSpan || 1);
                const text = lines(cell);
                const isHead =
                    cell.tagName === 'TH' || cell.closest('thead') !== null;
                const centered =
                    isHead ||
                    /(^|\s)(c|center|text-center|n|pg)(\s|$)/.test(
                        cell.className,
                    ) ||
                    /text-align:\s*center/.test(
                        cell.getAttribute('style') ?? '',
                    ) ||
                    cell.getAttribute('align') === 'center';

                for (let r = 0; r < rowspan; r++) {
                    for (let c = 0; c < colspan; c++) {
                        occupied.add(`${rowIndex + r}:${col + c}`);
                    }
                }

                placed[rowIndex] ??= [];
                placed[rowIndex][col] = {
                    t: text,
                    b: isHead || undefined,
                    a: centered ? 'c' : undefined,
                };

                if (colspan > 1 || rowspan > 1) {
                    merges.push([
                        origin + rowIndex,
                        col,
                        origin + rowIndex + rowspan - 1,
                        col + colspan - 1,
                    ]);
                } else {
                    const longest = Math.max(
                        0,
                        ...text.split('\n').map((line) => line.length),
                    );
                    widths[col] = Math.max(
                        widths[col] ?? 0,
                        Math.min(longest + 2, 45),
                    );
                }

                col += colspan;
                cols = Math.max(cols, col);
            }

            rowIndex++;
        }

        const height = Math.max(
            rowIndex,
            ...Array.from(occupied).map((key) => Number(key.split(':')[0]) + 1),
        );

        for (let r = 0; r < height; r++) {
            const source = placed[r] ?? [];
            const width = Math.max(
                source.length,
                ...Array.from(occupied)
                    .filter((key) => key.startsWith(`${r}:`))
                    .map((key) => Number(key.split(':')[1]) + 1),
            );
            const dense: GridCell[] = [];

            for (let c = 0; c < width; c++) {
                // Cells under a merge are blank but keep the table border.
                dense.push(source[c] ?? { t: '' });
            }

            rows.push(dense.length > 0 ? dense : [{ t: '', noborder: true }]);
        }

        rows.push([{ t: '', noborder: true }]);
    }

    const span = Math.max(cols, part.isCover ? 8 : 1);
    textRows.forEach((r) => {
        if (span > 1) {
            merges.push([r, 0, r, span - 1]);
        }
    });

    const strip = (cell: GridCell): GridCell =>
        Object.fromEntries(
            Object.entries(cell).filter(([, value]) => value !== undefined),
        ) as GridCell;

    return {
        name,
        cols: span,
        col_widths: Array.from(
            { length: span },
            (_, c) =>
                Math.max(widths[c] ?? 0, part.isCover ? 14 : 6) * PX_PER_CHAR,
        ),
        merges,
        rows: rows.map((row) => row.map(strip)),
    };
}

/**
 * The report as a book: `sheets` holds one grid per lembar; the top-level
 * fields mirror the first sheet so code reading a single grid keeps working.
 */
export function reportBook(html: string): DocumentGrid {
    const used = new Set<string>();
    const sheets = reportParts(html)
        .filter((part) => part.rows.length > 0)
        .map((part) => partToGrid(part, sheetName(part.name, used)));

    if (sheets.length === 0) {
        return { name: 'Laporan', cols: 1, rows: [[{ t: '' }]], merges: [] };
    }

    return { ...sheets[0], sheets };
}

/** The sheets of a grid: a book's sheets, or the single grid itself. */
export const sheetsOf = (grid: DocumentGrid): DocumentGrid[] =>
    grid.sheets && grid.sheets.length > 0 ? grid.sheets : [grid];
