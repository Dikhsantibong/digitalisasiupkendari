import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

/**
 * Phone layout for pages whose inputs are hand-built <table>s: when enabled,
 * every table inside is shown as one card per row (see `.mobile-cards` in
 * app.css) — no horizontal table. Each cell gets its column label from the
 * table header (colspan / rowspan headers are joined, e.g. "APD · Layak"), and
 * the inputs, selects and buttons in the cells stay exactly the same elements,
 * so the page's behaviour is untouched. A table opts out with `data-keep-table`.
 * Disabled (desktop, tablet) it renders its children as they are.
 *
 * Long tables stay light: a card with many fields folds to its number and two
 * key fields (tap to open, tap the bottom bar to close), cards render in
 * batches of {@link BATCH} ("Tampilkan … lagi" under the table), and a row
 * added by the page opens automatically. Folded and hidden rows are
 * `display: none`, so the phone does not lay them out or paint them.
 */
export function MobileTableCards({
    enabled,
    children,
}: {
    enabled: boolean;
    children: ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = ref.current;

        if (!enabled || !root) {
            return;
        }

        let frame = 0;
        const label = () => {
            frame = 0;
            root.querySelectorAll('table:not([data-keep-table])').forEach(
                (table) => labelTable(table as HTMLTableElement),
            );
        };
        const schedule = () => {
            if (!frame) {
                frame = requestAnimationFrame(label);
            }
        };

        // Tap a folded card to open it; tap an open card's bottom bar to fold it;
        // tap "Tampilkan … lagi" (the table's ::after) for the next batch.
        const onClick = (event: MouseEvent) => {
            const target = event.target as HTMLElement;

            if (target.matches('table[data-mc][data-mc-rest]')) {
                const table = target as HTMLTableElement;
                table.dataset.mcLimit = String(
                    Number(table.dataset.mcLimit ?? BATCH) + BATCH,
                );
                labelTable(table);

                return;
            }

            const row = target.closest('table[data-mc] tr[data-mc-fold]');

            if (!row) {
                return;
            }

            if (!row.hasAttribute('data-mc-open')) {
                row.setAttribute('data-mc-open', '');
            } else if (target === row) {
                row.removeAttribute('data-mc-open');
            }
        };

        label();
        const observer = new MutationObserver(schedule);
        observer.observe(root, { childList: true, subtree: true });
        root.addEventListener('click', onClick);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
            root.removeEventListener('click', onClick);
            root.querySelectorAll('table[data-mc]').forEach((table) =>
                table.removeAttribute('data-mc'),
            );
        };
    }, [enabled]);

    return (
        <div
            ref={ref}
            className={enabled ? 'mobile-cards contents' : 'contents'}
        >
            {children}
        </div>
    );
}

/** Column labels of a table from its <thead> (or first header row), honouring colspan & rowspan. */
function columnLabels(table: HTMLTableElement): string[] {
    const headRows = Array.from(table.tHead?.rows ?? []);
    const grid: string[][] = [];

    headRows.forEach((row, r) => {
        grid[r] ??= [];
        let c = 0;

        Array.from(row.cells).forEach((cell) => {
            while (grid[r][c] !== undefined) {
                c++;
            }

            const text = (cell.textContent ?? '').replace(/\s+/g, ' ').trim();

            for (let dr = 0; dr < cell.rowSpan; dr++) {
                grid[r + dr] ??= [];

                for (let dc = 0; dc < cell.colSpan; dc++) {
                    grid[r + dr][c + dc] = dr === 0 ? text : '\u0000';
                }
            }

            c += cell.colSpan;
        });
    });

    const width = Math.max(0, ...grid.map((row) => row.length));

    return Array.from({ length: width }, (_, c) => {
        const parts: string[] = [];

        grid.forEach((row) => {
            const text = row[c];

            if (text && text !== '\u0000' && parts[parts.length - 1] !== text) {
                parts.push(text);
            }
        });

        return parts.join(' · ');
    });
}

/** Cards shown per table before "Tampilkan … lagi". */
const BATCH = 15;

/** A card with more fields than this folds to its key fields. */
const FOLD_AFTER = 3;

/** Column labels that usually name the row; preferred as the folded card's key fields. */
const KEY_HINT =
    /nama|uraian|material|peralatan|kegiatan|item|deskripsi|temuan|lokasi|pekerjaan|jenis|barang|tools|mesin|apd|judul|tanggal|lokasi|kondisi|status/i;

/** Rows seen by each table; a row that appears later (Tambah) opens. */
const seenRows = new WeakMap<HTMLTableElement, WeakSet<HTMLTableRowElement>>();

function labelTable(table: HTMLTableElement) {
    const labels = columnLabels(table);
    const width = labels.length;
    const firstPass = !seenRows.has(table);
    const seen = seenRows.get(table) ?? new WeakSet<HTMLTableRowElement>();
    seenRows.set(table, seen);
    const dataRowList: HTMLTableRowElement[] = [];
    const added: HTMLTableRowElement[] = [];
    let limit = Number(table.dataset.mcLimit ?? BATCH);

    // Columns still covered by a rowSpan cell of an earlier row (per tbody / tfoot),
    // so a row's cells get the labels of the columns they really sit in.
    let covered: number[] = [];
    let group: Element | null = null;

    Array.from(table.tBodies)
        .flatMap((body) => Array.from(body.rows))
        .concat(Array.from(table.tFoot?.rows ?? []))
        .forEach((row) => {
            if (row.parentElement !== group) {
                group = row.parentElement;
                covered = [];
            }

            const cells = Array.from(row.cells);
            const next = covered.map((n) => Math.max(0, n - 1));
            let c = 0;
            let visible = 0;

            cells.forEach((cell) => {
                while ((covered[c] ?? 0) > 0) {
                    c++;
                }

                const text = cell.colSpan > 1 ? '' : (labels[c] ?? '');

                if (cell.rowSpan > 1) {
                    for (let dc = 0; dc < cell.colSpan; dc++) {
                        next[c + dc] = cell.rowSpan - 1;
                    }
                }

                if (cell.getAttribute('data-label') !== text) {
                    cell.setAttribute('data-label', text);
                }

                if (
                    cell.textContent?.trim() ||
                    cell.querySelector('input, select, textarea, button, img')
                ) {
                    visible++;
                }

                c += cell.colSpan;
            });

            covered = next;

            // A single cell spanning the table (group title, empty state, total) reads as a heading.
            const isSection =
                cells.length <= 2 &&
                width > 2 &&
                cells.some((cell) => cell.colSpan >= width - 1);

            row.toggleAttribute('data-mc-section', isSection);
            row.toggleAttribute('data-mc-empty', visible === 0);

            if (
                isSection ||
                visible === 0 ||
                row.parentElement?.tagName === 'TFOOT'
            ) {
                return;
            }

            // Fold long cards to the row number and two key fields.
            const labelled = cells.filter(
                (cell) => (cell.getAttribute('data-label') ?? '') !== '',
            );
            const number = labelled.find((cell) =>
                /^(no\.?|#|nomor)$/i.test(
                    cell.getAttribute('data-label') ?? '',
                ),
            );
            const candidates = labelled.filter((cell) => cell !== number);
            const keys = [
                ...candidates.filter((cell) =>
                    KEY_HINT.test(cell.getAttribute('data-label') ?? ''),
                ),
                ...candidates,
            ]
                .filter((cell, i, list) => list.indexOf(cell) === i)
                .slice(0, 2);
            const fold = labelled.length > FOLD_AFTER;

            cells.forEach((cell) => {
                cell.toggleAttribute('data-mc-key', keys.includes(cell));
                cell.toggleAttribute('data-mc-no', cell === number);
            });
            row.toggleAttribute('data-mc-fold', fold);

            if (!seen.has(row)) {
                seen.add(row);
                added.push(row);
            }

            dataRowList.push(row);
        });

    // Exactly one new row after the first render = the page's "Tambah": open it
    // and keep it in view. Many new rows at once (period switch) = a fresh list.
    if (!firstPass && added.length === 1) {
        added[0].setAttribute('data-mc-open', '');
        limit = Math.max(limit, dataRowList.indexOf(added[0]) + 1);
    }

    dataRowList.forEach((row, index) =>
        row.toggleAttribute('data-mc-more', index >= limit),
    );

    const dataRows = dataRowList.length;
    table.dataset.mcLimit = String(limit);
    const rest = Math.max(0, dataRows - limit);

    if (rest > 0) {
        table.setAttribute(
            'data-mc-rest',
            `Tampilkan ${Math.min(rest, BATCH)} lagi (${rest} tersisa)`,
        );
    } else {
        table.removeAttribute('data-mc-rest');
    }

    if (!table.hasAttribute('data-mc')) {
        table.setAttribute('data-mc', '');
    }
}
